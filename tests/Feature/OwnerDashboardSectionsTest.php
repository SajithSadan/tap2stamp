<?php

use App\Enums\ActionType;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Review;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function ownerWithShop(array $shopAttributes = []): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $shop = Shop::factory()->create(['user_id' => $owner->id, ...$shopAttributes]);

    return [$owner, $shop];
}

test('every dashboard section needs an owner login', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/dashboard/customers', '/dashboard/activity', '/dashboard/reviews', '/dashboard/staff', '/dashboard/settings']);

test('each dashboard section renders its own page', function (string $path, string $component) {
    [$owner] = ownerWithShop();

    $this->actingAs($owner)->get($path)->assertOk()->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/dashboard', 'Dashboard/Overview'],
    ['/dashboard/customers', 'Dashboard/Customers'],
    ['/dashboard/activity', 'Dashboard/Activity'],
    ['/dashboard/reviews', 'Dashboard/Reviews'],
    ['/dashboard/staff', 'Dashboard/Staff'],
    ['/dashboard/settings', 'Dashboard/Settings'],
]);

test('the overview has 14 zero-filled days of stamps and redemptions', function () {
    [$owner, $shop] = ownerWithShop();
    StampLog::factory()->count(3)->create(['shop_id' => $shop->id, 'created_at' => now()]);
    StampLog::factory()->create(['shop_id' => $shop->id, 'action_type' => ActionType::RewardRedeemed, 'created_at' => now()->subDay()]);
    StampLog::factory()->create(['shop_id' => $shop->id, 'created_at' => now()->subDays(20)]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->has('chart', 14)
            ->where('chart.13.stamps', 3)
            ->where('chart.12.redeemed', 1)
            ->where('chart.0.stamps', 0)
            ->where('stats.stamps_today', 3)
            ->has('recentActivity', 5)
        );
});

test('the overview reports rewards waiting and the average rating', function () {
    [$owner, $shop] = ownerWithShop(['max_stamps' => 6]);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'current_stamps' => 6]);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'current_stamps' => 2]);
    Review::factory()->create(['shop_id' => $shop->id, 'rating' => 5]);
    Review::factory()->create(['shop_id' => $shop->id, 'rating' => 4]);

    $this->actingAs($owner)->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('stats.customer_count', 2)
            ->where('stats.rewards_ready', 1)
            ->where('stats.average_rating', 4.5)
            ->where('stats.review_count', 2)
        );
});

test('activity shows which staff member gave the stamp', function () {
    [$owner, $shop] = ownerWithShop();
    $sam = StaffMember::factory()->for($shop)->create(['name' => 'Sam']);
    StampLog::factory()->create(['shop_id' => $shop->id, 'staff_member_id' => $sam->id]);

    $this->actingAs($owner)->get('/dashboard/activity')
        ->assertInertia(fn ($page) => $page->where('activity.data.0.staff_name', 'Sam'));
});

test('customers are searchable by name, only from this shop, without phone numbers', function () {
    [$owner, $shop] = ownerWithShop();
    $jamie = Customer::factory()->create(['name' => 'Jamie Smith']);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id, 'customer_id' => $jamie->id]);
    CustomerShopCard::factory()->create(['shop_id' => $shop->id]);
    CustomerShopCard::factory()->create(['customer_id' => Customer::factory()->create(['name' => 'Jamie Other'])->id]);

    $this->actingAs($owner)->get('/dashboard/customers?q=jamie')
        ->assertInertia(fn ($page) => $page
            ->where('search', 'jamie')
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Jamie Smith')
            ->missing('customers.data.0.phone')
        );
});

test('reviews show a 5-to-1 star breakdown of only this shop\'s reviews', function () {
    [$owner, $shop] = ownerWithShop();
    Review::factory()->create(['shop_id' => $shop->id, 'rating' => 5]);
    Review::factory()->create(['shop_id' => $shop->id, 'rating' => 3]);
    Review::factory()->create(['rating' => 1]);

    $this->actingAs($owner)->get('/dashboard/reviews')
        ->assertInertia(fn ($page) => $page
            ->where('summary.count', 2)
            ->where('summary.distribution.0', ['stars' => 5, 'count' => 1])
            ->where('summary.distribution.2', ['stars' => 3, 'count' => 1])
            ->where('summary.distribution.4', ['stars' => 1, 'count' => 0])
            ->has('reviews.data', 2)
        );
});

test('an owner can add a staff member, and only the PIN hash is stored', function () {
    [$owner, $shop] = ownerWithShop();

    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '4321'])
        ->assertRedirect('/dashboard/staff');

    $sam = $shop->staffMembers()->sole();
    expect($sam->name)->toBe('Sam');
    expect($sam->pin_hash)->not->toBe('4321');
    expect(Hash::check('4321', $sam->pin_hash))->toBeTrue();
});

test('staff names are unique per shop and PINs must be 4 to 6 digits', function () {
    [$owner, $shop] = ownerWithShop();
    StaffMember::factory()->for($shop)->create(['name' => 'Sam']);
    StaffMember::factory()->create(['name' => 'Alex']); // another shop - fine to reuse

    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '4321'])->assertSessionHasErrors('name');
    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Alex', 'pin' => '12'])->assertSessionHasErrors('pin');
    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Alex', 'pin' => '12ab'])->assertSessionHasErrors('pin');
    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Alex', 'pin' => '123456'])->assertSessionHasNoErrors();
});

test('an owner can reset a staff PIN', function () {
    [$owner, $shop] = ownerWithShop();
    $sam = StaffMember::factory()->for($shop)->withPin('1111')->create();

    $this->actingAs($owner)->put("/dashboard/staff-members/{$sam->id}/pin", ['pin' => '2222'])->assertRedirect('/dashboard/staff');

    expect(Hash::check('2222', $sam->fresh()->pin_hash))->toBeTrue();
});

test('removing a staff member deactivates them and signs them out of devices', function () {
    [$owner, $shop] = ownerWithShop();
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id]);
    $sam = $device->fresh()->staffMember;

    $this->actingAs($owner)->delete("/dashboard/staff-members/{$sam->id}")->assertRedirect('/dashboard/staff');

    expect($sam->fresh()->deactivated_at)->not->toBeNull();
    expect($device->fresh()->staff_member_id)->toBeNull();
    $this->actingAs($owner)->get('/dashboard/staff')->assertInertia(fn ($page) => $page->has('staffMembers', 0));
});

test('an owner cannot manage another shop\'s staff', function () {
    [$owner] = ownerWithShop();
    $outsider = StaffMember::factory()->create();

    $this->actingAs($owner)->put("/dashboard/staff-members/{$outsider->id}/pin", ['pin' => '9999'])->assertForbidden();
    $this->actingAs($owner)->delete("/dashboard/staff-members/{$outsider->id}")->assertForbidden();
    expect($outsider->fresh()->deactivated_at)->toBeNull();
});

test('the staff page shows who is signed in on which device', function () {
    [$owner, $shop] = ownerWithShop();
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'name' => 'Counter iPad']);
    $name = $device->fresh()->staffMember->name;

    $this->actingAs($owner)->get('/dashboard/staff')
        ->assertInertia(fn ($page) => $page
            ->where('staffMembers.0.signed_in_on', 'Counter iPad')
            ->where('staffDevices.0.signed_in', $name)
        );
});
