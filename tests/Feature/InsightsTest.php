<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // A Monday, midday (UK time - the app's timezone).
    $this->travelTo(Carbon::parse('2026-06-15 12:00'));
});

/** @return array{0: User, 1: Shop} */
function insightsOwner(array $shop = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, 'max_stamps' => 10, ...$shop])];
}

function insightsCard(Shop $shop, string $name, array $card = []): CustomerShopCard
{
    return CustomerShopCard::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => Customer::factory()->create(['name' => $name])->id,
        ...$card,
    ]);
}

/** Scans on these days ago (at 10:00 unless a time is given), keeping last_stamped_at in step. */
function visits(CustomerShopCard $card, array $daysAgo, ActionType $action = ActionType::StampAdded, string $time = '10:00'): void
{
    foreach ($daysAgo as $days) {
        DB::table('stamp_logs')->insert([
            'customer_id' => $card->customer_id,
            'shop_id' => $card->shop_id,
            'action_type' => $action->value,
            'created_at' => today()->subDays($days)->setTimeFromTimeString($time),
        ]);
    }

    $last = DB::table('stamp_logs')->where('customer_id', $card->customer_id)->where('shop_id', $card->shop_id)->max('created_at');
    $card->update(['last_stamped_at' => $last]);
}

function insights(User $owner, array $query = []): array
{
    return test()->actingAs($owner)->get('/dashboard/insights?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

test('owners have an Insights page in their menu', function () {
    [$owner] = insightsOwner();

    $this->actingAs($owner)->get('/dashboard/insights')->assertInertia(fn ($page) => $page
        ->component('Dashboard/Insights')
        ->where('ranges', [30, 90, 365])
        ->where('regulars.range', 90)
        ->where('navigation.main', fn ($items) => collect($items)->contains('label', 'Insights')));
});

test('admins and guests cannot open an owner\'s insights', function () {
    $this->get('/dashboard/insights')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))->get('/dashboard/insights')->assertForbidden();
});

/* ---------- Regulars ---------- */

test('regulars are ranked by visits in the period, counting one visit per day', function () {
    [$owner, $shop] = insightsOwner();
    $amy = insightsCard($shop, 'Amy');
    $ben = insightsCard($shop, 'Ben');
    $cat = insightsCard($shop, 'Cat');
    visits($amy, [1, 1, 1, 8, 15, 22]);             // 4 visit days, 3 scans on one day
    visits($ben, [2, 4, 6, 8, 10]);                 // 5 visits, every 2 days
    visits($ben, [4], ActionType::RewardRedeemed);  // same day as a stamp: still one visit
    visits($cat, [200]);                            // outside 90 days

    $regulars = insights($owner)['regulars'];

    expect(collect($regulars['customers'])->pluck('name')->all())->toBe(['Ben', 'Amy'])
        ->and($regulars['active_customers'])->toBe(2)
        ->and($regulars['customers'][0])->toMatchArray(['visits' => 5, 'rewards' => 1, 'every_days' => 2.0])
        ->and($regulars['customers'][1])->toMatchArray(['visits' => 4, 'every_days' => 7.0]);

    // The 365-day view reaches Cat too.
    expect(collect(insights($owner, ['regulars' => 365])['regulars']['customers'])->pluck('name'))->toContain('Cat');
});

test('close to a reward lists ready cards and cards 1-2 stamps away', function () {
    [$owner, $shop] = insightsOwner();
    insightsCard($shop, 'Ready', ['current_stamps' => 10]);
    insightsCard($shop, 'Nine', ['current_stamps' => 9]);
    insightsCard($shop, 'Eight', ['current_stamps' => 8]);
    insightsCard($shop, 'Seven', ['current_stamps' => 7]);

    $rewards = insights($owner)['rewards'];

    expect(collect($rewards['ready'])->pluck('name')->all())->toBe(['Ready'])
        ->and(collect($rewards['almost'])->pluck('name')->all())->toBe(['Nine', 'Eight'])
        ->and(collect($rewards['almost'])->pluck('to_go')->all())->toBe([1, 2]);
});

/* ---------- Due back / drifting ---------- */

test('regulars are due back or drifting away by their own rhythm', function () {
    [$owner, $shop] = insightsOwner();
    visits(insightsCard($shop, 'Weekly'), [26, 19, 12, 5]);        // every 7 days, last 5 days ago → due in 2
    visits(insightsCard($shop, 'Late'), [17, 13, 9]);               // every 4 days, 9 away → 5 days late, not yet drifting (< 14)
    visits(insightsCard($shop, 'Drifter', ['marketing_consent' => true]), [45, 40, 35, 30]); // every 5 days, 30 away → drifting
    visits(insightsCard($shop, 'Gone'), [215, 210, 205, 200]);      // away 200 days → gone, not listed
    visits(insightsCard($shop, 'Rare'), [300, 200, 100, 3]);        // every ~99 days → not a regular
    visits(insightsCard($shop, 'Twice'), [10, 3]);                  // too few visits to judge
    visits(insightsCard($shop, 'InToday'), [14, 7, 0]);             // here today → not "due"

    $dueBack = insights($owner)['dueBack'];

    expect(collect($dueBack['due'])->pluck('due_in_days', 'name')->all())->toBe(['Late' => -5, 'Weekly' => 2])
        ->and(collect($dueBack['drifting'])->pluck('name')->all())->toBe(['Drifter'])
        ->and($dueBack['drifting'][0])->toMatchArray(['away_days' => 30, 'every_days' => 5.0, 'visits' => 4, 'marketing_consent' => true])
        ->and($dueBack['due_count'])->toBe(2)
        ->and($dueBack['drifting_count'])->toBe(1);
});

/* ---------- Is the card working? ---------- */

test('the second-visit rate counts new customers who came back within 30 days', function () {
    [$owner, $shop] = insightsOwner();
    visits(insightsCard($shop, 'Returned'), [40, 35]);   // first visit in the cohort, back 5 days later
    visits(insightsCard($shop, 'Once'), [45]);           // in the cohort, never back
    visits(insightsCard($shop, 'Late'), [58, 20]);       // in the cohort, back after 38 days - too late
    visits(insightsCard($shop, 'TooNew'), [10, 5]);      // first visit < 30 days ago: not judged yet
    visits(insightsCard($shop, 'Old'), [100, 41]);       // first visit before the cohort

    $second = insights($owner, ['loyalty' => 30])['loyalty']['second_visit'];

    expect($second)->toMatchArray(['customers' => 3, 'came_back' => 1, 'rate' => 33, 'window_days' => 30]);
});

test('visits are split into first and returning visits per day, week or month', function () {
    [$owner, $shop] = insightsOwner();
    visits(insightsCard($shop, 'Amy'), [3, 1]);
    visits(insightsCard($shop, 'Ben'), [1]);

    $visits = insights($owner, ['loyalty' => 30])['loyalty']['visits'];
    $byDate = collect($visits['rows'])->keyBy('date');

    expect($visits['per'])->toBe('day')
        ->and($visits['rows'])->toHaveCount(30)
        ->and($byDate['2026-06-12'])->toMatchArray(['first' => 1, 'returning' => 0])
        ->and($byDate['2026-06-14'])->toMatchArray(['first' => 1, 'returning' => 1])
        ->and($visits['returning_share'])->toBe(33);

    expect(insights($owner, ['loyalty' => 90])['loyalty']['visits']['per'])->toBe('week')
        ->and(insights($owner, ['loyalty' => 365])['loyalty']['visits']['per'])->toBe('month');
});

test('card health: rewards, days to fill a card, who earned one, and where cards stall', function () {
    [$owner, $shop] = insightsOwner();
    $fast = insightsCard($shop, 'Fast', ['rewards_claimed' => 2]);
    visits($fast, [40]);
    visits($fast, [30, 10], ActionType::RewardRedeemed);    // 10 days, then 20 days
    $slow = insightsCard($shop, 'Slow', ['rewards_claimed' => 1]);
    visits($slow, [80]);
    visits($slow, [20], ActionType::RewardRedeemed);        // 60 days
    visits(insightsCard($shop, 'Stuck2', ['current_stamps' => 2]), [90]);
    visits(insightsCard($shop, 'Stuck2b', ['current_stamps' => 2]), [70]);
    visits(insightsCard($shop, 'Stuck7', ['current_stamps' => 7]), [61]);
    visits(insightsCard($shop, 'Active', ['current_stamps' => 3]), [5]); // recent - not stalled

    $cards = insights($owner, ['loyalty' => 90])['loyalty']['cards'];

    expect($cards)->toMatchArray([
        'rewards' => 3,
        'median_days_to_fill' => 20,
        'customers' => 6,
        'earned' => 2,
        'earned_share' => 33,
        'stalled_total' => 3,
        'suggest_fewer' => false, // too few customers to say
    ])
        ->and(collect($cards['stalled'])->pluck('cards', 'stamps')->only([2, 7])->all())->toBe([2 => 2, 7 => 1]);
});

/* ---------- Busy times ---------- */

test('busy times count scans by weekday and hour, Monday first', function () {
    [$owner, $shop] = insightsOwner();
    $card = insightsCard($shop, 'Amy');
    visits($card, [7, 14], time: '09:15');   // Mondays 9am
    visits($card, [1], time: '17:40');       // Sunday 5pm

    $grid = insights($owner, ['busy' => 30])['busy']['grid'];

    expect($grid[0][9])->toBe(2)
        ->and($grid[6][17])->toBe(1)
        ->and(array_sum(array_map('array_sum', $grid)))->toBe(3);
});

/* ---------- Scoping ---------- */

test('insights only ever use the owner\'s own shop', function () {
    [$owner, $shop] = insightsOwner();
    [, $other] = insightsOwner();
    visits(insightsCard($other, 'Elsewhere', ['current_stamps' => 10]), [5, 3, 1]);

    $props = insights($owner);

    expect($props['regulars']['customers'])->toBe([])
        ->and($props['rewards']['ready'])->toBe([])
        ->and(array_sum(array_map('array_sum', $props['busy']['grid'])))->toBe(0);
});

test('an unknown period falls back to 90 days', function () {
    [$owner] = insightsOwner();

    expect(insights($owner, ['regulars' => 7, 'busy' => 'x'])['regulars']['range'])->toBe(90);
});
