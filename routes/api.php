<?php

use App\Http\Controllers\DeployController;
use Illuminate\Support\Facades\Route;

// Outward-facing endpoints (served under /api, stateless - no session, no
// CSRF). In-app routes stay in web.php.

// `php artisan migrate` from the browser after a deploy (no SSH on Hostinger).
// No sign-in on purpose: a pending migration can break the admin login itself.
Route::get('/deploy/migrate', [DeployController::class, 'browserMigrate'])
    ->middleware('throttle:5,1')
    ->name('api.deploy.migrate');
