<?php

use App\Http\Controllers\Admin\ShopOwnerController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Dev\CustomThemeController;
use App\Http\Controllers\Dev\ThemePreviewController;
use App\Http\Controllers\MyCardsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDeviceController;
use App\Http\Controllers\StaffSetupController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing'));

// Customer loyalty card: the page shell renders via Inertia, but which
// uuid (if any) is known only lives in the browser's localStorage, so the
// register/card-state endpoints are plain JSON, called client-side after
// the page has already loaded.
Route::get('/s/{shop:slug}', [CardController::class, 'show'])->name('card.show');
Route::post('/s/{shop:slug}/register', [CardController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('card.register');
// withoutScopedBindings(): a customer isn't a direct relation of a shop
// (there's no Shop::customers()) - the shop/customer link is the explicit
// CustomerShopCard lookup CardController::cardState() already does, not
// something Eloquent's automatic nested-binding scoping should guess at.
Route::get('/s/{shop:slug}/card/{customer:uuid}', [CardController::class, 'cardState'])
    ->name('card.state')
    ->withoutScopedBindings();

// In-app rating/review (never posted to Google or shown publicly - saved
// to our own DB, visible only to the shop owner once Stage 4 exists).
// Requires an existing card, same nested-binding caveat as card.state.
Route::post('/s/{shop:slug}/card/{customer:uuid}/review', [ReviewController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('card.review.store')
    ->withoutScopedBindings();

// Cross-shop view: the mobile bottom nav's "My Cards" tab. Same uuid-in-
// localStorage identity as the card routes above - no shop in the URL
// since this aggregates every shop the customer has a card at.
Route::get('/my-cards', fn () => Inertia::render('MyCards'))->name('my-cards');
Route::get('/my-cards/{customer:uuid}', [MyCardsController::class, 'index'])->name('my-cards.index');

// Auth: owners and the one admin share the same users table + login form,
// role decides where store() redirects to (see CLAUDE.md "Admin panel").
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Admin: onboards new shops + their owner login (no self-service
// registration - see CLAUDE.md "Admin panel"). No shop param anywhere in
// dashboard routes below, so there's no ID to scope wrong.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [ShopOwnerController::class, 'index'])->name('index');
    Route::get('/shops/create', [ShopOwnerController::class, 'create'])->name('shops.create');
    Route::post('/shops', [ShopOwnerController::class, 'store'])->name('shops.store');
});

// Owner dashboard: always "my shop" (auth()->user()->shop), never a shop
// param in the URL - structurally impossible for one owner to view another's
// data through this route, not just policy-enforced.
Route::middleware(['auth', 'role:owner'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
    Route::post('/staff-devices', [StaffDeviceController::class, 'store'])->name('staff-devices.store');
    Route::delete('/staff-devices/{staffDevice}', [StaffDeviceController::class, 'destroy'])->name('staff-devices.destroy');
});

// Staff device onboarding: this route IS the login step (no session/user
// exists yet), so it validates the raw token from the URL directly, not
// via AuthenticateStaffDevice (that's for the already-onboarded API below).
Route::get('/staff/setup/{token}', [StaffSetupController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('staff.setup');

// Scanner shell: client-side checks localStorage for a saved token before
// calling the API below - no server-side auth needed for the page itself.
Route::get('/staff', fn () => Inertia::render('Staff/Scanner'))->name('staff.scanner');

// Staff scanner API: bearer-token authenticated (AuthenticateStaffDevice),
// not session/CSRF based - see the CSRF-exempt list in bootstrap/app.php.
Route::middleware('staff.auth')->prefix('api/staff')->name('staff.')->group(function () {
    Route::get('/me', [StaffController::class, 'me'])->name('me');
    Route::post('/scan', [StaffController::class, 'scan'])->middleware('throttle:60,1')->name('scan');
    Route::get('/summary', [StaffController::class, 'summary'])->name('summary');
});

// Runs `php artisan migrate` / bootstraps the admin account over HTTP for
// hosting plans without SSH access. Must work in every environment (it's for
// production), so both are protected by a bearer token (DEPLOY_MIGRATE_TOKEN)
// instead of an environment gate.
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])
    ->middleware('throttle:5,1')
    ->name('deploy.migrate');
Route::post('/deploy/seed-admin', [DeployController::class, 'seedAdmin'])
    ->middleware('throttle:5,1')
    ->name('deploy.seed-admin');

// Dev-only internal tooling: browse the 50 built-in themes and save custom
// variants (colors/fonts) to the database. Not part of the customer-facing
// app; gated to local + testing so it never ships to production routing,
// but is still reachable by the Pest feature tests below (which run under
// APP_ENV=testing per phpunit.xml).
if (app()->environment('local', 'testing')) {
    Route::get('/dev/themes', [ThemePreviewController::class, 'index'])->name('dev.themes.index');
    Route::get('/dev/themes/{slug}', [ThemePreviewController::class, 'show'])->name('dev.themes.show');

    Route::post('/dev/custom-themes', [CustomThemeController::class, 'store'])->name('dev.custom-themes.store');
    Route::get('/dev/custom-themes/{customTheme:slug}', [CustomThemeController::class, 'show'])->name('dev.custom-themes.show');
    Route::put('/dev/custom-themes/{customTheme:slug}', [CustomThemeController::class, 'update'])->name('dev.custom-themes.update');
    Route::delete('/dev/custom-themes/{customTheme:slug}', [CustomThemeController::class, 'destroy'])->name('dev.custom-themes.destroy');
}
