<?php

use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Collection;

function usersAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada Admin']);
}

/** The Users page's rows, keyed by email. */
function userRows(User $admin): Collection
{
    return collect(test()->actingAs($admin)->get('/admin/users')->assertOk()->viewData('page')['props']['users'])->keyBy('email');
}

test('the admin sees every user, including owners who never got a shop', function () {
    $admin = usersAdmin();
    $live = User::factory()->create(['role' => UserRole::Owner, 'email' => 'live@example.test']);
    $shop = Shop::factory()->create(['user_id' => $live->id, 'name' => 'Bean There']);
    User::factory()->create([
        'role' => UserRole::Owner,
        'email' => 'stuck@example.test',
        'google_id' => 'g-123',
        'password' => null,
        // Step 1 saved, the final step never created the shop.
        'onboarding_draft' => ['name' => 'Crumbs Bakery', 'contact_name' => 'Cat', 'contact_phone_code' => '44', 'contact_phone' => '7700 900123', 'town' => 'Leeds', 'postcode' => 'LS1 1AA'],
    ]);
    User::factory()->create(['role' => UserRole::Owner, 'email' => 'new@example.test']);

    $this->actingAs($admin)->get('/admin/users')->assertInertia(fn ($page) => $page->component('Admin/Users')->has('users', 4));
    $rows = userRows($admin);

    expect($rows['live@example.test'])->toMatchArray(['setup' => 'live', 'shop' => ['id' => $shop->id, 'name' => 'Bean There'], 'draft' => null])
        ->and($rows['stuck@example.test'])->toMatchArray([
            'setup' => 'business',
            'shop' => null,
            'via_google' => true,
            'has_password' => false,
            'draft' => [
                'business_name' => 'Crumbs Bakery',
                'contact_name' => 'Cat',
                'phone' => '+44 7700 900123',
                'phone_tel' => '+447700900123',
                'town' => 'Leeds',
                'state' => null,
                'postcode' => 'LS1 1AA',
            ],
        ])
        ->and($rows['new@example.test'])->toMatchArray(['setup' => 'not_started', 'draft' => null])
        ->and($rows[$admin->email])->toMatchArray(['role' => 'admin', 'setup' => null]);
});

test('the users list never sends passwords, tokens or Google ids', function () {
    User::factory()->create(['role' => UserRole::Owner, 'google_id' => 'g-secret']);

    $row = userRows(usersAdmin())->first();

    expect($row)->not->toHaveKeys(['password', 'remember_token', 'google_id', 'onboarding_draft']);
});

test('Users is in the admin menu', function () {
    $this->actingAs(usersAdmin())->get('/admin/users')->assertInertia(fn ($page) => $page
        ->where('navigation.main', fn ($items) => collect($items)->contains(fn ($item) => $item['label'] === 'Users' && $item['active'])));
});

test('only admins can see the users list', function () {
    $this->get('/admin/users')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))->get('/admin/users')->assertForbidden();
});
