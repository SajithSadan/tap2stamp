<?php

namespace App\Models;

use App\Support\Currencies;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to pay for a year of a plan (App\Services\SubscriptionCheckout).
 * Pending until Stripe / Razorpay confirms it - server-side, never on the
 * browser's word - then paid, with the year it covers.
 */
class SubscriptionPayment extends Model
{
    public const STRIPE = 'stripe';

    public const RAZORPAY = 'razorpay';

    public const PENDING = 'pending';

    public const PAID = 'paid';

    /** Fully refunded at the provider: the year it paid for was taken back. */
    public const REFUNDED = 'refunded';

    protected $fillable = [
        'shop_id',
        'plan_id',
        'user_id',
        'plan_name',
        'provider',
        'currency',
        'amount',
        'amount_refunded',
        'status',
        'provider_order_id',
        'provider_payment_id',
        'period_starts_at',
        'period_ends_at',
        'paid_at',
        'refunded_at',
        'disputed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_refunded' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * One row of a shop's payment history (owner's Billing & plan): when, which
     * plan, how much, how, the year it covers and the provider's reference
     * (for support / the provider's own receipt).
     *
     * @return array<string, mixed>
     */
    public function toHistoryRow(): array
    {
        $day = fn ($at) => $at?->timezone('Europe/London')->format('j M Y');

        return [
            'id' => $this->id,
            'paid_label' => $day($this->paid_at),
            'plan' => $this->plan_name,
            'amount' => $this->amountLabel(),
            'method' => $this->provider === self::STRIPE ? 'Card (Stripe)' : 'Razorpay',
            'period_label' => $this->period_starts_at ? $day($this->period_starts_at).' - '.$day($this->period_ends_at) : null,
            'reference' => $this->provider_payment_id,
            // "Refunded ₹4,999" (all of it: the year was taken back) or a part of it; null = none.
            'refund_label' => $this->amount_refunded > 0
                ? ($this->status === self::REFUNDED ? 'Refunded' : 'Partly refunded').' '.$this->amountLabel($this->amount_refunded)
                : null,
            'disputed' => $this->disputed_at !== null,
        ];
    }

    /**
     * A Stripe payment's PaymentIntent (what a refund needs): kept as the
     * payment id once a Checkout Session is paid; payments from the old card
     * form have it as the order id.
     */
    public function stripeIntentId(): ?string
    {
        return str_starts_with((string) $this->provider_order_id, 'pi_') ? $this->provider_order_id : $this->provider_payment_id;
    }

    /** "₹4,999" / "£49.99" - the payment's amount, or $minor in its currency. */
    public function amountLabel(?int $minor = null): string
    {
        return Currencies::format($minor ?? $this->amount, $this->currency);
    }
}
