<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\StripeGateway;
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
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'between:1,'.Order::MAX_QUANTITY],
        ]);
        $shop = $request->user()->shop;
        $product = Product::findOrFail($input['product_id']);

        if (! $shop->deliveryAddress()) {
            return back()->withErrors(['order' => 'Add your shop address in Settings first, so we know where to post it.']);
        }

        try {
            return Inertia::location($orders->startCheckout($shop, $product, $request->user(), (int) $input['quantity']));
        } catch (ApiErrorException $e) {
            Log::error('Stripe checkout failed', ['shop_id' => $shop->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['order' => "We couldn't open the payment page. Please try again in a moment."]);
        }
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
