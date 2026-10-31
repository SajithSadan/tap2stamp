<?php

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\PlanCoupon;
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
    ]);
    Plan::where('name', 'Growth')->update(['prices' => ['INR' => '4999', 'GBP' => '49.50']]);
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_C', 'status' => 'created'])]);
});

function planCouponGrowth(): Plan
{
    return Plan::where('name', 'Growth')->first();
}

function planCouponOwner(string $country = 'IN'): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'country' => $country, 'trial_ends_at' => now()->addDays(10)]);

    return [$owner, $shop];
}

function planCoupon(array $attributes = []): PlanCoupon
{
    return PlanCoupon::create([
        'code' => 'LAUNCH',
        'discount_type' => PlanCoupon::FIXED,
        'discounts' => ['IN' => '1000', 'GB' => '10'],
        'once_per_shop' => true,
        'is_active' => true,
        ...$attributes,
    ]);
}

test('the admin adds and edits a plan coupon with a discount per country', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get('/admin/plans/coupons')->assertInertia(fn ($page) => $page->component('Admin/PlanCoupons/Index'));

    $this->post('/admin/plans/coupons', [
        'code' => ' launch20 ',
        'discount_type' => 'percent',
        'discounts' => [['country' => 'GB', 'value' => '20'], ['country' => 'in', 'value' => '15']],
        'once_per_shop' => true,
        'is_active' => true,
    ])->assertSessionHasNoErrors();

    $coupon = PlanCoupon::firstOrFail();
    expect($coupon->code)->toBe('LAUNCH20')->and($coupon->discounts)->toBe(['GB' => '20', 'IN' => '15']);

    // Same country twice, a percentage over 100, no countries: refused.
    $this->put("/admin/plans/coupons/{$coupon->id}", [
        'code' => 'LAUNCH20', 'discount_type' => 'percent', 'once_per_shop' => true, 'is_active' => true,
        'discounts' => [['country' => 'GB', 'value' => '120'], ['country' => 'GB', 'value' => '5']],
    ])->assertSessionHasErrors(['discounts.0.value', 'discounts.1.country']);
    $this->put("/admin/plans/coupons/{$coupon->id}", [
        'code' => 'LAUNCH20', 'discount_type' => 'percent', 'discounts' => [],
    ])->assertSessionHasErrors('discounts');

    $this->put("/admin/plans/coupons/{$coupon->id}", [
        'code' => 'LAUNCH20', 'discount_type' => 'fixed', 'once_per_shop' => false, 'is_active' => false,
        'discounts' => [['country' => 'IN', 'value' => '999.50']],
    ])->assertSessionHasNoErrors();
    expect($coupon->fresh())->discount_type->toBe('fixed')->discounts->toBe(['IN' => '999.50'])->is_active->toBeFalse();
});

test('owners cannot manage plan coupons', function () {
    [$owner] = planCouponOwner();

    $this->actingAs($owner)->get('/admin/plans/coupons')->assertForbidden();
    $this->post('/admin/plans/coupons', ['code' => 'X'])->assertForbidden();
});

test('the confirmation box shows the price, and the discount once a coupon is applied', function () {
    planCoupon();
    [$owner] = planCouponOwner('IN');

    $this->actingAs($owner)->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id])->assertOk()
        ->assertJsonPath('list_label', '₹4,999')
        ->assertJsonPath('amount_label', '₹4,999')
        ->assertJsonPath('discount_label', null)
        ->assertJsonPath('coupon', null);

    $this->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'launch'])->assertOk()
        ->assertJsonPath('discount_label', '₹1,000')
        ->assertJsonPath('amount', 399900)
        ->assertJsonPath('amount_label', '₹3,999')
        ->assertJsonPath('coupon.label', '₹1,000 off');
});

test('a coupon only works in its countries and while active', function (array $coupon, string $country, string $message) {
    planCoupon($coupon);
    [$owner] = planCouponOwner($country);

    $this->actingAs($owner)->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', $message);
})->with([
    'country not listed' => [['discounts' => ['GB' => '10']], 'IN', "This coupon code can't be used in your country."],
    'switched off' => [['is_active' => false], 'IN', 'This coupon code has expired.'],
    'expired' => [['expires_at' => now()->subDay()], 'IN', 'This coupon code has expired.'],
]);

test('an unknown code, or one for another plan, is refused', function () {
    $other = Plan::where('name', '!=', 'Growth')->first();
    planCoupon(['plan_id' => $other->id]);
    [$owner] = planCouponOwner('IN');

    $this->actingAs($owner)->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'NOPE'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', "This coupon code isn't valid.");
    $this->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', "This coupon code can't be used on the Growth plan.");
});

test('paying with a coupon charges the discounted amount and counts as one use per shop', function () {
    $coupon = planCoupon();
    [$owner, $shop] = planCouponOwner('IN');

    $id = $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])->assertOk()
        ->assertJsonPath('amount', 399900)
        ->json('payment_id');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/orders') && $request['amount'] === 399900);

    $payment = SubscriptionPayment::find($id);
    expect($payment)->amount->toBe(399900)->list_amount->toBe(499900)->coupon_code->toBe('LAUNCH')->plan_coupon_id->toBe($coupon->id);

    // Unpaid attempts don't use it up; a paid one does.
    expect($coupon->isUsedUp())->toBeFalse();
    app(SubscriptionCheckout::class)->markPaid($payment, 'pay_1');
    expect($payment->fresh()->toHistoryRow()['coupon_label'])->toBe('Coupon LAUNCH · ₹1,000 off');

    $this->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])
        ->assertStatus(422)->assertJsonPath('errors.code.0', "You've already used this coupon code.");
});

test('a coupon that takes the whole price off starts the plan with nothing to pay', function () {
    planCoupon(['code' => 'FREEYEAR', 'discount_type' => PlanCoupon::PERCENT, 'discounts' => ['IN' => '100']]);
    [$owner, $shop] = planCouponOwner('IN');

    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => planCouponGrowth()->id, 'code' => 'FREEYEAR'])->assertOk()
        ->assertJsonPath('provider', 'free')
        ->assertJsonPath('status', 'paid');

    Http::assertNothingSent();
    expect($shop->fresh())->plan_id->toBe(planCouponGrowth()->id)->crm_stage->toBe('paid')
        ->and(SubscriptionPayment::first())->provider->toBe('free')->amount->toBe(0)->status->toBe('paid');
});

test('a coupon that would leave too little to charge is refused', function () {
    planCoupon(['discounts' => ['IN' => '4998.50']]);
    [$owner] = planCouponOwner('IN');

    $this->actingAs($owner)->postJson('/dashboard/plan/quote', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])->assertStatus(422);
});

test("Stripe's Checkout page is opened for the discounted amount", function () {
    planCoupon();
    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')->once()
            ->withArgs(fn (array $p) => $p['line_items'][0]['price_data']['unit_amount'] === 3950
                && $p['metadata']['coupon'] === 'LAUNCH'
                && str_contains($p['line_items'][0]['price_data']['product_data']['description'], 'coupon LAUNCH'))
            ->andReturn(['id' => 'cs_c', 'url' => 'https://checkout.stripe.com/c/pay/cs_c']);
    });
    [$owner] = planCouponOwner('GB');

    $this->actingAs($owner)->postJson('/dashboard/plan/pay', ['plan_id' => planCouponGrowth()->id, 'code' => 'LAUNCH'])->assertOk()
        ->assertJsonPath('url', 'https://checkout.stripe.com/c/pay/cs_c');
    expect(SubscriptionPayment::first())->amount->toBe(3950)->list_amount->toBe(4950);
});
