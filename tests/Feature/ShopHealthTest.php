<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Models\User;
use App\Services\ShopHealth;

/** One shop's figures for ShopHealth::assess(), all zero / empty unless given. */
function healthFigures(array $overrides = []): array
{
    return [
        'first_stamp_at' => null, 'last_stamp_at' => null,
        'active_days_14' => 0, 'active_days_prev_14' => 0,
        'stamps_week' => 0, 'stamps_prev_week' => 0,
        'rewards_total' => 0, 'rewards_30d' => 0,
        'visitors' => 0, 'returning' => 0, 'customers' => 0, 'new_customers_30d' => 0,
        'staff' => 0, 'devices' => 0, 'display' => false, 'owner_login_at' => null, 'extras' => false,
        ...$overrides,
    ];
}

function assessHealth(array $overrides): array
{
    return app(ShopHealth::class)->assess(healthFigures($overrides), now());
}

// --- The rules ---------------------------------------------------------------

test('a shop that never stamped is "never started", at step 2 (or 3 with staff)', function () {
    $empty = assessHealth([]);
    expect($empty['segment'])->toBe('never_started')
        ->and($empty['step'])->toBe(2)
        ->and($empty['score'])->toBe(0)
        ->and($empty['reasons'][0])->toBe('No stamps yet, and no staff or staff device set up');

    $withStaff = assessHealth(['staff' => 2]);
    expect($withStaff['step'])->toBe(3)->and($withStaff['next_step'])->toBe('First stamp');
});

test('no stamps for 14 days is "stalled"', function () {
    $r = assessHealth(['first_stamp_at' => now()->subDays(40), 'last_stamp_at' => now()->subDays(20), 'active_days_prev_14' => 2, 'customers' => 3, 'visitors' => 3]);

    expect($r['segment'])->toBe('stalled')->and($r['reasons'][0])->toBe('No stamps for 20 days');
});

test('a busy shop whose stamping halved is "declining", before anything else', function () {
    $r = assessHealth([
        'first_stamp_at' => now()->subDays(60), 'last_stamp_at' => now()->subDays(2),
        'active_days_14' => 3, 'active_days_prev_14' => 10,
        'customers' => 80, 'visitors' => 70, 'returning' => 30, 'stamps_week' => 2, 'stamps_prev_week' => 20,
    ]);

    expect($r['segment'])->toBe('declining')
        ->and($r['reasons'])->toContain('Stamped on 3 of the last 14 days, down from 10')
        ->and($r['reasons'])->toContain('Stamps fell from 20 to 2 this week');
});

test('stamping with few customers coming back is "trickling"; enough of them is "healthy"', function () {
    $base = ['first_stamp_at' => now()->subDays(30), 'last_stamp_at' => now(), 'active_days_14' => 8, 'customers' => 40, 'visitors' => 40];

    expect(assessHealth([...$base, 'returning' => 4])['segment'])->toBe('trickling') // 10%
        ->and(assessHealth([...$base, 'returning' => 12])['segment'])->toBe('healthy') // 30%
        ->and(assessHealth([...$base, 'customers' => 6, 'visitors' => 6, 'returning' => 5])['segment'])->toBe('trickling'); // too few yet
});

test('a shop doing everything scores 100 and reaches the daily habit step', function () {
    $r = assessHealth([
        'first_stamp_at' => now()->subDays(60), 'last_stamp_at' => now(),
        'active_days_14' => 12, 'active_days_prev_14' => 12, 'stamps_week' => 40, 'stamps_prev_week' => 40,
        'rewards_total' => 9, 'rewards_30d' => 4, 'visitors' => 100, 'returning' => 40, 'customers' => 120, 'new_customers_30d' => 30,
        'staff' => 2, 'devices' => 1, 'display' => true, 'owner_login_at' => now()->subDay(), 'extras' => true,
    ]);

    expect($r['score'])->toBe(100)
        ->and($r['segment'])->toBe('healthy')
        ->and($r['step'])->toBe(8)
        ->and($r['next_step'])->toBeNull()
        ->and(array_sum(ShopHealth::SCORE))->toBe(100);
});

test('the ladder stops at the first step not reached', function () {
    // Has rewards (step 7) but under 10 customers (step 5): stops at 4.
    $r = assessHealth(['first_stamp_at' => now()->subDays(5), 'last_stamp_at' => now(), 'active_days_14' => 2, 'customers' => 4, 'visitors' => 4, 'returning' => 4, 'rewards_total' => 1]);

    expect($r['step'])->toBe(4)->and($r['next_step'])->toBe('Traction (10+ customers)');
});

// --- From the database -------------------------------------------------------------

test('shops are scored from their real stamps, cards, staff and sign-ins', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);
    $quiet = Shop::factory()->create();
    StaffMember::factory()->create(['shop_id' => $shop->id]);
    ActivityLog::create(['actor_type' => 'owner', 'user_id' => $owner->id, 'actor_name' => 'o', 'action' => 'auth.signed_in', 'description' => 'Signed in']);

    // 12 customers; 4 of them came on two different days.
    foreach (range(1, 12) as $i) {
        $customer = Customer::factory()->create();
        CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id]);
        StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::StampAdded, 'created_at' => now()->subDays($i % 10)]);
        if ($i <= 4) {
            StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::StampAdded, 'created_at' => now()->subDays(12)]);
        }
    }
    // The last customer's reward, the same day as their stamp (so still one visit).
    StampLog::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'action_type' => ActionType::RewardRedeemed, 'created_at' => now()->subDays(12 % 10)]);

    $health = app(ShopHealth::class)->forShops(Shop::all());
    $m = $health[$shop->id]['metrics'];

    expect($m['customers'])->toBe(12)
        ->and($m['visitors'])->toBe(12)
        ->and($m['returning'])->toBe(4)
        ->and($m['return_rate'])->toBe(33)
        ->and($m['rewards_total'])->toBe(1)
        ->and($m['staff'])->toBe(1)
        ->and($m['active_days_14'])->toBe(11) // days 0-9 and 12
        ->and($m['owner_login_at'])->not->toBeNull()
        ->and($health[$shop->id]['segment'])->toBe('healthy')
        ->and($health[$shop->id]['step'])->toBe(8)
        ->and($health[$quiet->id]['segment'])->toBe('never_started');
});

// --- Trial & pipeline ---------------------------------------------------------------

test('a new shop starts a free trial of the configured length', function () {
    config(['loyalty.trial_days' => 30]);
    $shop = Shop::factory()->create();

    expect($shop->trial_ends_at->isSameDay(now()->addDays(30)))->toBeTrue()
        ->and($shop->fresh()->crm_stage)->toBe('trial');
});

test('the shops grid shows health, stage (activated once stamping) and trial days left', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $idle = Shop::factory()->create(['created_at' => now()->subDay(), 'trial_ends_at' => now()->addDays(5)]);
    $busy = Shop::factory()->create(['trial_ends_at' => now()->subDays(3)]);
    StampLog::factory()->create(['shop_id' => $busy->id, 'action_type' => ActionType::StampAdded]);

    $this->actingAs($admin)->get('/admin')->assertInertia(fn ($page) => $page
        ->where('segments.healthy', 'Healthy')
        ->where('shops.0.id', $busy->id)
        ->where('shops.0.stage', 'activated')
        ->where('shops.0.trial_days_left', -3)
        ->where('shops.0.health.segment', 'trickling')
        ->where('shops.1.stage', 'trial')
        ->where('shops.1.trial_days_left', 5)
        ->where('shops.1.health.segment', 'never_started'));
});

test('the admin sets the stage, moves the trial end and records why a shop was lost', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $shop = Shop::factory()->create();

    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->component('Admin/ShopSettings')
        ->where('health.segment', 'never_started')
        ->where('pipeline.crm_stage', 'trial')
        ->has('healthModel.ladder', 8));

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/account", ['crm_stage' => 'lost', 'trial_ends_at' => '2026-12-01', 'lost_reason' => ' Price '])
        ->assertSessionHasNoErrors();
    $shop->refresh();
    expect($shop->crm_stage)->toBe('lost')
        ->and($shop->lost_reason)->toBe('Price')
        // All of 1 Dec, UK time.
        ->and($shop->trial_ends_at->timezone('Europe/London')->format('Y-m-d H:i'))->toBe('2026-12-01 23:59');

    // Not lost any more: the reason goes.
    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/account", ['crm_stage' => 'paid', 'trial_ends_at' => '', 'lost_reason' => 'Price']);
    expect($shop->fresh()->only(['crm_stage', 'lost_reason', 'trial_ends_at']))->toBe(['crm_stage' => 'paid', 'lost_reason' => null, 'trial_ends_at' => null]);

    // Logged by RecordsActivity.
    expect(ActivityLog::where('action', 'shop.updated')->where('shop_id', $shop->id)->exists())->toBeTrue();
});

test('the pipeline is validated and admin only', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/account", ['crm_stage' => 'vip', 'trial_ends_at' => 'soon'])
        ->assertSessionHasErrors(['crm_stage', 'trial_ends_at']);
    $this->actingAs($owner)->put("/admin/shops/{$shop->id}/account", ['crm_stage' => 'paid'])->assertForbidden();

    expect($shop->fresh()->crm_stage)->toBe('trial');
});
