<?php

use App\Enums\UserRole;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // No catalog and no Gemini: uploading must still work.
    config(['services.product_api.url' => null, 'services.gemini.key' => null]);
    Storage::fake('uploads');
});

function uploadOwner(string $name = 'Olive'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'name' => $name]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id])];
}

function uploadMenu(User $owner): MenuItem
{
    test()->actingAs($owner)->put('/dashboard/menu', ['sections' => [['name' => 'Cakes', 'items' => [
        ['name' => 'Carrot cake', 'description' => null, 'price' => '£3.20', 'tags' => []],
    ]]]])->assertSessionHasNoErrors();

    return MenuItem::latest('id')->first();
}

test('an owner uploads their own photo for an item, stored as a small WebP', function () {
    [$owner, $shop] = uploadOwner('Olive');
    $item = uploadMenu($owner);

    $this->actingAs($owner)
        ->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => UploadedFile::fake()->image('cake.jpg', 2400, 1800)])
        ->assertOk()
        ->assertJsonPath('items.0.id', $item->id)
        ->assertJsonPath('items.0.image_status', 'uploaded');

    $item->refresh();
    expect($item->image_status)->toBe('uploaded')
        ->and($item->image_reason)->toBe('Uploaded by Olive')
        ->and($item->image_path)->toStartWith("menu-items/{$shop->id}/")->toEndWith('.webp');

    // Re-encoded and capped at 1200 px on its longest side (from 2400 x 1800).
    $size = getimagesizefromstring(Storage::disk('uploads')->get($item->image_path));
    expect($size['mime'])->toBe('image/webp')
        ->and([$size[0], $size[1]])->toBe([1200, 900]);
});

test('the editor offers uploads even with no catalog set up', function () {
    [$owner] = uploadOwner();

    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page->where('imagesEnabled', false));
});

test('a new upload replaces the old photo file', function () {
    [$owner] = uploadOwner();
    $item = uploadMenu($owner);

    $this->actingAs($owner)->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => UploadedFile::fake()->image('a.png', 800, 800)]);
    $first = $item->fresh()->image_path;
    $this->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => UploadedFile::fake()->image('b.png', 800, 800)]);

    Storage::disk('uploads')->assertMissing($first);
    Storage::disk('uploads')->assertExists($item->fresh()->image_path);
});

test('saving the menu keeps an uploaded photo', function () {
    [$owner] = uploadOwner();
    $item = uploadMenu($owner);
    $this->actingAs($owner)->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => UploadedFile::fake()->image('cake.jpg', 800, 800)]);
    $path = $item->fresh()->image_path;

    $this->put('/dashboard/menu', ['sections' => [['name' => 'Cakes', 'items' => [
        ['name' => 'Carrot cake', 'description' => 'With walnuts', 'price' => '£3.20', 'tags' => [], 'image_path' => $path],
    ]]]])->assertSessionHasNoErrors();

    expect(MenuItem::sole())->image_path->toBe($path)->image_status->toBe('uploaded');
});

test('only real photos of a sensible size are accepted', function () {
    [$owner] = uploadOwner();
    $item = uploadMenu($owner);
    $post = fn ($file) => $this->actingAs($owner)->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => $file]);

    $post(UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'))->assertSessionHasErrors('photo');
    $post(UploadedFile::fake()->image('tiny.jpg', 120, 120))->assertSessionHasErrors('photo');
    $post(UploadedFile::fake()->image('huge.jpg', 800, 800)->size(5000))->assertSessionHasErrors('photo');

    expect($item->fresh()->image_path)->toBeNull();
});

test('photos can only be uploaded to your own shop\'s items', function () {
    [$owner] = uploadOwner();
    [$other] = uploadOwner('Other');
    $item = uploadMenu($owner);

    $this->actingAs($other)
        ->post('/dashboard/menu/images/upload', ['item' => $item->id, 'photo' => UploadedFile::fake()->image('x.jpg', 800, 800)])
        ->assertNotFound();

    expect($item->fresh()->image_path)->toBeNull();
});

test('the admin can upload a photo for any shop', function () {
    [$owner, $shop] = uploadOwner();
    $item = uploadMenu($owner);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);

    $this->actingAs($admin)
        ->post("/admin/shops/{$shop->id}/menu/images/upload", ['item' => $item->id, 'photo' => UploadedFile::fake()->image('x.jpg', 800, 800)])
        ->assertOk();

    expect($item->fresh()->image_reason)->toBe('Uploaded by Ada');
});
