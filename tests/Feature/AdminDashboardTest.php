<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\QrCode;
use App\Models\Review;
use App\Models\Shop;
use App\Models\StampLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

function dashboardAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function logStamp(Shop $shop, Customer $customer, Carbon|string $when, ActionType $type = ActionType::StampAdded): void
{
    StampLog::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id, 'action_type' => $type, 'created_at' => $when]);
}

test('the admin dashboard is in the admin menu and admin-only', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id]);

    $this->get('/admin/dashboard')->assertRedirect('/login');
    $this->actingAs($owner)->get('/admin/dashboard')->assertForbidden();

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('navigation.main.0.label', 'Dashboard')
        ->where('navigation.main.0.active', true)
    );
});

test('every card has its own range: 30 days by default, 7 or 90 on request, anything else ignored', function () {
    $admin = dashboardAdmin();

    $this->actingAs($admin)->get('/admin/dashboard?visits=7&customerGrowth=90&comparison=365')->assertInertia(fn ($page) => $page
        ->where('visits.range', 7)
        ->where('customerGrowth.range', 90)
        ->has('customerGrowth.days', 90)
        ->where('comparison.range', 30) // 365 isn't allowed
        ->where('kpis.range', 30)
        ->where('shopGrowth.range', 30)
        ->has('shopGrowth.days', 30)
        ->where('activeCustomers.range', 30)
        ->where('stampsRewards.range', 30)
        ->where('shops.range', 30)
        ->where('funnel.range', 30)
    );
});

test('changing one card reloads only that card', function () {
    $version = app(HandleInertiaRequests::class)->version(Request::create('/'));

    $response = $this->actingAs(dashboardAdmin())->get('/admin/dashboard?visits=7', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $version,
        'X-Inertia-Partial-Component' => 'Admin/Dashboard',
        'X-Inertia-Partial-Data' => 'visits',
    ]);

    $response->assertOk()->assertJsonPath('props.visits.range', 7);
    $props = $response->json('props');

    foreach (['kpis', 'customerGrowth', 'comparison', 'shops', 'ratings', 'health'] as $other) {
        expect($props)->not->toHaveKey($other);
    }
});

test('the same metrics are given for this period and the one before it', function () {
    $shop = Shop::factory()->create();
    $alice = Customer::factory()->create(['created_at' => now()->subDays(20)]);
    $bob = Customer::factory()->create(['created_at' => now()->subDays(2)]);

    // The week before (range 7): Alice's first visit.
    logStamp($shop, $alice, now()->subDays(10));
    // This week: Alice returns twice, Bob visits for the first time and redeems.
    logStamp($shop, $alice, now()->subDay());
    logStamp($shop, $alice, now()->subDays(2));
    logStamp($shop, $bob, now()->subDays(2));
    logStamp($shop, $bob, now()->subDays(2), ActionType::RewardRedeemed);

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?kpis=7&comparison=7')->assertInertia(fn ($page) => $page
        ->where('kpis.totals.customers', 2)
        ->where('kpis.current.new_customers', 1)
        ->where('kpis.current.stamps', 3)
        ->where('kpis.current.rewards', 1)
        ->where('kpis.current.active_customers', 2)
        ->where('kpis.current.first_time_customers', 1)
        ->where('kpis.current.returning_customers', 1)
        ->where('kpis.current.active_shops', 1)
        ->where('kpis.previous.stamps', 1)
        ->where('kpis.previous.active_customers', 1)
        ->where('kpis.previous.first_time_customers', 1)
        ->where('kpis.previous.new_customers', 0)
        // The comparison table reads the same numbers for the same range.
        ->where('comparison.current.stamps', 3)
        ->where('comparison.previous.stamps', 1)
    );
});

test('growth charts carry a running total and new-per-day', function () {
    $this->travelTo(Carbon::parse('2026-09-28 12:00'));

    Customer::factory()->create(['created_at' => '2026-08-01 10:00']); // before the range
    Customer::factory()->create(['created_at' => '2026-09-26 10:00']);
    Customer::factory()->create(['created_at' => '2026-09-28 09:00']);
    Shop::factory()->create(['created_at' => '2026-09-27 09:00']);

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?customerGrowth=7&shopGrowth=7')->assertInertia(fn ($page) => $page
        ->where('customerGrowth.days.0.date', '2026-09-22')
        ->where('customerGrowth.days.0.total', 1)
        ->where('customerGrowth.days.4.new', 1)
        ->where('customerGrowth.days.4.total', 2)
        ->where('customerGrowth.days.6.total', 3)
        ->where('customerGrowth.total', 3)
        ->where('customerGrowth.new', 2)
        ->where('shopGrowth.days.5.total', 1)
        ->where('shopGrowth.days.5.new', 1)
    );
});

test('active customers are split into first visits and returning; stamps and rewards per day', function () {
    $this->travelTo(Carbon::parse('2026-09-28 12:00'));
    $shop = Shop::factory()->create();
    $regular = Customer::factory()->create();
    $newbie = Customer::factory()->create();

    logStamp($shop, $regular, '2026-09-01 10:00');
    logStamp($shop, $regular, '2026-09-28 09:00');
    logStamp($shop, $newbie, '2026-09-28 10:00');
    logStamp($shop, $newbie, '2026-09-28 10:05', ActionType::RewardRedeemed); // still one first-time customer

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?activeCustomers=7&stampsRewards=7')->assertInertia(fn ($page) => $page
        ->where('activeCustomers.days.6.active', 2)
        ->where('activeCustomers.days.6.first_time', 1)
        ->where('activeCustomers.days.6.returning', 1)
        ->where('activeCustomers.first_time', 1)
        ->where('activeCustomers.returning', 1)
        ->where('stampsRewards.days.6.stamps', 2)
        ->where('stampsRewards.days.6.rewards', 1)
        ->where('stampsRewards.stamps', 2)
        ->where('stampsRewards.rewards', 1)
    );
});

test('visits are bucketed by weekday (Monday first) and hour, stamps and rewards separately', function () {
    $this->travelTo(Carbon::parse('2026-09-28 18:00')); // a Monday

    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create();
    logStamp($shop, $customer, '2026-09-28 14:20'); // Monday 14:00
    logStamp($shop, $customer, '2026-09-28 14:50');
    logStamp($shop, $customer, '2026-09-27 09:05'); // Sunday 09:00
    logStamp($shop, $customer, '2026-09-27 09:30', ActionType::RewardRedeemed);

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?visits=7')->assertInertia(fn ($page) => $page
        ->has('visits.stamps', 7)
        ->has('visits.stamps.0', 24)
        ->where('visits.stamps.0.14', 2)
        ->where('visits.stamps.6.9', 1)
        ->where('visits.rewards.6.9', 1)
        ->where('visits.rewards.0.14', 0)
    );
});

test('the headline rating is the average of each shop\'s own average', function () {
    $busy = Shop::factory()->create(['name' => 'Busy Bakery']);
    $small = Shop::factory()->create(['name' => 'Small Cafe']);

    // Busy: 4 reviews averaging 5. Small: 1 review of 1. Per-review mean would be 4.2;
    // per-shop mean is (5 + 1) / 2 = 3 - a busy shop can't drown out a struggling one.
    foreach (range(1, 4) as $i) {
        Review::factory()->create(['shop_id' => $busy->id, 'customer_id' => Customer::factory()->create()->id, 'rating' => 5]);
    }
    Review::factory()->create(['shop_id' => $small->id, 'customer_id' => Customer::factory()->create()->id, 'rating' => 1]);

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->where('kpis.rating.shop_average', 3)
        ->where('ratings.shop_average', 3)
        ->where('ratings.rated_shops', 2)
        ->where('ratings.reviews', 5)
        ->where('ratings.distribution.0', ['stars' => 5, 'count' => 4])
        ->where('ratings.distribution.4', ['stars' => 1, 'count' => 1])
        // Only shops with enough reviews to judge are ranked as lowest.
        ->has('ratings.lowest', 1)
        ->where('ratings.lowest.0.name', 'Busy Bakery')
    );
});

test('health ratios describe repeat use, rewards, multi-shop customers and opt-ins', function () {
    $a = Shop::factory()->create();
    $b = Shop::factory()->create();
    $loyal = Customer::factory()->create();
    $oneOff = Customer::factory()->create();

    CustomerShopCard::factory()->create(['customer_id' => $loyal->id, 'shop_id' => $a->id, 'rewards_claimed' => 1, 'marketing_consent' => true]);
    CustomerShopCard::factory()->create(['customer_id' => $loyal->id, 'shop_id' => $b->id]);
    CustomerShopCard::factory()->create(['customer_id' => $oneOff->id, 'shop_id' => $a->id]);

    logStamp($a, $loyal, now()->subDay());
    logStamp($b, $loyal, now()->subDays(2));
    logStamp($a, $oneOff, now()->subDay());

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->where('health.repeat_rate', 50)
        ->where('health.multi_shop_rate', 50)
        ->where('health.reward_reach_rate', 33)
        ->where('health.marketing_opt_in_rate', 33)
    );
});

test('ratios and averages are null, not zero, when there is nothing to measure yet', function () {
    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->where('health.repeat_rate', null)
        ->where('ratings.shop_average', null)
        ->where('kpis.rating.shop_average', null)
        ->where('kpis.current.avg_rating', null)
    );
});

test('owner activation follows the owners who joined in the chosen period', function () {
    $customer = Customer::factory()->create();
    $owner = fn (string $joined) => User::factory()->create(['role' => UserRole::Owner, 'created_at' => $joined]);
    $shopFor = fn (User $u) => Shop::factory()->create(['user_id' => $u->id]);

    // Joined in the last 30 days: one never set up, one set up with no stamps,
    // one stamped early on but has gone quiet (> 14 days), one still stamping.
    $owner('-5 days');
    $shopFor($owner('-10 days'));
    logStamp($shopFor($owner('-25 days')), $customer, now()->subDays(20));
    logStamp($shopFor($owner('-3 days')), $customer, now()->subDay());

    // Joined 80 days ago: only in the 90-day group (and the all-time line).
    $shopFor($owner('-80 days'));

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?funnel=30')->assertInertia(fn ($page) => $page
        ->where('funnel.range', 30)
        ->where('funnel.steps.0.value', 4)
        ->where('funnel.steps.1.value', 3)
        ->where('funnel.steps.2.value', 2)
        ->where('funnel.steps.3.value', 1)
        ->where('funnel.all_time.owners', 5)
    );

    // A different range is a different group of owners - every bar changes.
    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?funnel=7')->assertInertia(fn ($page) => $page
        ->where('funnel.steps.0.value', 2)
        ->where('funnel.steps.1.value', 1)
        ->where('funnel.steps.2.value', 1)
        ->where('funnel.steps.3.value', 1)
    );
    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?funnel=90')->assertInertia(fn ($page) => $page
        ->where('funnel.steps.0.value', 5)
        ->where('funnel.steps.1.value', 4)
    );
});

test('owner activation reports the typical days from sign-up to first stamp', function () {
    $customer = Customer::factory()->create();
    $fast = Shop::factory()->create(['user_id' => User::factory()->create(['role' => UserRole::Owner, 'created_at' => now()->subDays(10)])->id]);
    $slow = Shop::factory()->create(['user_id' => User::factory()->create(['role' => UserRole::Owner, 'created_at' => now()->subDays(20)])->id]);
    logStamp($fast, $customer, now()->subDays(9)); // 1 day after joining
    logStamp($slow, $customer, now()->subDays(15)); // 5 days after joining

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard?funnel=30')->assertInertia(fn ($page) => $page
        ->where('funnel.median_days_to_first_stamp', 3)
    );
});

test('shops are compared on customers, stamps, rewards and rating, busiest first', function () {
    $customer = Customer::factory()->create();
    $busy = Shop::factory()->create(['name' => 'Busy Bakery']);
    $steady = Shop::factory()->create(['name' => 'Steady Cafe']);
    Shop::factory()->create(['name' => 'Silent Barber']);

    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $busy->id]);
    foreach (range(1, 3) as $i) {
        logStamp($busy, $customer, now()->subDays($i));
    }
    logStamp($busy, $customer, now()->subDay(), ActionType::RewardRedeemed);
    Review::factory()->create(['shop_id' => $busy->id, 'customer_id' => $customer->id, 'rating' => 4]);
    logStamp($steady, $customer, now()->subDay());
    logStamp($steady, $customer, now()->subDays(40)); // previous 30-day period

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->has('shops.rows', 2)
        ->where('shops.rows.0.name', 'Busy Bakery')
        ->where('shops.rows.0.stamps', 3)
        ->where('shops.rows.0.rewards', 1)
        ->where('shops.rows.0.customers', 1)
        ->where('shops.rows.0.new_customers', 1)
        ->where('shops.rows.0.rating', 4)
        ->where('shops.rows.1.name', 'Steady Cafe')
        ->where('shops.rows.1.previous', 1)
        ->where('shops.rows.1.rating', null)
    );
});

test('QR progress, newest owners and quiet shops are included', function () {
    $customer = Customer::factory()->create();
    $quiet = Shop::factory()->create(['name' => 'Quiet Cafe', 'created_at' => now()->subMonth()]);
    logStamp($quiet, $customer, now()->subDays(20));
    QrCode::factory()->count(3)->create();
    QrCode::factory()->mapped()->create();
    User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'g-1', 'password' => null]);

    $this->actingAs(dashboardAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->where('kpis.current.qr_mapped', 1)
        ->where('qr.total', 4)
        ->where('qr.mapped', 1)
        ->where('recentOwners.0.shop_name', null)
        ->where('recentOwners.0.via_google', true)
        ->has('quietShops', 1)
        ->where('quietShops.0.name', 'Quiet Cafe')
    );
});
