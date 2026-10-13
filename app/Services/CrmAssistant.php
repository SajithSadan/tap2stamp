<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopNote;
use App\Models\User;
use App\Support\Countries;
use App\Support\CrmStage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The admin CRM's AI (MONETISATION-PLAN.md phase 2), "rules decide, AI
 * explains": fixed rules pick which shops need the admin (candidates()), and
 * Gemini only writes the why + one suggested action (weeklyBriefing()) or a
 * call-prep sheet for one shop (callPrep()). Gemini gets per-shop figures
 * (ShopHealth) and the admin's own notes - never customers' names or phones -
 * and it changes nothing.
 */
class CrmAssistant
{
    public const MAX_BRIEFING_SHOPS = 20;

    /** Why the rules picked a shop, in priority order => label. */
    public const FLAGS = [
        'declining' => 'Declining',
        'trial_ending' => 'Trial ending',
        'ready_to_convert' => 'Ready to convert',
        'stalled' => 'Stalled',
        'never_started' => 'Never started',
    ];

    private const PLAYBOOK = <<<'TXT'
Playbook (what usually works):
- Never started: offer a 10-minute setup call; the admin can set up staff and the card for them ("View as owner").
- Stalled: the QR is probably not visible or staff forget to scan - push the counter display / downloadable QR, refresh staff on their PIN.
- Trickling (stamping, few come back): fix the card - fewer stamps or a better reward; show them their Insights page.
- Ready to convert (healthy, still on trial): make the upgrade offer, quoting their own numbers; offer an early-bird price before the trial ends.
- Declining: a personal call within 48 hours to find out what changed.
- Trial ending: extend it if they are active but not convinced yet, or make the offer if they are healthy.
TXT;

    public function __construct(
        private Gemini $gemini,
        private ShopHealth $health,
        private ShopTimeline $timeline,
    ) {}

    public function enabled(): bool
    {
        return $this->gemini->configured();
    }

    /**
     * The shops that need the admin, by rule, most urgent first: not paid,
     * lost or blocked.
     *
     * @return Collection<int, array{shop: Shop, health: array, flag: string}>
     */
    public function candidates(): Collection
    {
        $shops = Shop::with('owner:id,name,email,welcome_sent_at')
            ->whereNull('blocked_at')
            ->whereNotIn('crm_stage', [CrmStage::PAID, CrmStage::LOST])
            ->get();
        $health = $this->health->forShops($shops);
        $today = now()->startOfDay();
        $order = array_flip(array_keys(self::FLAGS));

        return $shops
            ->map(function (Shop $shop) use ($health, $today) {
                $h = $health[$shop->id];
                $trialDays = $shop->trial_ends_at ? (int) $today->diffInDays($shop->trial_ends_at->copy()->startOfDay(), false) : null;
                $flag = match (true) {
                    $h['segment'] === 'declining' => 'declining',
                    $trialDays !== null && $trialDays >= -3 && $trialDays <= 7 => 'trial_ending',
                    $h['segment'] === 'healthy' => 'ready_to_convert',
                    $h['segment'] === 'stalled' => 'stalled',
                    $h['segment'] === 'never_started' && $shop->created_at->lte(now()->subDays(3)) => 'never_started',
                    default => null,
                };

                return $flag ? ['shop' => $shop, 'health' => $h, 'flag' => $flag] : null;
            })
            ->filter()
            ->sortBy([fn ($a, $b) => $order[$a['flag']] <=> $order[$b['flag']], fn ($a, $b) => $b['health']['score'] <=> $a['health']['score']])
            ->values();
    }

    /**
     * Generates and stores this week's briefing (Setting::CRM_BRIEFING).
     *
     * @return array<string, mixed>|string the briefing, or 'empty' / 'busy' / 'unavailable'
     */
    public function weeklyBriefing(User $admin): array|string
    {
        $candidates = $this->candidates()->take(self::MAX_BRIEFING_SHOPS);
        if ($candidates->isEmpty()) {
            return 'empty';
        }

        $facts = $candidates->map(fn ($c) => $this->facts($c['shop'], $c['health'], $c['flag']))->all();

        $answer = $this->gemini->json(
            'crm_briefing',
            <<<'TXT'
You are the customer-success assistant for Tada Tap, a digital loyalty card app for small local businesses (cafes, restaurants, salons, barbers, shops). The admin wants to turn free-trial shops into paying customers by helping them get real value first.
You get shops that fixed rules flagged (field "flag"), with their real figures and the admin's notes. For EACH shop return:
- why: one short sentence on why it needs attention this week, quoting its own numbers.
- action: one concrete thing the admin should do this week (call, WhatsApp, setup help, change the card, upgrade offer...).
- priority: high / medium / low.
Also a "summary" of 1-2 sentences for the whole list.
Rules: never invent numbers or facts that are not in the data; plain, friendly English; no emojis; keep each field under 200 characters.

TXT.self::PLAYBOOK,
            'Shops (JSON): '.json_encode($facts, JSON_UNESCAPED_UNICODE),
            [
                'type' => 'OBJECT',
                'properties' => [
                    'summary' => ['type' => 'STRING'],
                    'items' => ['type' => 'ARRAY', 'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'shop_id' => ['type' => 'INTEGER'],
                            'priority' => ['type' => 'STRING', 'enum' => ['high', 'medium', 'low']],
                            'why' => ['type' => 'STRING'],
                            'action' => ['type' => 'STRING'],
                        ],
                        'required' => ['shop_id', 'priority', 'why', 'action'],
                    ]],
                ],
                'required' => ['summary', 'items'],
            ],
        );

        if (! is_array($answer)) {
            return $answer;
        }

        // Only the shops we sent, once each, in our priority order.
        $byId = $candidates->keyBy(fn ($c) => $c['shop']->id);
        $items = collect($answer['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && $byId->has((int) ($item['shop_id'] ?? 0)))
            ->unique(fn ($item) => (int) $item['shop_id'])
            ->map(function ($item) use ($byId) {
                $c = $byId[(int) $item['shop_id']];

                return [
                    'shop_id' => $c['shop']->id,
                    'shop_name' => $c['shop']->name,
                    'flag' => $c['flag'],
                    'segment' => $c['health']['segment'],
                    'score' => $c['health']['score'],
                    'priority' => in_array($item['priority'] ?? null, ['high', 'medium', 'low'], true) ? $item['priority'] : 'medium',
                    'why' => Str::limit(trim((string) ($item['why'] ?? '')), 300),
                    'action' => Str::limit(trim((string) ($item['action'] ?? '')), 300),
                ];
            })
            ->sortBy(fn ($item) => array_search($item['shop_id'], $byId->keys()->all(), true))
            ->values()
            ->all();

        $briefing = [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $admin->name,
            'summary' => Str::limit(trim((string) ($answer['summary'] ?? '')), 500),
            'items' => $items,
        ];
        Setting::set(Setting::CRM_BRIEFING, $briefing);

        return $briefing;
    }

    /** The last briefing, with when it was made ("3 days ago"). */
    public static function lastBriefing(): ?array
    {
        $briefing = Setting::get(Setting::CRM_BRIEFING);

        return $briefing ? [
            ...$briefing,
            'generated_label' => Carbon::parse($briefing['generated_at'])->timezone('Europe/London')->format('j M Y, H:i'),
            'generated_ago' => Carbon::parse($briefing['generated_at'])->diffForHumans(),
        ] : null;
    }

    /**
     * "Brief me" before calling a shop: a short summary, talking points,
     * likely objections and the next step. Not stored.
     *
     * @return array<string, mixed>|string
     */
    public function callPrep(Shop $shop): array|string
    {
        $health = $this->health->forShop($shop);
        $facts = $this->facts($shop, $health, null);
        $facts['recent_history'] = collect($this->timeline->for($shop, 15))
            ->map(fn ($e) => substr($e['at'], 0, 10).' '.$e['title'].($e['kind'] === 'note' ? ': '.Str::limit((string) $e['detail'], 200) : ''))
            ->all();

        $answer = $this->gemini->json(
            'call_prep',
            <<<'TXT'
You prepare the Tada Tap admin for a phone call with one shop owner. Tada Tap is a digital loyalty card app for small local businesses; the shop is on (or just finished) a free trial, and the goal is to help it get value and then convert it to a paid plan.
From the data, return:
- summary: at most 5 short lines - who they are, how they are really doing (quote the numbers), and where they are stuck.
- talking_points: up to 4 short points to raise on the call.
- likely_objections: up to 3 objections they may raise, each followed by a one-line answer (format "Objection - answer").
- next_step: the one outcome to aim for on this call.
Never invent numbers or facts; plain friendly English; no emojis.

TXT.self::PLAYBOOK,
            'Shop (JSON): '.json_encode($facts, JSON_UNESCAPED_UNICODE),
            [
                'type' => 'OBJECT',
                'properties' => [
                    'summary' => ['type' => 'STRING'],
                    'talking_points' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'likely_objections' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                    'next_step' => ['type' => 'STRING'],
                ],
                'required' => ['summary', 'talking_points', 'likely_objections', 'next_step'],
            ],
            $shop->id,
        );

        if (! is_array($answer)) {
            return $answer;
        }

        $list = fn ($value, int $max) => collect(is_array($value) ? $value : [])
            ->map(fn ($line) => Str::limit(trim((string) $line), 300))->filter()->take($max)->values()->all();

        return [
            'summary' => Str::limit(trim((string) ($answer['summary'] ?? '')), 800),
            'talking_points' => $list($answer['talking_points'] ?? [], 4),
            'likely_objections' => $list($answer['likely_objections'] ?? [], 3),
            'next_step' => Str::limit(trim((string) ($answer['next_step'] ?? '')), 300),
        ];
    }

    /**
     * What the AI gets about one shop: its own figures, card, status and the
     * admin's latest notes. No customer names or phone numbers.
     */
    public function facts(Shop $shop, array $health, ?string $flag): array
    {
        $m = $health['metrics'];
        $today = now()->startOfDay();
        $trialDays = $shop->trial_ends_at ? (int) $today->diffInDays($shop->trial_ends_at->copy()->startOfDay(), false) : null;
        $lastLogin = $m['owner_login_at'] ? Carbon::parse($m['owner_login_at']) : null;

        return [
            'shop_id' => $shop->id,
            'name' => $shop->name,
            'business_type' => $shop->industryLabel() ?? 'not set',
            'country' => Countries::name($shop->country),
            'flag' => $flag ? self::FLAGS[$flag] : null,
            'days_since_signup' => (int) $shop->created_at->copy()->startOfDay()->diffInDays($today),
            'stage' => CrmStage::DISPLAY[CrmStage::display($shop->crm_stage, $m['first_stamp_at'] !== null)],
            'trial' => match (true) {
                $trialDays === null => 'no trial date',
                $trialDays < 0 => 'ended '.(-$trialDays).' days ago',
                default => "{$trialDays} days left",
            },
            'card' => "{$shop->max_stamps} stamps for: {$shop->reward_title}",
            'health' => collect($health)->only(['score', 'segment_label', 'step_label', 'next_step', 'reasons'])->all(),
            'figures' => [
                'customers' => $m['customers'],
                'new_customers_30_days' => $m['new_customers_30d'],
                'customers_who_came_back' => $m['returning'],
                'return_rate_percent' => $m['return_rate'],
                'stamping_days_last_14' => $m['active_days_14'],
                'stamps_this_week' => $m['stamps_week'],
                'stamps_last_week' => $m['stamps_prev_week'],
                'rewards_given' => $m['rewards_total'],
                'staff_accounts' => $m['staff'],
            ],
            'counter_display_or_qr_in_place' => $m['display'],
            'welcomed_on_whatsapp' => $shop->owner?->welcome_sent_at !== null,
            'owner_last_signed_in' => $lastLogin ? $lastLogin->diffForHumans() : 'never',
            'admin_notes' => $shop->notes()->latest()->limit(3)->get()
                ->map(fn (ShopNote $note) => $note->created_at->format('j M').': '.Str::limit($note->body, 300))->all(),
            'open_follow_ups' => $shop->tasks()->open()->orderBy('due_on')->limit(5)->pluck('title')->all(),
        ];
    }
}
