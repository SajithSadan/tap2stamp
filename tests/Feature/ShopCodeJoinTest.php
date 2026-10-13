<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopCodeLock;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\ShopCodeJoin;
use Illuminate\Routing\Middleware\ThrottleRequests;

const BROWSER_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
const BROWSER_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

function joinWith(string $code, string $browser = BROWSER_A)
{
    return test()->postJson('/staff/join', ['code' => $code, 'browser_id' => $browser], ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)']);
}

// --- The code -----------------------------------------------------------------

test('every shop gets its own unique shop code', function () {
    $codes = Shop::factory()->count(5)->create()->pluck('shop_code');

    expect($codes->unique())->toHaveCount(5)
        ->and($codes->every(fn ($c) => preg_match('/^[A-HJ-NP-Z2-9]{6}$/', $c) === 1))->toBeTrue();
});

test('the owner sees their shop code on the Staff page and can get a new one', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $old = $shop->shop_code;

    $this->actingAs($owner)->get('/dashboard/staff')
        ->assertInertia(fn ($page) => $page->component('Dashboard/Staff')->where('shopCode', $old));

    $this->actingAs($owner)->post('/dashboard/shop-code')->assertRedirect();

    expect($shop->fresh()->shop_code)->not->toBe($old);
    joinWith($old)->assertStatus(422);
});

// --- Joining ------------------------------------------------------------------

test('the right code adds the device to that shop and its token works', function () {
    $shop = Shop::factory()->create();

    $response = joinWith(strtolower(substr($shop->shop_code, 0, 3)).' '.substr($shop->shop_code, 3))
        ->assertOk()
        ->assertJson(['shop_name' => $shop->name]);

    $device = StaffDevice::sole();
    expect($device->shop_id)->toBe($shop->id)
        ->and($device->name)->toBe('iPhone (shop code)') // the test's user agent names no browser
        ->and($device->token_hash)->toBe(hash('sha256', $response->json('token')));

    $this->getJson('/api/staff/me', ['Authorization' => 'Bearer '.$response->json('token')])->assertOk();
});

test('a wrong code adds nothing and says how many tries are left', function () {
    Shop::factory()->create();

    joinWith('ZZZZZZ')->assertStatus(422)->assertJson(['code' => 'wrong_code', 'tries_left' => 2]);
    joinWith('ZZZZZZ')->assertStatus(422)->assertJson(['tries_left' => 1]);

    expect(StaffDevice::count())->toBe(0);
});

test('a blocked shop\'s code adds no device', function () {
    $shop = Shop::factory()->create(['blocked_at' => now()]);

    joinWith($shop->shop_code)->assertForbidden()->assertJson(['code' => 'shop_blocked']);
    expect(StaffDevice::count())->toBe(0);
});

test('the browser id is required and checked', function () {
    $this->postJson('/staff/join', ['code' => 'ABCDEF'])->assertUnprocessable()->assertJsonValidationErrors('browser_id');
    $this->postJson('/staff/join', ['code' => 'ABCDEF', 'browser_id' => 'short'])->assertJsonValidationErrors('browser_id');
});

// --- Locking ------------------------------------------------------------------

test('3 wrong codes in a day lock that browser, even for the right code', function () {
    $shop = Shop::factory()->create();

    joinWith('ZZZZZZ');
    joinWith('ZZZZZZ');
    joinWith('ZZZZZZ')->assertStatus(423)->assertJson(['code' => 'join_locked', 'device_id' => 'AAAAAAAA']);

    joinWith($shop->shop_code)->assertStatus(423);
    expect(StaffDevice::count())->toBe(0)
        ->and(ShopCodeLock::active()->where('browser_id', BROWSER_A)->count())->toBe(1);

    // Another phone on the same network isn't affected.
    joinWith($shop->shop_code, BROWSER_B)->assertOk();
});

test('the screen knows it is locked when it opens again (a reload)', function () {
    Shop::factory()->create();

    $this->getJson('/staff/join/status?browser_id='.BROWSER_A)->assertOk()->assertJson(['locked' => false]);

    foreach (range(1, 3) as $i) {
        joinWith('ZZZZZZ');
    }

    $this->getJson('/staff/join/status?browser_id='.BROWSER_A)->assertOk()->assertJson(['locked' => true, 'device_id' => 'AAAAAAAA']);
    $this->getJson('/staff/join/status?browser_id='.BROWSER_B)->assertJson(['locked' => false]);
    $this->getJson('/staff/join/status')->assertUnprocessable();
});

test('a lock doesn\'t wear off the next day; wrong codes on different days don\'t add up', function () {
    $shop = Shop::factory()->create();

    joinWith('ZZZZZZ');
    joinWith('ZZZZZZ');
    $this->travel(1)->days();
    joinWith('ZZZZZZ')->assertStatus(422)->assertJson(['tries_left' => 2]);
    joinWith('ZZZZZZ');
    joinWith('ZZZZZZ')->assertStatus(423);

    $this->travel(3)->days();
    joinWith($shop->shop_code)->assertStatus(423);
});

test('10 wrong codes in a day from one network lock the network', function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    $shop = Shop::factory()->create();

    foreach (['c', 'd', 'e'] as $letter) {
        foreach (range(1, 3) as $i) {
            joinWith('ZZZZZZ', str_repeat($letter, 32));
        }
    }
    joinWith('ZZZZZZ', str_repeat('f', 32))->assertStatus(423);

    // A fresh browser id on that network is locked too.
    joinWith($shop->shop_code, str_repeat('g', 32))->assertStatus(423);
    expect(ShopCodeLock::active()->whereNotNull('ip')->count())->toBe(1);
});

// --- Unlocking ----------------------------------------------------------------

test('only an admin can unlock, and the count starts again after it', function () {
    $shop = Shop::factory()->create();
    foreach (range(1, 3) as $i) {
        joinWith('ZZZZZZ');
    }
    $lock = ShopCodeLock::sole();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner)->delete("/admin/shop-code-locks/{$lock->id}")->assertForbidden();
    expect($lock->fresh()->unlocked_at)->toBeNull();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->get('/admin/settings')
        ->assertInertia(fn ($page) => $page->component('Admin/Settings')
            ->where('shopCodeLocks.0.device_id', 'AAAAAAAA')
            ->where('shopCodeLocks.0.kind', 'device')
            ->where('shopCodeLocks.0.wrong_codes', 3)
            ->where('shopCodeLocks.0.device', 'iPhone'));
    $this->actingAs($admin)->delete("/admin/shop-code-locks/{$lock->id}")->assertRedirect();

    expect($lock->fresh())->unlocked_at->not->toBeNull()->unlocked_by->toBe($admin->id);

    $this->travel(1)->minutes();
    joinWith('ZZZZZZ')->assertStatus(422)->assertJson(['tries_left' => 2]);
    joinWith($shop->shop_code)->assertOk();
});

test('the device name says which browser and system it is', function (string $ua, string $name) {
    expect(ShopCodeJoin::deviceName($ua))->toBe($name);
})->with([
    'iPhone Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari on iPhone (shop code)'],
    'iPhone Chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0 Mobile/15E148 Safari/604.1', 'Chrome on iPhone (shop code)'],
    'Samsung' => ['Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0 Mobile Safari/537.36', 'Samsung Internet on Android (shop code)'],
    'Windows Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36', 'Chrome on Windows (shop code)'],
    'Windows Edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36 Edg/129.0', 'Edge on Windows (shop code)'],
    'unknown' => ['curl/8.0', 'Unknown device (shop code)'],
]);
