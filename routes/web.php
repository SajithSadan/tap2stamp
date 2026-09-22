<?php

use App\Http\Controllers\Dev\CustomThemeController;
use App\Http\Controllers\Dev\ThemePreviewController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Landing'));

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
