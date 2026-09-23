<?php

use App\Events\CardUpdated;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use Illuminate\Support\Facades\Event;

test('a successful stamp dispatches CardUpdated on the correct channel with the right payload', function () {
    Event::fake([CardUpdated::class]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 2]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertOk();

    Event::assertDispatched(CardUpdated::class, function (CardUpdated $event) use ($customer, $shop) {
        return $event->customerUuid === $customer->uuid
            && $event->shopId === $shop->id
            && $event->stamps === 3
            && $event->maxStamps === 6
            && $event->action === 'stamp_added'
            && $event->rewardReady === false
            && $event->broadcastOn()->name === "card.{$customer->uuid}.{$shop->id}"
            && $event->broadcastAs() === 'card.updated';
    });
});

test('a redemption dispatches CardUpdated with action reward_redeemed', function () {
    Event::fake([CardUpdated::class]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 6]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertOk();

    Event::assertDispatched(CardUpdated::class, fn (CardUpdated $event) => $event->action === 'reward_redeemed' && $event->stamps === 0);
});

test('a rejected scan (cooldown) does not dispatch CardUpdated', function () {
    Event::fake([CardUpdated::class]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 2,
        'last_stamped_at' => now(),
    ]);

    $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], ['Authorization' => 'Bearer token'])
        ->assertStatus(409);

    Event::assertNotDispatched(CardUpdated::class);
});

test('an invalid payload does not dispatch CardUpdated', function () {
    Event::fake([CardUpdated::class]);

    $shop = Shop::factory()->create();
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);

    $this->postJson('/api/staff/scan', ['payload' => 'not-a-qr-code'], ['Authorization' => 'Bearer token'])
        ->assertStatus(422);

    Event::assertNotDispatched(CardUpdated::class);
});

test('a broadcast failure does not affect the stamp result', function () {
    // Point the pusher connection at a port nothing listens on, so the
    // broadcast attempt fails fast and deterministically (connection
    // refused) without depending on real network/Pusher reachability -
    // proving StampService's try/catch actually protects the stamp result.
    config([
        'broadcasting.default' => 'pusher',
        'broadcasting.connections.pusher.key' => 'test-key',
        'broadcasting.connections.pusher.secret' => 'test-secret',
        'broadcasting.connections.pusher.app_id' => 'test-app-id',
        'broadcasting.connections.pusher.options.host' => '127.0.0.1',
        'broadcasting.connections.pusher.options.port' => 1,
        'broadcasting.connections.pusher.options.scheme' => 'http',
        'broadcasting.connections.pusher.options.useTLS' => false,
    ]);

    $shop = Shop::factory()->create(['max_stamps' => 6]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 0]);

    $response = $this->postJson('/api/staff/scan', ['payload' => payloadFor($customer, $shop)], [
        'Authorization' => 'Bearer token',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok', 'code' => 'stamp_added', 'stamps' => 1]);

    expect(CustomerShopCard::where('customer_id', $customer->id)->where('shop_id', $shop->id)->first()->current_stamps)
        ->toBe(1);
});
