<?php

use App\Models\Shop;
use App\Models\StaffDevice;

test('a valid token renders the success state', function () {
    $shop = Shop::factory()->create(['name' => 'Artisan Cafe']);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'name' => 'Counter iPad', 'token_hash' => hash('sha256', 'good-token')]);

    $response = $this->get('/staff/setup/good-token');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Staff/Setup')
        ->where('valid', true)
        ->where('token', 'good-token')
        ->where('shopName', 'Artisan Cafe')
        ->where('deviceName', 'Counter iPad')
    );
});

test('an unknown token renders the invalid state without leaking a token back', function () {
    $response = $this->get('/staff/setup/does-not-exist');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Staff/Setup')
        ->where('valid', false)
        ->where('token', null)
    );
});

test('a revoked token renders the invalid state', function () {
    StaffDevice::factory()->revoked()->create(['token_hash' => hash('sha256', 'revoked-token')]);

    $response = $this->get('/staff/setup/revoked-token');

    $response->assertInertia(fn ($page) => $page->component('Staff/Setup')->where('valid', false));
});
