<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StampLog;
use App\Models\User;

test('an owner can scan a customer card and the activity is attributed to the owner', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'max_stamps' => 6]);
    $customer = Customer::factory()->create(['name' => 'Jamie Smith']);
    CustomerShopCard::factory()->create([
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'current_stamps' => 2,
    ]);

    $response = $this->actingAs($owner)->postJson('/dashboard/scan', [
        'payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}",
    ]);

    $response->assertOk()->assertJson([
        'status' => 'ok',
        'code' => 'stamp_added',
        'stamps' => 3,
        'customer_name' => 'Jamie Smith',
    ]);

    $log = StampLog::sole();
    expect($log->action_type)->toBe(ActionType::StampAdded)
        ->and($log->owner_user_id)->toBe($owner->id)
        ->and($log->staff_member_id)->toBeNull();

    $this->actingAs($owner)->get('/dashboard/activity')
        ->assertInertia(fn ($page) => $page
            ->where('activity.data.0.staff_name', $owner->name)
        );
});

test('the owner scanner rejects a customer QR code belonging to another shop', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $otherShop = Shop::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($owner)->postJson('/dashboard/scan', [
        'payload' => "TOKEN:{$customer->uuid}|SHOP:{$otherShop->id}",
    ])->assertForbidden()->assertJson(['code' => 'shop_mismatch']);

    expect(CustomerShopCard::count())->toBe(0)
        ->and(StampLog::count())->toBe(0);
});

test('a guest cannot use the owner scanner endpoint', function () {
    $this->postJson('/dashboard/scan', ['payload' => 'anything'])->assertUnauthorized();
});