<?php

use App\Http\Controllers\Api\PosCustomerController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Outward-facing endpoints (served under /api, stateless - no session, no
// CSRF). In-app routes stay in web.php.

// `php artisan migrate` from the browser after a deploy (no SSH on Hostinger).
// No sign-in on purpose: a pending migration can break the admin login itself.
Route::get('/deploy/migrate', [DeployController::class, 'browserMigrate'])
    ->middleware('throttle:5,1')
    ->name('api.deploy.migrate');

// Clear the cached config / routes / views and PHP's OPcache after a deploy, so
// the server runs the PHP just uploaded. Open like migrate; never the data cache.
Route::get('/deploy/clear-cache', [DeployController::class, 'clearCache'])
    ->middleware('throttle:5,1')
    ->name('api.deploy.clear-cache');

// Meta's WhatsApp webhook: delivery / read ticks, customers' replies, template
// approvals. GET is Meta's verify handshake; POST is trusted only with a valid
// X-Hub-Signature-256 (our Meta app secret).
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.webhook.verify');
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])->name('api.whatsapp.webhook');

// Stripe's payment confirmations: counter display orders and plan payments
// (both Stripe Checkout). Trusted only with a valid Stripe-Signature
// (STRIPE_WEBHOOK_SECRET).
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.stripe.webhook');

// The billing desktop app (POS): onboard a restaurant, sign in, then a bearer
// token (`pos.auth`) for its details and password. Send `Accept: application/json`.
Route::prefix('pos')->name('api.pos.')->group(function () {
    Route::post('/register', [PosCustomerController::class, 'register'])->middleware('throttle:pos-register')->name('register');
    Route::post('/login', [PosCustomerController::class, 'login'])->middleware('throttle:pos-login')->name('login');

    Route::middleware('pos.auth')->group(function () {
        Route::put('/password', [PosCustomerController::class, 'changePassword'])->middleware('throttle:pos-password')->name('password');

        Route::middleware('throttle:pos')->group(function () {
            Route::get('/me', [PosCustomerController::class, 'show'])->name('me');
            Route::put('/me', [PosCustomerController::class, 'update'])->name('me.update');
            Route::post('/logout', [PosCustomerController::class, 'logout'])->name('logout');
        });
    });
});

// Razorpay's plan payment confirmations: trusted only with a valid
// X-Razorpay-Signature (RAZORPAY_WEBHOOK_SECRET).
Route::post('/razorpay/webhook', RazorpayWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.razorpay.webhook');
