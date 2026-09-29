<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\User;
use App\Support\ThemeCatalog;

test('a guest cannot open the theme page', function () {
    $this->get('/dashboard/theme')->assertRedirect('/login');
});

test('the theme page lists the catalog and marks the default as current', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'theme' => null]);

    $this->actingAs($owner)->get('/dashboard/theme')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Theme')
            ->where('currentTheme', ThemeCatalog::DEFAULT)
            ->has('themes', count(ThemeCatalog::all()))
            ->has('themes.warm-artisan')
        );
});

test('an owner can choose a theme for their shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'warm-artisan'])->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->theme)->toBe('warm-artisan');
});

test('an unknown theme is rejected', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'not-a-theme'])->assertSessionHasErrors('theme');

    expect($shop->fresh()->theme)->toBeNull();
});

test('the customer card page gets the shop\'s chosen theme', function () {
    $shop = Shop::factory()->create(['theme' => 'warm-artisan']);

    $this->get("/s/{$shop->slug}")
        ->assertInertia(fn ($page) => $page
            ->component('Card')
            ->where('theme.slug', 'warm-artisan')
            ->where('theme.accent', ThemeCatalog::all()['warm-artisan']['accent'])
        );
});

test('a shop without a valid theme falls back to the default look', function (?string $stored) {
    $shop = Shop::factory()->create(['theme' => $stored]);

    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('theme.slug', ThemeCatalog::DEFAULT));
})->with([null, 'removed-theme']);

test('an owner can reset their shop back to the default theme', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan']);

    $this->actingAs($owner)->delete('/dashboard/theme')->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->theme)->toBeNull();
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('theme.slug', ThemeCatalog::DEFAULT));
});

test('the dashboard keeps the default look until the owner opts in', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan']);

    $this->actingAs($owner)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('shop.dashboard_theme', null));
});

test('an owner can use their theme in the dashboard too, and turn it off again', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan']);

    $this->actingAs($owner)->put('/dashboard/theme/dashboard', ['enabled' => true])->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->theme_in_dashboard)->toBeTrue();
    $this->actingAs($owner)->get('/dashboard/customers')
        ->assertInertia(fn ($page) => $page->where('shop.dashboard_theme.slug', 'warm-artisan'));

    $this->actingAs($owner)->put('/dashboard/theme/dashboard', ['enabled' => false]);

    expect($shop->fresh()->theme_in_dashboard)->toBeFalse();
});

test('the dashboard theme switch needs a true or false value', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme/dashboard', ['enabled' => 'maybe'])->assertSessionHasErrors('enabled');
});

function customFields(array $overrides = []): array
{
    return [
        'page_bg' => '#101010',
        'card_bg' => '#1A1A1A',
        'deep' => '#123456',
        'text' => '#FAFAFA',
        'muted' => '#A0A0A0',
        'accent' => '#FF6600',
        'accent_text' => '#000000',
        'border' => '#333333',
        'heading_font_name' => 'Playfair Display',
        'body_font_name' => 'Inter',
        'radius' => '16px',
        ...$overrides,
    ];
}

test('the default Tap2Stamp theme has three brand colours: navy, mint and white', function () {
    $theme = ThemeCatalog::forShop(null);

    expect($theme['slug'])->toBe('tap2stamp')
        ->and($theme['deep'])->toBe('#0F2A46')
        ->and($theme['accent'])->toBe('#17C68B')
        ->and($theme['card_bg'])->toBe('#FFFFFF');
});

test('every catalog theme has a deep colour, near-black unless it sets its own', function () {
    $themes = ThemeCatalog::all();

    foreach ($themes as $slug => $theme) {
        expect($theme['deep'])->toMatch('/^#[0-9A-F]{6}$/i', "{$slug} has no deep colour");
    }

    expect($themes['warm-artisan']['deep'])->toBe(ThemeCatalog::DEFAULT_DEEP);
});

test('a customisation saved before the deep colour existed keeps the theme\'s own', function () {
    $shop = Shop::factory()->create([
        'theme' => 'tap2stamp',
        'theme_custom' => collect(customFields())->except('deep')->all(),
    ]);

    expect($shop->appliedTheme()['deep'])->toBe('#0F2A46');
});

test('an owner can customise colours, fonts and corners on top of their theme', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan']);

    $this->actingAs($owner)->put('/dashboard/theme/custom', customFields())->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->theme_custom['accent'])->toBe('#FF6600');

    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page
        ->where('theme.slug', 'warm-artisan')
        ->where('theme.customised', true)
        ->where('theme.accent', '#FF6600')
        ->where('theme.deep', '#123456')
        ->where('theme.radius', '16px')
        ->where('theme.heading_font', "'Playfair Display', serif")
        ->where('theme.mood', 'dark')
    );
});

test('customisation only accepts hex colours, curated fonts and preset corners', function (array $bad, string $field) {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme/custom', customFields($bad))->assertSessionHasErrors($field);

    expect($shop->fresh()->theme_custom)->toBeNull();
})->with([
    [['accent' => 'red'], 'accent'],
    [['page_bg' => '#12345'], 'page_bg'],
    [['heading_font_name' => 'Comic Sans MS'], 'heading_font_name'],
    [['radius' => '3px'], 'radius'],
]);

test('picking a new theme or resetting drops the customisation', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan', 'theme_custom' => customFields()]);

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'modern-minimal']);
    expect($shop->fresh()->theme_custom)->toBeNull();

    $shop->update(['theme_custom' => customFields()]);
    $this->actingAs($owner)->delete('/dashboard/theme');
    expect($shop->fresh()->only(['theme', 'theme_custom']))->toBe(['theme' => null, 'theme_custom' => null]);
});

test('removing the customisation keeps the chosen theme', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan', 'theme_custom' => customFields()]);

    $this->actingAs($owner)->delete('/dashboard/theme/custom')->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->only(['theme', 'theme_custom']))->toBe(['theme' => 'warm-artisan', 'theme_custom' => null]);
});

test('the dashboard opt-in uses the customised theme too', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'theme' => 'warm-artisan', 'theme_custom' => customFields(), 'theme_in_dashboard' => true]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('shop.dashboard_theme.accent', '#FF6600'));
});

test('an owner can choose the stamp icon, and customers see it', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme/stamp-icon', ['stamp_icon' => 'coffee'])->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->stamp_icon)->toBe('coffee');
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.stamp_icon', 'coffee'));
});

test('an unknown stamp icon is rejected', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme/stamp-icon', ['stamp_icon' => 'rocket'])->assertSessionHasErrors('stamp_icon');

    expect($shop->fresh()->stamp_icon)->toBeNull();
});

test('shops without a stamp icon show the default tick, including in My Cards', function () {
    $shop = Shop::factory()->create(['stamp_icon' => null]);
    $coffeeShop = Shop::factory()->create(['stamp_icon' => 'coffee']);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $coffeeShop->id]);

    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.stamp_icon', 'check'));

    $icons = collect($this->getJson("/my-cards/{$customer->uuid}")->json('cards'))->pluck('stamp_icon', 'shop_id');
    expect($icons[$shop->id])->toBe('check');
    expect($icons[$coffeeShop->id])->toBe('coffee');
});

// --- Sign-up screen icon ------------------------------------------------------

test('an owner can choose the sign-up screen icon, separately from the stamp icon', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'stamp_icon' => 'check']);

    $this->actingAs($owner)->put('/dashboard/theme/signup-icon', ['signup_icon' => 'scissors'])->assertRedirect('/dashboard/theme');

    expect($shop->fresh()->signup_icon)->toBe('scissors')
        ->and($shop->fresh()->stamp_icon)->toBe('check');
    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.signup_icon', 'scissors'));
    $this->actingAs($owner)->get('/dashboard/theme')->assertInertia(fn ($page) => $page->where('signupIcon', 'scissors'));
});

test('an unknown sign-up icon is rejected', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->put('/dashboard/theme/signup-icon', ['signup_icon' => 'rocket'])->assertSessionHasErrors('signup_icon');

    expect($shop->fresh()->signup_icon)->toBeNull();
});

test('shops that never chose a sign-up icon show the neutral sparkles, not coffee beans', function () {
    $shop = Shop::factory()->create(['signup_icon' => null]);

    $this->get("/s/{$shop->slug}")->assertInertia(fn ($page) => $page->where('shop.signup_icon', 'sparkles'));
});
