<?php

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\StripeGateway;
use App\Services\SubscriptionCheckout;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'rzp_secret',
        'services.razorpay.webhook_secret' => 'rzp_webhook',
    ]);
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);
});

/** A shop (India) with a paid Growth payment through Razorpay (₹4,999, pay_1). */
function shopWithPlanPayment(): array
{
    $shop = Shop::factory()->create(['country' => 'IN', 'trial_ends_at' => now()->setDate(2026, 11, 15)->endOfDay()]);
    $growth = Plan::where('name', 'Growth')->first();
    $payment = SubscriptionPayment::create([
        'shop_id' => $shop->id, 'plan_id' => $growth->id, 'plan_name' => 'Growth', 'provider' => 'razorpay',
        'currency' => 'INR', 'amount' => 499900, 'status' => 'pending', 'provider_order_id' => 'order_ABC',
    ]);
    app(SubscriptionCheckout::class)->markPaid($payment, 'pay_1');

    return [$shop->fresh(), $payment->fresh()];
}

function refundUrl(Shop $shop, string $kind, int $id): string
{
    return "/admin/shops/{$shop->id}/payments/{$kind}/{$id}/refund";
}

test('the Payments tab lists plan payments and paid orders, newest first; only online ones can be refunded', function () {
    [$shop, $payment] = shopWithPlanPayment();
    $this->travel(1)->day();
    $card = Order::factory()->paid()->create(['shop_id' => $shop->id, 'stripe_payment_intent' => 'pi_order', 'quantity' => 2, 'total_pence' => 6000]);
    $this->travel(1)->day();
    $bank = Order::factory()->paid()->create(['shop_id' => $shop->id, 'payment_method' => PaymentMethod::BankTransfer]);
    Order::factory()->create(['shop_id' => $shop->id]); // an unfinished checkout isn't a payment

    $this->actingAs($this->admin)->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->has('payments', 3)
        ->where('payments.0.key', "order-{$bank->id}")
        ->where('payments.0.method', 'Bank transfer')
        ->where('payments.0.can_refund', false)
        ->where('payments.1.key', "order-{$card->id}")
        ->where('payments.1.amount', '£60')
        ->where('payments.1.can_refund', true)
        ->where('payments.1.refundable', '60.00')
        ->where('payments.2.key', "plan-{$payment->id}")
        ->where('payments.2.what', 'Growth plan')
        ->where('payments.2.can_refund', true)
        ->where('payments.2.refundable', '4999.00'));
});

test('a full Razorpay refund from the admin goes through Razorpay and takes the paid year back', function () {
    [$shop, $payment] = shopWithPlanPayment();
    Http::fake(['api.razorpay.com/v1/payments/pay_1/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'])]);

    $this->actingAs($this->admin)->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '4999', 'reason' => 'Closed down'])
        ->assertSessionHasNoErrors()->assertSessionHas('status', 'Refunded ₹4,999.');

    Http::assertSent(fn ($request) => $request['amount'] === 499900 && data_get($request->data(), 'notes.reason') === 'Closed down');
    expect($payment->fresh())->status->toBe('refunded')->amount_refunded->toBe(499900)
        ->and($shop->fresh()->subscription_ends_at)->toBeNull()
        ->and(ActivityLog::where('action', 'payment.refunded')->count())->toBe(1);

    // Nothing left to refund.
    $this->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '1'])->assertSessionHasErrors('amount');
});

test('a refund Razorpay has not processed yet changes nothing until its webhook arrives', function () {
    [$shop, $payment] = shopWithPlanPayment();
    Http::fake(['api.razorpay.com/v1/payments/pay_1/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'pending'])]);

    $this->actingAs($this->admin)->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '1000'])
        ->assertSessionHas('status', fn ($message) => str_contains($message, 'requested'));

    expect($payment->fresh())->status->toBe('paid')->amount_refunded->toBe(0);
});

test('a refund Razorpay refuses shows its reason and changes nothing', function () {
    [$shop, $payment] = shopWithPlanPayment();
    Http::fake(['api.razorpay.com/*' => Http::response(['error' => ['description' => 'Insufficient balance']], 400)]);

    $this->actingAs($this->admin)->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '4999'])->assertSessionHasErrors('amount');
    expect($payment->fresh()->amount_refunded)->toBe(0);
});

test('a Stripe order is refunded through Stripe; its charge.refunded webhook also records order refunds', function () {
    $shop = Shop::factory()->create();
    $order = Order::factory()->paid()->create(['shop_id' => $shop->id, 'stripe_payment_intent' => 'pi_order', 'total_pence' => 4000]);
    $this->mock(StripeGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('refund')->once()
        ->withArgs(fn ($intent, $amount) => $intent === 'pi_order' && $amount === 1500)
        ->andReturn(['id' => 're_1', 'status' => 'succeeded']));

    $this->actingAs($this->admin)->post(refundUrl($shop, 'order', $order->id), ['amount' => '15'])->assertSessionHasNoErrors();
    expect($order->fresh())->amount_refunded_pence->toBe(1500)->refunded_at->toBeNull()
        ->and($order->fresh()->isPaid())->toBeTrue();

    // The rest, refunded in Stripe's dashboard: the webhook brings the new total.
    app()->forgetInstance(StripeGateway::class);
    app()->instance(StripeGateway::class, new StripeGateway);
    config(['services.stripe.webhook_secret' => 'whsec_x']);
    $payload = json_encode(['id' => 'evt_r', 'object' => 'event', 'type' => 'charge.refunded', 'data' => ['object' => [
        'id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_order', 'amount_refunded' => 4000,
    ]]]);
    $time = time();
    $this->call('POST', '/api/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", 'whsec_x'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    expect($order->fresh())->amount_refunded_pence->toBe(4000)->refunded_at->not->toBeNull();
});

test('bank transfer orders, too much, other shops\' payments and non-admins cannot refund', function () {
    [$shop, $payment] = shopWithPlanPayment();
    $bank = Order::factory()->paid()->create(['shop_id' => $shop->id, 'payment_method' => PaymentMethod::BankTransfer]);
    $other = Shop::factory()->create();
    Http::fake();

    $this->actingAs($this->admin);
    $this->post(refundUrl($shop, 'order', $bank->id), ['amount' => '10'])->assertSessionHasErrors('amount');
    $this->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '5000'])->assertSessionHasErrors('amount');
    $this->post(refundUrl($other, 'plan', $payment->id), ['amount' => '10'])->assertNotFound();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($owner)->post(refundUrl($shop, 'plan', $payment->id), ['amount' => '10'])->assertForbidden();

    Http::assertNothingSent();
    expect($payment->fresh()->amount_refunded)->toBe(0);
});
