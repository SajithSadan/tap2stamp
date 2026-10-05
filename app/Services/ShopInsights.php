<?php

namespace App\Services;

use App\Enums\ActionType;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The owner's Insights page: who the regulars are, who's due back or
 * drifting away, whether the loyalty card is working, and when the shop is
 * busy. Everything is worked out from one shop's stamp_logs and cards.
 *
 * A "visit" is a day a customer was scanned (stamp or reward) - two scans on
 * the same day are one visit. Per-customer numbers are grouped in SQL (one
 * row per customer, never one per scan), so the page stays cheap however
 * long a shop has been running.
 */
class ShopInsights
{
    /** Each section's own period filter (days). */
    public const RANGES = [30, 90, 365];

    public const DEFAULT_RANGE = 90;

    /** How far back a customer's visit pattern is read. */
    private const PATTERN_DAYS = 365;

    /** A pattern needs this many visits before we predict anything from it. */
    public const MIN_VISITS_FOR_PATTERN = 3;

    /** Usually back within this many days = a regular (rarer visitors are never "due"). */
    public const MAX_REGULAR_GAP_DAYS = 45;

    /** Drifting = away more than twice their usual gap, and at least this long. */
    public const DRIFT_MIN_DAYS = 14;

    /** Away longer than this = gone; no longer listed as drifting. */
    public const LOST_AFTER_DAYS = 120;

    /** "Came back" for the second-visit rate = another visit within this many days. */
    public const RETURN_WINDOW_DAYS = 30;

    /** A part-filled card untouched this long has stalled. */
    public const STALLED_AFTER_DAYS = 60;

    private const TOP_LIMIT = 10;

    private const LIST_LIMIT = 25;

    public function __construct(private Shop $shop) {}

    /* ------------------------------------------------------------------
     | 1. Regulars and rewards
     * ---------------------------------------------------------------- */

    /** The most frequent visitors in the last $range days. */
    public function regulars(int $range): array
    {
        $start = $this->periodStart($range);

        $top = DB::query()->fromSub($this->visitDays($start), 'v')
            ->selectRaw('customer_id, COUNT(*) AS visits, MAX(day) AS last_day')
            ->groupBy('customer_id')
            ->orderByDesc('visits')
            ->orderByDesc('last_day')
            ->limit(self::TOP_LIMIT)
            ->get();

        $ids = $top->pluck('customer_id')->all();
        $rewards = $this->logs()->where('action_type', ActionType::RewardRedeemed)
            ->where('created_at', '>=', $start)
            ->whereIn('customer_id', $ids)
            ->selectRaw('customer_id, COUNT(*) AS total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');
        $patterns = $this->patterns($ids)->keyBy('customer_id');
        $cards = $this->cards($ids);

        return [
            'range' => $range,
            'active_customers' => DB::query()->fromSub($this->visitDays($start), 'v')->distinct()->count('customer_id'),
            'customers' => $top->filter(fn ($row) => isset($cards[$row->customer_id]))->map(fn ($row) => [
                ...$this->person($cards[$row->customer_id]),
                'visits' => (int) $row->visits,
                'rewards' => (int) ($rewards[$row->customer_id] ?? 0),
                'every_days' => $this->gap($patterns[$row->customer_id] ?? null),
            ])->values()->all(),
        ];
    }

    /** Cards with a reward waiting, and cards 1-2 stamps away from one. */
    public function closeToReward(): array
    {
        $max = $this->shop->max_stamps;

        $cards = $this->shop->cards()->with('customer:id,name,phone')
            ->where('current_stamps', '>=', max(1, $max - 2))
            ->orderByDesc('current_stamps')
            ->orderByDesc('last_stamped_at')
            ->limit(self::LIST_LIMIT * 2)
            ->get();

        [$ready, $almost] = $cards->partition(fn (CustomerShopCard $card) => $card->current_stamps >= $max);
        $row = fn (CustomerShopCard $card) => [...$this->person($card), 'to_go' => max(0, $max - $card->current_stamps)];

        return [
            'max_stamps' => $max,
            'ready' => $ready->take(self::LIST_LIMIT)->map($row)->values()->all(),
            'almost' => $almost->take(self::LIST_LIMIT)->map($row)->values()->all(),
        ];
    }

    /* ------------------------------------------------------------------
     | 2. Due back / drifting away
     * ---------------------------------------------------------------- */

    /**
     * From each regular's own rhythm (3+ visits, usually back within
     * MAX_REGULAR_GAP_DAYS): who's due in the next 7 days, and who has now
     * been away more than twice their usual gap.
     */
    public function dueBack(): array
    {
        $due = collect();
        $drifting = collect();

        foreach ($this->patterns() as $pattern) {
            $gap = $this->gap($pattern);

            if ($pattern->visits < self::MIN_VISITS_FOR_PATTERN || $gap > self::MAX_REGULAR_GAP_DAYS) {
                continue;
            }

            $last = Carbon::parse($pattern->last_day);
            $away = (int) $last->diffInDays(today());
            $driftAfter = max(2 * $gap, self::DRIFT_MIN_DAYS);

            if ($away >= $driftAfter) {
                if ($away <= self::LOST_AFTER_DAYS) {
                    $drifting->push(['customer_id' => $pattern->customer_id, 'visits' => (int) $pattern->visits, 'every_days' => $gap, 'away_days' => $away]);
                }

                continue;
            }

            $dueOn = $last->copy()->addDays((int) round($gap));
            if ($away > 0 && $dueOn->lte(today()->addDays(7))) {
                $due->push(['customer_id' => $pattern->customer_id, 'visits' => (int) $pattern->visits, 'every_days' => $gap, 'due_in_days' => (int) today()->diffInDays($dueOn, false)]);
            }
        }

        // Due soonest first; drifting: the most loyal first - they matter most.
        $due = $due->sortBy('due_in_days')->values();
        $drifting = $drifting->sortByDesc('visits')->values();
        $cards = $this->cards($due->take(self::LIST_LIMIT)->concat($drifting->take(self::LIST_LIMIT))->pluck('customer_id')->all());
        $rows = fn (Collection $list) => $list->take(self::LIST_LIMIT)
            ->filter(fn ($row) => isset($cards[$row['customer_id']]))
            ->map(fn ($row) => [...$this->person($cards[$row['customer_id']]), ...collect($row)->except('customer_id')->all()])
            ->values()->all();

        return [
            'due' => $rows($due),
            'due_count' => $due->count(),
            'drifting' => $rows($drifting),
            'drifting_count' => $drifting->count(),
        ];
    }

    /* ------------------------------------------------------------------
     | 3. Is the card working?
     * ---------------------------------------------------------------- */

    public function loyalty(int $range): array
    {
        return [
            'range' => $range,
            'second_visit' => $this->secondVisitRate($range),
            'visits' => $this->newVsReturning($range),
            'cards' => $this->cardHealth($range),
        ];
    }

    /**
     * Of the customers whose first visit was $range days long and ended
     * RETURN_WINDOW_DAYS ago (so every one of them has had the full window to
     * come back), how many visited again within it.
     */
    private function secondVisitRate(int $range): array
    {
        $to = today()->subDays(self::RETURN_WINDOW_DAYS);
        $from = $to->copy()->subDays($range - 1);

        $cohort = DB::query()->fromSub($this->firstDays(), 'f')
            ->whereBetween('f.first_day', [$from->toDateString(), $to->toDateString()])
            ->selectRaw(
                'COUNT(*) AS customers, COALESCE(SUM(EXISTS (
                    SELECT 1 FROM stamp_logs s WHERE s.shop_id = ? AND s.customer_id = f.customer_id
                    AND s.created_at >= f.first_day + INTERVAL 1 DAY
                    AND s.created_at < f.first_day + INTERVAL ? DAY
                )), 0) AS came_back',
                [$this->shop->id, self::RETURN_WINDOW_DAYS + 1],
            )
            ->first();

        $customers = (int) $cohort->customers;
        $cameBack = (int) $cohort->came_back;

        return [
            'from' => $from->format('j M'),
            'to' => $to->format('j M'),
            'window_days' => self::RETURN_WINDOW_DAYS,
            'customers' => $customers,
            'came_back' => $cameBack,
            'rate' => $customers ? (int) round($cameBack / $customers * 100) : null,
        ];
    }

    /**
     * Visits in the period split into first visits and returning ones -
     * per day (30), week (90) or month (365), zero-filled.
     */
    private function newVsReturning(int $range): array
    {
        $start = $this->periodStart($range);

        $days = DB::query()->fromSub($this->visitDays($start), 'v')
            ->joinSub($this->firstDays(), 'f', 'f.customer_id', '=', 'v.customer_id')
            ->selectRaw('v.day, SUM(v.day = f.first_day) AS first_visits, SUM(v.day > f.first_day) AS returning_visits')
            ->groupBy('v.day')
            ->get();

        [$bucket, $step, $label] = match (true) {
            $range <= 31 => [fn (Carbon $d) => $d->copy(), 'addDay', fn (Carbon $d) => $d->format('j M')],
            $range <= 120 => [fn (Carbon $d) => $d->copy()->startOfWeek(), 'addWeek', fn (Carbon $d) => $d->format('j M')],
            default => [fn (Carbon $d) => $d->copy()->startOfMonth(), 'addMonth', fn (Carbon $d) => $d->format('M Y')],
        };

        $rows = [];
        for ($d = $bucket($start); $d->lte(today()); $d = $d->copy()->{$step}()) {
            $rows[$d->toDateString()] = ['date' => $d->toDateString(), 'label' => $label($d), 'first' => 0, 'returning' => 0];
        }
        foreach ($days as $day) {
            $key = $bucket(Carbon::parse($day->day))->toDateString();
            if (isset($rows[$key])) {
                $rows[$key]['first'] += (int) $day->first_visits;
                $rows[$key]['returning'] += (int) $day->returning_visits;
            }
        }

        $first = array_sum(array_column($rows, 'first'));
        $returning = array_sum(array_column($rows, 'returning'));

        return [
            'per' => match ($step) {
                'addDay' => 'day', 'addWeek' => 'week', default => 'month'
            },
            'rows' => array_values($rows),
            'first' => $first,
            'returning' => $returning,
            'returning_share' => $first + $returning ? (int) round($returning / ($first + $returning) * 100) : null,
        ];
    }

    /**
     * Rewards claimed in the period, how long a card takes to fill (from the
     * customer's previous reward, or first visit, to the reward), how many
     * customers have ever earned one, and where part-filled cards stall.
     */
    private function cardHealth(int $range): array
    {
        $start = $this->periodStart($range);
        $max = $this->shop->max_stamps;

        $rewards = $this->logs()->where('action_type', ActionType::RewardRedeemed)
            ->orderBy('customer_id')->orderBy('created_at')
            ->get(['customer_id', 'created_at']);
        $firstDays = DB::query()->fromSub($this->firstDays(), 'f')
            ->whereIn('customer_id', $rewards->pluck('customer_id')->unique()->all())
            ->pluck('first_day', 'customer_id');

        $daysToFill = [];
        $previous = [];
        foreach ($rewards as $reward) {
            $at = Carbon::parse($reward->created_at);
            $since = $previous[$reward->customer_id] ?? Carbon::parse($firstDays[$reward->customer_id] ?? $at);
            if ($at->gte($start)) {
                $daysToFill[] = (int) $since->copy()->startOfDay()->diffInDays($at->copy()->startOfDay());
            }
            $previous[$reward->customer_id] = $at;
        }
        sort($daysToFill);

        $cards = $this->shop->cards()->whereNotNull('last_stamped_at');
        $customers = (clone $cards)->count();
        $earned = (clone $cards)->where(fn ($q) => $q->where('rewards_claimed', '>', 0)->orWhere('current_stamps', '>=', $max))->count();

        $stalled = (clone $cards)
            ->whereBetween('current_stamps', [1, max(1, $max - 1)])
            ->where('last_stamped_at', '<', now()->subDays(self::STALLED_AFTER_DAYS))
            ->selectRaw('current_stamps, COUNT(*) AS total')
            ->groupBy('current_stamps')
            ->pluck('total', 'current_stamps');
        $stalledTotal = (int) $stalled->sum();

        // Most stalled cards stop early, and few customers ever finish one:
        // the card may feel too long. Only said once there's enough to go on.
        $earlyStalls = $stalled->filter(fn ($n, $stamps) => $stamps <= $max / 2)->sum();
        $earnedShare = $customers ? (int) round($earned / $customers * 100) : null;

        return [
            'max_stamps' => $max,
            'rewards' => count($daysToFill),
            'median_days_to_fill' => $daysToFill ? $daysToFill[intdiv(count($daysToFill), 2)] : null,
            'customers' => $customers,
            'earned' => $earned,
            'earned_share' => $earnedShare,
            'stalled_days' => self::STALLED_AFTER_DAYS,
            'stalled_total' => $stalledTotal,
            'stalled' => collect(range(1, max(1, $max - 1)))
                ->map(fn (int $stamps) => ['stamps' => $stamps, 'cards' => (int) ($stalled[$stamps] ?? 0)])
                ->all(),
            'suggest_fewer' => $customers >= 20 && $earnedShare < 25 && $stalledTotal >= 10 && $earlyStalls / $stalledTotal >= 0.5,
        ];
    }

    /* ------------------------------------------------------------------
     | 4. When are you busy?
     * ---------------------------------------------------------------- */

    /** Scans per weekday (Monday first) x hour, in UK time (the app's timezone). */
    public function busyTimes(int $range): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));

        // MySQL/MariaDB: DAYOFWEEK is 1 = Sunday ... 7 = Saturday.
        $this->logs()->where('created_at', '>=', $this->periodStart($range))
            ->selectRaw('DAYOFWEEK(created_at) AS dow, HOUR(created_at) AS hour, COUNT(*) AS total')
            ->groupBy('dow', 'hour')
            ->get()
            ->each(function ($cell) use (&$grid) {
                $grid[((int) $cell->dow + 5) % 7][(int) $cell->hour] = (int) $cell->total;
            });

        return ['range' => $range, 'grid' => $grid];
    }

    /* ------------------------------------------------------------------
     | Building blocks
     * ---------------------------------------------------------------- */

    private function periodStart(int $range): Carbon
    {
        return today()->subDays($range - 1);
    }

    private function logs(): Builder
    {
        return DB::table('stamp_logs')->where('shop_id', $this->shop->id);
    }

    /** One row per customer per day they were scanned. */
    private function visitDays(?Carbon $from = null): Builder
    {
        return $this->logs()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->selectRaw('customer_id, DATE(created_at) AS day')
            ->groupBy('customer_id', 'day');
    }

    /** Each customer's first-ever visit to this shop. */
    private function firstDays(): Builder
    {
        return $this->logs()
            ->selectRaw('customer_id, MIN(DATE(created_at)) AS first_day')
            ->groupBy('customer_id');
    }

    /**
     * Each customer's visits over the last PATTERN_DAYS: how many, first and
     * last. Optionally only for some customers.
     */
    private function patterns(?array $customerIds = null): Collection
    {
        return DB::query()->fromSub($this->visitDays(today()->subDays(self::PATTERN_DAYS - 1)), 'v')
            ->when($customerIds !== null, fn ($q) => $q->whereIn('customer_id', $customerIds))
            ->selectRaw('customer_id, COUNT(*) AS visits, MIN(day) AS first_day, MAX(day) AS last_day')
            ->groupBy('customer_id')
            ->get();
    }

    /** Average days between visits, or null with fewer than two visits. */
    private function gap(?object $pattern): ?float
    {
        if (! $pattern || $pattern->visits < 2) {
            return null;
        }

        $span = Carbon::parse($pattern->first_day)->diffInDays(Carbon::parse($pattern->last_day));

        return round($span / ($pattern->visits - 1), 1);
    }

    /** @return Collection<int, CustomerShopCard> keyed by customer_id */
    private function cards(array $customerIds): Collection
    {
        return $this->shop->cards()->with('customer:id,name,phone')
            ->whereIn('customer_id', $customerIds)
            ->get()
            ->keyBy('customer_id');
    }

    /** What every customer row on the page shows. */
    private function person(CustomerShopCard $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->customer->name,
            'phone' => $card->customer->phone,
            // Opted in to messages from this shop (for WhatsApp later).
            'marketing_consent' => (bool) $card->marketing_consent,
            'stamps' => $card->current_stamps,
            'last_visit' => $card->last_stamped_at?->timezone('Europe/London')->format('j M Y'),
        ];
    }
}
