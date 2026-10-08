<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Facades\DB;

function featureAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/** @return array{0: User, 1: Shop} */
function featureOwner(array $shop = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, ...$shop])];
}

/** The owner's menu labels. */
function ownerMenu(User $owner): array
{
    $items = test()->actingAs($owner)->get('/dashboard')->viewData('page')['props']['navigation']['main'];

    return array_column($items, 'label');
}

function giveMenu(Shop $shop): void
{
    $shop->menuSections()->create(['name' => 'Drinks', 'position' => 0])->items()->create(['name' => 'Latte', 'position' => 0]);
}

function giveCampaign(Shop $shop): void
{
    DB::table('marketing_campaigns')->insert([
        'shop_id' => $shop->id, 'audience' => 'all', 'message' => 'Hello', 'recipients_count' => 1,
        'status' => 'done', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('with nothing set, every shop keeps Menu and WhatsApp (adding the switches changed nothing)', function () {
    [$owner] = featureOwner();

    expect(ownerMenu($owner))->toContain('Menu', 'WhatsApp');
    $this->actingAs($owner)->get('/dashboard/menu')->assertOk();
    $this->get('/dashboard/marketing')->assertOk();
});

test('a feature that is off is gone from the owner menu, its pages, its actions and the public menu', function () {
    [$owner, $shop] = featureOwner();
    giveMenu($shop);
    Features::setDefault(Features::MENU, false);
    Features::setDefault(Features::WHATSAPP, false);

    expect(ownerMenu($owner))->not->toContain('Menu')->not->toContain('WhatsApp');

    $this->actingAs($owner)->get('/dashboard/menu')->assertNotFound();
    $this->put('/dashboard/menu', ['sections' => []])->assertNotFound();
    $this->postJson('/dashboard/menu/images/upload', ['item' => 1])->assertNotFound();
    $this->get('/dashboard/marketing')->assertNotFound();
    $this->post('/dashboard/marketing', ['message' => 'Hi'])->assertNotFound();

    // Customers too.
    $this->get($shop->menuUrl())->assertNotFound();
    expect($shop->menuSections()->count())->toBe(1); // nothing deleted - switching back on brings it back
});

test('the admin switches a shop on even when the default is off, and back to the default', function () {
    [$owner, $shop] = featureOwner();
    giveMenu($shop);
    Features::setDefault(Features::MENU, false);
    $admin = featureAdmin();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/features", ['features' => ['menu' => 'on']])->assertSessionHasNoErrors();

    expect($shop->fresh()->features)->toBe(['menu' => true])
        ->and(ownerMenu($owner))->toContain('Menu');
    $this->actingAs($owner)->get('/dashboard/menu')->assertOk();
    $this->get($shop->fresh()->menuUrl())->assertOk();

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/features", ['features' => ['menu' => 'default']]);

    expect($shop->fresh()->features)->toBeNull();
    // fresh(): the test's $owner still holds the shop it loaded before (a real request loads it anew).
    $this->actingAs($owner->fresh())->get('/dashboard/menu')->assertNotFound();
});

test('the admin switches a shop off even when the default is on', function () {
    [$owner, $shop] = featureOwner();

    $this->actingAs(featureAdmin())->put("/admin/shops/{$shop->id}/features", ['features' => ['whatsapp' => 'off']]);

    expect(ownerMenu($owner))->toContain('Menu')->not->toContain('WhatsApp');
    $this->actingAs($owner)->get('/dashboard/marketing')->assertNotFound();
});

test('switching a default off keeps it on for shops already using it', function () {
    [, $withMenu] = featureOwner();
    giveMenu($withMenu);
    [, $withCampaign] = featureOwner();
    giveCampaign($withCampaign);
    [, $chosenOff] = featureOwner(['features' => ['menu' => false]]);
    giveMenu($chosenOff);
    [, $unused] = featureOwner();

    $this->actingAs(featureAdmin())
        ->put('/admin/settings/features', ['feature' => 'menu', 'enabled' => false])
        ->assertSessionHas('status', 'Menu is now off by default. Kept on for the 1 shop already using it.');
    $this->put('/admin/settings/features', ['feature' => 'whatsapp', 'enabled' => false]);

    expect(Features::default(Features::MENU))->toBeFalse()
        ->and($withMenu->fresh()->hasFeature(Features::MENU))->toBeTrue()
        ->and($withCampaign->fresh()->hasFeature(Features::WHATSAPP))->toBeTrue()
        ->and($chosenOff->fresh()->hasFeature(Features::MENU))->toBeFalse() // the admin's own choice stands
        ->and($unused->fresh()->hasFeature(Features::MENU))->toBeFalse()
        ->and($unused->fresh()->hasFeature(Features::WHATSAPP))->toBeFalse();
});

test('admin settings show each default and how many shops differ', function () {
    featureOwner(['features' => ['menu' => true]]);
    featureOwner(['features' => ['menu' => false, 'whatsapp' => false]]);
    Features::setDefault(Features::WHATSAPP, false);

    $this->actingAs(featureAdmin())->get('/admin/settings')->assertInertia(fn ($page) => $page
        ->where('features.0.key', 'menu')
        ->where('features.0.default', true)
        ->where('features.0.forced_on', 1)
        ->where('features.0.forced_off', 1)
        ->where('features.1.key', 'whatsapp')
        ->where('features.1.default', false)
        ->where('features.1.forced_off', 1));
});

test('the shop settings page shows each feature with this shop\'s override', function () {
    [, $shop] = featureOwner(['features' => ['whatsapp' => true]]);
    Features::setDefault(Features::WHATSAPP, false);

    $this->actingAs(featureAdmin())->get("/admin/shops/{$shop->id}/settings")->assertInertia(fn ($page) => $page
        ->where('features.0', ['key' => 'menu', ...Features::ALL['menu'], 'default' => true, 'override' => null])
        ->where('features.1.default', false)
        ->where('features.1.override', true));
});

test('the admin can still prepare a menu while it is off, and is told so', function () {
    [, $shop] = featureOwner();
    Features::setDefault(Features::MENU, false);

    $this->actingAs(featureAdmin())->get("/admin/shops/{$shop->id}/menu")->assertOk()->assertInertia(fn ($page) => $page
        ->component('Admin/Menu/Edit')
        ->where('menuOff', true));
});

test('viewing as the owner shows what the owner gets', function () {
    [, $shop] = featureOwner();
    Features::setDefault(Features::MENU, false);
    $admin = featureAdmin();

    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->get('/dashboard/menu')->assertNotFound();
});

test('feature settings are validated and admin-only', function () {
    [$owner, $shop] = featureOwner();
    $admin = featureAdmin();

    $this->actingAs($admin)->put('/admin/settings/features', ['feature' => 'teleport', 'enabled' => true])->assertSessionHasErrors('feature');
    $this->put("/admin/shops/{$shop->id}/features", ['features' => ['menu' => 'maybe']])->assertSessionHasErrors('features.menu');

    // Unknown feature keys are ignored, not stored.
    $this->put("/admin/shops/{$shop->id}/features", ['features' => ['teleport' => 'on']]);
    expect($shop->fresh()->features)->toBeNull();

    $this->actingAs($owner)->put('/admin/settings/features', ['feature' => 'menu', 'enabled' => false])->assertForbidden();
    $this->actingAs($owner)->put("/admin/shops/{$shop->id}/features", ['features' => ['menu' => 'off']])->assertForbidden();
});
