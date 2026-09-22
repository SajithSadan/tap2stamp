<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Dev\CustomThemeController;
use App\Http\Controllers\Dev\ThemePreviewController;
use App\Http\Controllers\MyCardsController;
use App\Http\Controllers\ReviewController;
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

// Runs `php artisan migrate` over HTTP for hosting plans without SSH access.
// Must work in every environment (it's for production), so it's protected
// by a bearer token (DEPLOY_MIGRATE_TOKEN) instead of an environment gate.
Route::post('/deploy/migrate', [DeployController::class, 'migrate'])
    ->middleware('throttle:5,1')
    ->name('deploy.migrate');

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
