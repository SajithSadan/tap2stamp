<?php

namespace App\Services;

use App\Enums\OrderStage;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Product orders: owners pay online (Stripe Checkout), or the admin records
 * a payment taken by bank transfer / cash. Either way a paid order marks the
 * shop as having ordered (shops.product_ordered_at), which hides the
 * "order your counter display" banner for good.
 */
class OrderService
{
    public function __construct(private StripeGateway $stripe) {}

    /** Stripe's smallest card payment in GBP; a coupon can't leave less than this (but can make it free). */
    public const STRIPE_MINIMUM_PENCE = 30;

    /**
     * What $quantity of $product costs with $coupon (if any).
     *
     * @return array{list: int, discount: int, total: int}
     */
    public function price(Product $product, int $quantity, ?Coupon $coupon = null): array
    {
        $list = $product->totalFor($quantity);
        $discount = $coupon?->discountFor($list) ?? 0;

        return ['list' => $list, 'discount' => $discount, 'total' => $list - $discount];
    }

    /**
     * Opens a Stripe Checkout page for one product (any quantity). Reuses the shop's
     * unfinished order for the same product, so going back and forth to
     * Stripe doesn't pile up pending orders. A coupon's discount is passed to
     * Stripe as a one-off Stripe coupon for that exact amount.
     *
     * @return string the Stripe Checkout URL to send the owner to
     */
    public function startCheckout(Shop $shop, Product $product, User $owner, int $quantity = 1, ?Coupon $coupon = null): string
    {
        $price = $this->price($product, $quantity, $coupon);

        $order = $shop->orders()
            ->where('product_id', $product->id)
            ->where('status', OrderStatus::Pending)
            ->where('payment_method', PaymentMethod::Stripe)
            ->latest()
            ->firstOrNew();

        $order->fill([
            'product_id' => $product->id,
            'user_id' => $owner->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price_pence' => $product->price_pence,
            'total_pence' => $price['total'],
            'list_total_pence' => $price['list'],
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'payment_method' => PaymentMethod::Stripe,
            'status' => OrderStatus::Pending,
            'delivery_address' => $shop->deliveryAddress(),
        ])->save();

        $discounts = $price['discount'] > 0 ? [['coupon' => $this->stripe->createCoupon([
            'amount_off' => $price['discount'],
            'currency' => 'gbp',
            'duration' => 'once',
            'max_redemptions' => 1,
            'name' => $coupon->code,
        ])]] : [];

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            // One line per price step, so Stripe's total and receipt match ours
            // (e.g. 1 × £40, then 2 × £20 for the extra ones).
            'line_items' => collect($product->priceBreakdown($quantity))->map(fn (array $step, int $i) => [
                'quantity' => $step['quantity'],
                'price_data' => [
                    'currency' => 'gbp',
                    'unit_amount' => $step['unit_pence'],
                    'product_data' => array_filter([
                        'name' => $i === 0 ? $product->name : "{$product->name} (from item {$step['from']})",
                        'description' => $i === 0 ? $product->description : null,
                    ]),
                ],
            ])->all(),
            ...($discounts ? ['discounts' => $discounts] : []),
            'customer_email' => $owner->email,
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => $order->id, 'shop_id' => $shop->id],
            'success_url' => route('dashboard.orders.success', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('dashboard.orders'),
        ]);

        $order->update(['stripe_session_id' => $session['id']]);

        return $session['url'];
    }

    /** A coupon took the whole price off: nothing to pay, so the order goes straight ahead. */
    public function placeFree(Shop $shop, Product $product, User $owner, int $quantity, Coupon $coupon): Order
    {
        return DB::transaction(function () use ($shop, $product, $owner, $quantity, $coupon) {
            $order = $shop->orders()->create([
                'product_id' => $product->id,
                'user_id' => $owner->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price_pence' => $product->price_pence,
                'total_pence' => 0,
                'list_total_pence' => $product->totalFor($quantity),
                'coupon_id' => $coupon->id,
                'coupon_code' => $coupon->code,
                'payment_method' => PaymentMethod::Free,
                'status' => OrderStatus::Pending,
                'delivery_address' => $shop->deliveryAddress(),
            ]);

            return $this->markPaid($order);
        });
    }

    /**
     * Marks the order behind a Stripe Checkout session as paid, if Stripe
     * says it is. Called from both the success redirect and the webhook,
     * whichever arrives first - safe to call twice.
     */
    public function confirmCheckout(array $session): ?Order
    {
        if (($session['payment_status'] ?? null) !== 'paid') {
            return null;
        }

        $order = Order::where('stripe_session_id', $session['id'] ?? null)->first();

        return $order ? $this->markPaid($order, $session['payment_intent'] ?? null) : null;
    }

    /**
     * An order the admin arranges for a shop (e.g. agreed on the phone), at
     * the normal price or an agreed one (a promotion, or free). Either the
     * money is already in ($paid), or it waits for the shop's bank transfer
     * until the admin confirms it (confirmPayment). Free is always "paid".
     */
    public function recordManual(Shop $shop, Product $product, User $admin, PaymentMethod $method, int $quantity, int $totalPence, bool $paid, ?string $note): Order
    {
        $free = $method === PaymentMethod::Free;

        return DB::transaction(function () use ($shop, $product, $admin, $method, $quantity, $totalPence, $paid, $note, $free) {
            $order = $shop->orders()->create([
                'product_id' => $product->id,
                'user_id' => $admin->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price_pence' => $product->price_pence,
                'total_pence' => $free ? 0 : $totalPence,
                'list_total_pence' => $product->totalFor($quantity),
                'payment_method' => $method,
                'status' => OrderStatus::Pending,
                'delivery_address' => $shop->deliveryAddress(),
                'note' => $note,
            ]);

            return $paid || $free ? $this->markPaid($order) : $order;
        });
    }

    /** The admin saw the shop's bank transfer (or took the cash): the order goes ahead. */
    public function confirmPayment(Order $order, PaymentMethod $method): Order
    {
        if (! $order->awaitingManualPayment()) {
            return $order;
        }

        $order->update(['payment_method' => $method]);

        return $this->markPaid($order);
    }

    /**
     * The admin correcting an order. Stripe orders keep their product, amount
     * and method (that's what was charged); only where it goes and the note
     * change. Arranged orders can change everything; switching an unpaid one
     * to Free means nothing is owed, so it goes ahead.
     */
    public function update(Order $order, array $values): Order
    {
        return DB::transaction(function () use ($order, $values) {
            $changes = [
                'delivery_address' => $values['delivery_address'] ?? null,
                'note' => $values['note'] ?? null,
            ];

            if ($order->payment_method !== PaymentMethod::Stripe) {
                $product = Product::findOrFail($values['product_id']);
                $method = $values['payment_method'];

                $changes += [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $values['quantity'],
                    'unit_price_pence' => $product->price_pence,
                    'list_total_pence' => $product->totalFor($values['quantity']),
                    'payment_method' => $method,
                    'total_pence' => $method === PaymentMethod::Free ? 0 : $values['total_pence'],
                ];
            }

            $order->update($changes);

            return $order->awaitingManualPayment() && $order->payment_method === PaymentMethod::Free
                ? $this->markPaid($order)
                : $order;
        });
    }

    /** Calls off an order that was never paid. Paid orders can't be cancelled here. */
    public function cancel(Order $order): Order
    {
        if ($order->status === OrderStatus::Pending) {
            $order->update(['status' => OrderStatus::Cancelled]);
        }

        return $order;
    }

    /**
     * The admin moving a paid order to a stage - forwards (stamping today's
     * date on any stage skipped over) or back to fix a mis-click (clearing
     * the later stages). Courier/tracking are kept from Dispatched on.
     * Emails the owner when it newly reaches Dispatched or Delivered.
     */
    public function moveTo(Order $order, OrderStage $stage, array $tracking = []): Order
    {
        $before = $order->stage();
        $values = [];

        foreach (OrderStage::cases() as $step) {
            if ($step === OrderStage::Received) {
                continue; // paid_at - set by payment, never by hand
            }

            $values[$step->column()] = $step->index() <= $stage->index()
                ? ($order->{$step->column()} ?? now())
                : null;
        }

        $shipped = $stage->index() >= OrderStage::Dispatched->index();
        foreach (['courier', 'tracking_number', 'tracking_url'] as $field) {
            $values[$field] = $shipped ? (array_key_exists($field, $tracking) ? $tracking[$field] : $order->{$field}) : null;
        }

        $order->update($values);

        if ($before !== null && $stage->index() > $before->index()) {
            match ($stage) {
                OrderStage::Dispatched => $this->notifyOwner($order, new OrderDispatched($order)),
                OrderStage::Delivered => $this->notifyOwner($order, new OrderDelivered($order)),
                default => null,
            };
        }

        return $order;
    }

    /**
     * Mail is sent straight away (QUEUE_CONNECTION=sync on Hostinger), so a
     * mail problem is logged and never undoes the admin's change.
     */
    private function notifyOwner(Order $order, $mailable): void
    {
        $email = $order->shop->owner?->email ?? $order->shop->contact_email;

        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->send($mailable);
        } catch (Throwable $e) {
            Log::error('Order email failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
        }
    }

    private function markPaid(Order $order, ?string $paymentIntent = null): Order
    {
        return DB::transaction(function () use ($order, $paymentIntent) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->isPaid()) {
                return $order;
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'stripe_payment_intent' => $paymentIntent ?? $order->stripe_payment_intent,
            ]);

            Shop::whereKey($order->shop_id)
                ->whereNull('product_ordered_at')
                ->update(['product_ordered_at' => now()]);

            return $order;
        });
    }
}
