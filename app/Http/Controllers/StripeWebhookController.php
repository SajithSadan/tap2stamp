<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

/**
 * Stripe's server-to-server confirmation that a Checkout was paid - covers
 * owners who close the tab before the success redirect. Trusted only via the
 * Stripe-Signature header (STRIPE_WEBHOOK_SECRET); CSRF-exempt like /deploy/*.
 */
class StripeWebhookController extends Controller
{
    private const PAID_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function __invoke(Request $request, StripeGateway $stripe, OrderService $orders): JsonResponse
    {
        try {
            $event = $stripe->webhookEvent($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (SignatureVerificationException|UnexpectedValueException) {
            Log::warning('Rejected Stripe webhook', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 400);
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
