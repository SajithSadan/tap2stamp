<?php

use App\Models\Shop;
use App\Models\StaffDevice;

test('responses carry the security headers', function () {
    $this->get('/')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
});

test('error responses carry the security headers too', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Frame-Options', 'DENY');
});

test('a missing page renders the friendly error page', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

test('an unknown shop slug renders the friendly error page', function () {
    $this->get('/s/no-such-shop')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 404));
});

test('JSON callers still get a JSON error, not the error page', function () {
    $shop = Shop::factory()->create();

    $this->getJson("/s/{$shop->slug}/card/00000000-0000-0000-0000-000000000000")
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

test('customer registration is rate limited per IP', function () {
    $shop = Shop::factory()->create();

    foreach (range(1, 10) as $i) {
        $this->postJson("/s/{$shop->slug}/register", ['name' => 'Sam', 'phone' => '07700900'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)])
            ->assertOk();
    }

    $this->postJson("/s/{$shop->slug}/register", ['name' => 'Sam', 'phone' => '07700900999'])
        ->assertStatus(429);
});

test('staff setup is rate limited per IP', function () {
    foreach (range(1, 10) as $i) {
        $this->get('/staff/setup/not-a-real-token')->assertOk();
    }

    $this->get('/staff/setup/not-a-real-token')
        ->assertStatus(429)
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 429));
});

test('scanning is rate limited per device, not per IP', function () {
    $shop = Shop::factory()->create();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'device-a')]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'device-b')]);

    // An invalid payload still counts as an attempt - keeps the test fast.
    foreach (range(1, 60) as $i) {
        $this->postJson('/api/staff/scan', ['payload' => 'junk'], ['Authorization' => 'Bearer device-a'])
            ->assertStatus(422);
    }

    $this->postJson('/api/staff/scan', ['payload' => 'junk'], ['Authorization' => 'Bearer device-a'])
        ->assertStatus(429);

    // Same IP, different device: unaffected.
    $this->postJson('/api/staff/scan', ['payload' => 'junk'], ['Authorization' => 'Bearer device-b'])
        ->assertStatus(422);
});

test('customer-facing routes have no way to change stamps', function () {
    $customerRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 's/') || str_starts_with($route->uri(), 'my-cards'))
        ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    // Only registration and reviews write anything, and neither touches stamps
    // (covered by CardRegistrationTest / ReviewTest).
    expect($customerRoutes)->toEqualCanonicalizing([
        's/{shop}/register',
        's/{shop}/card/{customer}/review',
    ]);
});
