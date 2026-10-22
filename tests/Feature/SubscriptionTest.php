<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\User;
use App\Services\SubscriptionCheckout;
use App\Support\LandingPage;

function admin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/** A shop with an owner, a signed-in staff device and a customer card on 2 stamps. */
function subscriptionSetup(array $shopAttributes = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'max_stamps' => 8, ...$shopAttributes]);
    StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'sub-token')]);
    $customer = Customer::factory()->create();
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 2]);

    return [$shop, $owner, $customer];
}

function endTrial(Shop $shop): Shop
{
    $shop->forceFill(['trial_ends_at' => now()->subDay(), 'subscription_ends_at' => null])->save();

    return $shop->fresh();
}

/** The landing page form as the editor sends it, with $plans (default: the saved ones). */
function plansForm(?array $plans = null): array
{
    $content = LandingPage::content();

    return [...collect($content)->except('plans')->all(), 'plans' => $plans ?? $content['plans']];
}

test('the launch plans are the landing page pricing, Starter is the default', function () {
    expect(Plan::ordered()->pluck('name')->all())->toBe(['Starter', 'Growth', 'Elite Pro'])
        ->and(Plan::defaultPlan()->name)->toBe('Starter')
        ->and(Plan::ordered()->first()->description)->toBe(LandingPage::defaults()['plans'][0]['description'])
        ->and(Plan::where('name', 'Growth')->value('badge'))->toBe('Most Popular');
});

test('a new shop starts on the default plan with its trial length', function () {
    Plan::where('is_default', true)->update(['is_default' => false]);
    $plan = Plan::create(['name' => 'Two months', 'prices' => ['INR' => '999'], 'trial_days' => 60, 'is_default' => true]);

    $shop = Shop::factory()->create();

    expect($shop->plan_id)->toBe($plan->id)
        ->and((int) round(now()->diffInDays($shop->trial_ends_at)))->toBe(60)
        ->and($shop->subscriptionStatus())->toBe(Shop::SUBSCRIPTION_TRIAL);
});

test('subscription status: trial, expired, active', function () {
    $shop = Shop::factory()->create();
    expect($shop->subscriptionStatus())->toBe('trial');

    $shop = endTrial($shop);
    expect($shop->subscriptionStatus())->toBe('expired');

    $shop->forceFill(['subscription_ends_at' => now()->addYear()])->save();
    expect($shop->fresh()->subscriptionStatus())->toBe('active');

    $shop->forceFill(['subscription_ends_at' => now()->subDay()])->save();
    expect($shop->fresh()->subscriptionStatus())->toBe('expired');
});

test('paying during the free trial starts the yearly plan when the trial ends', function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $shop = Shop::factory()->create(['trial_ends_at' => now()->setDate(2026, 11, 15)->endOfDay()]);

    $shop->startPaidYear();
    $shop->refresh();

    expect($shop->subscription_ends_at->timezone('Europe/London')->format('Y-m-d'))->toBe('2027-11-15')
        ->and($shop->trial_ends_at->format('Y-m-d'))->toBe('2026-11-15')
        ->and($shop->subscriptionStatus())->toBe('active')
        ->and($shop->subscriptionSummary()['trial_running'])->toBeTrue();
});

test('renewing before the paid year ends adds on, upgrading after it ended starts today', function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $shop = Shop::factory()->create(['trial_ends_at' => now()->subMonths(2)]);

    $shop->forceFill(['subscription_ends_at' => now()->addMonth()])->save();
    $shop->startPaidYear();
    expect($shop->fresh()->subscription_ends_at->timezone('Europe/London')->format('Y-m-d'))->toBe('2027-11-20');

    $shop->forceFill(['subscription_ends_at' => now()->subMonth()])->save();
    $shop->startPaidYear();
    expect($shop->fresh()->subscription_ends_at->timezone('Europe/London')->format('Y-m-d'))->toBe('2027-10-20');
});

test('the admin edits the plans on the landing page: saved plans keep their id and shops', function () {
    $this->actingAs(admin())->get('/admin/landing-page')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/LandingPage')
        ->has('content.plans', 3)
        ->where('content.plans.0.is_default', true));

    $growth = Plan::where('name', 'Growth')->first();
    $shop = Shop::factory()->create(['plan_id' => $growth->id]);
    [$starter, , $elite] = LandingPage::content()['plans'];
    $new = [...$starter, 'id' => null, 'name' => 'Pro', 'is_default' => false, 'features' => ['Unlimited scans', '', 'Menu']];
    $plans = LandingPage::content()['plans'];
    $plans[1]['name'] = 'Growth Plus';
    $plans[2]['is_active'] = false;

    $this->put('/admin/landing-page', plansForm([$plans[0], $new, $plans[1], $plans[2]]))->assertSessionHasNoErrors();

    expect(Plan::ordered()->pluck('name')->all())->toBe(['Starter', 'Pro', 'Growth Plus', 'Elite Pro'])
        ->and($growth->fresh()->name)->toBe('Growth Plus')
        ->and($shop->fresh()->plan_id)->toBe($growth->id)
        ->and(Plan::where('name', 'Pro')->first()->features)->toBe(['Unlimited scans', 'Menu'])
        ->and(Plan::find($elite['id'])->is_active)->toBeFalse();

    // Not offered: off the landing page.
    auth()->logout();
    $this->get('/')->assertInertia(fn ($page) => $page->has('plans', 3)->where('plans.2.name', 'Growth Plus'));
});

test('a plan left out is deleted and its shops move to the default', function () {
    $growth = Plan::where('name', 'Growth')->first();
    $shop = Shop::factory()->create(['plan_id' => $growth->id]);
    $plans = collect(LandingPage::content()['plans'])->reject(fn ($p) => $p['id'] === $growth->id)->values()->all();

    $this->actingAs(admin())->put('/admin/landing-page', plansForm($plans))->assertSessionHasNoErrors();

    expect(Plan::find($growth->id))->toBeNull()
        ->and($shop->fresh()->plan_id)->toBe(Plan::defaultPlan()->id);
});

test('exactly one default plan, offered, with its free trial', function () {
    $this->actingAs(admin());
    $plans = LandingPage::content()['plans'];

    $both = $plans;
    $both[1]['is_default'] = true;
    $this->put('/admin/landing-page', plansForm($both))->assertSessionHasErrors('plans');

    $none = $plans;
    $none[0]['is_default'] = false;
    $this->put('/admin/landing-page', plansForm($none))->assertSessionHasErrors('plans');

    $hidden = $plans;
    $hidden[0]['is_active'] = false;
    $this->put('/admin/landing-page', plansForm($hidden))->assertSessionHasErrors('plans.0.is_active');

    $long = $plans;
    $long[0]['trial_days'] = 400;
    $this->put('/admin/landing-page', plansForm($long))->assertSessionHasErrors('plans.0.trial_days');

    $this->put('/admin/landing-page', plansForm([...$plans, ['id' => 999999, ...collect($plans[0])->except('id')->all(), 'is_default' => false]]))
        ->assertSessionHasErrors('plans.3.id');

    // Moving the default: the other is switched off, and its trial is what new shops get.
    $moved = $plans;
    $moved[0]['is_default'] = false;
    $moved[1] = [...$moved[1], 'is_default' => true, 'trial_days' => 45];
    $this->put('/admin/landing-page', plansForm($moved))->assertSessionHasNoErrors();

    expect(Plan::where('is_default', true)->pluck('name')->all())->toBe(['Growth'])
        ->and(Plan::defaultTrialDays())->toBe(45);
});

test('owners and staff cannot manage plans', function () {
    [, $owner] = subscriptionSetup();

    $this->actingAs($owner)->get('/admin/landing-page')->assertForbidden();
    $this->put('/admin/landing-page', plansForm())->assertForbidden();
    $shop = $owner->shop;
    $this->put("/admin/shops/{$shop->id}/scanning", ['paused' => true])->assertForbidden();
    expect($shop->fresh()->scanningPaused())->toBeFalse();
});

test('an ended trial never stops scanning by itself; the admin pauses and resumes one shop by hand', function () {
    [$shop, , $customer] = subscriptionSetup();
    $other = Shop::factory()->create();
    endTrial($shop);
    endTrial($other);
    $auth = ['Authorization' => 'Bearer sub-token'];
    $payload = "TOKEN:{$customer->uuid}|SHOP:{$shop->id}";

    $this->postJson('/api/staff/scan', ['payload' => $payload], $auth)->assertOk()->assertJsonPath('code', 'stamp_added');

    $this->actingAs(admin())->put("/admin/shops/{$shop->id}/scanning", ['paused' => true])->assertSessionHasNoErrors();
    expect($shop->fresh()->scanningPaused())->toBeTrue()
        ->and($other->fresh()->scanningPaused())->toBeFalse();

    $this->travel(9)->hours();
    $this->postJson('/api/staff/scan', ['payload' => $payload], $auth)
        ->assertForbidden()->assertJsonPath('code', 'scanning_paused');
    $this->getJson('/api/staff/me', $auth)->assertOk()->assertJsonPath('scan_paused', true);

    expect(CustomerShopCard::where('shop_id', $shop->id)->value('current_stamps'))->toBe(3);

    // Resumed: stamps again.
    $this->put("/admin/shops/{$shop->id}/scanning", ['paused' => false]);
    $this->postJson('/api/staff/scan', ['payload' => $payload], $auth)->assertOk()->assertJsonPath('code', 'stamp_added');
});

test('a paused shop that pays online scans again', function () {
    [$shop] = subscriptionSetup();
    $shop->forceFill(['scanning_paused_at' => now()])->save();
    $payment = $shop->subscriptionPayments()->create([
        'plan_id' => $shop->plan_id, 'plan_name' => 'Starter', 'provider' => 'razorpay', 'currency' => 'INR',
        'amount' => 299900, 'status' => 'pending', 'provider_order_id' => 'order_P',
    ]);

    app(SubscriptionCheckout::class)->markPaid($payment, 'pay_P');

    expect($shop->fresh()->scanningPaused())->toBeFalse();
});

test('the owner sees the paused state and their scanner is refused', function () {
    [$shop, $owner, $customer] = subscriptionSetup();
    endTrial($shop);
    $shop->forceFill(['scanning_paused_at' => now()])->save();

    $this->actingAs($owner)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
        ->where('shop.subscription.status', 'expired')
        ->where('shop.subscription.paused', true)
        ->where('shop.subscription.plan', 'Starter'));
    $this->postJson('/dashboard/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"])
        ->assertForbidden()->assertJsonPath('code', 'scanning_paused');

    // Customers still see their card.
    $this->getJson("/s/{$shop->slug}/card/{$customer->uuid}")->assertOk()->assertJsonPath('stamps', 2);
});

test("the owner's Plan page lists offered plans in the shop's currency", function () {
    [$shop, $owner] = subscriptionSetup(['country' => 'IN']);
    Plan::where('name', 'Elite Pro')->update(['is_active' => false]);

    $this->actingAs($owner)->get('/dashboard/plan')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/Plan')
        ->has('plans', 2)
        ->where('plans.0.name', 'Starter')
        ->where('plans.0.price', '₹2,999')
        ->where('plans.0.current', true)
        ->where('plans.1.current', false));

    // No GBP price on the launch plans: a UK owner is asked to get in touch.
    $shop->forceFill(['country' => 'GB'])->save();
    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page->where('plans.0.price', null));
});

test('on the free trial every plan says Upgrade; once it ended unpaid, every plan says Pay now', function () {
    [$shop, $owner] = subscriptionSetup(['country' => 'IN']);

    $this->actingAs($owner)->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('plans.0.action', 'upgrade')
        ->where('plans.1.action', 'upgrade')
        ->where('plans.2.action', 'upgrade')
        ->where('renewFrom', null));

    endTrial($shop);
    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('plans.0.action', 'pay')
        ->where('plans.1.action', 'pay')
        ->where('plans.2.action', 'pay'));
});

test('paid: their plan is locked until 30 days before it ends, dearer plans upgrade, cheaper ones switch', function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    [$shop, $owner] = subscriptionSetup(['country' => 'IN']);
    $growth = Plan::where('name', 'Growth')->first();
    $shop->forceFill(['plan_id' => $growth->id, 'trial_ends_at' => now()->subMonth(), 'subscription_ends_at' => now()->addDays(200)])->save();

    $this->actingAs($owner)->get('/dashboard/plan')->assertInertia(fn ($page) => $page
        ->where('plans.0.name', 'Starter')->where('plans.0.action', 'switch')
        ->where('plans.1.name', 'Growth')->where('plans.1.action', 'locked')
        ->where('plans.2.action', 'upgrade')
        ->where('renewFrom', now()->addDays(170)->timezone('Europe/London')->format('j M Y')));

    // Paying for it early is refused by the server too.
    config(['services.razorpay.key_id' => 'rzp_test', 'services.razorpay.key_secret' => 'secret']);
    $this->postJson('/dashboard/plan/pay', ['plan_id' => $growth->id])->assertStatus(422);

    // 30 days before the end (and after it has ended): Renew.
    $shop->forceFill(['subscription_ends_at' => now()->addDays(30)])->save();
    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page->where('plans.1.action', 'renew'));
    $shop->forceFill(['subscription_ends_at' => now()->subDay()])->save();
    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page->where('plans.1.action', 'renew')->where('plans.2.action', 'upgrade'));

    // No price to compare in the shop's currency: switch.
    $shop->forceFill(['country' => 'GB'])->save();
    $this->actingAs($owner->refresh())->get('/dashboard/plan')->assertInertia(fn ($page) => $page->where('plans.2.action', 'switch'));
});

test('the admin previews the pricing page for any country', function () {
    Plan::where('name', 'Growth')->update(['prices' => ['INR' => '4999', 'GBP' => '59']]);
    Plan::where('name', 'Elite Pro')->update(['is_active' => false]);
    $this->actingAs(admin());

    // Default: India, rupees only.
    $this->get('/admin/plans/preview')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Plans/Preview')
        ->where('country', 'IN')
        ->where('currency', 'INR')
        ->has('plans', 2)
        ->where('plans.0.price', '₹2,999')
        ->where('plans.1.price', '₹4,999'));

    // UK: pounds where set, "ask us" where not; unknown countries fall back to India.
    $this->get('/admin/plans/preview?country=gb')->assertInertia(fn ($page) => $page
        ->where('country', 'GB')
        ->where('currency', 'GBP')
        ->where('plans.0.price', null)
        ->where('plans.1.price', '£59'));
    $this->get('/admin/plans/preview?country=ZZ')->assertInertia(fn ($page) => $page->where('country', 'IN'));
});

test('owners cannot open the pricing preview', function () {
    [, $owner] = subscriptionSetup();

    $this->actingAs($owner)->get('/admin/plans/preview')->assertForbidden();
});

test('the admin sets the plan and paid year', function () {
    [$shop, , $customer] = subscriptionSetup();
    endTrial($shop);
    $growth = Plan::where('name', 'Growth')->first();

    $this->actingAs(admin())->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->where('subscription.status', 'expired')
        ->where('pipeline.plan_id', $shop->plan_id)
        ->has('plans', 3));

    $this->put("/admin/shops/{$shop->id}/account", [
        'crm_stage' => 'paid',
        'trial_ends_at' => $shop->trial_ends_at->format('Y-m-d'),
        'plan_id' => $growth->id,
        'subscription_ends_at' => now()->addYear()->format('Y-m-d'),
    ])->assertSessionHasNoErrors();

    $shop->refresh();
    expect($shop->plan_id)->toBe($growth->id)
        ->and($shop->subscriptionStatus())->toBe('active')
        ->and($shop->scanningPaused())->toBeFalse();

    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], ['Authorization' => 'Bearer sub-token'])
        ->assertOk()->assertJsonPath('code', 'stamp_added');
});

test('the shops grid sends each shop\'s trial days left and whether it ever paid (trial-end filter)', function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
    $today = Shop::factory()->create(['name' => 'Ends Today', 'trial_ends_at' => now()->endOfDay()]);
    $paid = Shop::factory()->create(['name' => 'Paid', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => now()->addYear()]);
    $ended = Shop::factory()->create(['name' => 'Ended', 'trial_ends_at' => now()->subDays(5)]);

    $this->actingAs(admin())->get('/admin')->assertInertia(function ($page) use ($today, $paid, $ended) {
        $rows = collect($page->toArray()['props']['shops'])->keyBy('id');
        expect($rows[$today->id]['trial_days_left'])->toBe(0)
            ->and($rows[$today->id]['subscription']['paid'])->toBeFalse()
            ->and($rows[$paid->id]['subscription']['paid'])->toBeTrue()
            ->and($rows[$ended->id]['trial_days_left'])->toBe(-5);
    });
});

test('the shops grid shows each shop\'s plan and status', function () {
    [$shop] = subscriptionSetup();
    endTrial($shop);

    $this->actingAs(admin())->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.plan', 'Starter')
        ->where('shops.0.subscription.status', 'expired')
        ->where('shops.0.subscription.paused', false));
});
