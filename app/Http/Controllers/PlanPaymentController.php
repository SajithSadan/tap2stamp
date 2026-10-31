<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\PlanCoupon;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The owner pays for a year of a plan from Billing & plan: start() opens the
 * payment page (Stripe Checkout / Razorpay's window), confirm() asks the provider
 * whether it really went through. Always the signed-in owner's own shop.
 */
class PlanPaymentController extends Controller
{
    /**
     * The confirmation box before paying: the price, the year it covers and,
     * with a coupon code, the discount and what's left (422 with the reason
     * when the code can't be used).
     */
    public function quote(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        [$plan, $coupon, $refusal] = $this->payablePlan($request, $checkout);

        return $refusal ?? response()->json($checkout->quote($request->user()->shop, $plan, $coupon));
    }

    public function start(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        [$plan, $coupon, $refusal] = $this->payablePlan($request, $checkout);
        if ($refusal) {
            return $refusal;
        }
        $shop = $request->user()->shop;

        try {
            return response()->json($checkout->start($shop, $request->user(), $plan, $coupon));
        } catch (Throwable $e) {
            Log::error('Plan payment could not start', ['shop' => $shop->id, 'provider' => $checkout->providerFor($shop), 'message' => $e->getMessage()]);

            return response()->json(['message' => "Payments aren't available right now. Please try again in a few minutes."], 503);
        }
    }

    public function confirm(Request $request, SubscriptionCheckout $checkout): JsonResponse
    {
        $input = $request->validate([
            // Razorpay's window answers with our payment id; Stripe's page comes back with its session id.
            'payment_id' => ['required_without:session_id', 'nullable', 'integer'],
            'session_id' => ['required_without:payment_id', 'nullable', 'string', 'max:255'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:100'],
            'razorpay_order_id' => ['nullable', 'string', 'max:100'],
            'razorpay_signature' => ['nullable', 'string', 'max:200'],
        ]);
        // Only this owner's shop's payments can be found.
        $payments = $request->user()->shop->subscriptionPayments();
        $payment = filled($input['payment_id'] ?? null)
            ? $payments->findOrFail($input['payment_id'])
            : $payments->where('provider', SubscriptionPayment::STRIPE)->where('provider_order_id', $input['session_id'])->firstOrFail();

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

    /**
     * The plan (+ coupon) in the request, checked: offered and payable here
     * (else 404), not locked (their paid plan before its renew window), and
     * the coupon code usable by this shop on it.
     *
     * @return array{0: ?Plan, 1: ?PlanCoupon, 2: ?JsonResponse} the refusal, when there is one
     */
    private function payablePlan(Request $request, SubscriptionCheckout $checkout): array
    {
        $input = $request->validate([
            'plan_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:32'],
        ]);
        $shop = $request->user()->shop;
        $plan = Plan::active()->find($input['plan_id']);

        abort_unless($plan && $checkout->payable($shop, $plan), 404);

        // Their own plan, already paid for: only renewable near (or after) the end of the year.
        if ($plan->buttonAction($shop, $checkout->currencyFor($shop)) === 'locked') {
            return [$plan, null, response()->json([
                'message' => 'Your plan is paid until '.$shop->subscription_ends_at->timezone('Europe/London')->format('j M Y').'. You can renew it from '.Plan::renewFrom($shop)->timezone('Europe/London')->format('j M Y').'.',
            ], 422)];
        }

        if (blank($input['code'] ?? null)) {
            return [$plan, null, null];
        }

        [$coupon, $problem] = $checkout->couponFor($shop, $plan, $input['code']);

        return $problem
            ? [$plan, null, response()->json(['message' => $problem, 'errors' => ['code' => [$problem]]], 422)]
            : [$plan, $coupon, null];
    }
}
