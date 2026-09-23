<?php

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\StampLog;

function staffDevice(Shop $shop, string $token = 'token'): StaffDevice
{
    return StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', $token)]);
}

function bearer(string $token = 'token'): array
{
    return ['Authorization' => "Bearer {$token}"];
}

test('the staff dashboard page renders', function () {
    $this->get('/staff')->assertOk()->assertInertia(fn ($page) => $page->component('Staff/Dashboard'));
});

test('me lists only this shop\'s active staff, with nobody signed in yet', function () {
    $shop = Shop::factory()->create();
    staffDevice($shop);
    StaffMember::factory()->for($shop)->create(['name' => 'Sam']);
    StaffMember::factory()->for($shop)->deactivated()->create(['name' => 'Gone']);
    StaffMember::factory()->create(['name' => 'Other shop']);

    $this->getJson('/api/staff/me', bearer())
        ->assertOk()
        ->assertJson(['staff' => null])
        ->assertJsonCount(1, 'staff_members')
        ->assertJsonPath('staff_members.0.name', 'Sam')
        ->assertJsonMissingPath('staff_members.0.pin_hash');
});

test('the right PIN signs a staff member in on the device', function () {
    $shop = Shop::factory()->create();
    $device = staffDevice($shop);
    $sam = StaffMember::factory()->for($shop)->withPin('4321')->create(['name' => 'Sam']);

    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $sam->id, 'pin' => '4321'], bearer())
        ->assertOk()
        ->assertJson(['staff' => ['id' => $sam->id, 'name' => 'Sam']]);

    expect($device->fresh()->staff_member_id)->toBe($sam->id);
    $this->getJson('/api/staff/me', bearer())->assertJsonPath('staff.name', 'Sam');
});

test('a wrong PIN is rejected', function () {
    $shop = Shop::factory()->create();
    $device = staffDevice($shop);
    $sam = StaffMember::factory()->for($shop)->withPin('4321')->create();

    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $sam->id, 'pin' => '1111'], bearer())
        ->assertStatus(422)
        ->assertJson(['code' => 'invalid_pin']);

    expect($device->fresh()->staff_member_id)->toBeNull();
});

test('a staff member from another shop cannot sign in on this device', function () {
    $shop = Shop::factory()->create();
    staffDevice($shop);
    $outsider = StaffMember::factory()->withPin('4321')->create();

    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $outsider->id, 'pin' => '4321'], bearer())
        ->assertStatus(422);
});

test('a removed staff member cannot sign in', function () {
    $shop = Shop::factory()->create();
    staffDevice($shop);
    $gone = StaffMember::factory()->for($shop)->withPin('4321')->deactivated()->create();

    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $gone->id, 'pin' => '4321'], bearer())
        ->assertStatus(422);
});

test('PIN attempts are rate limited per device', function () {
    $shop = Shop::factory()->create();
    staffDevice($shop);
    $sam = StaffMember::factory()->for($shop)->withPin('4321')->create();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/staff/sign-in', ['staff_member_id' => $sam->id, 'pin' => '0000'], bearer())->assertStatus(422);
    }

    $this->postJson('/api/staff/sign-in', ['staff_member_id' => $sam->id, 'pin' => '4321'], bearer())->assertStatus(429);
});

test('scanning without a signed-in staff member is refused without forgetting the device', function () {
    $shop = Shop::factory()->create();
    staffDevice($shop);
    $customer = Customer::factory()->create();

    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], bearer())
        ->assertStatus(403)
        ->assertJson(['code' => 'staff_signed_out']);

    expect(StampLog::count())->toBe(0);
});

test('a sign-in expires after the configured number of hours', function () {
    config(['loyalty.staff_session_hours' => 12]);

    $shop = Shop::factory()->create();
    $sam = StaffMember::factory()->for($shop)->create();
    StaffDevice::factory()->create([
        'shop_id' => $shop->id,
        'token_hash' => hash('sha256', 'token'),
        'staff_member_id' => $sam->id,
        'staff_signed_in_at' => now()->subHours(12)->subMinute(),
    ]);

    $this->getJson('/api/staff/summary', bearer())->assertStatus(403);
    $this->getJson('/api/staff/me', bearer())->assertJson(['staff' => null]);
});

test('signing out clears the device', function () {
    $shop = Shop::factory()->create();
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $this->postJson('/api/staff/sign-out', [], bearer())->assertOk();

    expect($device->fresh()->staff_member_id)->toBeNull();
    $this->getJson('/api/staff/summary', bearer())->assertStatus(403);
});

test('a scan is logged against the signed-in staff member', function () {
    $shop = Shop::factory()->create();
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();

    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], bearer())->assertOk();

    expect(StampLog::sole()->staff_member_id)->toBe($device->fresh()->staff_member_id);
});

test('the summary splits my numbers from the whole shop\'s', function () {
    $shop = Shop::factory()->create();
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $colleague = StaffMember::factory()->for($shop)->create();

    StampLog::factory()->count(2)->create(['shop_id' => $shop->id, 'staff_member_id' => $device->fresh()->staff_member_id]);
    StampLog::factory()->create(['shop_id' => $shop->id, 'staff_member_id' => $colleague->id]);

    $this->getJson('/api/staff/summary', bearer())
        ->assertOk()
        ->assertJson(['my_stamps_today' => 2, 'stamps_today' => 3]);
});

test('customer lookup finds this shop\'s customers by name or phone digits and masks the phone', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $jamie = Customer::factory()->create(['name' => 'Jamie Smith', 'phone' => '+447911123456']);
    CustomerShopCard::factory()->create(['customer_id' => $jamie->id, 'shop_id' => $shop->id, 'current_stamps' => 6]);

    // Same name, different shop: must never show up.
    $elsewhere = Customer::factory()->create(['name' => 'Jamie Elsewhere']);
    CustomerShopCard::factory()->create(['customer_id' => $elsewhere->id]);

    $this->getJson('/api/staff/customers?q=jamie', bearer())
        ->assertOk()
        ->assertJsonCount(1, 'customers')
        ->assertJsonPath('customers.0.name', 'Jamie Smith')
        ->assertJsonPath('customers.0.phone_ending', '456')
        ->assertJsonPath('customers.0.reward_ready', true)
        ->assertJsonMissingPath('customers.0.phone');

    $this->getJson('/api/staff/customers?q=123456', bearer())
        ->assertJsonCount(1, 'customers')
        ->assertJsonPath('customers.0.name', 'Jamie Smith');
});

test('customer lookup needs a signed-in staff member', function () {
    staffDevice(Shop::factory()->create());

    $this->getJson('/api/staff/customers?q=jamie', bearer())->assertStatus(403);
});
