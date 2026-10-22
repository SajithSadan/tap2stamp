<?php

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

// Meta's WhatsApp webhook: delivery / read ticks, customers' replies, template
// approvals. GET is Meta's verify handshake; POST is trusted only with a valid
// X-Hub-Signature-256 (our Meta app secret).
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.webhook.verify');
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'receive'])->name('api.whatsapp.webhook');

// Stripe's payment confirmations: counter display orders (Checkout) and plan
// payments (Payment Element). Trusted only with a valid Stripe-Signature
// (STRIPE_WEBHOOK_SECRET).
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.stripe.webhook');

// Razorpay's plan payment confirmations: trusted only with a valid
// X-Razorpay-Signature (RAZORPAY_WEBHOOK_SECRET).
Route::post('/razorpay/webhook', RazorpayWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('api.razorpay.webhook');
