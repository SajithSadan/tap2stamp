<?php

use App\Enums\UserRole;
use App\Models\Shop;
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

    $owner = User::where('email', 'jamie@example.com')->firstOrFail();
    expect($owner->role)->toBe(UserRole::Owner);

    $shop = Shop::where('slug', 'corner-bakery')->firstOrFail();
    expect($shop->user_id)->toBe($owner->id);
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
