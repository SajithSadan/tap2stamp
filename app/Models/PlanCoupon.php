<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code owners can use when paying for a plan (Billing & plan) -
 * separate from the counter display's Coupon. Its discount is set per
 * country (`discounts` = {GB: "10", IN: "500"}): a percentage, or an amount
 * in that country's currency. Countries not listed can't use it. Only paid
 * plan payments count as a use. Never deleted, switched off instead.
 */
class PlanCoupon extends Model
{
    use RecordsActivity;

    public const PERCENT = 'percent';

    public const FIXED = 'fixed';

    /** Left to pay after a coupon: nothing (free), or at least this many major units - providers refuse tiny charges. */
    public const MIN_CHARGE = 1;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discounts',
        'plan_id',
        'max_uses',
        'once_per_shop',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discounts' => 'array',
            'max_uses' => 'integer',
            'once_per_shop' => 'boolean',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function activityLabel(): string
    {
        return $this->code;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function paidPayments(): HasMany
    {
        return $this->payments()->where('status', SubscriptionPayment::PAID);
    }

    public static function normalise(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', static::normalise($code))->first();
    }

    /** The discount as typed for $country, or null when that country can't use it. */
    public function valueFor(?string $country): ?string
    {
        $value = $this->discounts[strtoupper((string) $country)] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** "10% off" / "₹500 off" for $country (its currency). */
    public function labelFor(?string $country, string $currency): string
    {
        $value = (string) $this->valueFor($country);

        return $this->discount_type === self::PERCENT
            ? "{$value}% off"
            : Currencies::format(Currencies::toMinor($value, $currency), $currency).' off';
    }

    /** Smallest units off $amount for a shop in $country paying in $currency - never more than $amount. */
    public function discountFor(int $amount, ?string $country, string $currency): int
    {
        $value = $this->valueFor($country);
        if ($value === null) {
            return 0;
        }

        $off = $this->discount_type === self::PERCENT
            ? (int) round($amount * (float) $value / 100)
            : Currencies::toMinor($value, $currency);

        return max(0, min($amount, $off));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsedUp(): bool
    {
        return $this->max_uses !== null && $this->paidPayments()->count() >= $this->max_uses;
    }

    /**
     * Why $shop can't use this coupon on $plan, or null when it can.
     * Messages are shown to the owner as they are.
     */
    public function problemFor(Shop $shop, Plan $plan): ?string
    {
        return match (true) {
            ! $this->is_active, $this->isExpired() => 'This coupon code has expired.',
            $this->valueFor($shop->country) === null => "This coupon code can't be used in your country.",
            $this->plan_id !== null && $this->plan_id !== $plan->id => "This coupon code can't be used on the {$plan->name} plan.",
            $this->isUsedUp() => 'This coupon code has been fully used.',
            $this->once_per_shop && $this->paidPayments()->where('shop_id', $shop->id)->exists() => "You've already used this coupon code.",
            default => null,
        };
    }
}
