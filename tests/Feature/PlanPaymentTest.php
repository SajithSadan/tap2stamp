<?php

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\StripeGateway;
use App\Services\SubscriptionCheckout;
use App\Support\PaymentProviders;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'rzp_secret',
        'services.razorpay.webhook_secret' => 'rzp_webhook',
    ]);
    // GBP prices too, so UK owners can pay.
    Plan::where('name', 'Starter')->update(['prices' => ['INR' => '2999', 'GBP' => '29']]);
    Plan::where('name', 'Growth')->update(['prices' => ['INR' => '4999', 'GBP' => '49.50']]);
});

/** An owner whose shop's trial ends on 15 Nov 2026 (today is 20 Oct 2026). */
function payingOwner(string $country = 'GB'): array
{
    test()->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create([
        'user_id' => $owner->id,
        'country' => $country,
        'trial_ends_at' => now()->setDate(2026, 11, 15)->endOfDay(),
    ]);

    return [$owner, $shop];
}

function stripeReady(array $intent = []): void
{
    test()->mock(StripeGateway::class, function (MockInterface $mock) use ($intent) {
        $mock->shouldReceive('elementsConfigured')->andReturn(true);
        $mock->shouldReceive('publishableKey')->andReturn('pk_test_123');
        $mock->shouldReceive('createPaymentIntent')->andReturn(['id' => 'pi_123', 'client_secret' => 'pi_123_secret']);
        $mock->shouldReceive('retrievePaymentIntent')->with('pi_123')->andReturn([
            'id' => 'pi_123', 'status' => 'succeeded', 'amount' => 4950, 'currency' => 'GBP', 'latest_charge' => 'ch_1', ...$intent,
        ]);
    });
}

function fakeRazorpayOrder(): void
{
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_ABC', 'status' => 'created'])]);
}

/** Razorpay's API answers for payment pay_1 (the order is faked by fakeRazorpayOrder()). */
function fakeRazorpayPayment(array $payment = [], ?array $captured = null): void
{
    $base = ['id' => 'pay_1', 'order_id' => 'order_ABC', 'amount' => 499900, 'currency' => 'INR', 'status' => 'captured', 'amount_refunded' => 0];
    Http::fake([
        'api.razorpay.com/v1/payments/*/capture' => Http::response([...$base, 'status' => 'captured', ...($captured ?? [])]),
        'api.razorpay.com/v1/payments/*' => Http::response([...$base, ...$payment]),
    ]);
}

/** Razorpay's answer from the payment window, correctly signed. */
function razorpayAnswer(int $paymentId): array
{
    return [
        'payment_id' => $paymentId,
        'razorpay_order_id' => 'order_ABC',
        'razorpay_payment_id' => 'pay_1',
        'razorpay_signature' => hash_hmac('sha256', 'order_ABC|pay_1', 'rzp_secret'),
    ];
}

/** Sends a signed Razorpay webhook. */
function razorpayWebhook(string $event, array $payload): TestResponse
{
    $body = json_encode(['event' => $event, 'payload' => $payload]);

    return test()->call('POST', '/api/razorpay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'rzp_webhook'),
    ], $body);
}

/** An owner (India) whose Growth payment by Razorpay is paid. */
function paidRazorpayOwner(): array
{
    fakeRazorpayOrder();
    fakeRazorpayPayment();
    [$owner, $shop] = payingOwner('IN');
    $id = test()->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');
    test()->postJson('/dashboard/plan/pay/confirm', razorpayAnswer($id))->assertOk();

    return [$owner, $shop->fresh(), SubscriptionPayment::find($id)];
}

function growth(): Plan
{
    return Plan::where('name', 'Growth')->first();
}

test('the UK pays with Stripe and other countries with Razorpay, until the admin adds them to Stripe', function () {
    expect(PaymentProviders::forCountry('GB'))->toBe('stripe')
        ->and(PaymentProviders::forCountry('IN'))->toBe('razorpay')
        ->and(PaymentProviders::forCountry('AE'))->toBe('razorpay');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->put('/admin/plans/payments', ['stripe_countries' => ['GB', 'AE']])->assertSessionHasNoErrors();

    expect(PaymentProviders::forCountry('AE'))->toBe('stripe')
        ->and(PaymentProviders::forCountry('IN'))->toBe('razorpay');

    $this->put('/admin/plans/payments', ['stripe_countries' => ['XX']])->assertSessionHasErrors('stripe_countries.0');
    $this->get('/admin/landing-page')->assertInertia(fn ($page) => $page
        ->where('payments.stripe_countries', ['GB', 'AE'])
        ->where('payments.razorpay_ready', true));
});

test('owners and staff cannot change the payment countries', function () {
    [$owner] = payingOwner();

    $this->actingAs($owner)->put('/admin/plans/payments', ['stripe_countries' => []])->assertForbidden();
    expect(Setting::get(Setting::PAYMENT_COUNTRIES))->toBeNull();
});

test('without the keys a plan is not payable here (the WhatsApp button stays)', function () {
    config(['services.razorpay.key_id' => null]);
    [$owner] = payingOwner('IN');

    $this->actingAs($owner)->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('paymentProvider', 'razorpay')
        ->where('plans.1.payable', false));
    $this->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->assertNotFound();
});

test('a UK owner pays by card: the plan starts when the free trial ends', function () {
    stripeReady();
    [$owner, $shop] = payingOwner('GB');

    $this->actingAs($owner)->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('paymentProvider', 'stripe')
        ->where('plans.1.name', 'Growth')
        ->where('plans.1.payable', true));

    $start = $this->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->assertOk()
        ->assertJsonPath('provider', 'stripe')
        ->assertJsonPath('client_secret', 'pi_123_secret')
        ->assertJsonPath('publishable_key', 'pk_test_123')
        ->assertJsonPath('amount_label', '£49.50');

    $payment = SubscriptionPayment::find($start->json('payment_id'));
    expect($payment)->status->toBe('pending')->amount->toBe(4950)->currency->toBe('GBP');

    $this->postJson('/dashboard/plan/pay/confirm', ['payment_id' => $payment->id])->assertOk()
        ->assertJsonPath('status', 'paid')
        ->assertJsonPath('paid_until_label', '15 Nov 2027');

    $shop->refresh();
    expect($shop->plan_id)->toBe(growth()->id)
        ->and($shop->crm_stage)->toBe('paid')
        ->and($shop->subscriptionStatus())->toBe('active')
        ->and($payment->fresh())->status->toBe('paid')->provider_payment_id->toBe('ch_1')
        ->and($payment->fresh()->period_starts_at->format('Y-m-d'))->toBe('2026-11-15');
});

test('a Stripe payment that did not go through, or for another amount, does not start the plan', function () {
    stripeReady(['status' => 'requires_payment_method']);
    [$owner, $shop] = payingOwner('GB');
    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');

    $this->postJson('/dashboard/plan/pay/confirm', ['payment_id' => $id])->assertStatus(202)->assertJsonPath('status', 'pending');
    expect($shop->fresh()->subscription_ends_at)->toBeNull();
});

test('an Indian owner pays with Razorpay; only a correctly signed answer starts the plan', function () {
    fakeRazorpayOrder();
    fakeRazorpayPayment();
    [$owner, $shop] = payingOwner('IN');

    $start = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->assertOk()
        ->assertJsonPath('provider', 'razorpay')
        ->assertJsonPath('key_id', 'rzp_test_key')
        ->assertJsonPath('order_id', 'order_ABC')
        ->assertJsonPath('amount', 499900)
        ->assertJsonPath('currency', 'INR')
        ->assertJsonMissingPath('key_secret');
    Http::assertSent(fn ($request) => $request['amount'] === 499900 && $request['currency'] === 'INR');

    $id = $start->json('payment_id');
    $this->postJson('/dashboard/plan/pay/confirm', [
        'payment_id' => $id, 'razorpay_order_id' => 'order_ABC', 'razorpay_payment_id' => 'pay_1', 'razorpay_signature' => 'forged',
    ])->assertStatus(202);
    expect($shop->fresh()->subscription_ends_at)->toBeNull();

    $this->postJson('/dashboard/plan/pay/confirm', [
        'payment_id' => $id,
        'razorpay_order_id' => 'order_ABC',
        'razorpay_payment_id' => 'pay_1',
        'razorpay_signature' => hash_hmac('sha256', 'order_ABC|pay_1', 'rzp_secret'),
    ])->assertOk()->assertJsonPath('status', 'paid');

    expect($shop->fresh()->plan_id)->toBe(growth()->id)
        ->and(SubscriptionPayment::find($id)->provider_payment_id)->toBe('pay_1');
});

test("Razorpay's signed webhook confirms a payment once; unsigned ones are refused", function () {
    fakeRazorpayOrder();
    [$owner, $shop] = payingOwner('IN');
    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id]);

    $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
        'id' => 'pay_9', 'order_id' => 'order_ABC', 'status' => 'captured', 'amount' => 499900, 'currency' => 'INR',
    ]]]]);
    $send = fn (string $signature) => $this->call('POST', '/api/razorpay/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
    ], $body);

    $send('nope')->assertStatus(400);
    expect($shop->fresh()->subscription_ends_at)->toBeNull();

    $send(hash_hmac('sha256', $body, 'rzp_webhook'))->assertOk();
    $send(hash_hmac('sha256', $body, 'rzp_webhook'))->assertOk();

    expect($shop->fresh()->subscription_ends_at->format('Y-m-d'))->toBe('2027-11-15')
        ->and(SubscriptionPayment::where('status', 'paid')->count())->toBe(1);
});

test("an owner can't confirm another shop's payment", function () {
    fakeRazorpayOrder();
    [$owner] = payingOwner('IN');
    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');

    $other = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $other->id, 'country' => 'IN']);

    $this->actingAs($other)->postJson('/dashboard/plan/pay/confirm', ['payment_id' => $id])->assertNotFound();
});

test('an admin viewing as the owner cannot pay', function () {
    fakeRazorpayOrder();
    [, $shop] = payingOwner('IN');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);
    $this->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->assertForbidden();
    expect(SubscriptionPayment::count())->toBe(0);
});

test('paid plans show in the admin list', function () {
    fakeRazorpayOrder();
    [$owner, $shop] = payingOwner('IN');
    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id]);
    app(SubscriptionCheckout::class)->markPaid(SubscriptionPayment::first(), 'pay_1');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->get('/admin/landing-page')->assertInertia(fn ($page) => $page
        ->where('recentPayments.0.shop', $shop->name)
        ->where('recentPayments.0.amount', '₹4,999')
        ->where('recentPayments.0.provider', 'razorpay'));
});

test('the owner sees their own paid payments as history - not unfinished attempts, not other shops', function () {
    fakeRazorpayOrder();
    [$owner, $shop] = payingOwner('IN');
    [, $otherShop] = payingOwner('IN');
    SubscriptionPayment::create([
        'shop_id' => $otherShop->id, 'plan_id' => growth()->id, 'plan_name' => 'Growth', 'provider' => 'razorpay',
        'currency' => 'INR', 'amount' => 499900, 'status' => SubscriptionPayment::PAID, 'paid_at' => now(), 'provider_order_id' => 'order_other',
    ]);

    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->assertOk();
    $this->get('/dashboard/plan')->assertInertia(fn ($page) => $page->has('payments', 0));

    app(SubscriptionCheckout::class)->markPaid($shop->subscriptionPayments()->first(), 'pay_123');

    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->has('payments', 1)
        ->where('payments.0.plan', 'Growth')
        ->where('payments.0.amount', '₹4,999')
        ->where('payments.0.method', 'Razorpay')
        ->where('payments.0.reference', 'pay_123')
        ->where('payments.0.period_label', '15 Nov 2026 - 15 Nov 2027'));
});

test('the browser check asks Razorpay: a signed answer for a payment that is not captured, or for another amount, starts nothing', function (array $payment) {
    fakeRazorpayOrder();
    fakeRazorpayPayment($payment, ['status' => 'authorized']);
    [$owner, $shop] = payingOwner('IN');
    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');

    $this->postJson('/dashboard/plan/pay/confirm', razorpayAnswer($id))->assertStatus(202)->assertJsonPath('status', 'pending');

    expect($shop->fresh()->subscription_ends_at)->toBeNull()
        ->and(SubscriptionPayment::find($id)->status)->toBe('pending');
})->with([
    'failed' => [['status' => 'failed']],
    'another amount' => [['amount' => 100]],
    'another currency' => [['currency' => 'USD']],
    'another order' => [['order_id' => 'order_OTHER']],
]);

test('an authorised Razorpay payment is captured first, then the plan starts', function () {
    fakeRazorpayOrder();
    fakeRazorpayPayment(['status' => 'authorized']);
    [$owner, $shop] = payingOwner('IN');
    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');

    $this->postJson('/dashboard/plan/pay/confirm', razorpayAnswer($id))->assertOk()->assertJsonPath('status', 'paid');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/payments/pay_1/capture') && $request['amount'] === 499900 && $request['currency'] === 'INR');
    expect($shop->fresh()->plan_id)->toBe(growth()->id);
});

test("Razorpay's payment.authorized webhook captures and starts the plan when the browser never came back", function () {
    fakeRazorpayOrder();
    fakeRazorpayPayment();
    [$owner, $shop] = payingOwner('IN');
    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id]);

    razorpayWebhook('payment.authorized', ['payment' => ['entity' => [
        'id' => 'pay_1', 'order_id' => 'order_ABC', 'status' => 'authorized', 'amount' => 499900, 'currency' => 'INR',
    ]]])->assertOk();

    expect($shop->fresh()->subscription_ends_at->format('Y-m-d'))->toBe('2027-11-15');
});

test('a full Razorpay refund takes the paid year back; a late "captured" webhook cannot bring it back', function () {
    [$owner, $shop, $payment] = paidRazorpayOwner();
    expect($shop->subscriptionStatus())->toBe('active');
    $refund = ['payment' => ['entity' => [
        'id' => 'pay_1', 'order_id' => 'order_ABC', 'status' => 'refunded', 'amount' => 499900, 'currency' => 'INR', 'amount_refunded' => 499900,
    ]]];

    razorpayWebhook('refund.processed', $refund)->assertOk();
    razorpayWebhook('refund.processed', $refund)->assertOk();
    razorpayWebhook('payment.captured', ['payment' => ['entity' => [
        'id' => 'pay_1', 'order_id' => 'order_ABC', 'status' => 'captured', 'amount' => 499900, 'currency' => 'INR',
    ]]])->assertOk();

    $shop->refresh();
    expect($payment->fresh())->status->toBe('refunded')->amount_refunded->toBe(499900)
        ->and($shop->subscription_ends_at)->toBeNull()
        ->and($shop->plan_id)->toBe(Plan::defaultPlan()->id)
        ->and($shop->crm_stage)->toBe('trial')
        ->and($shop->subscriptionStatus())->toBe('trial')
        ->and($shop->tasks()->count())->toBe(1);

    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('payments.0.refund_label', 'Refunded ₹4,999'));
});

test('a part refund is noted but the plan stays; a chargeback is flagged for the admin', function () {
    [, $shop, $payment] = paidRazorpayOwner();
    $entity = ['id' => 'pay_1', 'order_id' => 'order_ABC', 'amount' => 499900, 'currency' => 'INR', 'amount_refunded' => 100000];

    razorpayWebhook('refund.processed', ['payment' => ['entity' => $entity]])->assertOk();
    razorpayWebhook('payment.dispute.created', ['payment' => ['entity' => $entity]])->assertOk();
    razorpayWebhook('payment.dispute.created', ['payment' => ['entity' => $entity]])->assertOk();

    expect($payment->fresh())->status->toBe('paid')->amount_refunded->toBe(100000)->disputed_at->not->toBeNull()
        ->and($shop->fresh()->subscription_ends_at)->not->toBeNull()
        ->and($shop->tasks()->pluck('title')->all())->toHaveCount(2);
});

test('refunding the second paid year only takes that year off', function () {
    [$owner, $shop, $first] = paidRazorpayOwner();
    $second = SubscriptionPayment::create([
        ...$first->only(['shop_id', 'plan_id', 'user_id', 'plan_name', 'provider', 'currency', 'amount']),
        'status' => 'pending', 'provider_order_id' => 'order_SECOND',
    ]);
    app(SubscriptionCheckout::class)->markPaid($second, 'pay_2');
    expect($shop->fresh()->subscription_ends_at->format('Y-m-d'))->toBe('2028-11-15');

    app(SubscriptionCheckout::class)->recordRefund('razorpay', 'order_SECOND', 499900);

    expect($shop->fresh()->subscription_ends_at->format('Y-m-d'))->toBe('2027-11-15')
        ->and($shop->fresh()->plan_id)->toBe(growth()->id);
});

test("Stripe's refund and chargeback webhooks update a card-paid plan", function () {
    stripeReady();
    config(['services.stripe.webhook_secret' => 'whsec_plan']);
    [$owner, $shop] = payingOwner('GB');
    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => growth()->id])->json('payment_id');
    $this->postJson('/dashboard/plan/pay/confirm', ['payment_id' => $id])->assertOk();
    // The webhooks check real signatures: the real gateway from here on.
    $this->app->instance(StripeGateway::class, new StripeGateway);

    $send = function (string $type, array $object) {
        $payload = json_encode(['id' => 'evt_'.$type, 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]]);
        $time = time();

        return $this->call('POST', '/api/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", 'whsec_plan'),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    };

    $send('charge.dispute.created', ['id' => 'dp_1', 'object' => 'dispute', 'payment_intent' => 'pi_123'])->assertOk();
    expect(SubscriptionPayment::find($id)->disputed_at)->not->toBeNull();

    $send('charge.refunded', ['id' => 'ch_1', 'object' => 'charge', 'payment_intent' => 'pi_123', 'amount_refunded' => 4950])->assertOk();
    expect(SubscriptionPayment::find($id)->status)->toBe('refunded')
        ->and($shop->fresh()->subscription_ends_at)->toBeNull();

    // Another business's charge (e.g. a counter display order) isn't a plan payment.
    $send('charge.refunded', ['id' => 'ch_2', 'object' => 'charge', 'payment_intent' => 'pi_other', 'amount_refunded' => 4000])->assertOk();
});

test("the admin sees a shop's payments on its Account tab, also while its free trial still runs", function () {
    [, $shop] = paidRazorpayOwner();
    expect($shop->subscriptionSummary()['trial_running'])->toBeTrue();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->has('payments', 1)
        ->where('payments.0.plan', 'Growth')
        ->where('payments.0.amount', '₹4,999')
        ->where('payments.0.period_label', '15 Nov 2026 - 15 Nov 2027'));
});
