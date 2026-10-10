<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('uploads'));

/** A complete UK shop setup; override any field. */
function logoSetup(array $overrides = []): array
{
    return [
        'name' => 'Bean There', 'max_stamps' => 8, 'reward_title' => 'Free coffee',
        'country' => 'GB', 'contact_name' => 'Olive', 'contact_email' => 'olive@bean.test',
        'contact_phone' => '07700 900123', 'address_line1' => '1 High Street', 'town' => 'Leeds',
        'postcode' => 'LS1 4AP', 'delivery_same' => true,
        ...$overrides,
    ];
}

function logoOwner(): User
{
    return User::factory()->create(['role' => UserRole::Owner]);
}

test('a logo added at sign-up is saved and shown on the customer card page', function () {
    $this->actingAs(logoOwner())
        ->post('/onboarding', logoSetup(['logo' => UploadedFile::fake()->image('logo.webp', 512, 512)]))
        ->assertRedirect('/dashboard');

    $shop = Shop::sole();
    expect($shop->logo_path)->toStartWith("logos/{$shop->id}/");
    Storage::disk('uploads')->assertExists($shop->logo_path);

    auth()->logout();
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page
        ->component('Card')
        ->where('shop.logo_url', $shop->logoUrl()));
});

test('the logo is optional', function () {
    $this->actingAs(logoOwner())->post('/onboarding', logoSetup())->assertRedirect('/dashboard');

    expect(Shop::sole()->logo_path)->toBeNull();
});

test('a bad logo is refused and no shop is created', function (UploadedFile $file, string $message) {
    $this->actingAs(logoOwner())
        ->post('/onboarding', logoSetup(['logo' => $file]))
        ->assertSessionHasErrors(['logo' => $message]);

    expect(Shop::count())->toBe(0);
})->with([
    'not an image' => [fn () => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'), 'Upload a JPG, PNG or WebP image.'],
    'too small' => [fn () => UploadedFile::fake()->image('logo.png', 80, 80), 'That image is too small. Use one at least 120 × 120 pixels.'],
    'too big' => [fn () => UploadedFile::fake()->image('logo.png', 600, 600)->size(3000), 'That image is over 2 MB. Try a smaller one.'],
]);
