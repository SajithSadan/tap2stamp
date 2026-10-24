<?php

use App\Enums\UserRole;
use App\Models\MenuBanner;
use App\Models\Shop;
use App\Models\User;
use App\Services\MenuBanners;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('uploads'));

function bannerOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id])];
}

function bannerImage(int $width = 2400, int $height = 1200): UploadedFile
{
    return UploadedFile::fake()->image('offer.jpg', $width, $height);
}

test('an owner adds a banner, stored as a WebP of at most 1600 px', function () {
    [$owner, $shop] = bannerOwner();

    $this->actingAs($owner)->post('/dashboard/menu/banners', ['image' => bannerImage()])
        ->assertOk()
        ->assertJsonCount(1, 'banners');

    $banner = $shop->menuBanners()->sole();
    expect($banner->image_path)->toStartWith("menu-banners/{$shop->id}/")->toEndWith('.webp');

    $size = getimagesizefromstring(Storage::disk('uploads')->get($banner->image_path));
    expect($size['mime'])->toBe('image/webp')
        ->and([$size[0], $size[1]])->toBe([1600, 800]);
});

test('the editor and the public menu get the banners in order', function () {
    [$owner, $shop] = bannerOwner();
    $first = $shop->menuBanners()->create(['image_path' => 'menu-banners/a.webp', 'position' => 2]);
    $second = $shop->menuBanners()->create(['image_path' => 'menu-banners/b.webp', 'position' => 1]);

    $this->actingAs($owner)->get('/dashboard/menu')->assertInertia(fn ($page) => $page
        ->where('maxBanners', MenuBanners::MAX)
        ->where('banners.0.id', $second->id)
        ->where('banners.1.id', $first->id));

    $this->get($shop->menuUrl())->assertInertia(fn ($page) => $page
        ->component('Menu')
        ->where('banners.0.id', $second->id)
        ->where('banners.0.url', $second->url()));
});

test('a shop can have at most 5 banners', function () {
    [$owner, $shop] = bannerOwner();
    foreach (range(1, MenuBanners::MAX) as $i) {
        $shop->menuBanners()->create(['image_path' => "menu-banners/{$i}.webp", 'position' => $i]);
    }

    $this->actingAs($owner)->postJson('/dashboard/menu/banners', ['image' => bannerImage()])->assertStatus(422);

    expect($shop->menuBanners()->count())->toBe(MenuBanners::MAX);
});

test('banners must be a JPG, PNG or WebP image of at least 600 x 200', function () {
    [$owner, $shop] = bannerOwner();

    $this->actingAs($owner)->postJson('/dashboard/menu/banners', ['image' => bannerImage(400, 150)])->assertJsonValidationErrors('image');
    $this->actingAs($owner)->postJson('/dashboard/menu/banners', ['image' => UploadedFile::fake()->create('offer.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('image');

    expect($shop->menuBanners()->count())->toBe(0);
});

test('banners can be reordered and removed, and a removed one loses its file', function () {
    [$owner, $shop] = bannerOwner();
    $this->actingAs($owner)->post('/dashboard/menu/banners', ['image' => bannerImage()]);
    $this->actingAs($owner)->post('/dashboard/menu/banners', ['image' => bannerImage()]);
    [$a, $b] = $shop->menuBanners()->get();

    $this->actingAs($owner)->putJson('/dashboard/menu/banners', ['ids' => [$b->id, $a->id]])
        ->assertOk()
        ->assertJsonPath('banners.0.id', $b->id);

    $this->actingAs($owner)->deleteJson('/dashboard/menu/banners', ['banner' => $b->id])
        ->assertOk()
        ->assertJsonCount(1, 'banners');

    expect(MenuBanner::find($b->id))->toBeNull()
        ->and(Storage::disk('uploads')->exists($b->image_path))->toBeFalse()
        ->and(Storage::disk('uploads')->exists($a->image_path))->toBeTrue();
});

test("an owner can't remove or reorder another shop's banners", function () {
    [$owner] = bannerOwner();
    [, $other] = bannerOwner();
    $theirs = $other->menuBanners()->create(['image_path' => 'menu-banners/theirs.webp', 'position' => 1]);

    $this->actingAs($owner)->deleteJson('/dashboard/menu/banners', ['banner' => $theirs->id])->assertNotFound();
    $this->actingAs($owner)->putJson('/dashboard/menu/banners', ['ids' => [$theirs->id]])->assertNotFound();

    expect($theirs->fresh()->position)->toBe(1);
});

test('the admin manages any shop\'s banners', function () {
    [, $shop] = bannerOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/menu/banners", ['image' => bannerImage()])->assertOk();

    expect($shop->menuBanners()->count())->toBe(1);
});

test('clearing the menu keeps the banners', function () {
    [$owner, $shop] = bannerOwner();
    $shop->menuBanners()->create(['image_path' => 'menu-banners/a.webp', 'position' => 1]);

    $this->actingAs($owner)->delete('/dashboard/menu');

    expect($shop->menuBanners()->count())->toBe(1);
});
