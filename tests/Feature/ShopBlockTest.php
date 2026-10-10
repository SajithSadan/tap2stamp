<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;

/** A blocked shop with an owner, a signed-in staff device and a customer who also has a card elsewhere. */
function blockedSetup(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'max_stamps' => 8]);
    $other = Shop::factory()->create(['max_stamps' => 8]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'blocked-token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 3]);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $other->id, 'current_stamps' => 5]);

    $shop->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Unpaid'])->save();

    return [$shop, $other, $customer, $owner];
}

test('shops are active by default', function () {
    expect(Shop::factory()->create()->isBlocked())->toBeFalse();
});

test('the admin can block and unblock a shop', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/block", ['reason' => 'Closing down'])
        ->assertSessionHas('status', "{$shop->name} is blocked.");
    expect($shop->fresh())->isBlocked()->toBeTrue()->blocked_reason->toBe('Closing down');

    $this->get('/admin')->assertInertia(fn ($page) => $page->where('shops.0.status', 'blocked'));
    $this->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->where('shop.blocked_reason', 'Closing down')
        ->whereNot('shop.blocked_at', null));

    $this->delete("/admin/shops/{$shop->id}/block")->assertSessionHas('status', "{$shop->name} is active again.");
    expect($shop->fresh())->isBlocked()->toBeFalse()->blocked_reason->toBeNull();
});

test('owners and staff cannot block a shop', function () {
    [$shop, , , $owner] = blockedSetup();
    $shop->forceFill(['blocked_at' => null])->save();

    $this->actingAs($owner)->put("/admin/shops/{$shop->id}/block")->assertForbidden();
    expect($shop->fresh()->isBlocked())->toBeFalse();
});

test("a blocked shop's card page, endpoints and menu are inactive", function () {
    [$shop, , $customer] = blockedSetup();

    $this->get("/s/{$shop->slug}")->assertForbidden()->assertInertia(fn ($page) => $page
        ->component('ShopInactive')
        ->where('shopName', $shop->name));
    $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}")->assertForbidden()->assertJsonPath('code', 'shop_inactive');
    $this->postJson("/s/{$shop->slug}/register", ['name' => 'Ann', 'phone' => '07700900456'])->assertForbidden();
    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/join", ['marketing_consent' => false])->assertForbidden();
    $this->postJson("/s/{$shop->slug}/card/{$customer->uuid}/review", ['rating' => 5])->assertForbidden();
    $this->get($shop->menuUrl())->assertForbidden()->assertInertia(fn ($page) => $page->component('ShopInactive'));
});

test("the customer's cards at other shops keep working", function () {
    [$shop, $other, $customer] = blockedSetup();

    $this->getJson("/s/{$other->slug}/card/{$customer->uuid}")->assertOk()->assertJsonPath('stamps', 5);
    $this->get("/s/{$other->slug}")->assertOk()->assertInertia(fn ($page) => $page->component('Card'));

    $this->getJson("/my-cards/{$customer->uuid}")
        ->assertOk()
        ->assertJsonPath('cards.0.shop_id', $other->id)
        ->assertJsonPath('cards.0.active', true)
        ->assertJsonPath('cards.1.shop_id', $shop->id)
        ->assertJsonPath('cards.1.active', false);
});

test('staff on a blocked shop cannot sign in or scan, and keep their device', function () {
    [$shop, , $customer] = blockedSetup();
    $auth = ['Authorization' => 'Bearer blocked-token'];

    $this->getJson('/api/staff/me', $auth)->assertForbidden()->assertJsonPath('code', 'shop_blocked');
    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], $auth)
        ->assertForbidden()->assertJsonPath('code', 'shop_blocked');

    expect(CustomerShopCard::where('shop_id', $shop->id)->value('current_stamps'))->toBe(3)
        ->and(StaffDevice::where('shop_id', $shop->id)->whereNull('revoked_at')->exists())->toBeTrue();

    $shop->forceFill(['blocked_at' => null])->save();
    $this->getJson('/api/staff/me', $auth)->assertOk();
});

test("a blocked shop's device setup link doesn't connect", function () {
    $shop = Shop::factory()->create(['blocked_at' => now()]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'setup-token')]);

    $this->get('/staff/setup/setup-token')->assertInertia(fn ($page) => $page
        ->component('Staff/Setup')
        ->where('valid', false)
        ->where('blocked', true)
        ->where('token', null));
});

test('the owner sees a paused page and cannot change or stamp anything', function () {
    [$shop, , $customer, $owner] = blockedSetup();

    $this->actingAs($owner)->get('/dashboard')->assertForbidden()->assertInertia(fn ($page) => $page
        ->component('Dashboard/Blocked')
        ->where('shopName', $shop->name));
    $this->get('/dashboard/settings')->assertInertia(fn ($page) => $page->component('Dashboard/Blocked'));
    $this->postJson('/dashboard/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"])->assertForbidden();
    $this->put('/dashboard/theme', ['theme' => 'tap2stamp'])->assertForbidden();

    expect(CustomerShopCard::where('shop_id', $shop->id)->value('current_stamps'))->toBe(3);
});

test('the admin can still view a blocked shop as its owner', function () {
    [$shop] = blockedSetup();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard/Overview'));
});

test("the admin can preview a blocked shop's card page, the public can't", function () {
    [$shop] = blockedSetup();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->get("/s/{$shop->slug}?preview=1")->assertInertia(fn ($page) => $page->component('ShopInactive'));
    $this->actingAs($admin)->get("/s/{$shop->slug}?preview=1")->assertOk()->assertInertia(fn ($page) => $page->component('Card'));
});
