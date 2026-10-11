<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\Industries;

/** A complete UK shop setup (both steps); override any field. */
function industrySetup(array $overrides = []): array
{
    return [
        'name' => 'Fade Room', 'max_stamps' => 8, 'reward_title' => 'Free haircut', 'industry' => 'barber',
        'country' => 'GB', 'contact_name' => 'Sam', 'contact_email' => 'sam@fade.test', 'contact_phone' => '07700 900123',
        'address_line1' => '1 High Street', 'town' => 'Leeds', 'postcode' => 'LS1 4AP', 'delivery_same' => true,
        ...$overrides,
    ];
}

function industryOwner(?string $industry = null): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'industry' => $industry]);

    return [$owner, $shop];
}

// --- Sign-up ---------------------------------------------------------------

test('shop setup offers the industries and saves the one picked', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get('/onboarding')->assertInertia(fn ($page) => $page
        ->component('Onboarding/Shop')
        ->where('industries.0.key', 'cafe')
        ->where('industries.0.rewards.0', 'Free coffee'));

    $this->actingAs($owner)->post('/onboarding', industrySetup())->assertRedirect('/dashboard');

    $shop = $owner->fresh()->shop; // the page view above cached "no shop yet"
    expect($shop->industry)->toBe('barber')
        ->and($shop->industry_other)->toBeNull()
        ->and($shop->industryLabel())->toBe('Barber');
});

test('shop setup needs an industry, and words for "something else"', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', industrySetup(['industry' => '']))->assertSessionHasErrors('industry');
    $this->actingAs($owner)->post('/onboarding', industrySetup(['industry' => 'casino']))->assertSessionHasErrors('industry');
    $this->actingAs($owner)->post('/onboarding', industrySetup(['industry' => 'other']))->assertSessionHasErrors('industry_other');
    expect($owner->shop()->exists())->toBeFalse();

    $this->actingAs($owner)->post('/onboarding', industrySetup(['industry' => 'other', 'industry_other' => ' Bookshop ']))->assertRedirect('/dashboard');
    expect($owner->shop->industry)->toBe('other')
        ->and($owner->shop->industryLabel())->toBe('Bookshop');
});

test('the "something else" words are dropped for any other industry', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->post('/onboarding', industrySetup(['industry' => 'cafe', 'industry_other' => 'Bookshop']));

    expect($owner->shop->industry_other)->toBeNull();
});

// --- The one-time popup for older shops ----------------------------------------

test('an owner whose shop has no industry is asked on the dashboard', function () {
    [$owner] = industryOwner();

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('industryPrompt.industries.0.key', 'cafe'));
    $this->actingAs($owner)->get('/dashboard/settings')->assertInertia(fn ($page) => $page
        ->where('industryPrompt.industries', fn ($industries) => count($industries) === count(Industries::ALL)));
});

test('an owner who has answered is not asked again', function () {
    [$owner] = industryOwner('cafe');

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('industryPrompt', null));
});

test('an admin viewing as the owner is never asked', function () {
    [, $shop] = industryOwner();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('industryPrompt', null));
    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page->where('industryPrompt', null));
});

test('the popup saves the answer to the owner\'s own shop', function () {
    [$owner, $shop] = industryOwner();
    [, $other] = industryOwner();

    $this->actingAs($owner)->from('/dashboard')->put('/dashboard/industry', ['industry' => 'gym'])->assertRedirect('/dashboard');

    expect($shop->fresh()->industry)->toBe('gym')
        ->and($other->fresh()->industry)->toBeNull();
    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('industryPrompt', null));
});

test('the popup validates its answer', function () {
    [$owner, $shop] = industryOwner();

    $this->actingAs($owner)->put('/dashboard/industry', ['industry' => ''])->assertSessionHasErrors('industry');
    $this->actingAs($owner)->put('/dashboard/industry', ['industry' => 'other'])->assertSessionHasErrors('industry_other');

    expect($shop->fresh()->industry)->toBeNull();
});

// --- Settings -------------------------------------------------------------------

test('the owner can change the industry in Settings; leaving it out keeps it', function () {
    [$owner, $shop] = industryOwner('cafe');
    $base = ['name' => $shop->name, 'max_stamps' => 8, 'reward_title' => 'Free coffee'];

    $this->actingAs($owner)->get('/dashboard/settings')->assertInertia(fn ($page) => $page
        ->where('shop.industry', 'cafe')
        ->has('industries'));

    $this->actingAs($owner)->put('/dashboard/settings', [...$base, 'industry' => 'bakery'])->assertSessionHasNoErrors();
    expect($shop->fresh()->industry)->toBe('bakery');

    $this->actingAs($owner)->put('/dashboard/settings', $base)->assertSessionHasNoErrors();
    expect($shop->fresh()->industry)->toBe('bakery');
});

test('the admin can add a shop with or without an industry, and change it later', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $payload = [
        'owner_email' => 'pat@example.com', 'owner_password' => 'secret-pass-123',
        'shop_name' => 'Corner Bakery', 'shop_slug' => 'corner-bakery', 'shop_max_stamps' => 8,
        'shop_reward_title' => 'Free loaf', 'shop_stamp_icon' => 'check', 'shop_theme' => 'tap2stamp',
    ];

    $this->actingAs($admin)->post('/admin/shops', [...$payload, 'shop_industry' => 'bakery'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/admin/shops', [...$payload, 'owner_email' => 'kim@example.com', 'shop_slug' => 'kims'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post('/admin/shops', [...$payload, 'owner_email' => 'lee@example.com', 'shop_slug' => 'lees', 'shop_industry' => 'other'])
        ->assertSessionHasErrors('shop_industry_other');

    $bakery = Shop::where('slug', 'corner-bakery')->first();
    $kims = Shop::where('slug', 'kims')->first();
    expect($bakery->industry)->toBe('bakery')->and($kims->industry)->toBeNull();

    $settings = ['name' => $kims->name, 'max_stamps' => 8, 'reward_title' => 'Free loaf'];
    $this->actingAs($admin)->put("/admin/shops/{$kims->id}/settings", [...$settings, 'industry' => 'other', 'industry_other' => 'Bookshop'])->assertSessionHasNoErrors();
    expect($kims->fresh()->industryLabel())->toBe('Bookshop');

    // Cleared ("Not set"): the owner is asked again.
    $this->actingAs($admin)->put("/admin/shops/{$kims->id}/settings", [...$settings, 'industry' => ''])->assertSessionHasNoErrors();
    expect($kims->fresh()->industry)->toBeNull()->and($kims->fresh()->industry_other)->toBeNull();
});

test('the shops grid shows each shop\'s industry', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Shop::factory()->create(['industry' => 'mobile', 'created_at' => now()->subDay()]);
    Shop::factory()->create(['industry' => null]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.industry', null)
        ->where('shops.0.industry_label', null)
        ->where('shops.1.industry', 'mobile')
        ->where('shops.1.industry_label', 'Mobile phones & electronics'));
});

test('every industry has an icon in the frontend map, and no extras', function () {
    preg_match_all('/^\s{4}(\w+): Lu\w+,$/m', file_get_contents(resource_path('js/lib/industryIcons.js')), $matches);

    expect($matches[1])->toEqualCanonicalizing(Industries::keys());
});
