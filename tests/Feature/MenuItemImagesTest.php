<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Models\User;
use App\Services\MenuItemImages;
use App\Services\ProductCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config([
        'services.product_api' => ['url' => 'https://catalog.test', 'public_key' => 'pub', 'private_key' => 'secret', 'verify_ssl' => true, 'max_candidates' => 3],
        'services.gemini.key' => 'gm_key',
        'services.gemini.model' => 'gemini-2.5-flash',
        'services.gemini.fallback_model' => null,
        'services.gemini.image_min_confidence' => 0.75,
    ]);
    Storage::fake('uploads');
});

function photoOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id])];
}

function pngBytes(): string
{
    $image = imagecreatetruecolor(40, 40);
    ob_start();
    imagepng($image);

    return ob_get_clean();
}

function saveOneItemMenu(User $owner, array $extra = []): void
{
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Hot drinks', 'items' => [
        ['name' => 'Flat white', 'description' => null, 'price' => '£3.40', 'tags' => [], ...$extra],
    ]]]])->assertSessionHasNoErrors();
}

/** Catalog finds one product; Gemini answers $verdict (or $geminiStatus). */
function fakeCatalog(array $verdict = ['match' => true, 'confidence' => 0.92, 'detected' => 'a flat white', 'reason' => 'Latte art, cup'], int $geminiStatus = 200, array $products = [['id' => 7, 'name' => 'Flat White', 'image_endpoint' => 'https://catalog.test/api/products/7/image']]): void
{
    Http::fake([
        'catalog.test/api/products/7/image' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
        'catalog.test/api/products*' => Http::response(['data' => $products]),
        'generativelanguage.googleapis.com/*' => $geminiStatus === 200
            ? Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($verdict)]]]]]])
            : Http::response(['error' => ['message' => 'high demand']], $geminiStatus),
    ]);
}

test('after a save, a checked catalog photo is stored and shown on the menu', function () {
    [$owner, $shop] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();

    $this->actingAs($owner)->postJson('/dashboard/menu/images')
        ->assertOk()
        ->assertJson(['done' => 1, 'remaining' => 0, 'unavailable' => false])
        ->assertJsonPath('items.0.name', 'Flat white')
        ->assertJsonPath('items.0.image_status', 'found');

    $item = MenuItem::sole();
    expect($item->image_status)->toBe('found')
        ->and($item->image_confidence)->toBe(0.92)
        ->and($item->image_path)->toStartWith("menu-items/{$shop->id}/")->toEndWith('.png');
    Storage::disk('uploads')->assertExists($item->image_path);

    $this->get("/menu/{$shop->menu_slug}")
        ->assertInertia(fn ($page) => $page->where('sections.0.items.0.image_url', $item->imageUrl()));
});

test('requests to the catalog are signed', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertOk();

    Http::assertSent(function (Request $request) {
        if (! str_contains($request->url(), 'catalog.test/api/products?search=')) {
            return false;
        }
        $path = '/api/products?search=Flat+white';

        return $request->hasHeader('X-Public-Key', 'pub')
            && $request->header('X-Signature')[0] === ProductCatalog::signature($path, $request->header('X-Timestamp')[0], $request->header('X-Nonce')[0]);
    });
});

test('gemini is told what the catalog calls the image', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertOk();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'generativelanguage')
        && str_contains($request['contents'][0]['parts'][1]['text'], 'labels this image: "Flat White"'));
});

test('a photo Gemini rejects is never stored', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog(['match' => false, 'confidence' => 0.9, 'detected' => 'a bag of coffee beans', 'reason' => 'Not a drink']);

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertJsonPath('items.0.image_status', 'rejected');

    $item = MenuItem::sole();
    expect($item->image_path)->toBeNull()
        ->and($item->image_reason)->toContain('coffee beans');
    expect(Storage::disk('uploads')->allFiles())->toBe([]);
});

test('a low-confidence match is not kept either', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog(['match' => true, 'confidence' => 0.4, 'detected' => 'maybe a latte', 'reason' => 'Unclear']);

    $this->actingAs($owner)->postJson('/dashboard/menu/images');

    expect(MenuItem::sole()->image_status)->toBe('rejected')->and(MenuItem::sole()->image_path)->toBeNull();
});

test('nothing in the catalog is recorded as not found', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog(products: []);

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertJsonPath('items.0.image_status', 'not_found');
});

test('when Gemini is down nothing is stored and the item is tried again later', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog(geminiStatus: 503);

    $this->actingAs($owner)->postJson('/dashboard/menu/images')
        ->assertJson(['done' => 0, 'remaining' => 1, 'unavailable' => true]);

    expect(MenuItem::sole()->image_status)->toBeNull();
    expect(Storage::disk('uploads')->allFiles())->toBe([]);
});

test('re-saving the menu keeps photos, ignores made-up paths, and removing one deletes the file', function () {
    [$owner, $shop] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();
    $this->actingAs($owner)->postJson('/dashboard/menu/images');
    $path = MenuItem::sole()->image_path;

    // The editor sends the path back: kept.
    saveOneItemMenu($owner, ['image_path' => $path, 'price' => '£3.60']);
    expect(MenuItem::sole()->image_path)->toBe($path)->and(MenuItem::sole()->image_status)->toBe('found');

    // A path this shop never had: dropped.
    Storage::disk('uploads')->put('logos/other.png', 'x');
    saveOneItemMenu($owner, ['image_path' => 'logos/other.png']);
    expect(MenuItem::sole()->image_path)->toBeNull()->and(MenuItem::sole()->image_status)->toBe('removed');
    Storage::disk('uploads')->assertMissing($path);
    Storage::disk('uploads')->assertExists('logos/other.png');
});

test('a deleted item takes its photo file with it', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();
    $this->actingAs($owner)->postJson('/dashboard/menu/images');
    $path = MenuItem::sole()->image_path;

    $this->actingAs($owner)->delete('/dashboard/menu');

    Storage::disk('uploads')->assertMissing($path);
});

test('find again works for one item of your own shop only', function () {
    [$owner] = photoOwner();
    [$other] = photoOwner();
    saveOneItemMenu($owner);
    saveOneItemMenu($other);
    fakeCatalog();
    [$mine, $theirs] = MenuItem::orderBy('id')->get()->all();

    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['item' => $theirs->id])->assertNotFound();
    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['item' => $mine->id, 'query' => 'flat white coffee'])
        ->assertJsonPath('items.0.image_status', 'found');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'search=flat+white+coffee'));
    expect($theirs->fresh()->image_status)->toBeNull();
});

test('photos are off without the catalog keys', function () {
    config(['services.product_api.url' => null]);
    [$owner] = photoOwner();

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertNotFound();
    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page->where('imagesEnabled', false));
});

test('the admin can fetch photos for any shop', function () {
    [$owner, $shop] = photoOwner();
    saveOneItemMenu($owner);
    fakeCatalog();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->postJson("/admin/shops/{$shop->id}/menu/images")->assertJsonPath('items.0.image_status', 'found');
    $this->actingAs($owner)->postJson("/admin/shops/{$shop->id}/menu/images")->assertForbidden();
});

test('a catalog miss is retried hyphenated, then with the main word', function () {
    expect(MenuItemImages::searchTerms('Coca-Cola  330ml'))->toBe(['Coca-Cola 330ml', 'Coca-Cola-330ml', 'Coca-Cola']);

    [$owner] = photoOwner();
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Drinks', 'items' => [
        ['name' => 'Coca-Cola 330ml', 'description' => null, 'price' => '£1.50', 'tags' => []],
    ]]]]);
    Http::fake([
        'catalog.test/api/products/7/image' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
        'catalog.test/api/products?search=Coca-Cola-330ml' => Http::response(['data' => [['id' => 7, 'name' => 'COCA-COLA-330ML']]]),
        'catalog.test/api/products*' => Http::response(['data' => []]),
        'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['match' => true, 'confidence' => 0.97, 'detected' => 'Coca-Cola can', 'reason' => 'Same can'])]]]]]]),
    ]);

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertJsonPath('items.0.image_status', 'found');
});

test('the progress panel can ask for one item at a time', function () {
    [$owner] = photoOwner();
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Drinks', 'items' => [
        ['name' => 'Flat white', 'description' => null, 'price' => null, 'tags' => []],
        ['name' => 'Latte', 'description' => null, 'price' => null, 'tags' => []],
    ]]]]);
    fakeCatalog();

    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['limit' => 1])
        ->assertJson(['done' => 1, 'remaining' => 1])
        ->assertJsonPath('items.0.name', 'Flat white');
    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['limit' => 9])->assertUnprocessable();
});

test('an item that already has a photo keeps it when a new search finds nothing better', function () {
    [$owner] = photoOwner();
    saveOneItemMenu($owner);
    $verdict = fn (array $v) => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($v)]]]]]]);
    Http::fake([
        'catalog.test/api/products/7/image' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
        'catalog.test/api/products*' => Http::response(['data' => [['id' => 7, 'name' => 'Flat White']]]),
        'generativelanguage.googleapis.com/*' => Http::sequence()
            ->pushResponse($verdict(['match' => true, 'confidence' => 0.92, 'detected' => 'a flat white', 'reason' => 'Latte art']))
            ->pushResponse($verdict(['match' => false, 'confidence' => 0.8, 'detected' => 'a bag of beans', 'reason' => 'Not a drink'])),
    ]);
    $this->actingAs($owner)->postJson('/dashboard/menu/images');
    $item = MenuItem::sole();
    $path = $item->image_path;

    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['item' => $item->id])
        ->assertJsonPath('items.0.outcome', 'rejected')
        ->assertJsonPath('items.0.outcome_reason', fn ($reason) => str_contains($reason, 'bag of beans'))
        ->assertJsonPath('items.0.image_status', 'found');

    expect($item->fresh()->image_path)->toBe($path);
    Storage::disk('uploads')->assertExists($path);
});

test('find photos with retry looks again for items that got none, not ones taken off by hand', function () {
    [$owner] = photoOwner();
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Drinks', 'items' => [
        ['name' => 'Flat white', 'description' => null, 'price' => null, 'tags' => []],
        ['name' => 'Latte', 'description' => null, 'price' => null, 'tags' => []],
    ]]]]);
    MenuItem::where('name', 'Flat white')->update(['image_status' => 'not_found']);
    MenuItem::where('name', 'Latte')->update(['image_status' => 'removed']);
    fakeCatalog();

    $this->actingAs($owner)->postJson('/dashboard/menu/images')->assertJson(['done' => 0, 'remaining' => 0]);
    $this->actingAs($owner)->postJson('/dashboard/menu/images', ['retry' => true])
        ->assertJson(['done' => 1, 'remaining' => 0])
        ->assertJsonPath('items.0.name', 'Flat white');

    expect(MenuItem::where('name', 'Latte')->value('image_status'))->toBe('removed');
});
