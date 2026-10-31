<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Shop;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\Currencies;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

/**
 * Every payment a shop has made (Admin → shop settings → Payments): its plan
 * payments (Razorpay / Stripe) and its paid counter display orders (Stripe
 * Checkout, bank transfer, cash, free), newest first - and refunds of the
 * ones paid online, through the provider's own API. A refund the provider
 * confirms straight away is recorded at once; one still pending shows once
 * its webhook arrives (the same recordRefund(), so never counted twice).
 */
class ShopPayments
{
    public const PLAN = 'plan';

    public const ORDER = 'order';

    public function __construct(
        private SubscriptionCheckout $checkout,
        private OrderService $orders,
        private RazorpayGateway $razorpay,
        private StripeGateway $stripe,
    ) {}

    /** @return list<array<string, mixed>> newest first */
    public function rows(Shop $shop): array
    {
        $plans = $shop->subscriptionPayments()->whereIn('status', [SubscriptionPayment::PAID, SubscriptionPayment::REFUNDED])->get()
            ->map(fn (SubscriptionPayment $payment) => [
                ...$payment->toHistoryRow(),
                'key' => self::PLAN.'-'.$payment->id,
                'kind' => self::PLAN,
                'what' => "{$payment->plan_name} plan",
                'paid_at' => $payment->paid_at,
                ...$this->refundable($payment->status === SubscriptionPayment::PAID, $payment->amount, $payment->amount_refunded, $payment->currency),
            ]);

        $orders = $shop->orders()->where('status', OrderStatus::Paid)->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'key' => self::ORDER.'-'.$order->id,
                'kind' => self::ORDER,
                'what' => "{$order->quantity} × {$order->product_name}",
                'paid_at' => $order->paid_at,
                'paid_label' => $order->paid_at?->timezone('Europe/London')->format('j M Y'),
                'plan' => null,
                'amount' => Currencies::format($order->total_pence, 'GBP'),
                'method' => $order->payment_method->label(),
                'period_label' => null,
                'reference' => $order->stripe_payment_intent ?? PaymentMethod::reference($order->id),
                'refund_label' => $order->amount_refunded_pence > 0
                    ? ($order->amount_refunded_pence >= $order->total_pence ? 'Refunded' : 'Partly refunded').' '.Currencies::format($order->amount_refunded_pence, 'GBP')
                    : null,
                'disputed' => false,
                ...$this->refundable($order->payment_method === PaymentMethod::Stripe && filled($order->stripe_payment_intent), $order->total_pence, $order->amount_refunded_pence, 'GBP'),
            ]);

        return $plans->concat($orders)
            ->sortByDesc(fn (array $row) => $row['paid_at']?->getTimestamp() ?? 0)
            ->map(fn (array $row) => collect($row)->except('paid_at')->all())
            ->values()->all();
    }

    /**
     * Refunds $amount (as typed, e.g. "1200" / "49.50") of one of $shop's
     * payments through its provider. Returns the message for the admin.
     *
     * @throws ValidationException when it can't be refunded or the provider refuses
     */
    public function refund(Shop $shop, string $kind, int $id, string $amount, ?string $reason, User $admin): string
    {
        // Found only through this shop.
        $payment = $kind === self::PLAN ? $shop->subscriptionPayments()->findOrFail($id) : $shop->orders()->findOrFail($id);
        $plan = $payment instanceof SubscriptionPayment;

        $currency = $plan ? $payment->currency : 'GBP';
        $total = $plan ? $payment->amount : $payment->total_pence;
        $already = $plan ? $payment->amount_refunded : $payment->amount_refunded_pence;
        $online = $plan ? $payment->status === SubscriptionPayment::PAID : $payment->isPaid() && $payment->payment_method === PaymentMethod::Stripe && filled($payment->stripe_payment_intent);
        $minor = Currencies::toMinor($amount, $currency);

        if (! $online) {
            throw ValidationException::withMessages(['amount' => "This payment can't be refunded here."]);
        }
        if ($minor < 1 || $minor > $total - $already) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount up to '.Currencies::format($total - $already, $currency).'.']);
        }

        $notes = array_filter(['shop_id' => (string) $shop->id, 'refunded_by' => $admin->email, 'reason' => $reason]);
        $label = Currencies::format($minor, $currency);
        $what = $plan ? "{$payment->plan_name} plan" : "order #{$payment->id}";

        try {
            if ($plan && $payment->provider === SubscriptionPayment::RAZORPAY) {
                $done = $this->razorpay->refundPayment((string) $payment->provider_payment_id, $minor, $notes)['status'] === 'processed';
            } else {
                $intent = $plan ? $payment->stripeIntentId() : $payment->stripe_payment_intent;
                $done = $this->stripe->refund((string) $intent, $minor, $notes)['status'] === 'succeeded';
            }
        } catch (RuntimeException|ApiErrorException $e) {
            report($e);
            throw ValidationException::withMessages(['amount' => 'The refund was refused: '.$e->getMessage()]);
        }

        ActivityLogger::record('payment.refunded', "Refund of {$label} on {$what}".($reason ? " ({$reason})" : '').($done ? '' : ' - waiting for the provider'), $shop->id, $payment);

        if (! $done) {
            return "Refund of {$label} requested. It shows here once ".($plan ? ucfirst($payment->provider) : 'Stripe').' has processed it.';
        }

        DB::transaction(fn () => $plan
            ? $this->checkout->recordRefund($payment->provider, $payment->provider_order_id, $already + $minor)
            : $this->orders->recordRefund($payment->stripe_payment_intent, $already + $minor));

        return "Refunded {$label}.";
    }

    /** Whether the Refund button shows, and how much is left to refund. */
    private function refundable(bool $online, int $total, int $refunded, string $currency): array
    {
        $left = max(0, $total - $refunded);
        $decimals = Currencies::decimals($currency);

        return [
            'can_refund' => $online && $left > 0,
            'refundable' => $decimals > 0 ? number_format($left / 10 ** $decimals, $decimals, '.', '') : (string) $left,
            'refundable_label' => Currencies::format($left, $currency),
            'currency' => $currency,
        ];
    }
}
