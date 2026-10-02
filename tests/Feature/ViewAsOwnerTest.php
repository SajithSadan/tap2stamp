<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;

function viewAsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada Admin']);
}

/** @return array{0: User, 1: Shop} */
function viewedShop(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'name' => 'Olive Owner']);
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Bean There', 'reward_title' => 'Free coffee']);

    return [$owner, $shop];
}

test('an admin can view a shop\'s dashboard exactly as its owner sees it', function () {
    [$owner, $shop] = viewedShop();
    $admin = viewAsAdmin();

    $this->actingAs($admin)
        ->post("/admin/shops/{$shop->id}/view-as-owner")
        ->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard/Overview')
        ->where('shop.name', 'Bean There')
        ->where('auth.user.role', 'owner')
        ->where('auth.user.name', 'Olive Owner')
        ->where('viewAs.shop_name', 'Bean There')
        ->where('viewAs.owner_name', 'Olive Owner')
        ->where('viewAs.admin_name', 'Ada Admin'));

    // Every owner screen, e.g. Settings and Customers.
    $this->get('/dashboard/settings')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard/Settings'));
    $this->get('/dashboard/customers')->assertOk()->assertInertia(fn ($page) => $page->component('Dashboard/Customers'));
});

test('viewing as the owner starts read-only', function () {
    [, $shop] = viewedShop();

    $this->actingAs(viewAsAdmin())->post("/admin/shops/{$shop->id}/view-as-owner");

    $this->from('/dashboard/settings')
        ->put('/dashboard/settings', ['name' => 'Hijacked', 'max_stamps' => 5, 'reward_title' => 'x'])
        ->assertRedirect('/dashboard/settings')
        ->assertSessionHasErrors('view_as');
    $this->postJson('/dashboard/orders/coupon', ['code' => 'X', 'product_id' => 1, 'quantity' => 1])->assertForbidden();
    $this->post('/dashboard/staff-members', ['name' => 'Eve', 'pin' => '1234'])->assertSessionHasErrors('view_as');

    expect($shop->fresh()->name)->toBe('Bean There')
        ->and($shop->staffMembers()->count())->toBe(0);
});

test('the admin stays the admin everywhere else while viewing', function () {
    [, $shop] = viewedShop();

    $this->actingAs(viewAsAdmin())->post("/admin/shops/{$shop->id}/view-as-owner");

    $this->get('/admin')->assertOk()->assertInertia(fn ($page) => $page
        ->where('auth.user.role', 'admin')
        ->where('viewAs', null));
    // The admin panel still saves normally.
    $this->put("/admin/shops/{$shop->id}/settings", [])->assertSessionHasErrors();
});

test('back to admin ends the view and returns to the shop', function () {
    [, $shop] = viewedShop();
    $admin = viewAsAdmin();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->post('/admin/view-as-owner/stop')->assertRedirect("/admin/shops/{$shop->id}/settings");

    // An admin has no dashboard of their own.
    $this->get('/dashboard')->assertForbidden();
});

test('shops without an owner account cannot be viewed as one', function () {
    $shop = Shop::factory()->create(['user_id' => null]);

    $this->actingAs(viewAsAdmin())
        ->from('/admin')
        ->post("/admin/shops/{$shop->id}/view-as-owner")
        ->assertRedirect('/admin');

    $this->get('/dashboard')->assertForbidden();
});

test('owners cannot view other shops as their owner', function () {
    [$owner] = viewedShop();
    $otherShop = Shop::factory()->create(['name' => 'Someone Else']);

    $this->actingAs($owner)->post("/admin/shops/{$otherShop->id}/view-as-owner")->assertForbidden();

    // Even with the session flag forced in, an owner only ever sees their own shop.
    $this->withSession(['view_as_shop_id' => $otherShop->id])
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page->where('shop.name', 'Bean There')->where('auth.user.name', 'Olive Owner')->where('viewAs', null));
});

/* ---------- Allow changes ---------- */

test('with changes allowed, the admin can set up staff and devices for the owner', function () {
    [, $shop] = viewedShop();
    $admin = viewAsAdmin();
    $logged = [];
    Log::listen(function (MessageLogged $event) use (&$logged) {
        if ($event->message === 'Admin changed a shop as its owner') {
            $logged[] = $event->context;
        }
    });

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true])->assertRedirect();

    $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('viewAs.editing', true));

    $this->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '1234'])->assertSessionHasNoErrors();
    $this->post('/dashboard/staff-devices', ['name' => 'Till iPad'])->assertSessionHasNoErrors()->assertSessionHas('staffToken');
    $this->put('/dashboard/settings', ['name' => 'Bean There Too', 'max_stamps' => 8, 'reward_title' => 'Free coffee'])->assertSessionHasNoErrors();

    expect($shop->staffMembers()->pluck('name')->all())->toBe(['Sam'])
        ->and($shop->staffDevices()->pluck('name')->all())->toBe(['Till iPad'])
        ->and($shop->fresh()->name)->toBe('Bean There Too');

    // Each change is logged with the admin who made it.
    expect($logged)->toHaveCount(3)
        ->and($logged[0])->toBe(['admin_id' => $admin->id, 'shop_id' => $shop->id, 'action' => 'POST dashboard/staff-members']);
});

test('even with changes allowed, the admin cannot pay for orders or give stamps as the owner', function () {
    [, $shop] = viewedShop();

    $this->actingAs(viewAsAdmin())->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->post('/dashboard/orders', ['product_id' => 1, 'quantity' => 1])->assertSessionHasErrors('view_as');
    $this->postJson('/dashboard/scan', ['payload' => 'TOKEN:x|SHOP:1'])->assertForbidden();

    expect($shop->orders()->count())->toBe(0);
});

test('switching back to read-only, or viewing again, blocks changes again', function () {
    [, $shop] = viewedShop();

    $this->actingAs(viewAsAdmin())->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->put('/admin/view-as-owner/editing', ['editing' => true]);
    $this->put('/admin/view-as-owner/editing', ['editing' => false]);
    $this->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '1234'])->assertSessionHasErrors('view_as');

    $this->put('/admin/view-as-owner/editing', ['editing' => true]);
    $this->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '1234'])->assertSessionHasErrors('view_as');

    expect($shop->staffMembers()->count())->toBe(0);
});

test('changes can only be switched on while viewing a shop, and only by an admin', function () {
    [$owner] = viewedShop();

    $this->actingAs(viewAsAdmin())->put('/admin/view-as-owner/editing', ['editing' => true])->assertNotFound();
    $this->actingAs($owner)->put('/admin/view-as-owner/editing', ['editing' => true])->assertForbidden();
});
