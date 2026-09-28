<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\QrCode;
use App\Models\Review;
use App\Models\Shop;
use App\Models\StampLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide overview for the admin: growth, popularity and health across
 * every shop (the owner dashboard is the per-shop view).
 *
 * Every chart has its OWN 7 / 30 / 90-day filter: each section is a lazy
 * prop reading its own query param (?visits=7&compare=90...), so changing
 * one card does an Inertia partial reload of just that prop - the server
 * only recomputes that section. Daily series are grouped in SQL, so each
 * section is a fixed, small number of queries however big the platform is.
 */
class DashboardController extends Controller
{
    public const RANGES = [7, 30, 90];

    private const DEFAULT_RANGE = 30;

    private const QUIET_DAYS = 14;

    /** A shop needs this many reviews before it can appear as "lowest rated". */
    private const MIN_REVIEWS_TO_RANK = 3;

    /** periodMetrics() results for this request, keyed by period - sections often share one. */
    private array $metricsCache = [];

    public function index(Request $request): Response
    {
        $range = fn (string $section) => in_array($request->integer($section), self::RANGES, true) ? $request->integer($section) : self::DEFAULT_RANGE;

        return Inertia::render('Admin/Dashboard', [
            'ranges' => self::RANGES,
            'kpis' => fn () => $this->kpis($range('kpis')),
            'customerGrowth' => fn () => $this->growth($range('customerGrowth'), Customer::query()),
            'shopGrowth' => fn () => $this->growth($range('shopGrowth'), Shop::query()),
            'activeCustomers' => fn () => $this->activeCustomers($range('activeCustomers')),
            'stampsRewards' => fn () => $this->stampsRewards($range('stampsRewards')),
            'visits' => fn () => $this->visits($range('visits')),
            'comparison' => fn () => $this->comparison($range('comparison')),
            'shops' => fn () => $this->shopComparison($range('shops')),
            'funnel' => fn () => $this->funnel($range('funnel')),
            'ratings' => fn () => $this->ratings(),
            'health' => fn () => $this->health(),
            'qr' => fn () => [
                'total' => $total = QrCode::count(),
                'mapped' => $total ? QrCode::whereNotNull('destination_url')->count() : 0,
            ],
            'recentOwners' => fn () => $this->recentOwners(),
            'quietShops' => fn () => $this->quietShops(),
            'quietDays' => self::QUIET_DAYS,
        ]);
    }

    /* ------------------------------------------------------------------
     | Periods
     * ---------------------------------------------------------------- */

    /**
     * The current period (last $range days including today) and the one
     * just before it: [start, end, previousStart].
     *
     * @return array{0: Carbon, 1: Carbon, 2: Carbon}
     */
    private function window(int $range): array
    {
        $start = today()->subDays($range - 1);

        return [$start, today()->addDay(), $start->copy()->subDays($range)];
    }

    /** @return Collection<int, Carbon> */
    private function days(int $range): Collection
    {
        [$start] = $this->window($range);

        return collect(range(0, $range - 1))->map(fn (int $i) => $start->copy()->addDays($i));
    }

    private function dayRow(Carbon $day, array $values): array
    {
        return ['date' => $day->format('Y-m-d'), 'label' => $day->format('j M'), 'weekday' => $day->format('D'), ...$values];
    }

    /** "Y-m-d" => value, for rows created per day since $start. */
    private function perDay(Builder $query, Carbon $start, string $aggregate = 'COUNT(*)'): Collection
    {
        return $query->where('created_at', '>=', $start)
            ->selectRaw("DATE(created_at) as day, {$aggregate} as total")
            ->groupBy('day')
            ->toBase()
            ->pluck('total', 'day');
    }

    /** Each customer's first-ever stamp or reward - what makes a visit a "first visit". */
    private function firstVisits(): QueryBuilder
    {
        return DB::query()->fromSub(
            StampLog::selectRaw('customer_id, MIN(created_at) as first_at')->groupBy('customer_id'),
            'first_visits',
        );
    }

    /** Every comparable metric for one period [$from, $to). */
    private function periodMetrics(Carbon $from, Carbon $to): array
    {
        return $this->metricsCache[$from->toDateString().'|'.$to->toDateString()] ??= (function () use ($from, $to) {
            $in = fn (Builder $query, string $column = 'created_at') => $query->where($column, '>=', $from)->where($column, '<', $to);
            $logs = fn () => $in(StampLog::query());

            $active = $logs()->distinct()->count('customer_id');
            $firstTime = $this->firstVisits()->where('first_at', '>=', $from)->where('first_at', '<', $to)->count();
            $avgRating = $in(Review::query())->avg('rating');

            return [
                'new_customers' => $in(Customer::query())->count(),
                'active_customers' => $active,
                'first_time_customers' => $firstTime,
                'returning_customers' => $active - $firstTime,
                'active_shops' => $logs()->distinct()->count('shop_id'),
                'new_shops' => $in(Shop::query())->count(),
                'new_owners' => $in(User::where('role', UserRole::Owner))->count(),
                'stamps' => $logs()->where('action_type', ActionType::StampAdded)->count(),
                'rewards' => $logs()->where('action_type', ActionType::RewardRedeemed)->count(),
                'reviews' => $in(Review::query())->count(),
                'avg_rating' => $avgRating === null ? null : round((float) $avgRating, 1),
                'qr_mapped' => $in(QrCode::query(), 'mapped_at')->count(),
            ];
        })();
    }

    /* ------------------------------------------------------------------
     | Sections (one per card / filter)
     * ---------------------------------------------------------------- */

    private function kpis(int $range): array
    {
        [$start, $end, $prevStart] = $this->window($range);
        $perShop = $this->shopRatingAverages();

        return [
            'range' => $range,
            'current' => $this->periodMetrics($start, $end),
            'previous' => $this->periodMetrics($prevStart, $start),
            'totals' => ['customers' => Customer::count(), 'shops' => Shop::count()],
            'rating' => [
                'shop_average' => $perShop->isEmpty() ? null : round((float) $perShop->avg('avg_rating'), 1),
                'rated_shops' => $perShop->count(),
            ],
        ];
    }

    /** Running total and new-per-day for customers or shops. */
    private function growth(int $range, Builder $query): array
    {
        [$start] = $this->window($range);
        $new = $this->perDay($query->clone(), $start);
        $running = $query->clone()->where('created_at', '<', $start)->count();

        $days = $this->days($range)->map(function (Carbon $day) use ($new, &$running) {
            $running += $added = (int) ($new[$day->format('Y-m-d')] ?? 0);

            return $this->dayRow($day, ['total' => $running, 'new' => $added]);
        });

        return ['range' => $range, 'days' => $days->all(), 'total' => $running, 'new' => (int) $new->sum()];
    }

    /** Active customers per day, split into first visits and returning customers. */
    private function activeCustomers(int $range): array
    {
        [$start, $end, $prevStart] = $this->window($range);
        $active = $this->perDay(StampLog::query(), $start, 'COUNT(DISTINCT customer_id)');
        $firstTime = $this->firstVisits()
            ->where('first_at', '>=', $start)
            ->selectRaw('DATE(first_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = $this->days($range)->map(function (Carbon $day) use ($active, $firstTime) {
            $key = $day->format('Y-m-d');
            $all = (int) ($active[$key] ?? 0);
            $first = min($all, (int) ($firstTime[$key] ?? 0));

            return $this->dayRow($day, ['active' => $all, 'first_time' => $first, 'returning' => $all - $first]);
        });

        $current = $this->periodMetrics($start, $end);

        return [
            'range' => $range,
            'days' => $days->all(),
            'active' => $current['active_customers'],
            'first_time' => $current['first_time_customers'],
            'returning' => $current['returning_customers'],
            'previous_active' => $this->periodMetrics($prevStart, $start)['active_customers'],
        ];
    }

    private function stampsRewards(int $range): array
    {
        [$start] = $this->window($range);
        $stamps = $this->perDay(StampLog::where('action_type', ActionType::StampAdded), $start);
        $rewards = $this->perDay(StampLog::where('action_type', ActionType::RewardRedeemed), $start);

        $days = $this->days($range)->map(fn (Carbon $day) => $this->dayRow($day, [
            'stamps' => (int) ($stamps[$day->format('Y-m-d')] ?? 0),
            'rewards' => (int) ($rewards[$day->format('Y-m-d')] ?? 0),
        ]));

        return ['range' => $range, 'days' => $days->all(), 'stamps' => (int) $stamps->sum(), 'rewards' => (int) $rewards->sum()];
    }

    /**
     * Visits by weekday x hour: stamps and redeemed rewards as separate
     * grids of 7 rows (Mon..Sun) x 24 hours.
     */
    private function visits(int $range): array
    {
        [$start] = $this->window($range);

        // MySQL/MariaDB: DAYOFWEEK is 1 = Sunday ... 7 = Saturday.
        $cells = StampLog::where('created_at', '>=', $start)
            ->selectRaw('action_type, DAYOFWEEK(created_at) as dow, HOUR(created_at) as hour, COUNT(*) as total')
            ->groupBy('action_type', 'dow', 'hour')
            ->toBase()
            ->get();

        $grids = [
            'stamps' => array_fill(0, 7, array_fill(0, 24, 0)),
            'rewards' => array_fill(0, 7, array_fill(0, 24, 0)),
        ];

        foreach ($cells as $cell) {
            $grid = $cell->action_type === ActionType::RewardRedeemed->value ? 'rewards' : 'stamps';
            $grids[$grid][((int) $cell->dow + 5) % 7][(int) $cell->hour] = (int) $cell->total; // Monday first
        }

        return ['range' => $range, ...$grids];
    }

    private function comparison(int $range): array
    {
        [$start, $end, $prevStart] = $this->window($range);

        return ['range' => $range, 'current' => $this->periodMetrics($start, $end), 'previous' => $this->periodMetrics($prevStart, $start)];
    }

    /** The busiest shops side by side on the numbers an admin compares. */
    private function shopComparison(int $range): array
    {
        [$start, , $prevStart] = $this->window($range);
        $inPeriod = fn ($q) => $q->where('created_at', '>=', $start);

        $rows = Shop::with('owner:id,name')
            ->withCount([
                'cards as customers_count',
                'cards as new_customers' => $inPeriod,
                'stampLogs as stamps_period' => fn ($q) => $inPeriod($q->where('action_type', ActionType::StampAdded)),
                'stampLogs as rewards_period' => fn ($q) => $inPeriod($q->where('action_type', ActionType::RewardRedeemed)),
                'stampLogs as stamps_previous' => fn ($q) => $q->where('action_type', ActionType::StampAdded)
                    ->where('created_at', '>=', $prevStart)->where('created_at', '<', $start),
                'reviews as reviews_count',
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withMax('stampLogs as last_activity_at', 'created_at')
            ->orderByDesc('stamps_period')
            ->orderByDesc('customers_count')
            ->limit(10)
            ->get()
            ->filter(fn (Shop $shop) => $shop->stamps_period > 0)
            ->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'owner_name' => $shop->owner?->name,
                'customers' => $shop->customers_count,
                'new_customers' => $shop->new_customers,
                'stamps' => $shop->stamps_period,
                'previous' => $shop->stamps_previous,
                'rewards' => $shop->rewards_period,
                'rating' => $shop->rating === null ? null : round((float) $shop->rating, 1),
                'reviews' => $shop->reviews_count,
                'last_activity' => $shop->last_activity_at ? Carbon::parse($shop->last_activity_at)->diffForHumans() : null,
            ])
            ->values()
            ->all();

        return ['range' => $range, 'rows' => $rows];
    }

    /**
     * Owner activation as a cohort: of the owners who signed up in the last
     * $range days, how many set up a shop, gave a first stamp, and are still
     * stamping now. Every step is the same group of people, so the filter
     * changes every bar and each drop-off is a real one.
     */
    private function funnel(int $range): array
    {
        [$start] = $this->window($range);
        $owners = fn () => User::where('role', UserRole::Owner)->where('created_at', '>=', $start);
        $recent = now()->subDays(self::QUIET_DAYS);

        // Days from sign-up to the shop's first stamp, for owners who got there.
        $daysToFirstStamp = $owners()
            ->whereHas('shop.stampLogs')
            ->with(['shop' => fn ($q) => $q->withMin('stampLogs as first_stamp_at', 'created_at')])
            ->get()
            ->map(fn (User $owner) => $owner->created_at->diffInDays(Carbon::parse($owner->shop->first_stamp_at)))
            ->sort()
            ->values();

        return [
            'range' => $range,
            'steps' => [
                ['label' => 'Signed up', 'value' => $owners()->count()],
                ['label' => 'Set up their shop', 'value' => $owners()->whereHas('shop')->count()],
                ['label' => 'Gave a first stamp', 'value' => $owners()->whereHas('shop.stampLogs')->count()],
                ['label' => 'Still stamping (last '.self::QUIET_DAYS.' days)', 'value' => $owners()->whereHas('shop.stampLogs', fn ($q) => $q->where('created_at', '>=', $recent))->count()],
            ],
            'median_days_to_first_stamp' => $daysToFirstStamp->isEmpty() ? null : (int) round($daysToFirstStamp->median()),
            'all_time' => [
                'owners' => User::where('role', UserRole::Owner)->count(),
                'shops' => Shop::whereNotNull('user_id')->count(),
                'stamping' => Shop::whereHas('stampLogs')->count(),
            ],
        ];
    }

    /* ------------------------------------------------------------------
     | All-time panels (no filter)
     * ---------------------------------------------------------------- */

    /** Each rated shop's own average rating and review count. */
    private function shopRatingAverages(): Collection
    {
        return Review::selectRaw('shop_id, AVG(rating) as avg_rating, COUNT(*) as reviews')->groupBy('shop_id')->toBase()->get();
    }

    /**
     * Ratings belong to shops, so the headline is the average SHOP rating
     * (each shop's own average, averaged) - one busy shop with hundreds of
     * reviews can't drown out the rest. Plus the star split and the shops
     * that most need attention.
     */
    private function ratings(): array
    {
        $perShop = $this->shopRatingAverages();
        $stars = Review::selectRaw('rating, COUNT(*) as total')->groupBy('rating')->toBase()->pluck('total', 'rating');

        $lowest = $perShop->where('reviews', '>=', self::MIN_REVIEWS_TO_RANK)->sortBy('avg_rating')->take(5);
        $names = Shop::whereIn('id', $lowest->pluck('shop_id'))->pluck('name', 'id');

        return [
            'shop_average' => $perShop->isEmpty() ? null : round((float) $perShop->avg('avg_rating'), 1),
            'rated_shops' => $perShop->count(),
            'reviews' => (int) $perShop->sum('reviews'),
            'distribution' => collect([5, 4, 3, 2, 1])->map(fn (int $star) => ['stars' => $star, 'count' => (int) ($stars[$star] ?? 0)])->all(),
            'lowest' => $lowest->map(fn ($row) => [
                'id' => (int) $row->shop_id,
                'name' => $names[$row->shop_id] ?? 'Unknown shop',
                'average' => round((float) $row->avg_rating, 1),
                'reviews' => (int) $row->reviews,
            ])->values()->all(),
            'min_reviews' => self::MIN_REVIEWS_TO_RANK,
        ];
    }

    /** Platform health ratios, as whole percentages (null when there's nothing to divide by). */
    private function health(): array
    {
        $pct = fn (int $part, int $whole) => $whole > 0 ? (int) round($part / $whole * 100) : null;

        $stampedCustomers = StampLog::where('action_type', ActionType::StampAdded)->distinct()->count('customer_id');
        $repeatCustomers = DB::query()->fromSub(
            StampLog::where('action_type', ActionType::StampAdded)->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) >= 2'),
            'repeaters',
        )->count();
        $multiShop = DB::query()->fromSub(
            CustomerShopCard::select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) >= 2'),
            'multi',
        )->count();

        $cards = CustomerShopCard::count();
        $owners = User::where('role', UserRole::Owner)->count();

        return [
            'repeat_rate' => $pct($repeatCustomers, $stampedCustomers),
            'multi_shop_rate' => $pct($multiShop, Customer::count()),
            'reward_reach_rate' => $pct(CustomerShopCard::where('rewards_claimed', '>', 0)->count(), $cards),
            'marketing_opt_in_rate' => $pct(CustomerShopCard::where('marketing_consent', true)->count(), $cards),
            'google_owner_rate' => $pct(User::where('role', UserRole::Owner)->whereNotNull('google_id')->count(), $owners),
        ];
    }

    private function recentOwners(): array
    {
        return User::where('role', UserRole::Owner)
            ->with('shop:id,user_id,name,slug')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (User $owner) => [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'shop_name' => $owner->shop?->name,
                'via_google' => $owner->google_id !== null,
                'joined' => $owner->created_at->diffForHumans(),
            ])
            ->all();
    }

    /** Shops old enough to judge, with no stamps for QUIET_DAYS - worth a check-in. */
    private function quietShops(): array
    {
        return Shop::with('owner:id,name')
            ->withMax('stampLogs as last_activity_at', 'created_at')
            ->where('created_at', '<=', now()->subDays(self::QUIET_DAYS))
            ->whereDoesntHave('stampLogs', fn ($q) => $q->where('created_at', '>=', now()->subDays(self::QUIET_DAYS)))
            ->orderBy('last_activity_at')
            ->limit(5)
            ->get()
            ->map(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'slug' => $shop->slug,
                'owner_name' => $shop->owner?->name,
                'last_activity' => $shop->last_activity_at ? Carbon::parse($shop->last_activity_at)->diffForHumans() : null,
            ])
            ->all();
    }
}
