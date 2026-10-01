<?php

use App\Enums\OrderStage;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Mail\OrderDelivered;
use App\Mail\OrderDispatched;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\User;
use App\Services\StripeGateway;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;

/** An owner whose shop has an address, so it can be posted to. */
function orderingOwner(array $shopAttributes = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create([
        'user_id' => $owner->id,
        'address_line1' => '1 High Street',
        'town' => 'Leeds',
        'postcode' => 'LS1 1AA',
        ...$shopAttributes,
    ]);

    return [$owner, $shop];
}

function ordersAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/** Stripe is "configured" and Checkout returns this session. */
function fakeStripeCheckout(string $sessionId = 'cs_test_123'): void
{
    test()->mock(StripeGateway::class, function (MockInterface $mock) use ($sessionId) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')->andReturn([
            'id' => $sessionId,
            'url' => "https://checkout.stripe.com/c/pay/{$sessionId}",
        ]);
    });
}

/** The checkout form's body: the featured stand, $quantity of them. */
function standOrder(int $quantity = 1): array
{
    return ['product_id' => Product::featured()->id, 'quantity' => $quantity];
}

/** A Stripe-Signature header for $payload, signed the way Stripe does. */
function stripeSignature(string $payload, string $secret): string
{
    $time = time();

    return "t={$time},v1=".hash_hmac('sha256', "{$time}.{$payload}", $secret);
}

/* ---------- The owner's "What's next?" banner ---------- */

test('the launch product is the featured counter display', function () {
    expect(Product::featured())
        ->name->toBe('All-in-One Multi Link Stand')
        ->price_pence->toBe(4000);
});

test('an owner who has not ordered sees the counter display offer', function () {
    [$owner] = orderingOwner();

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->component('Dashboard/Overview')
        ->where('orderOffer.name', 'All-in-One Multi Link Stand')
        ->where('orderOffer.price_pence', 4000)
        ->where('orderOffer.delivery_address', '1 High Street, Leeds, LS1 1AA')
        ->where('orderOffer.can_pay_online', false)
    );
});

test('the offer is gone once the shop has ordered', function () {
    [$owner] = orderingOwner(['product_ordered_at' => now()]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('orderOffer', null));
});

test('there is no offer when no product is featured and on sale', function () {
    [$owner] = orderingOwner();
    Product::query()->update(['is_active' => false]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('orderOffer', null));
});

/* ---------- Paying online (Stripe Checkout) ---------- */

test('ordering opens Stripe Checkout for a pending order', function () {
    [$owner, $shop] = orderingOwner();
    $product = Product::featured();

    $this->mock(StripeGateway::class, function (MockInterface $mock) use ($product) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $params) => $params['mode'] === 'payment'
                && $params['line_items'][0]['price_data']['currency'] === 'gbp'
                && $params['line_items'][0]['price_data']['unit_amount'] === $product->price_pence
                && $params['line_items'][0]['quantity'] === 3
                && str_contains($params['success_url'], '{CHECKOUT_SESSION_ID}'))
            ->andReturn(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);
    });

    $this->actingAs($owner)
        ->post('/dashboard/orders', standOrder(3))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Pending)
        ->payment_method->toBe(PaymentMethod::Stripe)
        ->quantity->toBe(3)
        ->unit_price_pence->toBe(4000)
        ->total_pence->toBe(12000)
        ->stripe_session_id->toBe('cs_test_123')
        ->delivery_address->toBe('1 High Street, Leeds, LS1 1AA')
        ->and($shop->fresh()->product_ordered_at)->toBeNull();
});

test('going back to checkout again reuses the unfinished order', function () {
    [$owner, $shop] = orderingOwner();
    fakeStripeCheckout();

    $this->actingAs($owner)->post('/dashboard/orders', standOrder(1));
    $this->actingAs($owner)->post('/dashboard/orders', standOrder(2));

    expect($shop->orders()->sole()->quantity)->toBe(2);
});

test('an owner without an address is asked to add one first', function () {
    [$owner, $shop] = orderingOwner(['address_line1' => null, 'town' => null, 'postcode' => null]);
    fakeStripeCheckout();

    $this->actingAs($owner)
        ->from('/dashboard')
        ->post('/dashboard/orders', standOrder())
        ->assertRedirect('/dashboard')
        ->assertSessionHasErrors('order');

    expect($shop->orders()->count())->toBe(0);
});

test('online ordering is unavailable until Stripe is configured', function () {
    [$owner] = orderingOwner();
    config(['services.stripe.secret' => null]);

    $this->actingAs($owner)->post('/dashboard/orders', standOrder())->assertNotFound();
});

test('a shop that has already ordered can order more', function () {
    [$owner, $shop] = orderingOwner(['product_ordered_at' => now()]);
    fakeStripeCheckout();

    $this->actingAs($owner)
        ->post('/dashboard/orders', standOrder(2))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

    expect($shop->orders()->sole()->quantity)->toBe(2);
});

test('the quantity must be between 1 and the maximum', function (int $quantity) {
    [$owner, $shop] = orderingOwner();
    fakeStripeCheckout();

    $this->actingAs($owner)
        ->post('/dashboard/orders', standOrder($quantity))
        ->assertSessionHasErrors('quantity');

    expect($shop->orders()->count())->toBe(0);
})->with([0, Order::MAX_QUANTITY + 1]);

test('a product that is off sale cannot be ordered', function () {
    [$owner] = orderingOwner();
    fakeStripeCheckout();
    $product = Product::factory()->create(['is_active' => false]);

    $this->actingAs($owner)
        ->post('/dashboard/orders', ['product_id' => $product->id, 'quantity' => 1])
        ->assertSessionHasErrors('product_id');
});

test('returning from Stripe confirms the payment and hides the offer', function () {
    [$owner, $shop] = orderingOwner();
    $order = Order::factory()->create(['shop_id' => $shop->id, 'stripe_session_id' => 'cs_test_paid']);

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('retrieveCheckoutSession')->with('cs_test_paid')->andReturn([
            'id' => 'cs_test_paid',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_123',
        ]);
    });

    $this->actingAs($owner)
        ->get("/dashboard/orders/{$order->id}/success?session_id=cs_test_paid")
        ->assertRedirect('/dashboard/orders')
        ->assertSessionHas('status');

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->stripe_payment_intent->toBe('pi_123')
        ->and($shop->fresh()->product_ordered_at)->not->toBeNull();

    // fresh(): the same User instance still holds the shop loaded before paying.
    $this->actingAs($owner->fresh())->get('/dashboard')->assertInertia(fn ($page) => $page->where('orderOffer', null));
});

test('an unpaid Stripe session does not mark the order paid', function () {
    [$owner, $shop] = orderingOwner();
    $order = Order::factory()->create(['shop_id' => $shop->id, 'stripe_session_id' => 'cs_test_open']);

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('retrieveCheckoutSession')->andReturn([
            'id' => 'cs_test_open', 'payment_status' => 'unpaid', 'payment_intent' => null,
        ]);
    });

    $this->actingAs($owner)->get("/dashboard/orders/{$order->id}/success?session_id=cs_test_open");

    expect($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($shop->fresh()->product_ordered_at)->toBeNull();
});

test('an owner cannot open another shop\'s order', function () {
    [$owner] = orderingOwner();
    $otherOrder = Order::factory()->create();

    $this->actingAs($owner)->get("/dashboard/orders/{$otherOrder->id}/success")->assertNotFound();
});

/* ---------- Stripe webhook ---------- */

test('a signed checkout.session.completed webhook marks the order paid, once', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_hook']);
    $payload = json_encode([
        'id' => 'evt_1',
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_hook',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_hook',
        ]],
    ]);

    $send = fn () => $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_test'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $send()->assertOk();
    $paidAt = $order->fresh()->paid_at;
    $this->travel(5)->minutes();
    $send()->assertOk();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->stripe_payment_intent->toBe('pi_hook')
        ->paid_at->toEqual($paidAt)
        ->and($order->shop->fresh()->product_ordered_at)->not->toBeNull();
});

test('a webhook with a bad signature is rejected', function () {
    config(['services.stripe.webhook_secret' => 'whsec_test']);
    $order = Order::factory()->create(['stripe_session_id' => 'cs_test_forged']);
    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_test_forged', 'payment_status' => 'paid']],
    ]);

    $this->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, 'whsec_wrong'),
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect($order->fresh()->status)->toBe(OrderStatus::Pending);
});

/* ---------- Admin: manual orders and dispatch ---------- */

test('an admin can record a bank transfer order, which hides the owner offer', function () {
    [$owner, $shop] = orderingOwner();
    $admin = ordersAdmin();

    $this->actingAs($admin)
        ->post("/admin/shops/{$shop->id}/orders", [
            'product_id' => Product::featured()->id,
            'quantity' => 2,
            'payment_method' => 'bank_transfer',
            'paid' => true,
            'amount' => '80',
            'note' => 'Ref TADA-42',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Paid)
        ->payment_method->toBe(PaymentMethod::BankTransfer)
        ->quantity->toBe(2)
        ->total_pence->toBe(8000)
        ->user_id->toBe($admin->id)
        ->note->toBe('Ref TADA-42')
        ->and($shop->fresh()->product_ordered_at)->not->toBeNull();

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->where('orderOffer', null));
});

test('a free order is recorded at zero, whatever amount is sent', function () {
    [, $shop] = orderingOwner();

    $this->actingAs(ordersAdmin())->post("/admin/shops/{$shop->id}/orders", [
        'product_id' => Product::featured()->id,
        'quantity' => 1,
        'payment_method' => 'free',
        'paid' => true,
        'amount' => '40',
    ]);

    expect($shop->orders()->sole()->total_pence)->toBe(0);
});

test('an admin cannot record a manual order as a Stripe payment', function () {
    [, $shop] = orderingOwner();

    $this->actingAs(ordersAdmin())
        ->post("/admin/shops/{$shop->id}/orders", [
            'product_id' => Product::featured()->id,
            'quantity' => 1,
            'payment_method' => 'stripe',
            'paid' => true,
            'amount' => '40',
        ])
        ->assertSessionHasErrors('payment_method');

    expect($shop->orders()->count())->toBe(0);
});

test('owners cannot record orders', function () {
    [$owner, $shop] = orderingOwner();

    $this->actingAs($owner)
        ->post("/admin/shops/{$shop->id}/orders", [
            'product_id' => Product::featured()->id,
            'quantity' => 1,
            'payment_method' => 'cash',
            'paid' => true,
            'amount' => '40',
        ])
        ->assertForbidden();
});

test('the admin shop page shows its orders and whether it has ordered', function () {
    [, $shop] = orderingOwner();
    Order::factory()->paid()->create(['shop_id' => $shop->id]);

    $this->actingAs(ordersAdmin())
        ->get("/admin/shops/{$shop->id}/settings")
        ->assertInertia(fn ($page) => $page
            ->component('Admin/ShopSettings')
            ->has('orders', 1)
            ->where('productOrderedAt', null)
            ->has('products')
            ->has('paymentMethods', 3)
        );
});

test('the admin orders page shows the last 30 days by default, plus anything not delivered yet', function () {
    $recent = Order::factory()->paid()->create(['delivered_at' => now()]);
    $oldDone = Order::factory()->paid()->create(['delivered_at' => now()->subDays(40), 'created_at' => now()->subDays(45)]);
    $oldOpen = Order::factory()->paid()->create(['created_at' => now()->subDays(60), 'paid_at' => now()->subDays(60)]);

    $this->actingAs(ordersAdmin())
        ->get('/admin/orders')
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Index')
            ->where('days', 30)
            ->where('ranges', [7, 30, 90, 365])
            ->has('orders', 2)
            ->where('orders', fn ($orders) => collect($orders)->pluck('id')->sort()->values()->all() === collect([$recent->id, $oldOpen->id])->sort()->values()->all())
        );

    $this->actingAs(ordersAdmin())->get('/admin/orders?days=90')->assertInertia(fn ($page) => $page->where('days', 90)->has('orders', 3));
    $this->actingAs(ordersAdmin())->get('/admin/orders?days=12')->assertInertia(fn ($page) => $page->where('days', 30));
});

test('order insights total the period and compare it with the one before', function () {
    Order::factory()->paid()->create(['total_pence' => 8000, 'quantity' => 2, 'payment_method' => PaymentMethod::Stripe]);
    Order::factory()->paid()->create(['total_pence' => 4000, 'quantity' => 1, 'payment_method' => PaymentMethod::BankTransfer, 'processing_at' => now(), 'dispatched_at' => now()]);
    Order::factory()->paid()->create(['total_pence' => 4000, 'quantity' => 1, 'paid_at' => now()->subDays(40)]);
    Order::factory()->create(['total_pence' => 99999]); // unpaid - never counted

    $this->actingAs(ordersAdmin())->get('/admin/orders')->assertInertia(fn ($page) => $page
        ->where('insights.current.orders', 2)
        ->where('insights.current.collected_pence', 12000)
        ->where('insights.current.online_pence', 8000)
        ->where('insights.current.manual_pence', 4000)
        ->where('insights.current.units', 3)
        ->where('insights.previous.orders', 1)
        ->where('insights.previous.collected_pence', 4000)
        ->where('insights.to_do', 2)
        ->where('insights.on_the_way', 1)
    );
});

test('an admin moves an order through its stages, and the owner is emailed at dispatch and delivery', function () {
    Mail::fake();
    [$owner, $shop] = orderingOwner();
    $order = Order::factory()->paid()->create(['shop_id' => $shop->id]);
    $admin = ordersAdmin();

    $this->actingAs($admin)->put("/admin/orders/{$order->id}/stage", ['stage' => 'processing'])->assertRedirect();
    expect($order->fresh()->stage())->toBe(OrderStage::Processing);
    Mail::assertNothingSent();

    $this->actingAs($admin)->put("/admin/orders/{$order->id}/stage", [
        'stage' => 'dispatched',
        'courier' => 'Royal Mail',
        'tracking_number' => 'RM123456789GB',
        'tracking_url' => 'https://www.royalmail.com/track-your-item#/tracking-results/RM123456789GB',
    ]);
    expect($order->fresh())
        ->stage()->toBe(OrderStage::Dispatched)
        ->courier->toBe('Royal Mail')
        ->tracking_number->toBe('RM123456789GB');
    Mail::assertSent(OrderDispatched::class, fn ($mail) => $mail->hasTo($owner->email));

    $this->actingAs($admin)->put("/admin/orders/{$order->id}/stage", ['stage' => 'delivered']);
    expect($order->fresh())
        ->stage()->toBe(OrderStage::Delivered)
        ->tracking_number->toBe('RM123456789GB');
    Mail::assertSent(OrderDelivered::class);
});

test('jumping ahead dates the skipped stages, and stepping back clears later ones without emails', function () {
    Mail::fake();
    [, $shop] = orderingOwner();
    $order = Order::factory()->paid()->create(['shop_id' => $shop->id]);
    $admin = ordersAdmin();

    $this->actingAs($admin)->put("/admin/orders/{$order->id}/stage", ['stage' => 'dispatched', 'tracking_number' => 'X1']);
    expect($order->fresh()->processing_at)->not->toBeNull();
    Mail::assertSentCount(1);

    $this->actingAs($admin)->put("/admin/orders/{$order->id}/stage", ['stage' => 'processing']);
    expect($order->fresh())
        ->stage()->toBe(OrderStage::Processing)
        ->dispatched_at->toBeNull()
        ->tracking_number->toBeNull();
    Mail::assertSentCount(1);
});

test('an unpaid order cannot be moved along', function () {
    $order = Order::factory()->create();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}/stage", ['stage' => 'processing'])->assertStatus(422);
    expect($order->fresh()->processing_at)->toBeNull();
});

test('the tracking link must be a web address', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs(ordersAdmin())
        ->put("/admin/orders/{$order->id}/stage", ['stage' => 'dispatched', 'tracking_url' => 'javascript:alert(1)'])
        ->assertSessionHasErrors('tracking_url');
});

test('a mail problem never undoes the stage change', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
    $order = Order::factory()->paid()->create();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}/stage", ['stage' => 'dispatched'])->assertRedirect();

    expect($order->fresh()->stage())->toBe(OrderStage::Dispatched);
});

/* ---------- Owner: following orders ---------- */

test('the owner orders page lists paid orders with their progress, not abandoned checkouts', function () {
    [$owner, $shop] = orderingOwner();
    Order::factory()->paid()->create(['shop_id' => $shop->id, 'processing_at' => now(), 'quantity' => 2]);
    Order::factory()->create(['shop_id' => $shop->id]);
    Order::factory()->paid()->create();

    $this->actingAs($owner)->get('/dashboard/orders')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/Orders')
        ->has('orders', 1)
        ->where('orders.0.stage', 'processing')
        ->where('orders.0.quantity', 2)
        ->has('orders.0.steps', 4)
        ->missing('orders.0.note')
        ->has('products')
        ->where('maxQuantity', Order::MAX_QUANTITY)
    );
});

test('the overview shows an order on its way until it is delivered', function () {
    [$owner, $shop] = orderingOwner(['product_ordered_at' => now()]);
    $order = Order::factory()->paid()->create(['shop_id' => $shop->id, 'dispatched_at' => now(), 'processing_at' => now()]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('orderOffer', null)
        ->where('activeOrder.id', $order->id)
        ->where('activeOrder.stage', 'dispatched')
    );

    $order->update(['delivered_at' => now()]);

    $this->actingAs($owner->fresh())->get('/dashboard')->assertInertia(fn ($page) => $page->where('activeOrder', null));
});

test('the shops grid says which shops ordered the primary product, are awaiting payment, or not', function () {
    $primary = Product::featured();
    $sticker = Product::factory()->create(['name' => 'Table sticker']);
    $ordered = Shop::factory()->create(['name' => 'A Ordered']);
    $awaiting = Shop::factory()->create(['name' => 'B Awaiting']);
    $stickerOnly = Shop::factory()->create(['name' => 'C Sticker only', 'product_ordered_at' => now()]);
    Shop::factory()->create(['name' => 'D Nothing']);

    Order::factory()->paid()->create(['shop_id' => $ordered->id, 'product_id' => $primary->id]);
    Order::factory()->create(['shop_id' => $awaiting->id, 'product_id' => $primary->id, 'payment_method' => PaymentMethod::BankTransfer]);
    Order::factory()->paid()->create(['shop_id' => $stickerOnly->id, 'product_id' => $sticker->id]);

    $this->actingAs(ordersAdmin())->get('/admin')->assertInertia(function ($page) {
        $page->where('primaryProduct', 'All-in-One Multi Link Stand');
        $rows = collect($page->toArray()['props']['shops'])->keyBy('name');

        expect($rows['A Ordered']['product_ordered_at'])->not->toBeNull()
            ->and($rows['B Awaiting']['product_awaiting'])->toBeTrue()
            ->and($rows['C Sticker only']['product_ordered_at'])->toBeNull()
            ->and($rows['C Sticker only']['product_awaiting'])->toBeFalse()
            ->and($rows['D Nothing']['product_ordered_at'])->toBeNull();
    });
});

test('the dispatched and delivered emails render with the tracking details', function () {
    [, $shop] = orderingOwner(['name' => 'Bean There']);
    $order = Order::factory()->paid()->create([
        'shop_id' => $shop->id,
        'quantity' => 2,
        'courier' => 'Royal Mail',
        'tracking_number' => 'RM1GB',
        'tracking_url' => 'https://example.com/track/RM1GB',
    ]);

    expect((new OrderDispatched($order))->render())
        ->toContain('Bean There')
        ->toContain('2 × All-in-One Multi Link Stand')
        ->toContain('RM1GB')
        ->toContain('https://example.com/track/RM1GB');
    expect((new OrderDelivered($order))->render())->toContain('has been delivered');
});

test('the admin dashboard shows orders waiting to be posted and the latest ones', function () {
    $shop = Shop::factory()->create(['name' => 'Bean There']);
    Order::factory()->paid()->create(['shop_id' => $shop->id, 'total_pence' => 8000, 'quantity' => 2]);
    Order::factory()->paid()->create(['processing_at' => now(), 'paid_at' => now()->subHour()]);
    Order::factory()->paid()->create(['processing_at' => now(), 'dispatched_at' => now(), 'paid_at' => now()->subHours(2)]);
    Order::factory()->paid()->create(['dispatched_at' => now(), 'delivered_at' => now(), 'paid_at' => now()->subDays(10)]);
    Order::factory()->create(); // abandoned checkout - not an order

    $this->actingAs(ordersAdmin())->get('/admin/dashboard')->assertInertia(fn ($page) => $page
        ->component('Admin/Dashboard')
        ->where('orders.to_do', 2)
        ->where('orders.on_the_way', 1)
        ->where('orders.week_count', 3)
        ->has('orders.recent', 4)
        ->where('orders.recent.0.shop_name', 'Bean There')
    );
});

test('the orders page has one zero-filled sales row per day of the period', function () {
    Order::factory()->paid()->create(['total_pence' => 8000, 'paid_at' => now()]);
    Order::factory()->paid()->create(['total_pence' => 4000, 'paid_at' => now()]);
    Order::factory()->paid()->create(['total_pence' => 4000, 'paid_at' => now()->subDays(3)]);
    Order::factory()->create(['total_pence' => 9900]); // unpaid

    $this->actingAs(ordersAdmin())->get('/admin/orders?days=7')->assertInertia(fn ($page) => $page
        ->has('sales', 7)
        ->where('sales.6.date', today()->format('Y-m-d'))
        ->where('sales.6.collected', 12000)
        ->where('sales.6.orders', 2)
        ->where('sales.3.collected', 4000)
        ->where('sales.0.collected', 0)
    );
});

test('checkout charges price breaks, one Stripe line per price', function () {
    [$owner, $shop] = orderingOwner();
    $product = Product::featured();
    $product->update(['price_tiers' => [['from' => 2, 'price_pence' => 2000]]]);

    $this->mock(StripeGateway::class, function (MockInterface $mock) {
        $mock->shouldReceive('configured')->andReturn(true);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn (array $params) => count($params['line_items']) === 2
                && $params['line_items'][0]['quantity'] === 1
                && $params['line_items'][0]['price_data']['unit_amount'] === 4000
                && $params['line_items'][1]['quantity'] === 2
                && $params['line_items'][1]['price_data']['unit_amount'] === 2000)
            ->andReturn(['id' => 'cs_tiers', 'url' => 'https://checkout.stripe.com/c/pay/cs_tiers']);
    });

    $this->actingAs($owner)->post('/dashboard/orders', standOrder(3));

    expect($shop->orders()->sole()->total_pence)->toBe(8000);
});

test('pages get the price breaks so they can show totals', function () {
    [$owner] = orderingOwner();
    Product::featured()->update(['price_tiers' => [['from' => 2, 'price_pence' => 2000]]]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('orderOffer.price_tiers.0.from', 2)
        ->where('orderOffer.price_tiers.0.price_pence', 2000)
    );
    $this->actingAs($owner)->get('/dashboard/orders')->assertInertia(fn ($page) => $page
        ->where('products.0.price_tiers.0.price_pence', 2000)
    );
});

/* ---------- Orders arranged by the admin, paid by bank transfer later ---------- */

function arrangeOrder(Shop $shop, array $overrides = []): TestResponse
{
    return test()->actingAs(ordersAdmin())->post("/admin/shops/{$shop->id}/orders", [
        'product_id' => Product::featured()->id,
        'quantity' => 1,
        'payment_method' => 'bank_transfer',
        'paid' => false,
        'amount' => '30',
        'note' => 'Spring offer',
        ...$overrides,
    ]);
}

test('an admin can arrange an order at an agreed price that waits for the bank transfer', function () {
    [$owner, $shop] = orderingOwner();

    arrangeOrder($shop)->assertRedirect()->assertSessionHas('status');

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Pending)
        ->payment_method->toBe(PaymentMethod::BankTransfer)
        ->total_pence->toBe(3000)
        ->list_total_pence->toBe(4000)
        ->paid_at->toBeNull()
        ->and($shop->fresh()->product_ordered_at)->toBeNull();

    // The owner isn't offered the display again, and sees how to pay.
    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('orderOffer', null)
        ->where('activeOrder.awaiting_payment', true)
    );
    $this->actingAs($owner)->get('/dashboard/orders')->assertInertia(fn ($page) => $page
        ->has('orders', 1)
        ->where('orders.0.awaiting_payment', true)
        ->where('orders.0.reference', 'TADA-'.$shop->orders()->sole()->id)
        ->where('orders.0.list_total_pence', 4000)
    );
});

test('confirming the payment starts the order and marks the shop as ordered', function () {
    [, $shop] = orderingOwner();
    arrangeOrder($shop);
    $order = $shop->orders()->sole();

    $this->actingAs(ordersAdmin())
        ->put("/admin/orders/{$order->id}/paid", ['payment_method' => 'bank_transfer'])
        ->assertRedirect();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->stage()->toBe(OrderStage::Received)
        ->total_pence->toBe(3000)
        ->and($shop->fresh()->product_ordered_at)->not->toBeNull();
});

test('only orders waiting for a manual payment can be confirmed', function () {
    $stripeCheckout = Order::factory()->create();
    $paid = Order::factory()->paid()->create(['payment_method' => PaymentMethod::BankTransfer]);

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$stripeCheckout->id}/paid", ['payment_method' => 'bank_transfer'])->assertStatus(422);
    $this->actingAs(ordersAdmin())->put("/admin/orders/{$paid->id}/paid", ['payment_method' => 'bank_transfer'])->assertStatus(422);

    expect($stripeCheckout->fresh()->status)->toBe(OrderStatus::Pending);
});

test('an unpaid order can be cancelled, and then disappears from the owner\'s list', function () {
    [$owner, $shop] = orderingOwner();
    arrangeOrder($shop);
    $order = $shop->orders()->sole();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}/cancel")->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    $this->actingAs($owner)->get('/dashboard/orders')->assertInertia(fn ($page) => $page->has('orders', 0));
    // ...and the owner is offered the display again.
    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page->whereNot('orderOffer', null));
});

test('a paid order cannot be cancelled', function () {
    $order = Order::factory()->paid()->create();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}/cancel")->assertStatus(422);
    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('a free promotion order is paid straight away at zero, keeping the normal price', function () {
    [, $shop] = orderingOwner();

    arrangeOrder($shop, ['payment_method' => 'free', 'paid' => false, 'quantity' => 2]);

    expect($shop->orders()->sole())
        ->status->toBe(OrderStatus::Paid)
        ->total_pence->toBe(0)
        ->list_total_pence->toBe(8000);
});

test('the admin orders page has what New order needs', function () {
    Shop::factory()->create(['name' => 'Bean There']);

    $this->actingAs(ordersAdmin())->get('/admin/orders')->assertInertia(fn ($page) => $page
        ->where('shops.0.name', 'Bean There')
        ->where('products.0.name', 'All-in-One Multi Link Stand')
        ->where('bankDetailsSet', false)
    );
});

test('an admin can save bank details, which owners see on unpaid orders', function () {
    [$owner, $shop] = orderingOwner();
    arrangeOrder($shop);

    $this->actingAs(ordersAdmin())->put('/admin/settings/bank', [
        'account_name' => 'Techsa Ltd',
        'bank_name' => 'Barclays',
        'sort_code' => '123456',
        'account_number' => '12345678',
    ])->assertSessionHasNoErrors();

    $this->actingAs($owner)->get('/dashboard/orders')->assertInertia(fn ($page) => $page
        ->where('bankDetails.account_name', 'Techsa Ltd')
        ->where('bankDetails.sort_code', '12-34-56')
        ->where('bankDetails.account_number', '12345678')
    );
});

test('bank details are checked and can be removed', function () {
    $admin = ordersAdmin();

    $this->actingAs($admin)->put('/admin/settings/bank', ['account_name' => 'X', 'sort_code' => '12-34', 'account_number' => '123'])
        ->assertSessionHasErrors(['sort_code', 'account_number']);

    $this->actingAs($admin)->put('/admin/settings/bank', ['account_name' => 'X', 'sort_code' => '12-34-56', 'account_number' => '12345678']);
    $this->actingAs($admin)->put('/admin/settings/bank', ['account_name' => '', 'sort_code' => '', 'account_number' => '']);

    expect(Setting::get(Setting::BANK_DETAILS))->toBeNull();
});

/* ---------- Admin: editing an order ---------- */

test('an admin can change the quantity, price and method of an order they arranged', function () {
    [, $shop] = orderingOwner();
    arrangeOrder($shop);
    $order = $shop->orders()->sole();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}", [
        'product_id' => Product::featured()->id,
        'quantity' => 3,
        'payment_method' => 'cash',
        'amount' => '100',
        'delivery_address' => 'Back door, 1 High Street, Leeds',
        'note' => 'Bigger order agreed',
    ])->assertRedirect()->assertSessionHas('status');

    expect($order->fresh())
        ->quantity->toBe(3)
        ->total_pence->toBe(10000)
        ->list_total_pence->toBe(12000)
        ->payment_method->toBe(PaymentMethod::Cash)
        ->status->toBe(OrderStatus::Pending)
        ->delivery_address->toBe('Back door, 1 High Street, Leeds')
        ->note->toBe('Bigger order agreed');
});

test('switching an unpaid arranged order to free lets it go ahead', function () {
    [, $shop] = orderingOwner();
    arrangeOrder($shop);
    $order = $shop->orders()->sole();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}", [
        'product_id' => Product::featured()->id,
        'quantity' => 1,
        'payment_method' => 'free',
    ]);

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->total_pence->toBe(0)
        ->and($shop->fresh()->product_ordered_at)->not->toBeNull();
});

test('a Stripe order keeps what was charged - only address and note change', function () {
    $order = Order::factory()->paid()->create(['quantity' => 1, 'total_pence' => 4000, 'stripe_session_id' => 'cs_x']);

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$order->id}", [
        'quantity' => 5,
        'amount' => '1',
        'payment_method' => 'free',
        'delivery_address' => 'New address',
        'note' => 'Called to change address',
    ])->assertRedirect();

    expect($order->fresh())
        ->quantity->toBe(1)
        ->total_pence->toBe(4000)
        ->payment_method->toBe(PaymentMethod::Stripe)
        ->delivery_address->toBe('New address')
        ->note->toBe('Called to change address');
});

test('cancelled orders and unfinished Stripe checkouts cannot be edited', function () {
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled, 'payment_method' => PaymentMethod::BankTransfer]);
    $checkout = Order::factory()->create();

    $this->actingAs(ordersAdmin())->put("/admin/orders/{$cancelled->id}", ['note' => 'x'])->assertStatus(422);
    $this->actingAs(ordersAdmin())->put("/admin/orders/{$checkout->id}", ['note' => 'x'])->assertStatus(422);
});

test('an edit is checked', function () {
    [, $shop] = orderingOwner();
    arrangeOrder($shop);
    $order = $shop->orders()->sole();

    $this->actingAs(ordersAdmin())
        ->put("/admin/orders/{$order->id}", ['product_id' => Product::featured()->id, 'quantity' => 0, 'payment_method' => 'stripe', 'amount' => '-5'])
        ->assertSessionHasErrors(['quantity', 'payment_method', 'amount']);
});

test('owners cannot edit orders', function () {
    [$owner, $shop] = orderingOwner();
    $order = Order::factory()->paid()->create(['shop_id' => $shop->id]);

    $this->actingAs($owner)->put("/admin/orders/{$order->id}", ['note' => 'mine now'])->assertForbidden();
});
