<?php

use App\Models\Shop;
use App\Models\StaffDevice;

test('a request with a valid token is authenticated', function () {
    $shop = Shop::factory()->create();
    $device = StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'valid-token')]);

    $response = $this->getJson('/api/staff/me', ['Authorization' => 'Bearer valid-token']);

    $response->assertOk();
    $response->assertJson(['shop_name' => $shop->name, 'device_name' => $device->name]);
});

test('a missing token is rejected', function () {
    $this->getJson('/api/staff/me')->assertUnauthorized();
});

test('an unknown token is rejected', function () {
    $this->getJson('/api/staff/me', ['Authorization' => 'Bearer does-not-exist'])->assertUnauthorized();
});

test('a revoked token is rejected', function () {
    StaffDevice::factory()->revoked()->create(['token_hash' => hash('sha256', 'revoked-token')]);

    $this->getJson('/api/staff/me', ['Authorization' => 'Bearer revoked-token'])->assertUnauthorized();
});

test('a successful request updates last_used_at', function () {
    $device = StaffDevice::factory()->create(['token_hash' => hash('sha256', 'valid-token'), 'last_used_at' => null]);

    $this->getJson('/api/staff/me', ['Authorization' => 'Bearer valid-token'])->assertOk();

    expect($device->fresh()->last_used_at)->not->toBeNull();
});
