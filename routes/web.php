<?php

use App\Http\Controllers\AddressLookupController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\QrCodeController;
use App\Http\Controllers\Admin\QrDesignController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShopOwnerController;
use App\Http\Controllers\Admin\ShopSettingsController;
use App\Http\Controllers\Admin\ViewAsOwnerController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\Dev\CustomThemeController;
use App\Http\Controllers\Dev\ThemePreviewController;
use App\Http\Controllers\MyCardsController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OwnerScanController;
use App\Http\Controllers\QrRedirectController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ShopBannerController;
use App\Http\Controllers\ShopLogoController;
use App\Http\Controllers\ShopOnboardingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffDeviceController;
use App\Http\Controllers\StaffMemberController;
use App\Http\Controllers\StaffSetupController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// No public landing page: the marketing site (tadatap.co.uk) is the front door.
// Guests go to log in; signed-in users to their own home. Named `home` so the
// guest middleware also sends signed-in visitors of /login here.
Route::get('/', fn () => auth()->check()
    ? redirect(auth()->user()->homeUrl())
    : redirect()->route('login'))->name('home');

// Customer loyalty card: the page shell renders via Inertia, but which
// uuid (if any) is known only lives in the browser's localStorage, so the
// register/card-state endpoints are plain JSON, called client-side after
// the page has already loaded.
Route::get('/s/{shop:slug}', [CardController::class, 'show'])->name('card.show');
Route::post('/s/{shop:slug}/register', [CardController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('card.register');
Route::post('/s/{shop:slug}/card/{customer:uuid}/join', [CardController::class, 'registerExisting'])
    ->middleware('throttle:10,1')
    ->name('card.join')
    ->withoutScopedBindings();
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

    // Self-service owner sign-up (the landing page's "Start Free"). Always
    // creates an owner - admins are never self-registered.
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:5,1');

    // "Continue with Google" - sign-up and log-in in one flow (Socialite).
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('auth.google.callback');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// "Find address" on the shop address forms: our server calls findaddress.io
// so its API key never reaches the browser. Signed-in only, and throttled
// per user - every uncached lookup uses a paid credit.
Route::get('/address-lookup', AddressLookupController::class)
    ->middleware(['auth', 'throttle:20,1'])
    ->name('address-lookup');

// Admin: onboards new shops + their owner login (no self-service
// registration - see CLAUDE.md "Admin panel"). No shop param anywhere in
// dashboard routes below, so there's no ID to scope wrong.
// nav.access: who may open each menu page is declared once, in
// App\Support\Navigation (which also builds the sidebars).
Route::middleware(['auth', 'role:admin', 'nav.access'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/', [ShopOwnerController::class, 'index'])->name('index');
    Route::get('/shops/create', [ShopOwnerController::class, 'create'])->name('shops.create');
    Route::get('/shops/slug', [ShopOwnerController::class, 'slug'])->name('shops.slug');
    Route::post('/shops', [ShopOwnerController::class, 'store'])->name('shops.store');
    Route::get('/shops/{shop}/settings', [ShopSettingsController::class, 'edit'])->name('shops.settings.edit');
    Route::put('/shops/{shop}/settings', [ShopSettingsController::class, 'update'])->name('shops.settings.update');
    // "View as owner": the shop's owner dashboard, as the owner sees it (read-only until changes are allowed).
    Route::post('/shops/{shop}/view-as-owner', [ViewAsOwnerController::class, 'start'])->name('shops.view-as-owner');
    Route::put('/view-as-owner/editing', [ViewAsOwnerController::class, 'editing'])->name('view-as-owner.editing');
    Route::post('/view-as-owner/stop', [ViewAsOwnerController::class, 'stop'])->name('view-as-owner.stop');
    // Payment taken outside the app (bank transfer / cash) - hides the owner's order banner.
    Route::post('/shops/{shop}/orders', [AdminOrderController::class, 'store'])->name('shops.orders.store');

    // Product orders (to post out) and the products shops can order.
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::put('/orders/{order}/stage', [AdminOrderController::class, 'stage'])->name('orders.stage');
    Route::put('/orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::put('/orders/{order}/paid', [AdminOrderController::class, 'confirmPayment'])->name('orders.paid');
    Route::put('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');
    Route::post('/coupons', [CouponController::class, 'store'])->name('coupons.store');
    Route::put('/coupons/{coupon}', [CouponController::class, 'update'])->name('coupons.update');

    // Bulk QR stickers: generate in batches, map each to a destination later,
    // print as a PDF (built client-side from printData's JSON).
    Route::get('/qr-codes', [QrCodeController::class, 'index'])->name('qr-codes.index');
    Route::post('/qr-codes', [QrCodeController::class, 'store'])->name('qr-codes.store');
    Route::post('/qr-codes/print', [QrCodeController::class, 'printData'])->name('qr-codes.print');
    Route::post('/qr-codes/record-print', [QrCodeController::class, 'recordPrint'])->name('qr-codes.record-print');
    Route::delete('/qr-codes', [QrCodeController::class, 'destroy'])->name('qr-codes.destroy');
    Route::delete('/qr-codes/batches/{qrBatch}', [QrCodeController::class, 'destroyBatch'])->name('qr-codes.batches.destroy');

    // Sticker designs: a background image + where the QR goes on it, picked at print time.
    Route::get('/qr-codes/designs', [QrDesignController::class, 'index'])->name('qr-codes.designs.index');
    Route::get('/qr-codes/designs/create', [QrDesignController::class, 'create'])->name('qr-codes.designs.create');
    Route::get('/qr-codes/designs/{qrDesign}/edit', [QrDesignController::class, 'edit'])->name('qr-codes.designs.edit');
    Route::post('/qr-codes/designs', [QrDesignController::class, 'store'])->name('qr-codes.designs.store');
    Route::put('/qr-codes/designs/{qrDesign}', [QrDesignController::class, 'update'])->name('qr-codes.designs.update');
    Route::delete('/qr-codes/designs/{qrDesign}', [QrDesignController::class, 'destroy'])->name('qr-codes.designs.destroy');

    Route::put('/qr-codes/{qrCode}', [QrCodeController::class, 'update'])->name('qr-codes.update');

    // App-wide switches (API keys themselves stay in .env).
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/google', [SettingsController::class, 'updateGoogle'])->name('settings.google');
    Route::put('/settings/sidebar', [SettingsController::class, 'updateSidebar'])->name('settings.sidebar');
    Route::put('/settings/bank', [SettingsController::class, 'updateBank'])->name('settings.bank');
});

// Public landing link inside every printed QR sticker. Redirects to the
// mapped destination, or shows "Nothing found". Throttled so the code space
// can't be cheaply walked.
Route::get('/qr/{code}', [QrRedirectController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{1,16}')
    ->middleware('throttle:60,1')
    ->name('qr.show');

// Owner dashboard: always "my shop" (auth()->user()->shop), never a shop
// param in the URL - structurally impossible for one owner to view another's
// data through this route, not just policy-enforced.
// One-time shop setup straight after sign-up. Owners without a shop are
// sent here by shop.ready on every dashboard route below.
Route::middleware(['auth', 'role:owner'])->group(function () {
    Route::get('/onboarding', [ShopOnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding/business', [ShopOnboardingController::class, 'validateBusiness'])->middleware('throttle:30,1')->name('onboarding.business');
    Route::post('/onboarding', [ShopOnboardingController::class, 'store'])->middleware('throttle:10,1')->name('onboarding.store');
});

Route::middleware(['auth', 'role:owner', 'shop.ready', 'nav.access'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers');
    Route::get('/customers/export', [DashboardController::class, 'exportCustomers'])->name('customers.export');
    Route::get('/activity', [DashboardController::class, 'activity'])->name('activity');
    Route::get('/reviews', [DashboardController::class, 'reviews'])->name('reviews');
    Route::get('/staff', [DashboardController::class, 'staff'])->name('staff');
    Route::post('/scan', OwnerScanController::class)->middleware('throttle:30,1')->name('scan');
    Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [DashboardController::class, 'updateSettings'])->name('settings.update');
    Route::put('/settings/contact', [DashboardController::class, 'updateContact'])->name('settings.contact');
    Route::get('/theme', [DashboardController::class, 'theme'])->name('theme');
    Route::put('/theme', [DashboardController::class, 'updateTheme'])->name('theme.update');
    Route::delete('/theme', [DashboardController::class, 'resetTheme'])->name('theme.reset');
    Route::put('/theme/dashboard', [DashboardController::class, 'updateDashboardTheme'])->name('theme.dashboard');
    Route::put('/theme/custom', [DashboardController::class, 'updateCustomTheme'])->name('theme.custom');
    Route::delete('/theme/custom', [DashboardController::class, 'resetCustomTheme'])->name('theme.custom.reset');
    Route::put('/theme/header', [DashboardController::class, 'updateHeaderStyle'])->name('theme.header');
    Route::put('/theme/stamp-icon', [DashboardController::class, 'updateStampIcon'])->name('theme.stamp-icon');
    Route::put('/theme/signup-icon', [DashboardController::class, 'updateSignupIcon'])->name('theme.signup-icon');
    // POST, not PUT: file uploads need a real multipart POST.
    Route::post('/theme/banner', [ShopBannerController::class, 'update'])->name('theme.banner');
    Route::delete('/theme/banner', [ShopBannerController::class, 'destroy'])->name('theme.banner.destroy');
    Route::post('/theme/logo', [ShopLogoController::class, 'update'])->name('theme.logo');
    Route::delete('/theme/logo', [ShopLogoController::class, 'destroy'])->name('theme.logo.destroy');

    // Ordering products (any quantity) through Stripe Checkout, and tracking them.
    Route::get('/orders', [DashboardController::class, 'orders'])->name('orders');
    Route::post('/orders', [OrderController::class, 'checkout'])->middleware('throttle:10,1')->name('orders.checkout');
    // Checks a coupon code before ordering (throttled, so codes can't be guessed).
    Route::post('/orders/coupon', [OrderController::class, 'coupon'])->middleware('throttle:10,1')->name('orders.coupon');
    Route::get('/orders/{order}/success', [OrderController::class, 'success'])->name('orders.success');

    Route::post('/staff-members', [StaffMemberController::class, 'store'])->name('staff-members.store');
    Route::put('/staff-members/{staffMember}/pin', [StaffMemberController::class, 'updatePin'])->name('staff-members.pin');
    Route::delete('/staff-members/{staffMember}', [StaffMemberController::class, 'destroy'])->name('staff-members.destroy');

    Route::post('/staff-devices', [StaffDeviceController::class, 'store'])->name('staff-devices.store');
    Route::post('/staff-devices/{staffDevice}/setup-link', [StaffDeviceController::class, 'setupLink'])->name('staff-devices.setup-link');
    Route::delete('/staff-devices/{staffDevice}', [StaffDeviceController::class, 'destroy'])->name('staff-devices.destroy');
});

// Staff device onboarding: this route IS the login step (no session/user
// exists yet), so it validates the raw token from the URL directly, not
// via AuthenticateStaffDevice (that's for the already-onboarded API below).
Route::get('/staff/setup/{token}', [StaffSetupController::class, 'show'])
    ->middleware('throttle:10,1')
    ->name('staff.setup');

// Staff dashboard shell (PIN sign-in, scanner, customer lookup, today's
// stats): client-side checks localStorage for a saved device token before
// calling the API below - no server-side auth needed for the page itself.
Route::get('/staff', fn () => Inertia::render('Staff/Dashboard'))->name('staff.dashboard');

// Staff API: bearer-token authenticated (AuthenticateStaffDevice), not
// session/CSRF based - see the CSRF-exempt list in bootstrap/app.php. The
// device is owner-approved; stamping/lookup also need a staff member signed
// in on it with their PIN (EnsureStaffSignedIn).
Route::middleware('staff.auth')->prefix('api/staff')->name('staff.')->group(function () {
    Route::get('/me', [StaffController::class, 'me'])->name('me');
    Route::post('/sign-in', [StaffController::class, 'signIn'])->middleware('throttle:staff-pin')->name('sign-in');
    Route::post('/sign-out', [StaffController::class, 'signOut'])->name('sign-out');

    Route::middleware('staff.signed-in')->group(function () {
        Route::post('/scan', [StaffController::class, 'scan'])->middleware('throttle:staff-scan')->name('scan');
        Route::get('/summary', [StaffController::class, 'summary'])->name('summary');
        Route::get('/customers', [StaffController::class, 'customers'])->name('customers');
    });
});

// Stripe's payment confirmations (signature-checked, CSRF-exempt).
Route::post('/stripe/webhook', StripeWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('stripe.webhook');

// Runs `php artisan migrate` / bootstraps the admin account over HTTP for
// hosting plans without SSH access. Must work in every environment (it's for
// production), so both are protected by a bearer token (DEPLOY_MIGRATE_TOKEN)
// instead of an environment gate. Called by the deploy pipeline (DEPLOYMENT.md);
// the browser version without a token is GET /api/deploy/migrate in api.php.
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
