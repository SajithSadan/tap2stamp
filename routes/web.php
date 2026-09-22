<?php

use App\Http\Controllers\Dev\ThemePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

// Temporary, dev-only theme comparison. Removed once a theme is chosen.
if (app()->environment('local')) {
    Route::get('/dev/themes', [ThemePreviewController::class, 'index']);
    Route::get('/dev/themes/{slug}', [ThemePreviewController::class, 'show']);
}
