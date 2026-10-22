<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Currencies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A yearly subscription plan: one pricing card on the landing page and the
 * same plan on the owner's Billing & plan page, edited together on Admin →
 * Landing page. Prices are typed per currency by the admin (no conversion).
 * Exactly one plan is the default: every new shop starts on it, with its free
 * trial length (Shop::booted()). A plan that isn't offered (is_active false)
 * is hidden from both pages; shops already on it keep it.
 */
class Plan extends Model
{
    use RecordsActivity;

    /** A paid plan can be renewed from this many days before its year ends. */
    public const RENEW_DAYS = 30;

    protected $fillable = [
        'name',
        'description',
        'prices',
        'features',
        'note',
        'badge',
        'highlighted',
        'cta_label',
        'period',
        'billing_note',
        'trial_days',
        'is_default',
        'is_active',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'prices' => 'array',
            'features' => 'array',
            'highlighted' => 'boolean',
            'trial_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** The plan new shops start on (null only before any plan exists). */
    public static function defaultPlan(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /** How long a new shop's free trial is: the default plan's, else config. */
    public static function defaultTrialDays(): int
    {
        return static::where('is_default', true)->value('trial_days') ?? (int) config('loyalty.trial_days', 30);
    }

    /**
     * The plan as an owner sees it (Dashboard/Plan, and the admin's preview):
     * priced in $currency only - other currencies never reach the page.
     *
     * @return array<string, mixed>
     */
    public function forOwner(?string $currency, ?int $currentPlanId = null): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'period' => $this->period ?: '/ year',
            // null = no price in that currency ("Ask us for the price").
            'price' => $this->priceLabel($currency),
            'current' => $this->id === $currentPlanId,
        ];
    }

    /**
     * What the owner's button on this plan does for $shop (prices compared in
     * $currency):
     *  - never paid, trial running: upgrade (every plan);
     *  - never paid, trial ended: pay (every plan, "Choose a plan");
     *  - paid, this is their plan: renew from RENEW_DAYS before the paid year
     *    ends (or once it has), else locked (disabled - already paid up);
     *  - paid, another plan: upgrade (costs more) or switch (costs less or the
     *    same, or no price to compare).
     */
    public function buttonAction(Shop $shop, ?string $currency): string
    {
        if ($shop->subscription_ends_at === null) {
            return $shop->subscriptionStatus() === Shop::SUBSCRIPTION_EXPIRED ? 'pay' : 'upgrade';
        }

        if ($this->id === $shop->plan_id) {
            // By day (UK): ending on or before the 30th day from today.
            return $shop->subscription_ends_at->lte(now('Europe/London')->addDays(self::RENEW_DAYS)->endOfDay()) ? 'renew' : 'locked';
        }

        $amount = fn (?self $plan) => $plan && $currency && is_numeric($plan->prices[$currency] ?? null) ? (float) $plan->prices[$currency] : null;
        [$mine, $theirs] = [$amount($this), $amount($shop->plan)];

        return $mine !== null && $theirs !== null && $mine > $theirs ? 'upgrade' : 'switch';
    }

    /** The first day a paid plan can be renewed (RENEW_DAYS before it ends), or null when never paid. */
    public static function renewFrom(Shop $shop): ?Carbon
    {
        return $shop->subscription_ends_at?->copy()->subDays(self::RENEW_DAYS);
    }

    /**
     * The plan as the landing page editor edits it (LandingPage::content()).
     *
     * @return array<string, mixed>
     */
    public function toLanding(): array
    {
        return [
            ...$this->only([
                'id', 'name', 'description', 'note', 'badge', 'highlighted', 'cta_label', 'period', 'billing_note',
                'trial_days', 'is_default', 'is_active',
            ]),
            'prices' => $this->prices ?? [],
            'features' => $this->features ?? [],
        ];
    }

    /**
     * The yearly price in $currency for display ("₹2,999"), or null when the
     * plan has no price in it.
     */
    public function priceLabel(?string $currency): ?string
    {
        $amount = $currency ? ($this->prices[$currency] ?? null) : null;
        if ($amount === null || $amount === '') {
            return null;
        }

        $number = (float) $amount;
        $formatted = fmod($number, 1.0) === 0.0 ? number_format($number) : number_format($number, 2);

        return Currencies::symbol($currency).$formatted;
    }
}
