<?php

use App\Enums\ActionType;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StampLog;

function payloadFor(Customer $customer, Shop $shop): string
{
    return "TOKEN:{$customer->uuid}|SHOP:{$shop->id}";
}

test('scanning a customer adds a stamp', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create(['name' => 'Jamie Smith']);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 2]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson([
        'status' => 'ok',
        'code' => 'stamp_added',
        'stamps' => 3,
        'max_stamps' => 6,
        'customer_name' => 'Jamie Smith',
        'reward_ready' => false,
    ]);

    expect(StampLog::where('action_type', ActionType::StampAdded)->count())->toBe(1);
});

test('scanning a customer who has never visited this shop creates their card and stamps it', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson(['stamps' => 1]);
    expect(CustomerShopCard::where('customer_id', $customer->id)->where('shop_id', $shop->id)->first()->current_stamps)->toBe(1);
});

test('filling the card flags reward_ready', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 5]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertJson(['stamps' => 6, 'reward_ready' => true]);
});

test('scanning a full card redeems the reward instead of adding a stamp', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 6,
        'rewards_claimed' => 1,
    ]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok', 'code' => 'reward_redeemed', 'stamps' => 0]);

    expect($card->fresh()->rewards_claimed)->toBe(2);
    expect($card->fresh()->current_stamps)->toBe(0);
    expect(StampLog::where('action_type', ActionType::RewardRedeemed)->count())->toBe(1);
});

test('redemption ignores the cooldown - a full card redeems even right after its last stamp', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 6,
        'last_stamped_at' => now(),
    ]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson(['code' => 'reward_redeemed']);
});

test('a cooldown blocks a second stamp too soon after the last one', function () {
    config(['loyalty.stamp_cooldown_hours' => 8]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 2,
        'last_stamped_at' => now()->subHours(2),
    ]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertStatus(409);
    $response->assertJson(['status' => 'error', 'code' => 'cooldown', 'stamps' => 2]);
    $response->assertJsonStructure(['next_allowed_at']);
});

test('the cooldown boundary allows a stamp exactly at the configured number of hours', function () {
    config(['loyalty.stamp_cooldown_hours' => 8]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 2,
        'last_stamped_at' => now()->subHours(8),
    ]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson(['code' => 'stamp_added', 'stamps' => 3]);
});

test('a shop mismatch is rejected', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();
    StaffDevice::factory()->create(['shop_id' => $shopA->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shopB->id]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shopB)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertStatus(403);
    $response->assertJson(['status' => 'error', 'code' => 'shop_mismatch']);
});

test('an unknown customer uuid is rejected', function () {
    $shop = Shop::factory()->create();
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $response = $this->postJson('/api/staff/scan', [
        'payload' => 'TOKEN:11111111-1111-1111-1111-111111111111|SHOP:'.$shop->id,
    ], ['Authorization' => 'Bearer token']);

    $response->assertStatus(404);
    $response->assertJson(['status' => 'error', 'code' => 'customer_not_found']);
});

test('a malformed payload is rejected', function () {
    $shop = Shop::factory()->create();
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $response = $this->postJson('/api/staff/scan', ['payload' => 'https://example.com'], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertStatus(422);
    $response->assertJson(['status' => 'error', 'code' => 'invalid_qr']);
});

test('scanning requires an authenticated staff device', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)])->assertUnauthorized();
});

test('two scans in a row within the cooldown only ever result in one stamp', function () {
    // Genuine multi-connection concurrency isn't exercisable here - Pest
    // wraps each test in RefreshDatabase's transaction, so a second, truly
    // independent DB connection wouldn't see this test's data at all. This
    // instead proves the outcome the row lock exists to guarantee: calling
    // scan() twice in immediate succession never double-stamps.
    config(['loyalty.stamp_cooldown_hours' => 8]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 0]);

    $payload = payloadFor($customer, $shop);
    $headers = ['Authorization' => 'Bearer token'];

    $first = $this->postJson('/api/staff/scan', ['payload' => $payload], $headers);
    $second = $this->postJson('/api/staff/scan', ['payload' => $payload], $headers);

    $first->assertJson(['code' => 'stamp_added', 'stamps' => 1]);
    $second->assertStatus(409);
    $second->assertJson(['code' => 'cooldown']);

    expect(StampLog::where('action_type', ActionType::StampAdded)->count())->toBe(1);
});

test('the staff summary reflects a scan made today', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertOk();

    $response = $this->getJson('/api/staff/summary', ['Authorization' => 'Bearer token']);

    $response->assertOk();
    $response->assertJson(['stamps_today' => 1]);
});
