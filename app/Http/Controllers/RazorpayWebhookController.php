<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\RazorpayGateway;
use App\Services\SubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Razorpay's server-to-server events for plan payments - the source of truth
 * when the owner's browser never comes back. Trusted only via the
 * X-Razorpay-Signature header (RAZORPAY_WEBHOOK_SECRET). Subscribe to:
 * payment.authorized, payment.captured, order.paid (paid), refund.processed
 * (refunds) and payment.dispute.created (chargebacks). Each event is logged
 * with what it did.
 */
class RazorpayWebhookController extends Controller
{
    private const PAID_EVENTS = ['payment.authorized', 'payment.captured', 'order.paid'];

    public function __invoke(Request $request, RazorpayGateway $razorpay, SubscriptionCheckout $checkout): JsonResponse
    {
        if (! $razorpay->validWebhookSignature($request->getContent(), (string) $request->header('X-Razorpay-Signature'))) {
            Log::warning('Rejected Razorpay webhook', ['ip' => $request->ip()]);

            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $type = (string) $request->json('event');
        $payment = (array) $request->json('payload.payment.entity', []);

        // A refund event without its payment: ask Razorpay for it (its order and total refunded).
        if ($type === 'refund.processed' && blank($payment['order_id'] ?? null) && filled($paymentId = $request->json('payload.refund.entity.payment_id'))) {
            try {
                $payment = $razorpay->fetchPayment((string) $paymentId);
            } catch (Throwable $e) {
                Log::error('Razorpay refund: payment lookup failed', ['payment' => $paymentId, 'message' => $e->getMessage()]);

                // Not 2xx, so Razorpay sends it again later.
                return response()->json(['error' => 'Try again.'], 503);
            }
        }

        $outcome = match (true) {
            in_array($type, self::PAID_EVENTS, true) => $checkout->confirmRazorpayPayment($payment),
            $type === 'refund.processed' => $checkout->recordRefund(
                SubscriptionPayment::RAZORPAY,
                $payment['order_id'] ?? null,
                (int) ($payment['amount_refunded'] ?? 0),
            ),
            $type === 'payment.dispute.created' => $checkout->recordDispute(SubscriptionPayment::RAZORPAY, $payment['order_id'] ?? null),
            default => 'ignored',
        };

        Log::info('Razorpay webhook', ['event' => $type, 'order' => $payment['order_id'] ?? null, 'payment' => $payment['id'] ?? null, 'result' => $outcome]);

        return response()->json(['received' => true]);
    }
}
