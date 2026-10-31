<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Countries;
use App\Support\CrmStage;
use App\Support\Currencies;
use App\Support\PaymentProviders;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * An owner paying for a year of a plan, in the shop's own currency:
 * Stripe's hosted Checkout page for the Stripe countries (PaymentProviders),
 * Razorpay's payment window everywhere else.
 *
 * start() creates the provider's Checkout Session / order and a pending
 * SubscriptionPayment; the browser goes to Stripe's page / opens Razorpay's
 * window with it. Paid is only ever decided here, from the provider itself:
 * the Checkout Session fetched from Stripe, Razorpay's signed answer checked
 * against the payment fetched from Razorpay (captured), or either provider's
 * signed webhook - whichever comes first. markPaid() is idempotent. The
 * webhooks also bring refunds (a full one takes the paid year back) and
 * chargebacks (flagged for the admin).
 *
 * Stripe rows: provider_order_id = the Checkout Session (cs_), and once paid
 * provider_payment_id = its PaymentIntent (pi_), which refunds and disputes
 * name. Rows from the old card form have the PaymentIntent as the order id.
 */
class SubscriptionCheckout
{
    /** What a webhook / check did - written to the log. */
    public const MARKED_PAID = 'marked paid';

    public const ALREADY_PAID = 'already paid';

    public const NOT_PAID = 'not paid (provider says otherwise)';

    public const NOT_OURS = 'no matching plan payment';

    public const REFUNDED = 'refunded - paid year taken back';

    public const PARTLY_REFUNDED = 'partly refunded';

    public const DISPUTED = 'chargeback flagged';

    public const NO_CHANGE = 'no change';

    public function __construct(
        private StripeGateway $stripe,
        private RazorpayGateway $razorpay,
    ) {}

    /** stripe | razorpay for this shop, by its country. */
    public function providerFor(Shop $shop): string
    {
        return PaymentProviders::forCountry($shop->country);
    }

    /** Is that provider's payment window set up (keys in .env)? */
    public function available(Shop $shop): bool
    {
        return $this->providerFor($shop) === SubscriptionPayment::STRIPE
            ? $this->stripe->configured()
            : $this->razorpay->configured();
    }

    /** The shop's currency (its country's), or GBP when the country has none listed. */
    public function currencyFor(Shop $shop): string
    {
        return Currencies::forCountry($shop->country) ?? 'GBP';
    }

    /** Can $plan be paid for online by this shop (window set up + a price in its currency)? */
    public function payable(Shop $shop, Plan $plan): bool
    {
        return $plan->is_active
            && $this->available($shop)
            && filled($plan->prices[$this->currencyFor($shop)] ?? null)
            && Currencies::toMinor((string) $plan->prices[$this->currencyFor($shop)], $this->currencyFor($shop)) > 0;
    }

    /**
     * A pending payment + what the browser needs to open the payment window.
     *
     * @return array<string, mixed>
     */
    public function start(Shop $shop, User $owner, Plan $plan): array
    {
        $currency = $this->currencyFor($shop);
        $amount = Currencies::toMinor((string) $plan->prices[$currency], $currency);
        $provider = $this->providerFor($shop);
        $description = "Tada Tap {$plan->name} plan - 1 year - {$shop->name}";
        // The year this payment would cover, so the payment page can say so.
        $period = $shop->paidYearStart()->timezone('Europe/London')->format('j M Y').' - '.$shop->paidYearEnd()->format('j M Y');

        if ($provider === SubscriptionPayment::STRIPE) {
            $metadata = ['type' => 'subscription', 'shop_id' => $shop->id, 'plan_id' => $plan->id];
            $session = $this->stripe->createCheckoutSession([
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'unit_amount' => $amount,
                        'product_data' => ['name' => "Tada Tap {$plan->name} plan - 1 year", 'description' => $period],
                    ],
                ]],
                'customer_email' => $owner->email,
                'metadata' => $metadata,
                'payment_intent_data' => ['description' => $description, 'metadata' => $metadata],
                // Back on Billing & plan, which asks the server to check the session.
                'success_url' => route('dashboard.plan').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('dashboard.plan'),
            ]);
            $orderId = $session['id'];
        } else {
            $order = $this->razorpay->createOrder($amount, $currency, 'plan-'.$shop->id.'-'.now()->timestamp, [
                'shop_id' => (string) $shop->id,
                'plan_id' => (string) $plan->id,
            ]);
            $orderId = $order['id'];
        }

        $payment = $shop->subscriptionPayments()->create([
            'plan_id' => $plan->id,
            'user_id' => $owner->id,
            'plan_name' => $plan->name,
            'provider' => $provider,
            'currency' => $currency,
            'amount' => $amount,
            'status' => SubscriptionPayment::PENDING,
            'provider_order_id' => $orderId,
        ]);

        $common = [
            'provider' => $provider,
            'payment_id' => $payment->id,
            'plan' => $plan->name,
            'amount_label' => $payment->amountLabel(),
            'period_label' => $period,
        ];

        return $provider === SubscriptionPayment::STRIPE
            ? [...$common, 'url' => $session['url']]
            : [...$common,
                'key_id' => $this->razorpay->keyId(),
                'order_id' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'prefill' => [
                    'name' => $shop->contact_name ?: $owner->name,
                    'email' => $shop->contact_email ?: $owner->email,
                    'contact' => $shop->contactPhoneTel() ?? '',
                ],
                'country' => $shop->country ?? Countries::UK,
            ];
    }

    /**
     * The browser says the window finished: check with the provider itself.
     * Razorpay: the signed {order, payment id}, then the payment fetched from
     * Razorpay's API (captured, same order / amount / currency); Stripe: the
     * Checkout Session fetched from Stripe. True when the payment is paid.
     */
    public function confirm(SubscriptionPayment $payment, array $razorpay = []): bool
    {
        if ($payment->status === SubscriptionPayment::PAID) {
            return true;
        }

        if ($payment->provider === SubscriptionPayment::RAZORPAY) {
            $paymentId = (string) ($razorpay['razorpay_payment_id'] ?? '');
            $signed = $paymentId !== ''
                && ($razorpay['razorpay_order_id'] ?? null) === $payment->provider_order_id
                && $this->razorpay->validPaymentSignature($payment->provider_order_id, $paymentId, (string) ($razorpay['razorpay_signature'] ?? ''));

            return $signed && $this->settleRazorpay($payment, $this->razorpay->fetchPayment($paymentId)) === self::MARKED_PAID;
        }

        return $this->stripeSessionPaid($payment, $this->stripe->retrieveCheckoutSession($payment->provider_order_id)) === self::MARKED_PAID;
    }

    /**
     * Stripe webhook checkout.session.completed / async_payment_succeeded
     * (only our plan payments are found; NOT_OURS = maybe a counter display
     * order). What it did, for the log.
     */
    public function confirmStripeSession(array $session): string
    {
        $payment = $this->findPayment(SubscriptionPayment::STRIPE, $session['id'] ?? null);

        return $payment ? $this->stripeSessionPaid($payment, [
            'payment_status' => $session['payment_status'] ?? null,
            'payment_intent' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
            'amount_total' => (int) ($session['amount_total'] ?? 0),
            'currency' => strtoupper((string) ($session['currency'] ?? '')),
        ]) : self::NOT_OURS;
    }

    /**
     * Razorpay webhook payment.authorized / payment.captured / order.paid
     * (already signature-checked): the payment entity from the event.
     */
    public function confirmRazorpayPayment(array $entity): string
    {
        $payment = $this->findPayment(SubscriptionPayment::RAZORPAY, $entity['order_id'] ?? null);

        return $payment ? $this->settleRazorpay($payment, $entity) : self::NOT_OURS;
    }

    /**
     * A refund at the provider ($refunded = the payment's total refunded so
     * far, smallest unit). All of it: the payment becomes refunded and the
     * year it paid for is taken back off the shop. Part of it: only noted.
     * Either way the admin gets a follow-up on the shop. Idempotent.
     */
    public function recordRefund(string $provider, ?string $orderId, int $refunded): string
    {
        $payment = $this->findPayment($provider, $orderId);
        if (! $payment) {
            return self::NOT_OURS;
        }

        $outcome = DB::transaction(function () use ($payment, $refunded) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->first();
            $refunded = min($refunded, $payment->amount);
            if ($refunded <= $payment->amount_refunded) {
                return self::NO_CHANGE;
            }

            $payment->amount_refunded = $refunded;
            if ($refunded < $payment->amount || $payment->status !== SubscriptionPayment::PAID) {
                $payment->save();

                return self::PARTLY_REFUNDED;
            }

            $payment->forceFill(['status' => SubscriptionPayment::REFUNDED, 'refunded_at' => now()])->save();
            $this->takeBackYear($payment);

            return self::REFUNDED;
        });

        if ($outcome === self::REFUNDED || $outcome === self::PARTLY_REFUNDED) {
            $payment->refresh();
            $this->followUp($payment, $outcome === self::REFUNDED
                ? "Refunded {$payment->amountLabel()} ({$payment->plan_name}) - its paid year was taken off the shop"
                : "Part refund of {$payment->amountLabel($payment->amount_refunded)} on {$payment->amountLabel()} ({$payment->plan_name}) - plan unchanged");
        }

        return $outcome;
    }

    /**
     * A chargeback was opened. The plan stays (the dispute may be won); the
     * payment is flagged and the admin gets a follow-up. Idempotent.
     */
    public function recordDispute(string $provider, ?string $orderId): string
    {
        $payment = $this->findPayment($provider, $orderId);
        if (! $payment) {
            return self::NOT_OURS;
        }
        if ($payment->disputed_at) {
            return self::NO_CHANGE;
        }

        $payment->forceFill(['disputed_at' => now()])->save();
        $this->followUp($payment, "Chargeback opened on {$payment->amountLabel()} ({$payment->plan_name}) - answer it in ".ucfirst($payment->provider));

        return self::DISPUTED;
    }

    /**
     * Paid: the shop moves to the plan, and the year starts when its free
     * trial / current paid year ends (Shop::paidYearStart()), else today.
     * Locked and idempotent - the browser check and the webhook can both
     * arrive. True when the payment is (now or already) paid.
     */
    public function markPaid(SubscriptionPayment $payment, ?string $providerPaymentId = null): bool
    {
        return in_array($this->recordPaid($payment, $providerPaymentId), [self::MARKED_PAID, self::ALREADY_PAID], true);
    }

    /** By the provider's order id - for Stripe also by the PaymentIntent (refunds and disputes name that). */
    private function findPayment(string $provider, ?string $orderId): ?SubscriptionPayment
    {
        if (blank($orderId)) {
            return null;
        }

        return SubscriptionPayment::where('provider', $provider)
            ->where(fn ($query) => $query->where('provider_order_id', $orderId)
                ->when($provider === SubscriptionPayment::STRIPE, fn ($query) => $query->orWhere('provider_payment_id', $orderId)))
            ->first();
    }

    /**
     * Razorpay's payment must be for this order, amount and currency, and
     * captured (= the money is taken). An authorised one is captured first:
     * accounts without automatic capture would otherwise see Razorpay refund
     * it after a few days while the shop kept its plan.
     */
    private function settleRazorpay(SubscriptionPayment $payment, array $entity): string
    {
        $matches = ($entity['order_id'] ?? null) === $payment->provider_order_id
            && (int) ($entity['amount'] ?? 0) === $payment->amount
            && strtoupper((string) ($entity['currency'] ?? '')) === $payment->currency;
        if (! $matches) {
            return self::NOT_PAID;
        }

        if (($entity['status'] ?? null) === 'authorized') {
            try {
                $entity = $this->razorpay->capturePayment((string) $entity['id'], $payment->amount, $payment->currency);
            } catch (RuntimeException) {
                // Already captured meanwhile (automatic capture) - ask what it is now.
                $entity = $this->razorpay->fetchPayment((string) $entity['id']);
            }
        }

        return ($entity['status'] ?? null) === 'captured'
            ? $this->recordPaid($payment, (string) $entity['id'])
            : self::NOT_PAID;
    }

    /** Paid, for this amount and currency - then its PaymentIntent is kept for refunds. */
    private function stripeSessionPaid(SubscriptionPayment $payment, array $session): string
    {
        $paid = ($session['payment_status'] ?? null) === 'paid'
            && filled($session['payment_intent'] ?? null)
            && (int) ($session['amount_total'] ?? 0) === $payment->amount
            && strtoupper((string) ($session['currency'] ?? '')) === $payment->currency;

        return $paid ? $this->recordPaid($payment, $session['payment_intent']) : self::NOT_PAID;
    }

    private function recordPaid(SubscriptionPayment $payment, ?string $providerPaymentId): string
    {
        $outcome = DB::transaction(function () use ($payment, $providerPaymentId) {
            $payment = SubscriptionPayment::whereKey($payment->id)->lockForUpdate()->first();
            // Only a pending payment becomes paid: never twice, and never a refunded one again.
            if ($payment->status !== SubscriptionPayment::PENDING) {
                return $payment->status === SubscriptionPayment::PAID ? self::ALREADY_PAID : self::NO_CHANGE;
            }

            $shop = Shop::whereKey($payment->shop_id)->lockForUpdate()->first();
            $starts = $shop->paidYearStart();
            $ends = $shop->paidYearEnd();

            $payment->update([
                'status' => SubscriptionPayment::PAID,
                'provider_payment_id' => $providerPaymentId,
                'period_starts_at' => $starts,
                'period_ends_at' => $ends,
                'paid_at' => now(),
            ]);

            $shop->forceFill([
                'plan_id' => $payment->plan_id ?? $shop->plan_id,
                'subscription_ends_at' => $ends,
                'crm_stage' => CrmStage::PAID,
                'lost_reason' => null,
                // Paused for not paying: paying starts scanning again.
                'scanning_paused_at' => null,
            ])->save();

            return self::MARKED_PAID;
        });

        if ($outcome === self::MARKED_PAID) {
            $done = $payment->fresh();
            ActivityLogger::record(
                'subscription.paid',
                "Paid {$done->amountLabel()} for a year of {$done->plan_name} (".ucfirst($done->provider).', to '.$done->period_ends_at->timezone('Europe/London')->format('j M Y').')',
                $done->shop_id,
                $done,
                null,
                // The owner who paid - also when it's the webhook that confirms it.
                ($payer = User::find($done->user_id)) ? ActivityLogger::user($payer) : null,
            );
        }

        return $outcome;
    }

    /**
     * A fully refunded year comes off the shop (inside the refund's
     * transaction): its paid end moves back by that year's length - or is
     * cleared when no other payment is left - and, when it was the latest
     * year, the plan goes back to the one paid before (else the default).
     */
    private function takeBackYear(SubscriptionPayment $payment): void
    {
        $shop = Shop::whereKey($payment->shop_id)->lockForUpdate()->first();
        $others = SubscriptionPayment::where('shop_id', $shop->id)->where('status', SubscriptionPayment::PAID)
            ->whereKeyNot($payment->id)->orderByDesc('period_ends_at')->get();

        if ($others->isEmpty()) {
            $shop->forceFill([
                'subscription_ends_at' => null,
                'plan_id' => $payment->plan_id === $shop->plan_id ? (Plan::defaultPlan()?->id ?? $shop->plan_id) : $shop->plan_id,
                'crm_stage' => CrmStage::TRIAL,
            ])->save();

            return;
        }

        $length = $payment->period_starts_at && $payment->period_ends_at
            ? (int) $payment->period_starts_at->diffInSeconds($payment->period_ends_at)
            : 0;
        $wasLatest = $payment->period_ends_at && $shop->subscription_ends_at
            && abs($payment->period_ends_at->diffInSeconds($shop->subscription_ends_at)) < 60;

        $shop->forceFill([
            'subscription_ends_at' => $shop->subscription_ends_at?->copy()->subSeconds($length),
            'plan_id' => $wasLatest ? ($others->first()->plan_id ?? $shop->plan_id) : $shop->plan_id,
        ])->save();
    }

    /** A follow-up on the shop for the admin (Admin → Follow-ups / the shop's Account tab), due today. */
    private function followUp(SubscriptionPayment $payment, string $title): void
    {
        Shop::find($payment->shop_id)?->tasks()->create(['title' => mb_substr($title, 0, 200), 'due_on' => now('Europe/London')->toDateString()]);
    }
}
