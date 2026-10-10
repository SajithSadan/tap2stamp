<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StampLog;
use App\Models\User;

function payloadFor(Customer $customer, Shop $shop): string
{
    return "TOKEN:{$customer->uuid}|SHOP:{$shop->id}";
}

test('scanning a customer adds a stamp', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 5]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertJson(['stamps' => 6, 'reward_ready' => true]);
});

test('scanning a full card only shows the reward - nothing changes until it is marked as given', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6, 'reward_title' => 'Free coffee']);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create(['name' => 'Priya']);
    $card = CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 6,
        'rewards_claimed' => 1,
    ]);

    // An accidental second (or third) scan of a full card changes nothing.
    foreach ([1, 2] as $scan) {
        $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'code' => 'reward_ready', 'stamps' => 6, 'max_stamps' => 6, 'customer_name' => 'Priya', 'reward_title' => 'Free coffee']);
    }

    expect($card->fresh())->current_stamps->toBe(6)->rewards_claimed->toBe(1)
        ->and(StampLog::where('action_type', ActionType::RewardRedeemed)->count())->toBe(0);
});

test('"Mark reward as given" redeems a full card, once', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 6,
        'rewards_claimed' => 1,
    ]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop), 'redeem' => true], ['Authorization' => 'Bearer token'])
        ->assertOk()
        ->assertJson(['status' => 'ok', 'code' => 'reward_redeemed', 'stamps' => 0]);

    expect($card->fresh())->rewards_claimed->toBe(2)->current_stamps->toBe(0)
        ->and(StampLog::where('action_type', ActionType::RewardRedeemed)->count())->toBe(1);

    // A double tap: "already given" - never a second reward, never a stamp.
    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop), 'redeem' => true], ['Authorization' => 'Bearer token'])
        ->assertStatus(409)
        ->assertJson(['code' => 'no_reward', 'message' => 'This reward has already been given.']);

    expect($card->fresh())->rewards_claimed->toBe(2)->current_stamps->toBe(0)
        ->and(StampLog::count())->toBe(1);
});

test('"Mark reward as given" on a card that is not full never stamps it', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 3]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop), 'redeem' => true], ['Authorization' => 'Bearer token'])
        ->assertStatus(409)
        ->assertJson(['code' => 'no_reward', 'stamps' => 3]);

    expect($card->fresh()->current_stamps)->toBe(3)
        ->and(StampLog::count())->toBe(0);
});

test('a reward can be given even right after the stamp that filled the card (no cooldown)', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 6,
        'last_stamped_at' => now(),
    ]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertJson(['code' => 'reward_ready']);
    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop), 'redeem' => true], ['Authorization' => 'Bearer token'])
        ->assertOk()
        ->assertJson(['code' => 'reward_redeemed']);
});

test('the owner\'s scanner works the same: reward_ready first, then mark as given', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'max_stamps' => 6]);
    $customer = Customer::factory()->create();
    $card = CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 6]);

    $this->actingAs($owner)->postJson('/dashboard/scan', ['payload' => payloadFor($customer, $shop)])->assertJson(['code' => 'reward_ready']);
    expect($card->fresh()->current_stamps)->toBe(6);

    $this->postJson('/dashboard/scan', ['payload' => payloadFor($customer, $shop), 'redeem' => true])->assertJson(['code' => 'reward_redeemed']);
    expect($card->fresh()->current_stamps)->toBe(0);
});

test('a cooldown blocks a second stamp too soon after the last one', function () {
    config(['loyalty.stamp_cooldown_hours' => 8]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shopA->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $response = $this->postJson('/api/staff/scan', [
        'payload' => 'TOKEN:11111111-1111-1111-1111-111111111111|SHOP:'.$shop->id,
    ], ['Authorization' => 'Bearer token']);

    $response->assertStatus(404);
    $response->assertJson(['status' => 'error', 'code' => 'customer_not_found']);
});

test('a malformed payload is rejected', function () {
    $shop = Shop::factory()->create();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
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
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertOk();

    $response = $this->getJson('/api/staff/summary', ['Authorization' => 'Bearer token']);

    $response->assertOk();
    $response->assertJson(['stamps_today' => 1]);
});
