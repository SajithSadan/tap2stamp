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

test('an admin can create a shop and its owner', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->post('/admin/shops', [
        'owner_name' => 'Jamie Smith',
        'owner_email' => 'jamie@example.com',
        'shop_name' => 'Corner Bakery',
        'shop_slug' => 'corner-bakery',
        'shop_max_stamps' => 8,
        'shop_reward_title' => 'Free loaf after 8 stamps',
    ]);

    $response->assertRedirect('/admin');
    $response->assertSessionHas('generatedPassword');
    $response->assertSessionHas('createdOwnerEmail', 'jamie@example.com');

    $owner = User::where('email', 'jamie@example.com')->firstOrFail();
    expect($owner->role)->toBe(UserRole::Owner);

    $shop = Shop::where('slug', 'corner-bakery')->firstOrFail();
    expect($shop->user_id)->toBe($owner->id)
        // The owner is the business contact until changed.
        ->and($shop->contact_name)->toBe('Jamie Smith')
        ->and($shop->contact_email)->toBe('jamie@example.com');
});

test('creating a shop requires a unique slug and owner email', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $existingOwner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'taken@example.com']);
    Shop::factory()->create(['user_id' => $existingOwner->id, 'slug' => 'taken-slug']);

    $response = $this->actingAs($admin)->post('/admin/shops', [
        'owner_name' => 'Jamie Smith',
        'owner_email' => 'taken@example.com',
        'shop_name' => 'Corner Bakery',
        'shop_slug' => 'taken-slug',
        'shop_max_stamps' => 8,
        'shop_reward_title' => 'Free loaf after 8 stamps',
    ]);

    $response->assertSessionHasErrors(['owner_email', 'shop_slug']);
});
