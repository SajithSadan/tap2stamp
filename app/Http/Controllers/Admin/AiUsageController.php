<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who uses the AI (Gemini) and how much: totals, per feature, per shop, per
 * person and the latest requests, for a period. Tokens are as Gemini reported
 * them (AiUsage) - the basis of the bill.
 */
class AiUsageController extends Controller
{
    public const PERIODS = [7, 30, 90, 0]; // days; 0 = all time

    /**
     * Each model's use against our plan's limits (config services.gemini.limits):
     * right now (last 60 s, today since midnight Pacific - when Google resets
     * RPD) and the peak in the period (capped at 90 days). Counts this app's
     * requests only - one per AI action (a retry on a busy model isn't
     * counted twice), so it's close to, not exactly, Google's own figures.
     *
     * @return array<string, mixed>
     */
    public static function limits(int $days): array
    {
        $plan = config('services.gemini.limits');
        $appTz = config('app.timezone');
        $minuteAgo = now()->subMinute();
        $todayStart = now($plan['reset_timezone'])->startOfDay()->setTimezone($appTz);
        $since = now()->subDays($days > 0 ? min($days, 90) : 90)->startOfDay();

        $models = collect([config('services.gemini.model'), config('services.gemini.fallback_model')])
            ->merge(AiUsageLog::where('created_at', '>=', $since)->whereNotNull('model')->distinct()->pluck('model'))
            ->filter()
            ->unique()
            ->values();

        return [
            'tier' => $plan['tier'],
            'rpm' => $plan['rpm'],
            'tpm' => $plan['tpm'],
            'rpd' => $plan['rpd'],
            'resets' => $todayStart->copy()->addDay()->timezone($appTz)->format('H:i'),
            'peakDays' => $days > 0 ? min($days, 90) : 90,
            'models' => $models->map(function (string $model) use ($minuteAgo, $todayStart, $since, $appTz, $plan) {
                $rows = fn () => AiUsageLog::where('model', $model);
                $lastMinute = $rows()->where('created_at', '>=', $minuteAgo)->selectRaw('COUNT(*) as requests, COALESCE(SUM(input_tokens), 0) as tokens')->first();
                $minute = "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')";

                // Peak per calendar day on Google's clock: counted per hour in SQL, days summed here.
                $peakDay = $rows()->where('created_at', '>=', $since)
                    ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as requests")
                    ->groupBy('hour')
                    ->pluck('requests', 'hour')
                    ->groupBy(fn ($n, string $hour) => Carbon::parse($hour, $appTz)->timezone($plan['reset_timezone'])->toDateString(), true)
                    ->map->sum()
                    ->max() ?? 0;

                $now = [
                    'rpm' => (int) $lastMinute->requests,
                    'tpm' => (int) $lastMinute->tokens,
                    'rpd' => $rows()->where('created_at', '>=', $todayStart)->count(),
                ];

                // Peaks are per clock minute / Pacific day; "now" is the last 60 s, which can
                // straddle two clock minutes - a peak is never shown lower than right now.
                return [
                    'model' => $model,
                    ...$now,
                    'peak_rpm' => max($now['rpm'], (int) ($rows()->where('created_at', '>=', $since)->selectRaw("{$minute} as m, COUNT(*) as n")->groupBy('m')->orderByDesc('n')->value('n') ?? 0)),
                    'peak_tpm' => max($now['tpm'], (int) ($rows()->where('created_at', '>=', $since)->selectRaw("{$minute} as m, SUM(input_tokens) as n")->groupBy('m')->orderByDesc('n')->value('n') ?? 0)),
                    'peak_rpd' => max($now['rpd'], (int) $peakDay),
                ];
            })->all(),
        ];
    }

    public function index(Request $request): Response
    {
        $days = in_array((int) $request->query('days', 30), self::PERIODS, true) ? (int) $request->query('days', 30) : 30;
        $logs = fn () => AiUsageLog::query()->when($days > 0, fn ($q) => $q->where('ai_usage_logs.created_at', '>=', now()->subDays($days)->startOfDay()));
        $sums = 'COUNT(*) as requests, SUM(total_tokens) as tokens, SUM(status = \'ok\') as ok, MAX(ai_usage_logs.created_at) as last_at';
        $int = fn ($row) => collect($row)->map(fn ($value, $key) => in_array($key, ['requests', 'tokens', 'ok'], true) ? (int) $value : $value)->all();

        $totals = $logs()->selectRaw($sums.', COUNT(DISTINCT shop_id) as shops')->first();

        return Inertia::render('Admin/AiUsage', [
            // Lazy-refreshable on its own (the page reloads it every 30 s).
            'limits' => fn () => self::limits($days),
            'days' => $days,
            'periods' => self::PERIODS,
            'features' => AiUsageLog::FEATURES,
            'totals' => [
                'requests' => (int) $totals->requests,
                'tokens' => (int) $totals->tokens,
                'failed' => (int) $totals->requests - (int) $totals->ok,
                'shops' => (int) $totals->shops,
            ],
            'byFeature' => $logs()->selectRaw('feature, '.$sums)->groupBy('feature')->orderByDesc('requests')->get()->map($int),
            'byShop' => $logs()
                ->leftJoin('shops', 'shops.id', '=', 'ai_usage_logs.shop_id')
                ->selectRaw('ai_usage_logs.shop_id, shops.name as shop_name, '.$sums
                    .', SUM(feature = \'menu_import\') as menu_import, SUM(feature = \'photo_check\') as photo_check, SUM(feature = \'stock_command\') as stock_command')
                ->groupBy('ai_usage_logs.shop_id', 'shops.name')
                ->orderByDesc('tokens')
                ->limit(100)
                ->get()
                ->map(fn ($row) => [...$int($row), 'menu_import' => (int) $row->menu_import, 'photo_check' => (int) $row->photo_check, 'stock_command' => (int) $row->stock_command]),
            // One row per person: a staff member, an owner or an admin (names as they were when used).
            'byPerson' => $logs()
                ->leftJoin('shops', 'shops.id', '=', 'ai_usage_logs.shop_id')
                ->selectRaw('ai_usage_logs.actor_type, ai_usage_logs.user_id, ai_usage_logs.staff_member_id, MAX(ai_usage_logs.actor_name) as actor_name, MAX(shops.name) as shop_name, '.$sums)
                ->groupBy('ai_usage_logs.actor_type', 'ai_usage_logs.user_id', 'ai_usage_logs.staff_member_id')
                ->orderByDesc('requests')
                ->limit(100)
                ->get()
                ->map($int),
            'recent' => $logs()
                ->leftJoin('shops', 'shops.id', '=', 'ai_usage_logs.shop_id')
                ->select('ai_usage_logs.*', 'shops.name as shop_name')
                ->latest('ai_usage_logs.created_at')
                ->limit(50)
                ->get()
                ->map(fn (AiUsageLog $log) => [
                    'id' => $log->id,
                    'feature' => $log->feature,
                    'shop_name' => $log->shop_name,
                    'actor_type' => $log->actor_type,
                    'actor_name' => $log->actor_name,
                    'model' => $log->model,
                    'status' => $log->status,
                    'tokens' => $log->total_tokens,
                    'duration_ms' => $log->duration_ms,
                    'at' => $log->created_at->timezone('Europe/London')->format('j M, H:i'),
                ]),
        ]);
    }
}
