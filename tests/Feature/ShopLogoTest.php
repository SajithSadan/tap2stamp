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

test('a guest cannot upload a logo', function () {
    $this->post('/dashboard/theme/logo', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300)])
        ->assertRedirect('/login');
});

test('an owner can upload a logo and customers see it on the card page', function () {
    $this->actingAs($this->owner)
        ->post('/dashboard/theme/logo', ['logo' => UploadedFile::fake()->image('my-logo.png', 400, 400)])
        ->assertRedirect('/dashboard/theme');

    $path = $this->shop->fresh()->logo_path;

    expect($path)->toStartWith("logos/{$this->shop->id}/")->not->toContain('my-logo');
    Storage::disk('uploads')->assertExists($path);

    $url = Storage::disk('uploads')->url($path);
    $this->get("/s/{$this->shop->slug}")->assertInertia(fn ($page) => $page->where('shop.logo_url', $url));
    $this->actingAs($this->owner)->get('/dashboard/theme')->assertInertia(fn ($page) => $page->where('logoUrl', $url));
});

test('shops without a logo send none, so the card page shows the store icon', function () {
    $this->get("/s/{$this->shop->slug}")->assertInertia(fn ($page) => $page->where('shop.logo_url', null));
});

test('uploading a new logo replaces and deletes the old file', function () {
    $this->actingAs($this->owner)->post('/dashboard/theme/logo', ['logo' => UploadedFile::fake()->image('one.png', 300, 300)]);
    $first = $this->shop->fresh()->logo_path;

    $this->actingAs($this->owner)->post('/dashboard/theme/logo', ['logo' => UploadedFile::fake()->image('two.png', 300, 300)]);
    $second = $this->shop->fresh()->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('uploads')->assertMissing($first);
    Storage::disk('uploads')->assertExists($second);
});

test('an owner can remove their logo', function () {
    $this->actingAs($this->owner)->post('/dashboard/theme/logo', ['logo' => UploadedFile::fake()->image('one.png', 300, 300)]);
    $path = $this->shop->fresh()->logo_path;

    $this->actingAs($this->owner)->delete('/dashboard/theme/logo')->assertRedirect('/dashboard/theme');

    expect($this->shop->fresh()->logo_path)->toBeNull();
    Storage::disk('uploads')->assertMissing($path);
});

test('a logo has to be a usable image', function (Closure $file) {
    $this->actingAs($this->owner)->post('/dashboard/theme/logo', ['logo' => $file()])->assertSessionHasErrors('logo');

    expect($this->shop->fresh()->logo_path)->toBeNull();
})->with([
    'SVG' => [fn () => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.png', 80, 80)],
    'over 2 MB' => [fn () => UploadedFile::fake()->image('big.png', 400, 400)->size(3000)],
]);
