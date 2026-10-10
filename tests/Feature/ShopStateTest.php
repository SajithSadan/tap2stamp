<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use App\Support\Countries;

/** Shop setup for a cafe in Kochi; override any field. */
function indiaSetup(array $overrides = []): array
{
    return [
        'name' => 'Kochi Cafe', 'max_stamps' => 8, 'reward_title' => 'Free chai',
        'country' => 'IN', 'contact_name' => 'Anu', 'contact_email' => 'anu@kochi.cafe',
        'contact_phone' => '98765 43210', 'address_line1' => '12 MG Road', 'town' => 'Kochi',
        'state' => 'Kerala', 'postcode' => '682016', 'delivery_same' => true,
        ...$overrides,
    ];
}

function stateOwner(): User
{
    return User::factory()->create(['role' => UserRole::Owner]);
}

test('the country list carries India\'s states, and none for the UK', function () {
    $india = collect(Countries::options())->firstWhere('code', 'IN');
    $uk = collect(Countries::options())->firstWhere('code', 'GB');

    expect($india['states'])->toHaveCount(36)->toContain('Kerala', 'Delhi', 'Tamil Nadu')
        ->and($uk['states'])->toBeNull();
});

test('an Indian shop must choose its state from the list', function () {
    $owner = stateOwner();

    $this->actingAs($owner)->post('/onboarding/business', indiaSetup(['state' => '']))
        ->assertSessionHasErrors(['state' => 'Choose the state.']);
    $this->post('/onboarding/business', indiaSetup(['state' => 'Kerela']))
        ->assertSessionHasErrors(['state' => 'Choose the state from the list.']);
    $this->post('/onboarding/business', indiaSetup())->assertSessionHasNoErrors();
});

test('the state is saved with the shop and shown in its address', function () {
    $owner = stateOwner();

    $this->actingAs($owner)->post('/onboarding', indiaSetup())->assertRedirect('/dashboard');

    $shop = Shop::sole();
    expect($shop->state)->toBe('Kerala')
        ->and($shop->deliveryAddress())->toBe('12 MG Road, Kochi, Kerala, 682016');
});

test('a UK shop is never asked for a state, and any sent is dropped', function () {
    $owner = stateOwner();

    $this->actingAs($owner)->post('/onboarding', indiaSetup([
        'country' => 'GB', 'contact_phone' => '07700 900123', 'postcode' => 'LS1 4AP', 'town' => 'Leeds', 'state' => 'Kerala',
    ]))->assertRedirect('/dashboard');

    expect(Shop::sole()->state)->toBeNull();
});

test('the owner sets the state in Settings, and it is required for India', function () {
    $owner = stateOwner();
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'country' => 'IN']);
    $contact = collect(indiaSetup())->except(['name', 'max_stamps', 'reward_title'])->all();

    $this->actingAs($owner)->put('/dashboard/settings/contact', [...$contact, 'state' => ''])->assertSessionHasErrors('state');
    $this->put('/dashboard/settings/contact', [...$contact, 'state' => 'Tamil Nadu'])->assertSessionHasNoErrors();

    expect($shop->fresh()->state)->toBe('Tamil Nadu');
    $this->get('/dashboard/settings')->assertInertia(fn ($page) => $page->where('contact.state', 'Tamil Nadu'));
});

test('moving a shop out of India drops its state', function () {
    $owner = stateOwner();
    $shop = Shop::factory()->create(['user_id' => $owner->id, 'country' => 'IN', 'state' => 'Kerala']);
    $contact = collect(indiaSetup())->except(['name', 'max_stamps', 'reward_title'])->all();

    $this->actingAs($owner)->put('/dashboard/settings/contact', [
        ...$contact, 'country' => 'US', 'contact_phone' => '+1 212 555 0147', 'postcode' => '',
    ])->assertSessionHasNoErrors();

    expect($shop->fresh()->state)->toBeNull();
});

test('the admin can set an Indian shop\'s state, from the list only', function () {
    $shop = Shop::factory()->create(['country' => 'IN', 'name' => 'Kochi Cafe', 'max_stamps' => 8, 'reward_title' => 'Free chai']);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $base = ['name' => 'Kochi Cafe', 'max_stamps' => 8, 'reward_title' => 'Free chai', 'country' => 'IN'];

    $this->actingAs($admin)->put("/admin/shops/{$shop->id}/settings", [...$base, 'state' => 'Narnia'])->assertSessionHasErrors('state');
    $this->put("/admin/shops/{$shop->id}/settings", [...$base, 'state' => 'Goa'])->assertSessionHasNoErrors();

    expect($shop->fresh()->state)->toBe('Goa');
    $this->get('/admin')->assertInertia(fn ($page) => $page
        ->where('shops.0.state', 'Goa')
        ->where('shops.0.country', 'India'));
});
