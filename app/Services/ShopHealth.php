<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Is a shop really using Tada Tap? Fixed, explainable rules on data we
 * already have (MONETISATION-PLAN.md: "rules decide, AI explains"):
 *
 * - a **score** 0-100 (see SCORE for the parts),
 * - a **segment**: never_started / stalled / declining / trickling / healthy,
 * - the **ladder** step it has reached (signed up → … → habit),
 * - plain-English **reasons**, for the admin and, later, the AI briefing.
 *
 * All shops are scored together with a fixed number of grouped queries
 * (one row per shop), never one query per shop.
 */
class ShopHealth
{
    public const SEGMENTS = [
        'never_started' => 'Never started',
        'stalled' => 'Stalled',
        'declining' => 'Declining',
        'trickling' => 'Trickling',
        'healthy' => 'Healthy',
    ];

    /** The activation ladder: each step needs the ones before it. */
    public const LADDER = [
        1 => 'Signed up',
        2 => 'Shop set up',
        3 => 'Ready to stamp',
        4 => 'First stamp',
        5 => 'Traction (10+ customers)',
        6 => 'Customers come back',
        7 => 'First reward',
        8 => 'Daily habit',
    ];

    /** The score's parts and their maximum points (adds up to 100). */
    public const SCORE = [
        'active_days' => 30,   // days with a stamp in the last 14 (10+ = full)
        'trend' => 10,         // stamps this week vs last week
        'new_customers' => 15, // new cards in 30 days (20+ = full)
        'return_rate' => 15,   // share of customers with 2+ visits (30%+ = full)
        'rewards' => 10,       // a reward in 30 days (any reward ever = half)
        'staff' => 5,          // staff or a staff device set up
        'display' => 5,        // counter display ordered, or QR downloaded
        'owner_login' => 5,    // owner signed in within 14 days
        'extras' => 5,         // a menu or reviews
    ];

    /** Thresholds the rules use (kept here so the reasons can quote them). */
    public const TRACTION_CUSTOMERS = 10;

    public const RETURNING_CUSTOMERS = 3;

    public const HABIT_DAYS = 7;

    /**
     * @param  Collection<int, Shop>  $shops
     * @return array<int, array<string, mixed>> keyed by shop id
     */
    public function forShops(Collection $shops): array
    {
        $ids = $shops->pluck('id')->all();
        if ($ids === []) {
            return [];
        }

        $now = now();
        $stamps = $this->stampStats($ids, $now);
        $returning = $this->returningCustomers($ids);
        $cards = $this->cardStats($ids, $now);
        $staff = DB::table('staff_members')->whereIn('shop_id', $ids)->whereNull('deactivated_at')
            ->selectRaw('shop_id, COUNT(*) AS n')->groupBy('shop_id')->pluck('n', 'shop_id');
        $devices = DB::table('staff_devices')->whereIn('shop_id', $ids)->whereNull('revoked_at')
            ->selectRaw('shop_id, COUNT(*) AS n')->groupBy('shop_id')->pluck('n', 'shop_id');
        $menuItems = DB::table('menu_items')->join('menu_sections', 'menu_sections.id', '=', 'menu_items.menu_section_id')
            ->whereIn('menu_sections.shop_id', $ids)->selectRaw('menu_sections.shop_id, COUNT(*) AS n')
            ->groupBy('menu_sections.shop_id')->pluck('n', 'menu_sections.shop_id');
        $reviews = DB::table('reviews')->whereIn('shop_id', $ids)
            ->selectRaw('shop_id, COUNT(*) AS n')->groupBy('shop_id')->pluck('n', 'shop_id');
        $logins = DB::table('activity_logs')->where('action', 'auth.signed_in')
            ->whereIn('user_id', $shops->pluck('user_id')->filter()->all())
            ->selectRaw('user_id, MAX(created_at) AS at')->groupBy('user_id')->pluck('at', 'user_id');

        $result = [];
        foreach ($shops as $shop) {
            $s = $stamps[$shop->id] ?? null;
            $result[$shop->id] = $this->assess([
                'first_stamp_at' => $s?->first_at ? Carbon::parse($s->first_at) : null,
                'last_stamp_at' => $s?->last_at ? Carbon::parse($s->last_at) : null,
                'active_days_14' => (int) ($s->active_14 ?? 0),
                'active_days_prev_14' => (int) ($s->active_prev_14 ?? 0),
                'stamps_week' => (int) ($s->stamps_week ?? 0),
                'stamps_prev_week' => (int) ($s->stamps_prev_week ?? 0),
                'rewards_total' => (int) ($s->rewards_total ?? 0),
                'rewards_30d' => (int) ($s->rewards_30d ?? 0),
                'visitors' => (int) ($s->visitors ?? 0),
                'returning' => (int) ($returning[$shop->id] ?? 0),
                'customers' => (int) ($cards[$shop->id]->total ?? 0),
                'new_customers_30d' => (int) ($cards[$shop->id]->new_30d ?? 0),
                'staff' => (int) ($staff[$shop->id] ?? 0),
                'devices' => (int) ($devices[$shop->id] ?? 0),
                'display' => $shop->product_ordered_at !== null || (int) $shop->qr_downloads > 0,
                'owner_login_at' => isset($logins[$shop->user_id]) ? Carbon::parse($logins[$shop->user_id]) : null,
                'extras' => ($menuItems[$shop->id] ?? 0) > 0 || ($reviews[$shop->id] ?? 0) > 0,
            ], $now);
        }

        return $result;
    }

    public function forShop(Shop $shop): array
    {
        return $this->forShops(collect([$shop]))[$shop->id];
    }

    /**
     * The rules themselves, on one shop's figures (public so they can be
     * tested and reused without a database).
     *
     * @param  array<string, mixed>  $m
     * @return array{score: int, segment: string, segment_label: string, step: int, step_label: string, next_step: ?string, reasons: list<string>, metrics: array<string, mixed>}
     */
    public function assess(array $m, Carbon $now): array
    {
        $returnRate = $m['visitors'] > 0 ? $m['returning'] / $m['visitors'] : 0.0;

        // --- Score -------------------------------------------------------------
        $points = [
            'active_days' => min($m['active_days_14'], 10) / 10 * self::SCORE['active_days'],
            'trend' => match (true) {
                $m['stamps_week'] === 0 && $m['stamps_prev_week'] === 0 => 0,
                $m['stamps_week'] >= $m['stamps_prev_week'] * 0.8 => self::SCORE['trend'],
                $m['stamps_week'] >= $m['stamps_prev_week'] * 0.5 => self::SCORE['trend'] / 2,
                default => 0,
            },
            'new_customers' => min($m['new_customers_30d'], 20) / 20 * self::SCORE['new_customers'],
            // A rate needs a few customers to mean anything: full weight from 5.
            'return_rate' => min($returnRate / 0.3, 1) * min($m['visitors'] / 5, 1) * self::SCORE['return_rate'],
            'rewards' => $m['rewards_30d'] > 0 ? self::SCORE['rewards'] : ($m['rewards_total'] > 0 ? self::SCORE['rewards'] / 2 : 0),
            'staff' => $m['staff'] + $m['devices'] > 0 ? self::SCORE['staff'] : 0,
            'display' => $m['display'] ? self::SCORE['display'] : 0,
            'owner_login' => $m['owner_login_at']?->gte($now->copy()->subDays(14)) ? self::SCORE['owner_login'] : 0,
            'extras' => $m['extras'] ? self::SCORE['extras'] : 0,
        ];
        $score = (int) round(array_sum($points));

        // --- Ladder ------------------------------------------------------------
        $reached = [
            1 => true,
            2 => true,
            3 => $m['staff'] + $m['devices'] > 0 || $m['first_stamp_at'] !== null,
            4 => $m['first_stamp_at'] !== null,
            5 => $m['customers'] >= self::TRACTION_CUSTOMERS,
            6 => $m['returning'] >= self::RETURNING_CUSTOMERS,
            7 => $m['rewards_total'] > 0,
            8 => $m['active_days_14'] >= self::HABIT_DAYS,
        ];
        $step = 0;
        foreach ($reached as $n => $ok) {
            if (! $ok) {
                break;
            }
            $step = $n;
        }

        // --- Segment -----------------------------------------------------------
        $segment = match (true) {
            $m['first_stamp_at'] === null => 'never_started',
            // Was busy (5+ days in the 14 before), now at half that or less.
            $m['active_days_prev_14'] >= 5 && $m['active_days_14'] <= $m['active_days_prev_14'] / 2 => 'declining',
            $m['active_days_14'] === 0 => 'stalled',
            $m['customers'] < self::TRACTION_CUSTOMERS || $returnRate < 0.2 => 'trickling',
            default => 'healthy',
        };

        return [
            'score' => $score,
            'segment' => $segment,
            'segment_label' => self::SEGMENTS[$segment],
            'step' => $step,
            'step_label' => self::LADDER[$step],
            'next_step' => self::LADDER[$step + 1] ?? null,
            'reasons' => $this->reasons($m, $segment, $returnRate, $now),
            'metrics' => [
                'active_days_14' => $m['active_days_14'],
                'active_days_prev_14' => $m['active_days_prev_14'],
                'stamps_week' => $m['stamps_week'],
                'stamps_prev_week' => $m['stamps_prev_week'],
                'customers' => $m['customers'],
                'new_customers_30d' => $m['new_customers_30d'],
                'visitors' => $m['visitors'],
                'returning' => $m['returning'],
                'return_rate' => $m['visitors'] > 0 ? (int) round($returnRate * 100) : null,
                'rewards_total' => $m['rewards_total'],
                'rewards_30d' => $m['rewards_30d'],
                'staff' => $m['staff'],
                'devices' => $m['devices'],
                'display' => $m['display'],
                'first_stamp_at' => $m['first_stamp_at']?->toIso8601String(),
                'last_stamp_at' => $m['last_stamp_at']?->toIso8601String(),
                'owner_login_at' => $m['owner_login_at']?->toIso8601String(),
            ],
            'points' => array_map(fn ($p) => round($p, 1), $points),
        ];
    }

    /** @return list<string> the most useful few, most important first */
    private function reasons(array $m, string $segment, float $returnRate, Carbon $now): array
    {
        $ago = fn (?Carbon $at) => $at ? (int) $at->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()) : null;
        $reasons = [];

        match ($segment) {
            'never_started' => $reasons[] = $m['staff'] + $m['devices'] > 0
                ? 'Staff are set up, but no stamp has been given yet'
                : 'No stamps yet, and no staff or staff device set up',
            'stalled' => $reasons[] = 'No stamps for '.$ago($m['last_stamp_at']).' days',
            'declining' => $reasons[] = "Stamped on {$m['active_days_14']} of the last 14 days, down from {$m['active_days_prev_14']}",
            default => $reasons[] = "Stamped on {$m['active_days_14']} of the last 14 days",
        };

        if ($m['first_stamp_at'] !== null) {
            if ($m['visitors'] >= 5) {
                $reasons[] = "{$m['returning']} of {$m['visitors']} customers came back (".round($returnRate * 100).'%)';
            } elseif ($m['customers'] < self::TRACTION_CUSTOMERS) {
                $reasons[] = "Only {$m['customers']} ".($m['customers'] === 1 ? 'customer' : 'customers').' so far';
            }
            if ($m['rewards_total'] === 0 && $m['customers'] >= self::TRACTION_CUSTOMERS) {
                $reasons[] = 'Nobody has reached a reward yet';
            }
            if ($m['stamps_prev_week'] > 0 && $m['stamps_week'] < $m['stamps_prev_week'] * 0.5) {
                $reasons[] = "Stamps fell from {$m['stamps_prev_week']} to {$m['stamps_week']} this week";
            }
        }

        if (! $m['display']) {
            $reasons[] = 'No counter display ordered or QR downloaded';
        }
        if ($m['owner_login_at'] === null) {
            $reasons[] = 'Owner has never signed in';
        } elseif ($ago($m['owner_login_at']) > 14) {
            $reasons[] = 'Owner last signed in '.$ago($m['owner_login_at']).' days ago';
        }

        return array_slice($reasons, 0, 4);
    }

    /** One row per shop: first / last stamp, active days in two windows, weekly stamps, rewards, visitors. */
    private function stampStats(array $ids, Carbon $now): Collection
    {
        $d14 = $now->copy()->subDays(14);
        $d28 = $now->copy()->subDays(28);
        $d7 = $now->copy()->subDays(7);
        $d30 = $now->copy()->subDays(30);
        $stamp = ActionType::StampAdded->value;
        $reward = ActionType::RewardRedeemed->value;

        return DB::table('stamp_logs')
            ->whereIn('shop_id', $ids)
            ->selectRaw('shop_id, MIN(created_at) AS first_at, MAX(created_at) AS last_at, COUNT(DISTINCT customer_id) AS visitors')
            ->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? THEN DATE(created_at) END) AS active_14', [$d14])
            ->selectRaw('COUNT(DISTINCT CASE WHEN created_at >= ? AND created_at < ? THEN DATE(created_at) END) AS active_prev_14', [$d28, $d14])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND action_type = ? THEN 1 ELSE 0 END) AS stamps_week', [$d7, $stamp])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? AND action_type = ? THEN 1 ELSE 0 END) AS stamps_prev_week', [$d14, $d7, $stamp])
            ->selectRaw('SUM(CASE WHEN action_type = ? THEN 1 ELSE 0 END) AS rewards_total', [$reward])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND action_type = ? THEN 1 ELSE 0 END) AS rewards_30d', [$d30, $reward])
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');
    }

    /** Customers per shop who were scanned on 2+ different days. */
    private function returningCustomers(array $ids): Collection
    {
        $perCustomer = DB::table('stamp_logs')
            ->whereIn('shop_id', $ids)
            ->select('shop_id', 'customer_id')
            ->groupBy('shop_id', 'customer_id')
            ->havingRaw('COUNT(DISTINCT DATE(created_at)) >= 2');

        return DB::query()->fromSub($perCustomer, 'r')
            ->selectRaw('shop_id, COUNT(*) AS n')
            ->groupBy('shop_id')
            ->pluck('n', 'shop_id');
    }

    private function cardStats(array $ids, Carbon $now): Collection
    {
        return DB::table('customer_shop_cards')
            ->whereIn('shop_id', $ids)
            ->selectRaw('shop_id, COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS new_30d', [$now->copy()->subDays(30)])
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');
    }
}
