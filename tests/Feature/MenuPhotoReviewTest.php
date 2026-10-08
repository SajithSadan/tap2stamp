<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.product_api' => ['url' => 'https://catalog.test', 'public_key' => 'pub', 'private_key' => 'secret', 'verify_ssl' => true, 'max_candidates' => 3],
        'services.gemini.key' => 'gm_key',
        'services.gemini.model' => 'gemini-3.8-flash',
        'services.gemini.fallback_model' => null,
    ]);
    Storage::fake('uploads');
    Setting::set(Setting::MENU_PHOTO_CHECK, 'manual');
});

function reviewOwner(string $name = 'Olive'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'name' => $name]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id])];
}

function reviewPng(int $shade = 0): string
{
    $image = imagecreatetruecolor(40, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, $shade, $shade, $shade));
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

/** The owner's menu: one item, Flat white. */
function reviewMenu(User $owner): MenuItem
{
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Hot drinks', 'items' => [
        ['name' => 'Flat white', 'description' => null, 'price' => '£3.40', 'tags' => []],
    ]]]])->assertSessionHasNoErrors();

    return MenuItem::latest('id')->first();
}

/** The catalog has two candidate photos (or none); Gemini must never be asked. */
function reviewCatalog(bool $found = true): void
{
    Http::fake([
        'catalog.test/api/products/7/image' => Http::response(reviewPng(10), 200, ['Content-Type' => 'image/png']),
        'catalog.test/api/products/8/image' => Http::response(reviewPng(200), 200, ['Content-Type' => 'image/png']),
        'catalog.test/api/products*' => Http::response(['data' => $found ? [
            ['id' => 7, 'name' => 'Flat White', 'image_endpoint' => 'https://catalog.test/api/products/7/image'],
            ['id' => 8, 'name' => 'Flat White Large', 'image_endpoint' => 'https://catalog.test/api/products/8/image'],
        ] : []]),
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);
}

function noGeminiCalls(): void
{
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage'));
}

test('the editor is told photos are confirmed by hand', function () {
    [$owner] = reviewOwner();

    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page
        ->where('imagesEnabled', true)
        ->where('photoCheck', 'manual'));
});

test('manual mode lists the catalog photos to confirm, without asking Gemini', function () {
    [$owner] = reviewOwner();
    $item = reviewMenu($owner);
    reviewCatalog();

    $response = $this->actingAs($owner)->postJson('/dashboard/menu/images/review', ['item' => $item->id])->assertOk();

    expect($response->json('candidates'))->toHaveCount(2)
        ->and($response->json('candidates.0.label'))->toBe('Flat White')
        ->and($response->json('candidates.0.url'))->toBe('/dashboard/menu/images/review/'.$response->json('candidates.0.token'))
        ->and($response->json('items'))->toBe([]);
    noGeminiCalls();

    // Each photo can be shown in the editor.
    $this->get($response->json('candidates.1.url'))->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('yes stores that photo as the item\'s, and says who confirmed it', function () {
    [$owner, $shop] = reviewOwner('Olive');
    $item = reviewMenu($owner);
    reviewCatalog();
    $token = $this->actingAs($owner)->postJson('/dashboard/menu/images/review', ['item' => $item->id])->json('candidates.1.token');

    $this->postJson('/dashboard/menu/images/confirm', ['item' => $item->id, 'token' => $token])
        ->assertOk()
        ->assertJsonPath('items.0.image_status', 'found')
        ->assertJsonPath('items.0.name', 'Flat white');

    $item->refresh();
    expect($item->image_status)->toBe('found')
        ->and($item->image_confidence)->toBeNull()
        ->and($item->image_reason)->toBe('Confirmed by Olive')
        ->and($item->image_path)->toStartWith("menu-items/{$shop->id}/");
    expect(Storage::disk('uploads')->get($item->image_path))->toBe(reviewPng(200)); // the second photo, as chosen
    noGeminiCalls();

    // A token is used once.
    $this->postJson('/dashboard/menu/images/confirm', ['item' => $item->id, 'token' => $token])->assertStatus(422);
});

test('no to every photo marks the item as having none right', function () {
    [$owner] = reviewOwner('Olive');
    $item = reviewMenu($owner);
    reviewCatalog();
    $this->actingAs($owner)->postJson('/dashboard/menu/images/review', ['item' => $item->id]);

    $this->postJson('/dashboard/menu/images/confirm', ['item' => $item->id, 'token' => null])
        ->assertOk()
        ->assertJsonPath('items.0.image_status', 'rejected');

    expect($item->fresh())->image_status->toBe('rejected')->image_path->toBeNull()
        ->and($item->fresh()->image_reason)->toContain('checked by Olive');
});

test('nothing in the catalog comes back as not found, with no photos to confirm', function () {
    [$owner] = reviewOwner();
    $item = reviewMenu($owner);
    reviewCatalog(found: false);

    $this->actingAs($owner)->postJson('/dashboard/menu/images/review', ['item' => $item->id])
        ->assertOk()
        ->assertJsonPath('candidates', [])
        ->assertJsonPath('items.0.image_status', 'not_found');
});

test('held photos and items are for their own shop only', function () {
    [$owner] = reviewOwner();
    [$other, $otherShop] = reviewOwner('Other');
    $item = reviewMenu($owner);
    reviewCatalog();
    $token = $this->actingAs($owner)->postJson('/dashboard/menu/images/review', ['item' => $item->id])->json('candidates.0.token');

    $this->actingAs($other)->get("/dashboard/menu/images/review/{$token}")->assertNotFound();
    $this->actingAs($other)->postJson('/dashboard/menu/images/review', ['item' => $item->id])->assertNotFound();
    $this->actingAs($other)->postJson('/dashboard/menu/images/confirm', ['item' => $item->id, 'token' => $token])->assertNotFound();

    // A token can't put one item's photo on another item.
    $otherItem = reviewMenu($other);
    $this->actingAs($other)->postJson('/dashboard/menu/images/confirm', ['item' => $otherItem->id, 'token' => $token])->assertStatus(422);
    expect($otherItem->fresh()->image_path)->toBeNull();
});

test('the admin can confirm photos for any shop', function () {
    [$owner, $shop] = reviewOwner();
    $item = reviewMenu($owner);
    reviewCatalog();
    $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);

    $review = $this->actingAs($admin)->postJson("/admin/shops/{$shop->id}/menu/images/review", ['item' => $item->id])->assertOk();
    expect($review->json('candidates.0.url'))->toStartWith("/admin/shops/{$shop->id}/menu/images/review/");
    $this->get($review->json('candidates.0.url'))->assertOk();

    $this->postJson("/admin/shops/{$shop->id}/menu/images/confirm", ['item' => $item->id, 'token' => $review->json('candidates.0.token')])->assertOk();

    expect($item->fresh()->image_reason)->toBe('Confirmed by Ada');
});

test('each mode only answers its own endpoints', function () {
    [$owner] = reviewOwner();
    $item = reviewMenu($owner);
    reviewCatalog();

    // Manual: the AI search is off.
    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['item' => $item->id])->assertNotFound();

    // AI: the manual ones are off.
    Setting::set(Setting::MENU_PHOTO_CHECK, 'ai');
    $this->postJson('/dashboard/menu/images/review', ['item' => $item->id])->assertNotFound();
    $this->postJson('/dashboard/menu/images/confirm', ['item' => $item->id, 'token' => null])->assertNotFound();
});

test('without a Gemini key photos are always confirmed by hand', function () {
    config(['services.gemini.key' => null]);
    Setting::set(Setting::MENU_PHOTO_CHECK, 'ai');
    [$owner] = reviewOwner();

    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page
        ->where('imagesEnabled', true)
        ->where('photoCheck', 'manual'));
});

/* ---------- Admin setting ---------- */

test('the admin chooses how menu photos are checked', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('menuPhotos.chosen', 'manual')
        ->where('menuPhotos.mode', 'manual')
        ->where('menuPhotos.ai_available', true)
        ->where('menuPhotos.catalog_configured', true));

    $this->put('/admin/settings/menu-photos', ['mode' => 'ai'])->assertSessionHasNoErrors();
    expect(Setting::get(Setting::MENU_PHOTO_CHECK))->toBe('ai');

    $this->put('/admin/settings/menu-photos', ['mode' => 'robots'])->assertSessionHasErrors('mode');
});

test('AI checking cannot be chosen without a Gemini key', function () {
    config(['services.gemini.key' => null]);

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->put('/admin/settings/menu-photos', ['mode' => 'ai'])
        ->assertSessionHasErrors('mode');

    expect(Setting::get(Setting::MENU_PHOTO_CHECK))->toBe('manual');
});

test('owners cannot change how photos are checked', function () {
    [$owner] = reviewOwner();

    $this->actingAs($owner)->put('/admin/settings/menu-photos', ['mode' => 'ai'])->assertForbidden();
});
