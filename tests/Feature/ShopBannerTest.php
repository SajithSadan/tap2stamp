<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('uploads');

    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->shop = Shop::factory()->create(['user_id' => $this->owner->id]);
});

test('a guest cannot upload a banner', function () {
    auth()->logout();

    $this->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1200, 400)])
        ->assertRedirect('/login');
});

test('an owner can upload a banner and customers see it', function () {
    $this->actingAs($this->owner)
        ->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('shop-front.jpg', 1600, 600)])
        ->assertRedirect('/dashboard/theme');

    $path = $this->shop->fresh()->banner_path;

    expect($path)->toStartWith("banners/{$this->shop->id}/")->not->toContain('shop-front');
    Storage::disk('uploads')->assertExists($path);

    $this->get("/s/{$this->shop->slug}")
        ->assertInertia(fn ($page) => $page->where('shop.banner_url', Storage::disk('uploads')->url($path)));
});

test('uploading a new banner replaces and deletes the old file', function () {
    $this->actingAs($this->owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('one.jpg', 1200, 400)]);
    $first = $this->shop->fresh()->banner_path;

    $this->actingAs($this->owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('two.png', 1200, 400)]);
    $second = $this->shop->fresh()->banner_path;

    expect($second)->not->toBe($first);
    Storage::disk('uploads')->assertMissing($first);
    Storage::disk('uploads')->assertExists($second);
});

test('an owner can remove their banner', function () {
    $this->actingAs($this->owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1200, 400)]);
    $path = $this->shop->fresh()->banner_path;

    $this->actingAs($this->owner)->delete('/dashboard/theme/banner')->assertRedirect('/dashboard/theme');

    expect($this->shop->fresh()->banner_path)->toBeNull();
    Storage::disk('uploads')->assertMissing($path);
    $this->get("/s/{$this->shop->slug}")->assertInertia(fn ($page) => $page->where('shop.banner_url', null));
});

test('only reasonably sized JPG, PNG or WebP photos are accepted', function (UploadedFile $file) {
    $this->actingAs($this->owner)
        ->post('/dashboard/theme/banner', ['banner' => $file])
        ->assertSessionHasErrors('banner');

    expect($this->shop->fresh()->banner_path)->toBeNull();
})->with([
    'an SVG' => fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    'a PDF' => fn () => UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf'),
    'too small' => fn () => UploadedFile::fake()->image('tiny.jpg', 300, 100),
    'over 4 MB' => fn () => UploadedFile::fake()->image('huge.jpg', 1600, 600)->size(5000),
]);

test('the theme page gets the current banner', function () {
    $this->actingAs($this->owner)->post('/dashboard/theme/banner', ['banner' => UploadedFile::fake()->image('b.jpg', 1200, 400)]);

    $this->actingAs($this->owner)->get('/dashboard/theme')
        ->assertInertia(fn ($page) => $page->where('bannerUrl', Storage::disk('uploads')->url($this->shop->fresh()->banner_path)));
});
