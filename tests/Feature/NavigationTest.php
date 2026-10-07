<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureNavigationAccess;
use App\Models\Shop;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** A user of the given role, ready to open their area (owners need a shop). */
function navUser(UserRole $role): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($role === UserRole::Owner) {
        // Card link allowed, so the full owner menu (incl. "Customer page") shows.
        Shop::factory()->create(['user_id' => $user->id, 'show_card_link' => true]);
    }

    return $user;
}

test('every menu page opens for exactly the roles the registry lists', function () {
    $pages = collect(Navigation::items())->reject(fn ($item) => $item['external'] ?? false);

    foreach (UserRole::cases() as $role) {
        $user = navUser($role);

        foreach ($pages as $item) {
            $response = $this->actingAs($user)->get(route($item['route']));
            $allowed = in_array($role, $item['roles'], true);

            expect($response->status())->toBe(
                $allowed ? 200 : 403,
                "{$role->value} opening {$item['route']}",
            );
        }
    }
});

test('each role\'s menu holds only its own items', function (UserRole $role) {
    $user = navUser($role);
    $home = $role === UserRole::Admin ? '/admin' : '/dashboard';

    // Items with an href closure may hide themselves (e.g. Orders vs QR codes by the shop's country).
    $expected = collect(Navigation::items())
        ->filter(fn ($item) => in_array($role, $item['roles'], true))
        ->filter(fn ($item) => ! isset($item['href']) || ($item['href'])($user) !== null)
        ->pluck('label')
        ->all();

    $this->actingAs($user)->get($home)->assertInertia(fn ($page) => $page
        ->where('navigation', fn ($nav) => collect($nav['main'])->concat($nav['footer'])->pluck('label')->all() === $expected)
    );
})->with([UserRole::Admin, UserRole::Owner]);

test('the current page is marked active, including its sub-pages', function () {
    $admin = navUser(UserRole::Admin);

    $active = fn (string $url) => collect(
        $this->actingAs($admin)->get($url)->viewData('page')['props']['navigation']['main']
    )->where('active', true)->pluck('label')->all();

    expect($active('/admin'))->toBe(['Shops'])
        ->and($active('/admin/qr-codes'))->toBe(['QR codes'])
        ->and($active('/admin/settings'))->toBe(['Settings']);
});

test('the owner footer links to their own customer page in a new tab, when the admin allows it', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'slug' => 'corner-bakery', 'show_card_link' => true]);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('navigation.footer.0.label', 'Customer page')
        ->where('navigation.footer.0.href', route('card.show', 'corner-bakery'))
        ->where('navigation.footer.0.external', true)
    );
});

test('nav.access itself blocks a role the registry does not list for that page', function () {
    $owner = navUser(UserRole::Owner);
    $request = Request::create('/admin/qr-codes');
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('admin.qr-codes.index'));
    $request->setUserResolver(fn () => $owner);

    $passed = false;

    try {
        (new EnsureNavigationAccess)->handle($request, function () use (&$passed) {
            $passed = true;

            return response('ok');
        });
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect($passed)->toBeFalse();
});

test('nav.access lets non-menu routes through to their own guards', function () {
    $request = Request::create('/admin/qr-codes/print', 'POST');
    $request->setRouteResolver(fn () => Route::getRoutes()->getByName('admin.qr-codes.print'));
    $request->setUserResolver(fn () => null);

    $response = (new EnsureNavigationAccess)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('guests get no menu at all', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page->where('navigation', null));
});

test('the registry only uses real routes and known roles', function () {
    foreach (Navigation::items() as $item) {
        expect(Route::has($item['route']))->toBeTrue("{$item['route']} is not a route")
            ->and($item['roles'])->not->toBeEmpty()
            ->each->toBeInstanceOf(UserRole::class);
    }
});

test('by default the owner gets no customer page link', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);
    Shop::factory()->create(['user_id' => $owner->id, 'slug' => 'corner-bakery']);

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('navigation.footer', [])
        ->where('shop.slug', null)
        ->where('shop.show_card_link', false)
    );
});
