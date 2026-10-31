<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\OrderService;
use App\Services\StripeGateway;
use App\Services\SubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe's server-to-server events - cover owners who close the tab before
 * the success redirect. Trusted only via the Stripe-Signature header
 * (STRIPE_WEBHOOK_SECRET). Subscribe to: checkout.session.completed and
 * checkout.session.async_payment_succeeded (plan payments, else counter
 * display orders), charge.refunded and charge.dispute.created (refunds,
 * chargebacks). Plan payment events are logged with what they did.
 */
class StripeWebhookController extends Controller
{
    private const PAID_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function __invoke(Request $request, StripeGateway $stripe, OrderService $orders, SubscriptionCheckout $subscriptions): JsonResponse
    {
        try {
            $event = $stripe->webhookEvent($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            Log::warning('Rejected Stripe webhook', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];
        $intent = is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null;

        // Plan payments (SubscriptionCheckout): paid, refunded, disputed - else a
        // counter display order (OrderService), also paid by Stripe Checkout.
        $paidEvent = in_array($type, self::PAID_EVENTS, true);
        $outcome = match (true) {
            $paidEvent => $subscriptions->confirmStripeSession($object),
            $type === 'charge.refunded' => ($result = $subscriptions->recordRefund(SubscriptionPayment::STRIPE, $intent, (int) ($object['amount_refunded'] ?? 0))) === SubscriptionCheckout::NOT_OURS
                ? $orders->recordRefund($intent, (int) ($object['amount_refunded'] ?? 0))
                : $result,
            $type === 'charge.dispute.created' => $subscriptions->recordDispute(SubscriptionPayment::STRIPE, $intent),
            default => null,
        };

        // Not a plan payment's session: a counter display order's.
        if ($paidEvent && $outcome === SubscriptionCheckout::NOT_OURS) {
            $orders->confirmCheckout([
                'id' => $object['id'] ?? null,
                'payment_status' => $object['payment_status'] ?? null,
                'payment_intent' => $intent,
            ]);
            $outcome = null;
        }

        if ($outcome !== null) {
            Log::info('Stripe webhook', ['event' => $type, 'intent' => $intent ?? ($object['id'] ?? null), 'result' => $outcome]);
        }

        return response()->json(['received' => true]);
    }
}
