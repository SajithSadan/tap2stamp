<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Models\User;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Support\Facades\Hash;

test('a guest is redirected to login', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('an owner cannot access the admin panel', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get('/admin')->assertForbidden();
});

test('an admin can view the shop list', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Artisan Cafe']);

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('shops.0.name', 'Artisan Cafe')
        ->where('shops.0.owner_email', $owner->email)
    );
});

test('each shop row carries the numbers the grid shows', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'g-1']);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
    StaffMember::factory()->create(['shop_id' => $shop->id]);
    StaffMember::factory()->create(['shop_id' => $shop->id, 'deactivated_at' => now()]); // removed - not counted
    Review::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'rating' => 4]);

    StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::StampAdded]);
    StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::RewardRedeemed]);
    // Outside the 30-day window: not counted in stamps_30d.
    StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::StampAdded, 'created_at' => now()->subDays(40)]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->component('Admin/Index')
        ->where('shops.0.customers', 1)
        ->where('shops.0.new_customers_30d', 1)
        ->where('shops.0.stamps_30d', 1)
        ->where('shops.0.rewards_total', 1)
        ->where('shops.0.rating', 4)
        ->where('shops.0.reviews', 1)
        ->where('shops.0.staff', 1)
        ->where('shops.0.owner_via_google', true)
        ->where('shops.0.status', 'active')
        ->where('shops.0.last_activity_at', fn ($value) => $value !== null)
    );
});

test('shops are active, quiet or not started depending on their last stamp', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = Customer::factory()->create();
    $stampAt = fn (Shop $shop, $when) => StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'created_at' => $when]);

    $stampAt(Shop::factory()->create(['name' => 'Active', 'created_at' => now()->subDays(3)]), now()->subDays(2));
    $stampAt(Shop::factory()->create(['name' => 'Quiet', 'created_at' => now()->subDays(2)]), now()->subDays(20));
    Shop::factory()->create(['name' => 'Not started', 'created_at' => now()->subDay()]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('quietDays', 14)
        ->where('shops', fn ($shops) => collect($shops)->pluck('status', 'name')->all() === [
            'Not started' => 'not_started',
            'Quiet' => 'quiet',
            'Active' => 'active',
        ])
    );
});

test('add shop has no menu item of its own - the Shops item stays highlighted there', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/shops/create')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Create')
        ->where('navigation.main', fn ($items) => ! collect($items)->contains('label', 'Add shop')
            && collect($items)->firstWhere('label', 'Shops')['active'] === true)
    );
});

function newShopPayload(array $overrides = []): array
{
    return [
        'owner_email' => 'jamie@example.com',
        'owner_password' => 'secret-pass-123',
        'shop_name' => 'Corner Bakery',
        'shop_slug' => 'corner-bakery',
        'shop_max_stamps' => 8,
        'shop_reward_title' => 'Free loaf after 8 stamps',
        'shop_stamp_icon' => 'croissant',
        'shop_theme' => 'warm-artisan',
        ...$overrides,
    ];
}

test('the add shop page sends the theme catalog', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/shops/create')->assertInertia(fn ($page) => $page
        ->component('Admin/Create')
        ->where('defaultTheme', ThemeCatalog::DEFAULT)
        ->has('themes.warm-artisan')
    );
});

test('an admin can create a shop with its login, stamp card and theme', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post('/admin/shops', newShopPayload());

    $response->assertRedirect('/admin');
    $response->assertSessionHas('generatedPassword', 'secret-pass-123');
    $response->assertSessionHas('createdOwnerEmail', 'jamie@example.com');

    $owner = User::where('email', 'jamie@example.com')->firstOrFail();
    expect($owner->role)->toBe(UserRole::Owner)
        ->and($owner->name)->toBe('Corner Bakery')
        ->and(Hash::check('secret-pass-123', $owner->password))->toBeTrue();

    $shop = Shop::where('slug', 'corner-bakery')->firstOrFail();
    expect($shop->user_id)->toBe($owner->id)
        // The owner is the business contact until changed (the owner's name starts as the shop's).
        ->and($shop->contact_name)->toBe('Corner Bakery')
        ->and($shop->contact_email)->toBe('jamie@example.com')
        ->and($shop->theme)->toBe('warm-artisan')
        ->and($shop->stamp_icon)->toBe('croissant');
});

test('picking the default theme and tick stores null, so later default changes still apply', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/admin/shops', newShopPayload([
        'shop_theme' => ThemeCatalog::DEFAULT,
        'shop_stamp_icon' => StampIcons::DEFAULT,
    ]))->assertRedirect('/admin');

    $shop = Shop::where('slug', 'corner-bakery')->firstOrFail();
    expect($shop->theme)->toBeNull()->and($shop->stamp_icon)->toBeNull();
});

test('creating a shop requires a unique slug and owner email', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $existingOwner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'taken@example.com']);
    Shop::factory()->create(['user_id' => $existingOwner->id, 'slug' => 'taken-slug']);

    $response = $this->actingAs($admin)->post('/admin/shops', newShopPayload([
        'owner_email' => 'taken@example.com',
        'shop_slug' => 'taken-slug',
    ]));

    $response->assertSessionHasErrors(['owner_email', 'shop_slug']);
});

test('creating a shop rejects a short password, unknown theme and unknown stamp icon', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post('/admin/shops', newShopPayload([
        'owner_password' => 'short',
        'shop_theme' => 'no-such-theme',
        'shop_stamp_icon' => 'no-such-icon',
    ]))->assertSessionHasErrors(['owner_password', 'shop_theme', 'shop_stamp_icon']);

    expect(Shop::count())->toBe(0);
});

test('the card link check says whether a link is free and suggests one that is', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Shop::factory()->create(['slug' => 'corner-bakery']);
    Shop::factory()->create(['slug' => 'corner-bakery-2']);

    $this->actingAs($admin)->getJson('/admin/shops/slug?slug=corner-bakery')
        ->assertOk()
        ->assertJson(['available' => false, 'suggestion' => 'corner-bakery-3']);

    $this->actingAs($admin)->getJson('/admin/shops/slug?slug=new-place')
        ->assertJson(['available' => true, 'suggestion' => 'new-place']);
});

test('the card link check offers free link ideas from the shop name', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Shop::factory()->create(['slug' => 'the-corner-bakery-shop']);

    $this->actingAs($admin)->getJson('/admin/shops/slug?slug=x&name=The+Corner+Bakery+Shop')
        ->assertOk()
        ->assertJsonPath('ideas', ['corner-bakery', 'cornerbakery', 'corner', 'corner-bakery-rewards']);

    $this->actingAs($admin)->getJson('/admin/shops/slug?slug=x')
        ->assertJsonPath('ideas', []);
});

test('only admins can use the card link check', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->getJson('/admin/shops/slug?slug=x')->assertUnauthorized();
    $this->actingAs($owner)->getJson('/admin/shops/slug?slug=x')->assertForbidden();
});
