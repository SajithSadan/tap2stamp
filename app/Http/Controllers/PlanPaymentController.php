<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\SubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The owner pays for a year of a plan from Billing & plan: start() opens the
 * payment window (Stripe card form / Razorpay), confirm() asks the provider
 * whether it really went through. Always the signed-in owner's own shop.
 */
class PlanPaymentController extends Controller
{
    public function start(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        $input = $request->validate(['plan_id' => ['required', 'integer']]);
        $shop = $request->user()->shop;
        $plan = Plan::active()->find($input['plan_id']);

        abort_unless($plan && $checkout->payable($shop, $plan), 404);

        // Their own plan, already paid for: only renewable near (or after) the end of the year.
        if ($plan->buttonAction($shop, $checkout->currencyFor($shop)) === 'locked') {
            return response()->json([
                'message' => 'Your plan is paid until '.$shop->subscription_ends_at->timezone('Europe/London')->format('j M Y').'. You can renew it from '.Plan::renewFrom($shop)->timezone('Europe/London')->format('j M Y').'.',
            ], 422);
        }

        try {
            return response()->json($checkout->start($shop, $request->user(), $plan));
        } catch (Throwable $e) {
            Log::error('Plan payment could not start', ['shop' => $shop->id, 'provider' => $checkout->providerFor($shop), 'message' => $e->getMessage()]);

            return response()->json(['message' => "Payments aren't available right now. Please try again in a few minutes."], 503);
        }
    }

    public function confirm(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        $input = $request->validate([
            'payment_id' => ['required', 'integer'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:100'],
            'razorpay_order_id' => ['nullable', 'string', 'max:100'],
            'razorpay_signature' => ['nullable', 'string', 'max:200'],
        ]);
        // Only this owner's shop's payments can be found.
        $payment = $request->user()->shop->subscriptionPayments()->findOrFail($input['payment_id']);

        try {
            $paid = $checkout->confirm($payment, $input);
        } catch (Throwable $e) {
            Log::error('Plan payment could not be checked', ['payment' => $payment->id, 'message' => $e->getMessage()]);
            $paid = false;
        }

        if (! $paid) {
            // Not (yet) confirmed: a slow method can still be confirmed by the webhook.
            return response()->json(['status' => 'pending', 'message' => "We haven't received the payment yet. If money left your account, it will show here shortly."], 202);
        }

        return response()->json([
            'status' => 'paid',
            'paid_until_label' => $payment->fresh()->period_ends_at?->timezone('Europe/London')->format('j M Y'),
        ]);
    }
}
