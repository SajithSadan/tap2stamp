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
 * checkout.session.async_payment_succeeded (counter display orders),
 * payment_intent.succeeded, charge.refunded and charge.dispute.created (plan
 * payments: paid, refunds, chargebacks - each logged with what it did).
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

        // Plan payments made with the card form (SubscriptionCheckout): paid, refunded, disputed.
        // Intents / charges that aren't a plan payment come back as "no matching plan payment".
        $outcome = match ($type) {
            'payment_intent.succeeded' => $subscriptions->confirmStripeIntent($object),
            // A plan payment, else a counter display order paid by Stripe Checkout.
            'charge.refunded' => ($result = $subscriptions->recordRefund(SubscriptionPayment::STRIPE, $intent, (int) ($object['amount_refunded'] ?? 0))) === SubscriptionCheckout::NOT_OURS
                ? $orders->recordRefund($intent, (int) ($object['amount_refunded'] ?? 0))
                : $result,
            'charge.dispute.created' => $subscriptions->recordDispute(SubscriptionPayment::STRIPE, $intent),
            default => null,
        };
        if ($outcome !== null) {
            Log::info('Stripe webhook', ['event' => $type, 'intent' => $intent ?? ($object['id'] ?? null), 'result' => $outcome]);
        }

        if (in_array($event['type'] ?? null, self::PAID_EVENTS, true)) {
            $session = $event['data']['object'] ?? [];

            $orders->confirmCheckout([
                'id' => $session['id'] ?? null,
                'payment_status' => $session['payment_status'] ?? null,
                'payment_intent' => is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
            ]);
        }

        return response()->json(['received' => true]);
    }
}
