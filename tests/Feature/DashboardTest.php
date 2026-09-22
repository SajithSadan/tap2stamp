<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;

test('a guest is redirected to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('an admin cannot access the owner dashboard', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/dashboard')->assertForbidden();
});

test('an owner sees only their own shop on the dashboard', function () {
    $ownerA = User::factory()->create(['role' => UserRole::Owner]);
    $shopA = Shop::factory()->create(['user_id' => $ownerA->id, 'name' => 'Shop A']);

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $ownerB->id, 'name' => 'Shop B']);

    $response = $this->actingAs($ownerA)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard/Index')
        ->where('shop.id', $shopA->id)
        ->where('shop.name', 'Shop A')
    );
});

test('an owner can update their shop settings', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'reward_title' => 'Old reward']);

    $response = $this->actingAs($owner)->put('/dashboard/settings', [
        'name' => $shop->name,
        'max_stamps' => $shop->max_stamps,
        'reward_title' => 'New reward',
        'google_review_url' => null,
        'instagram_url' => null,
        'wifi_ssid' => null,
        'wifi_password' => null,
    ]);

    $response->assertRedirect('/dashboard');
    expect($shop->fresh()->reward_title)->toBe('New reward');
});

test('settings validation rejects an out-of-range stamp count', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->put('/dashboard/settings', [
        'name' => $shop->name,
        'max_stamps' => 20,
        'reward_title' => $shop->reward_title,
    ]);

    $response->assertSessionHasErrors('max_stamps');
});

test('adding a staff device stores only its hash and flashes the plain token once', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->post('/dashboard/staff-devices', ['name' => 'Counter iPad']);

    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('staffToken');

    $device = $shop->staffDevices()->firstOrFail();
    $token = session('staffToken');

    expect($device->token_hash)->toBe(hash('sha256', $token));
    expect($device->token_hash)->not->toBe($token);
});

test('revoking a device sets revoked_at', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $device = $shop->staffDevices()->create(['name' => 'Old device', 'token_hash' => hash('sha256', 'x')]);

    $response = $this->actingAs($owner)->delete("/dashboard/staff-devices/{$device->id}");

    $response->assertRedirect('/dashboard');
    expect($device->fresh()->revoked_at)->not->toBeNull();
});

test('an owner cannot revoke another owner\'s device', function () {
    $ownerA = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $ownerA->id]);

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $shopB = Shop::factory()->create(['user_id' => $ownerB->id]);
    $device = $shopB->staffDevices()->create(['name' => 'B device', 'token_hash' => hash('sha256', 'y')]);

    $this->actingAs($ownerA)->delete("/dashboard/staff-devices/{$device->id}")->assertForbidden();
    expect($device->fresh()->revoked_at)->toBeNull();
});
