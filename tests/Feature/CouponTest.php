<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\StripeGateway;
use Mockery\MockInterface;

/** An owner whose shop has an address, so it can be posted to. */
function couponOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create([
        'user_id' => $owner->id,
        'address_line1' => '1 High Street',
        'town' => 'Leeds',
        'postcode' => 'LS1 1AA',
    ]);

    return [$owner, $shop];
}

function couponAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/** Stripe is configured; Checkout and the one-off Stripe coupon are allowed (not asserted). */
function stripeAcceptsAnything(): void
{
    test()->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCoupon')->andReturn('stripe_coupon_1');
        $mock->shouldReceive('createCheckoutSession')->andReturn(['id' => 'cs_coupon', 'url' => 'https://checkout.stripe.com/c/pay/cs_coupon']);
    });
}

/** A paid order at $shop that used $coupon. */
function paidWith(Coupon $coupon, Shop $shop): Order
{
    return Order::factory()->create([
        'shop_id' => $shop->id,
        'coupon_id' => $coupon->id,
        'coupon_code' => $coupon->code,
        'status' => OrderStatus::Paid,
        'paid_at' => now(),
    ]);
}

function stand(int $quantity = 1, array $extra = []): array
{
    return ['product_id' => Product::featured()->id, 'quantity' => $quantity, ...$extra];
}

/* ---------- Working out the discount ---------- */

test('a percentage coupon takes that share off the total', function () {
    $coupon = Coupon::factory()->make(['discount_value' => 15]);

    expect($coupon->discountFor(8000))->toBe(1200)
        ->and($coupon->label())->toBe('15% off');
});

test('a fixed coupon never takes off more than the total', function () {
    $coupon = Coupon::factory()->fixed(5000)->make();

    expect($coupon->discountFor(4000))->toBe(4000)
        ->and($coupon->discountFor(12000))->toBe(5000)
        ->and($coupon->label())->toBe('£50 off')
        ->and(Coupon::factory()->fixed(750)->make()->label())->toBe('£7.50 off');
});

/* ---------- Checking a code before ordering ---------- */

test('an owner can check a coupon code, in any case', function () {
    [$owner] = couponOwner();
    Coupon::factory()->create(['code' => 'WELCOME10']);

    $this->actingAs($owner)
        ->postJson('/dashboard/orders/coupon', stand(1, ['code' => ' welcome10 ']))
        ->assertOk()
        ->assertJsonPath('coupon.code', 'WELCOME10')
        ->assertJsonPath('coupon.label', '10% off')
        ->assertJsonPath('coupon.discount_type', 'percent');
});

test('unknown, switched-off, expired and wrong-product codes are refused with a reason', function () {
    [$owner] = couponOwner();
    $other = Product::factory()->create(['name' => 'Table sticker']);
    Coupon::factory()->create(['code' => 'OFF', 'is_active' => false]);
    Coupon::factory()->create(['code' => 'OLD', 'expires_at' => now()->subMinute()]);
    Coupon::factory()->create(['code' => 'STICKERS', 'product_id' => $other->id]);

    $check = fn (string $code) => $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => $code]));

    $check('NOPE')->assertStatus(422)->assertJsonPath('message', "That coupon code isn't valid.");
    $check('OFF')->assertStatus(422)->assertJsonPath('message', 'This coupon code has expired.');
    $check('OLD')->assertStatus(422)->assertJsonPath('message', 'This coupon code has expired.');
    $check('STICKERS')->assertStatus(422)->assertJsonPath('message', "This coupon code can't be used on All-in-One Multi Link Stand.");
});

test('a coupon expiring today still works until the end of the day', function () {
    $this->travelTo(now('Europe/London')->setTime(23, 30));
    [$owner] = couponOwner();

    $this->actingAs(couponAdmin())->post('/admin/coupons', [
        'code' => 'TODAY', 'discount_type' => 'percent', 'discount_value' => 10,
        'expires_on' => now('Europe/London')->format('Y-m-d'), 'once_per_shop' => true, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'TODAY']))->assertOk();

    $this->travel(31)->minutes();
    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'TODAY']))->assertStatus(422);
});

test('a once-per-shop coupon cannot be used twice by the same shop, but other shops can', function () {
    [$owner, $shop] = couponOwner();
    [$otherOwner] = couponOwner();
    $coupon = Coupon::factory()->create(['code' => 'ONCE']);
    paidWith($coupon, $shop);

    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'ONCE']))
        ->assertStatus(422)->assertJsonPath('message', "You've already used this coupon code.");
    $this->actingAs($otherOwner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'ONCE']))->assertOk();
});

test('a coupon stops working once its total uses are reached, counting only paid orders', function () {
    [$owner] = couponOwner();
    $coupon = Coupon::factory()->create(['code' => 'FIRST2', 'max_uses' => 2]);
    paidWith($coupon, Shop::factory()->create());
    Order::factory()->create(['coupon_id' => $coupon->id]); // abandoned checkout - not a use

    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'FIRST2']))->assertOk();

    paidWith($coupon, Shop::factory()->create());

    $this->actingAs($owner)->postJson('/dashboard/orders/coupon', stand(1, ['code' => 'FIRST2']))
        ->assertStatus(422)->assertJsonPath('message', 'This coupon code has been fully used.');
});

/* ---------- Ordering with a coupon ---------- */

test('checkout with a coupon charges the discounted total through a one-off Stripe coupon', function () {
    [$owner, $shop] = couponOwner();
    Coupon::factory()->create(['code' => 'SAVE25', 'discount_value' => 25]);

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCoupon')
            ->once()
            ->withArgs(fn (array $p) => $p['amount_off'] === 2000 && $p['currency'] === 'gbp' && $p['max_redemptions'] === 1 && $p['name'] === 'SAVE25')
            ->andReturn('stripe_coupon_1');
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $p) => $p['discounts'] === [['coupon' => 'stripe_coupon_1']]
                && $p['line_items'][0]['price_data']['unit_amount'] === 4000)
            ->andReturn(['id' => 'cs_coupon', 'url' => 'https://checkout.stripe.com/c/pay/cs_coupon']);
    });

    $this->actingAs($owner)
        ->post('/dashboard/orders', stand(2, ['coupon' => 'save25']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_coupon');

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Pending)
        ->list_total_pence->toBe(8000)
        ->total_pence->toBe(6000)
        ->coupon_code->toBe('SAVE25');
});

test('checkout without a coupon sends no discount to Stripe', function () {
    [$owner, $shop] = couponOwner();

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldNotReceive('createCoupon');
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $p) => ! array_key_exists('discounts', $p))
            ->andReturn(['id' => 'cs_plain', 'url' => 'https://checkout.stripe.com/c/pay/cs_plain']);
    });

    $this->actingAs($owner)->post('/dashboard/orders', stand())->assertRedirect('https://checkout.stripe.com/c/pay/cs_plain');

    expect($shop->orders()->sole())->coupon_id->toBeNull()->total_pence->toBe(4000);
});

test('going back to checkout without the coupon clears it from the unfinished order', function () {
    [$owner, $shop] = couponOwner();
    Coupon::factory()->create(['code' => 'SAVE10']);
    stripeAcceptsAnything();

    $this->actingAs($owner)->post('/dashboard/orders', stand(1, ['coupon' => 'SAVE10']));
    $this->actingAs($owner)->post('/dashboard/orders', stand());

    expect($shop->orders()->sole())->coupon_id->toBeNull()->coupon_code->toBeNull()->total_pence->toBe(4000);
});

test('an invalid coupon at checkout goes back with the reason and creates nothing', function () {
    [$owner, $shop] = couponOwner();
    stripeAcceptsAnything();

    $this->actingAs($owner)
        ->from('/dashboard/orders')
        ->post('/dashboard/orders', stand(1, ['coupon' => 'MADEUP']))
        ->assertRedirect('/dashboard/orders')
        ->assertSessionHasErrors(['order' => "That coupon code isn't valid."]);

    expect($shop->orders()->count())->toBe(0);
});

test('a 100% coupon places a free, paid order without going to Stripe', function () {
    [$owner, $shop] = couponOwner();
    Coupon::factory()->create(['code' => 'FREESTAND', 'discount_value' => 100]);

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldNotReceive('createCheckoutSession');
        $mock->shouldNotReceive('createCoupon');
    });

    $this->actingAs($owner)
        ->post('/dashboard/orders', stand(1, ['coupon' => 'FREESTAND']))
        ->assertRedirect('/dashboard/orders')
        ->assertSessionHas('status');

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Paid)
        ->payment_method->toBe(PaymentMethod::Free)
        ->total_pence->toBe(0)
        ->list_total_pence->toBe(4000)
        ->coupon_code->toBe('FREESTAND')
        ->and($shop->fresh()->product_ordered_at)->not->toBeNull();
});

test('a coupon leaving less than Stripe can charge is refused', function () {
    [$owner, $shop] = couponOwner();
    Coupon::factory()->fixed(3990)->create(['code' => 'ALMOST']);
    stripeAcceptsAnything();

    $this->actingAs($owner)
        ->post('/dashboard/orders', stand(1, ['coupon' => 'ALMOST']))
        ->assertSessionHasErrors('order');

    expect($shop->orders()->count())->toBe(0);
});

test('the owner sees the coupon on their order', function () {
    [$owner, $shop] = couponOwner();
    paidWith(Coupon::factory()->create(['code' => 'SHOWN']), $shop);

    $this->actingAs($owner)->get('/dashboard/orders')->assertInertia(fn ($page) => $page
        ->component('Dashboard/Orders')
        ->where('orders.0.coupon_code', 'SHOWN'));
});

/* ---------- Admin ---------- */

test('the admin can list, add and edit coupons', function () {
    $admin = couponAdmin();
    $product = Product::featured();

    $this->actingAs($admin)->post('/admin/coupons', [
        'code' => 'launch-5', 'discount_type' => 'fixed', 'discount_value' => '5.50',
        'product_id' => $product->id, 'max_uses' => 50, 'once_per_shop' => false, 'is_active' => true,
        'description' => 'Launch offer',
    ])->assertSessionHasNoErrors();

    $coupon = Coupon::sole();
    expect($coupon)
        ->code->toBe('LAUNCH-5')
        ->discount_type->toBe('fixed')
        ->discount_value->toBe(550)
        ->product_id->toBe($product->id)
        ->max_uses->toBe(50)
        ->once_per_shop->toBeFalse()
        ->expires_at->toBeNull();

    $this->actingAs($admin)->put("/admin/coupons/{$coupon->id}", [
        'code' => 'LAUNCH-5', 'discount_type' => 'percent', 'discount_value' => 20,
        'expires_on' => '2030-01-31', 'once_per_shop' => true, 'is_active' => false,
    ])->assertSessionHasNoErrors();

    expect($coupon->fresh())
        ->discount_value->toBe(20)
        ->product_id->toBeNull()
        ->max_uses->toBeNull()
        ->is_active->toBeFalse()
        ->and($coupon->fresh()->expires_at->timezone('Europe/London')->format('Y-m-d H:i'))->toBe('2030-01-31 23:59');

    $this->actingAs($admin)->get('/admin/coupons')->assertInertia(fn ($page) => $page
        ->component('Admin/Coupons/Index')
        ->where('coupons.0.code', 'LAUNCH-5')
        ->where('coupons.0.label', '20% off')
        ->where('coupons.0.uses', 0));
});

test('coupon codes must be unique, tidy and sensible', function () {
    $admin = couponAdmin();
    Coupon::factory()->create(['code' => 'TAKEN']);
    $base = ['discount_type' => 'percent', 'discount_value' => 10, 'once_per_shop' => true, 'is_active' => true];

    $this->actingAs($admin)->post('/admin/coupons', [...$base, 'code' => 'taken'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post('/admin/coupons', [...$base, 'code' => 'HAS SPACE'])->assertSessionHasErrors('code');
    $this->actingAs($admin)->post('/admin/coupons', [...$base, 'code' => 'BIG', 'discount_value' => 101])->assertSessionHasErrors('discount_value');
    $this->actingAs($admin)->post('/admin/coupons', [...$base, 'code' => 'HALF', 'discount_value' => 12.5])->assertSessionHasErrors('discount_value');
});

test('owners cannot manage coupons', function () {
    [$owner] = couponOwner();

    $this->actingAs($owner)->get('/admin/coupons')->assertForbidden();
    $this->actingAs($owner)->post('/admin/coupons', ['code' => 'MINE'])->assertForbidden();
});
