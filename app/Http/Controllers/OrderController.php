<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Services\OrderService;
use App\Services\StripeGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner ordering the featured product (the counter display) through
 * Stripe Checkout. Always "my shop" - the order id in the success URL is
 * looked up through the signed-in owner's own shop.
 */
class OrderController extends Controller
{
    public function checkout(Request $request, OrderService $orders, StripeGateway $stripe): Response|RedirectResponse
    {
        abort_unless($stripe->configured(), 404);

        $input = $request->validate([
            ...$this->orderRules(),
            'coupon' => ['nullable', 'string', 'max:32'],
        ]);
        $shop = $request->user()->shop;
        $product = Product::findOrFail($input['product_id']);
        $quantity = (int) $input['quantity'];

        if (! $shop->deliveryAddress()) {
            return back()->withErrors(['order' => 'Add your shop address in Settings first, so we know where to post it.']);
        }

        $coupon = null;
        if (filled($input['coupon'] ?? null)) {
            [$coupon, $problem] = $this->findCoupon($input['coupon'], $shop, $product);

            if ($problem) {
                return back()->withErrors(['order' => $problem]);
            }
        }

        $total = $orders->price($product, $quantity, $coupon)['total'];

        if ($total === 0) {
            $order = $orders->placeFree($shop, $product, $request->user(), $quantity, $coupon);

            return redirect()->route('dashboard.orders')->with('status', "Thank you! Order #{$order->id} is confirmed - you can follow it here.");
        }

        if ($total < OrderService::STRIPE_MINIMUM_PENCE) {
            return back()->withErrors(['order' => "This coupon leaves too little to pay by card. Please get in touch and we'll sort it out."]);
        }

        try {
            return Inertia::location($orders->startCheckout($shop, $product, $request->user(), $quantity, $coupon));
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout failed', ['shop_id' => $shop->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['order' => "We couldn't open the payment page. Please try again in a moment."]);
        }
    }

    /**
     * "Apply" on the order forms: is this code good for this product? Answers
     * the coupon (to show the new price) or a 422 with why not. The checkout
     * checks it again - this only previews.
     */
    public function coupon(Request $request): JsonResponse
    {
        $input = $request->validate([
            ...$this->orderRules(),
            'code' => ['required', 'string', 'max:32'],
        ]);

        [$coupon, $problem] = $this->findCoupon($input['code'], $request->user()->shop, Product::findOrFail($input['product_id']));

        return $problem
            ? response()->json(['message' => $problem], 422)
            : response()->json(['coupon' => $coupon->summary()]);
    }

    private function orderRules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'between:1,'.Order::MAX_QUANTITY],
        ];
    }

    /** @return array{0: ?Coupon, 1: ?string} the coupon, or why it can't be used */
    private function findCoupon(string $code, Shop $shop, Product $product): array
    {
        $coupon = Coupon::findByCode($code);

        return $coupon
            ? [$coupon, $coupon->problemFor($shop, $product)]
            : [null, "That coupon code isn't valid."];
    }

    /** Stripe sends the owner back here after paying. The webhook confirms it too, whichever is first. */
    public function success(Request $request, string $order, OrderService $orders, StripeGateway $stripe): RedirectResponse
    {
        $order = $request->user()->shop->orders()->findOrFail($order);
        $sessionId = (string) $request->query('session_id');

        if (! $order->isPaid() && $sessionId !== '' && $sessionId === $order->stripe_session_id) {
            try {
                $order = $orders->confirmCheckout($stripe->retrieveCheckoutSession($sessionId)) ?? $order;
            } catch (ApiErrorException $e) {
                Log::error('Stripe session lookup failed', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            }
        }

        return redirect()->route('dashboard.orders')->with('status', $order->isPaid()
            ? "Thank you! Order #{$order->id} is confirmed - you can follow it here."
            : "Thanks! We're confirming your payment with Stripe - it will show here shortly.");
    }
}
