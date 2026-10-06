<?php

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\User;

beforeEach(function () {
    // Never the real storage/logs/laravel.log.
    $this->logFile = tempnam(sys_get_temp_dir(), 'log');
    config(['logging.channels.single.path' => $this->logFile]);
});

afterEach(function () {
    @unlink($this->logFile);
});

function activityAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

/** @return array{0: User, 1: Shop} */
function activityOwner(): array
{
    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@cafe.test']);

    return [$owner, Shop::factory()->create(['user_id' => $owner->id, 'name' => 'Bean There'])];
}

// --- Access ---------------------------------------------------------------

test('only an admin can open the activity log and the laravel log', function () {
    [$owner] = activityOwner();

    foreach (['/admin/activity', '/admin/logs', '/admin/logs/lines', '/admin/logs/download'] as $url) {
        $this->actingAs($owner)->get($url)->assertForbidden();
    }
    $this->actingAs($owner)->post('/admin/logs/clear')->assertForbidden();
    auth()->logout();
    $this->get('/admin/activity')->assertRedirect('/login');
});

// --- What gets recorded ---------------------------------------------------

test('a staff stamp records who stamped whom, never the phone', function () {
    $shop = Shop::factory()->create(['max_stamps' => 6]);
    $device = StaffDevice::factory()->withStaff()->create(['shop_id' => $shop->id, 'token_hash' => hash('sha256', 'token')]);
    $customer = Customer::factory()->create(['name' => 'Priya', 'phone' => '+447911123456']);
    CustomerShopCard::factory()->create(['customer_id' => $customer->id, 'shop_id' => $shop->id, 'current_stamps' => 2]);

    $this->postJson('/api/staff/scan', ['payload' => "TOKEN:{$customer->uuid}|SHOP:{$shop->id}"], ['Authorization' => 'Bearer token'])->assertOk();

    $log = ActivityLog::where('action', 'stamp.added')->sole();
    expect($log->actor_type)->toBe('staff')
        ->and($log->staff_member_id)->toBe($device->staff_member_id)
        ->and($log->actor_name)->toBe($device->staffMember->name)
        ->and($log->shop_id)->toBe($shop->id)
        ->and($log->description)->toBe("Stamped Priya's card (3/6)")
        ->and($log->changes)->toBe(['stamps' => [2, 3]])
        ->and(json_encode($log->toArray()))->not->toContain('7911123456');
});

test('an owner change keeps its before and after', function () {
    [$owner, $shop] = activityOwner();

    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'warm-artisan']);

    $log = ActivityLog::where('action', 'shop.updated')->sole();
    expect($log->actor_type)->toBe('owner')
        ->and($log->actor_name)->toBe('owner@cafe.test')
        ->and($log->shop_id)->toBe($shop->id)
        ->and($log->changes)->toBe(['theme' => [null, 'warm-artisan']]);
});

test('secrets are logged as changed, never their values', function () {
    [$owner, $shop] = activityOwner();
    $this->actingAs($owner)->post('/dashboard/staff-members', ['name' => 'Sam', 'pin' => '4321']);
    $sam = StaffMember::where('name', 'Sam')->sole();

    $this->actingAs($owner)->put("/dashboard/staff-members/{$sam->id}/pin", ['pin' => '9876']);

    $added = ActivityLog::where('action', 'staff_member.created')->sole();
    $reset = ActivityLog::where('action', 'staff_member.updated')->sole();
    expect($added->description)->toBe('Added staff member Sam')
        ->and($added->changes['pin_hash'])->toBe([null, 'set'])
        ->and($reset->changes)->toBe(['pin_hash' => ['•••', 'changed']])
        ->and(ActivityLog::all()->toJson())->not->toContain('$2y$');
});

test('an admin changing a shop as its owner is recorded as the admin', function () {
    [, $shop] = activityOwner();
    $admin = activityAdmin();
    $this->actingAs($admin)->post("/admin/shops/{$shop->id}/view-as-owner");
    $this->actingAs($admin)->put('/admin/view-as-owner/editing', ['editing' => true]);

    $this->actingAs($admin)->put('/dashboard/theme', ['theme' => 'warm-artisan']);

    $log = ActivityLog::where('action', 'shop.updated')->sole();
    expect($log->actor_type)->toBe('admin')
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->as_owner)->toBeTrue()
        ->and($log->shop_id)->toBe($shop->id);
    expect(ActivityLog::where('action', 'view_as.started')->exists())->toBeTrue();
});

test('customers are never recorded', function () {
    $shop = Shop::factory()->create();

    $this->postJson("/s/{$shop->slug}/register", ['name' => 'Jamie', 'phone' => '07911123456'])->assertSuccessful();

    expect(ActivityLog::count())->toBe(0);
});

test('a menu save records which items were added, removed and changed', function () {
    [$owner] = activityOwner();
    $menu = fn (string $price, string $extra) => ['sections' => [['name' => 'Drinks', 'items' => [
        ['name' => 'Latte', 'description' => null, 'price' => $price, 'tags' => []],
        ['name' => $extra, 'description' => null, 'price' => '£2', 'tags' => []],
    ]]]];

    $this->actingAs($owner)->put('/dashboard/menu', $menu('£3.00', 'Tea'));
    $this->actingAs($owner)->put('/dashboard/menu', $menu('£3.20', 'Mocha'));

    $log = ActivityLog::where('action', 'menu.saved')->latest('id')->first();
    expect($log->changes)->toMatchArray([
        'items added' => [null, ['Mocha']],
        'items removed' => [['Tea'], null],
        'Latte · price' => ['£3.00', '£3.20'],
    ]);
});

test('sign-ins are recorded for admins and owners', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'boss@tada.test', 'password' => 'secret-pass']);

    $this->post('/login', ['email' => 'boss@tada.test', 'password' => 'secret-pass']);

    $log = ActivityLog::where('action', 'auth.signed_in')->sole();
    expect($log->user_id)->toBe($admin->id)->and($log->actor_type)->toBe('admin');
});

// --- The page -------------------------------------------------------------

test('the activity page lists the log in the grid, filtered by period, role and shop', function () {
    [$owner, $shop] = activityOwner();
    $admin = activityAdmin();
    $this->actingAs($owner)->put('/dashboard/theme', ['theme' => 'warm-artisan']);
    $this->actingAs($admin)->put('/admin/settings/sidebar', ['bg' => '#112233', 'text' => null]);
    (new ActivityLog)->forceFill(['actor_type' => 'owner', 'actor_name' => 'old', 'action' => 'shop.updated', 'description' => 'Long ago', 'created_at' => now()->subDays(40)])->save();

    $this->actingAs($admin)->get('/admin/activity')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Activity')
            ->has('rows', 2)
            ->where('filters.days', 7)
            ->where('rows.0.actor_type', 'admin'));

    $this->actingAs($admin)->get('/admin/activity?role=owner')
        ->assertInertia(fn ($page) => $page->has('rows', 1)->where('rows.0.shop.name', 'Bean There')->where('rows.0.changes.theme', [null, 'warm-artisan']));

    $this->actingAs($admin)->get("/admin/activity?shop={$shop->id}&days=90")
        ->assertInertia(fn ($page) => $page->has('rows', 1));

    $this->actingAs($admin)->get('/admin/activity?days=90')
        ->assertInertia(fn ($page) => $page->has('rows', 3));

    $this->actingAs($admin)->get('/admin/activity?role=customer')->assertSessionHasErrors('role');
});

// --- Laravel log ----------------------------------------------------------

test('the log page shows the last lines of the laravel log', function () {
    $lines = collect(range(1, 300))->map(fn ($i) => "[2026-10-06 10:00:00] local.INFO: line {$i}");
    file_put_contents($this->logFile, $lines->implode("\n")."\n");
    $admin = activityAdmin();

    $this->actingAs($admin)->get('/admin/logs')->assertInertia(fn ($page) => $page->component('Admin/Logs'));

    $this->actingAs($admin)->getJson('/admin/logs/lines?lines=100')
        ->assertOk()
        ->assertJsonCount(100, 'lines')
        ->assertJsonPath('lines.0', '[2026-10-06 10:00:00] local.INFO: line 201')
        ->assertJsonPath('lines.99', '[2026-10-06 10:00:00] local.INFO: line 300')
        ->assertJsonPath('exists', true);

    $this->actingAs($admin)->getJson('/admin/logs/lines?lines=999999')->assertUnprocessable();
});

test('a missing log is reported, not an error', function () {
    unlink($this->logFile);

    $this->actingAs(activityAdmin())->getJson('/admin/logs/lines')
        ->assertOk()
        ->assertJson(['lines' => [], 'exists' => false]);
});

test('the admin can download and clear the log, and the clearing is recorded', function () {
    file_put_contents($this->logFile, "[2026-10-06 10:00:00] local.ERROR: boom\n");
    $admin = activityAdmin();

    $this->actingAs($admin)->get('/admin/logs/download')->assertOk()->assertDownload();

    $this->actingAs($admin)->postJson('/admin/logs/clear')->assertOk();

    expect(file_get_contents($this->logFile))->not->toContain('boom')
        ->and(ActivityLog::where('action', 'logs.cleared')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('activity and logs are in the admin sidebar but not the phone tab bar', function () {
    $this->actingAs(activityAdmin())->get('/admin/activity')
        ->assertInertia(fn ($page) => $page->where('navigation.main', fn ($items) => collect($items)->whereIn('label', ['Activity', 'Logs'])->every(fn ($i) => $i['mobile'] === false)
            && collect($items)->where('label', 'Activity')->first()['active']));
});
