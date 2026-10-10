<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;
use App\Support\Features;

beforeEach(fn () => config(['loyalty.stamp_cooldown_hours' => 8, 'loyalty.multi_stamp_gap_minutes' => 2]));

/** A shop with a signed-in staff device (Bearer token) and a customer with 1 stamp. */
function multiShop(array $shop = []): array
{
    $shop = Shop::factory()->create(['max_stamps' => 10, ...$shop]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 1, 'last_stamped_at' => now()]);

    return [$shop, $customer, $card];
}

function scanAgain(Shop $shop, Customer $customer)
{
    return test()->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], ['Authorization' => 'Bearer token']);
}

test('it starts off: one stamp, then the usual wait - nothing changed for anyone', function () {
    [$shop, $customer] = multiShop();

    expect(Features::default(Features::MULTIPLE_STAMPS))->toBeFalse()
        ->and($shop->hasFeature(Features::MULTIPLE_STAMPS))->toBeFalse();

    $this->travel(3)->hours();
    scanAgain($shop, $customer)->assertStatus(409)->assertJsonPath('code', 'cooldown');
});

test('with it on, a customer can collect another stamp a few minutes later', function () {
    [$shop, $customer, $card] = multiShop();
    Features::setDefault(Features::MULTIPLE_STAMPS, true);

    $this->travel(3)->minutes();
    scanAgain($shop, $customer)->assertOk()->assertJson(['code' => 'stamp_added', 'stamps' => 2]);

    expect($card->fresh()->current_stamps)->toBe(2);
});

test('with it on, a double scan within the gap still counts once', function () {
    [$shop, $customer, $card] = multiShop();
    Features::setDefault(Features::MULTIPLE_STAMPS, true);

    $this->travel(30)->seconds();
    scanAgain($shop, $customer)
        ->assertStatus(409)
        ->assertJsonPath('code', 'cooldown')
        ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Just stamped at'));

    expect($card->fresh()->current_stamps)->toBe(1);
});

test('the admin can switch it on for one shop while the default is off', function () {
    [$shop, $customer] = multiShop(['features' => ['multiple_stamps' => true]]);
    $otherShop = Shop::factory()->create();

    expect($shop->hasFeature(Features::MULTIPLE_STAMPS))->toBeTrue()
        ->and($otherShop->hasFeature(Features::MULTIPLE_STAMPS))->toBeFalse();

    $this->travel(5)->minutes();
    scanAgain($shop, $customer)->assertOk();
});

test('the admin can switch it off for one shop while the default is on', function () {
    Features::setDefault(Features::MULTIPLE_STAMPS, true);
    [$shop, $customer] = multiShop(['features' => ['multiple_stamps' => false]]);

    $this->travel(5)->minutes();
    scanAgain($shop, $customer)->assertStatus(409)->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Already stamped today'));
});

test('admin screens list it, and turning its default off leaves no shop overrides', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    [$shop] = multiShop();

    $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('features', fn ($features) => collect($features)->contains(fn ($f) => $f['key'] === 'multiple_stamps' && $f['default'] === false && $f['keep_in_use'] === false)));

    $this->put('/admin/settings/features', ['feature' => 'multiple_stamps', 'enabled' => true])->assertSessionHasNoErrors();
    $this->put('/admin/settings/features', ['feature' => 'multiple_stamps', 'enabled' => false])->assertSessionHas('status', 'Multiple stamps a day is now off by default.');

    expect($shop->fresh()->features)->toBeNull();

    $this->put("/admin/shops/{$shop->id}/features", ['features' => ['multiple_stamps' => 'on']])->assertSessionHasNoErrors();
    expect($shop->fresh()->hasFeature(Features::MULTIPLE_STAMPS))->toBeTrue();
});
