<?php

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use App\Support\LandingPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** A saved landing page offering GBP (default), USD and INR. */
function landingWithCurrencies(): array
{
    $content = LandingPage::defaults();
    $content['currencies'] = ['GBP', 'USD', 'INR'];
    $content['youtube_url'] = 'https://youtu.be/dQw4w9WgXcQ';
    $content['plans'] = collect($content['plans'])->map(fn ($plan, $i) => [
        ...$plan,
        'prices' => ['GBP' => ['4.99', '9.99', '15.99'][$i], 'USD' => ['5.99', '11.99', '19.99'][$i], 'INR' => ['399', '799', '1299'][$i]],
    ])->all();
    Setting::set(Setting::LANDING_PAGE, $content);

    return $content;
}

/** Lookups sent to ipinfo (other requests, e.g. Inertia's SSR probe in tests, don't count). */
function ipinfoCalls(): int
{
    return Http::recorded(fn ($request) => str_contains($request->url(), 'api.ipinfo.io'))->count();
}

function landingAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

function landingForm(array $overrides = []): array
{
    return [
        'title' => 'Loyalty for independents',
        'description' => 'Tap to stamp.',
        'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10',
        'currencies' => ['GBP', 'EUR'],
        'default_currency' => 'GBP',
        'plans' => [[
            'name' => 'Starter', 'description' => 'Small shops', 'note' => '', 'badge' => '', 'highlighted' => false,
            'cta_label' => 'Start Free', 'period' => '/ month', 'billing_note' => 'Billed annually',
            'prices' => ['GBP' => '4.99', 'EUR' => '5.49'], 'features' => ['Loyalty card', '', '  NFC tap  '],
        ]],
        ...$overrides,
    ];
}

beforeEach(fn () => config(['services.ipinfo.token' => null]));

// --- The public page ---------------------------------------------------------------

test('visitors see the default page in pounds until the admin saves one', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('currency', 'GBP')
        ->where('youtube_id', null)
        ->has('plans', 3)
        ->where('plans.0.name', 'Starter')
        ->where('plans.0.symbol', '£')
        ->where('plans.0.price', '4.99')
        ->where('plans.1.highlighted', true)
        ->missing('plans.0.prices')
    );
});

test('signed-in users still go straight to their home; the admin can preview', function () {
    $admin = landingAdmin();
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get('/?preview=1')->assertRedirect('/onboarding');
    $this->actingAs($admin)->get('/')->assertRedirect('/admin');
    $this->actingAs($admin)->get('/?preview=1')->assertInertia(fn ($page) => $page->component('Landing'));
});

test('the currency follows the visitor\'s country (Cloudflare header) when it is offered', function (string $country, string $currency, string $symbol, string $price) {
    landingWithCurrencies();

    $this->withHeader('CF-IPCountry', $country)->get('/')->assertInertia(fn ($page) => $page
        ->where('currency', $currency)
        ->where('plans.0.symbol', $symbol)
        ->where('plans.0.price', $price)
        ->where('youtube_id', 'dQw4w9WgXcQ')
    );
})->with([
    'UK' => ['GB', 'GBP', '£', '4.99'],
    'US' => ['US', 'USD', '$', '5.99'],
    'India' => ['IN', 'INR', '₹', '399'],
    'France (EUR not offered -> default)' => ['FR', 'GBP', '£', '4.99'],
]);

test('without Cloudflare the country comes from ipinfo, cached per IP', function () {
    landingWithCurrencies();
    config(['services.ipinfo.token' => 'tok']);
    Cache::flush();
    Http::fake(['api.ipinfo.io/lite/*' => Http::response(['ip' => '8.8.8.8', 'country_code' => 'US'])]);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/')->assertInertia(fn ($page) => $page->where('currency', 'USD'));
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])->get('/')->assertInertia(fn ($page) => $page->where('currency', 'USD'));

    expect(ipinfoCalls())->toBe(1);
    Http::assertSent(fn ($request) => $request->url() === 'https://api.ipinfo.io/lite/8.8.8.8' && $request->hasHeader('Authorization', 'Bearer tok'));
});

test('local IPs and failed lookups fall back to the default currency', function () {
    landingWithCurrencies();
    config(['services.ipinfo.token' => 'tok']);
    Cache::flush();
    Http::fake(['api.ipinfo.io/lite/*' => Http::response(['error' => 'quota'], 429)]);

    $this->get('/')->assertInertia(fn ($page) => $page->where('currency', 'GBP')); // 127.0.0.1: not looked up
    expect(ipinfoCalls())->toBe(0);

    $this->withServerVariables(['REMOTE_ADDR' => '8.8.4.4'])->get('/')->assertInertia(fn ($page) => $page->where('currency', 'GBP'));
});

test('?currency= shows an offered currency on purpose, and ignores others', function () {
    landingWithCurrencies();

    $this->get('/?currency=inr')->assertInertia(fn ($page) => $page->where('currency', 'INR')->where('plans.2.price', '1299'));
    $this->get('/?currency=JPY')->assertInertia(fn ($page) => $page->where('currency', 'GBP'));
});

test('youtube links of every usual shape give the video id', function (string $url) {
    expect(LandingPage::youtubeId($url))->toBe('dQw4w9WgXcQ');
})->with([
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ',
    'https://youtu.be/dQw4w9WgXcQ?si=abc',
    'https://www.youtube.com/shorts/dQw4w9WgXcQ',
    'https://www.youtube.com/embed/dQw4w9WgXcQ',
    'youtube.com/live/dQw4w9WgXcQ',
]);

test('a non-YouTube link is not a video', function () {
    expect(LandingPage::youtubeId('https://vimeo.com/123'))->toBeNull()
        ->and(LandingPage::youtubeId(null))->toBeNull();
});

// --- Admin -----------------------------------------------------------------------------

test('only the admin can edit the landing page', function () {
    $owner = User::factory()->create(['role' => UserRole::Owner]);

    $this->actingAs($owner)->get('/admin/landing-page')->assertForbidden();
    $this->actingAs($owner)->put('/admin/landing-page', landingForm())->assertForbidden();
    $this->actingAs(landingAdmin())->get('/admin/landing-page')->assertInertia(fn ($page) => $page
        ->component('Admin/LandingPage')
        ->where('content.plans.0.name', 'Starter')
        ->has('currencyOptions')
    );
});

test('the admin saves the page; it is tidied and shown to visitors', function () {
    $this->actingAs(landingAdmin())->put('/admin/landing-page', landingForm())->assertSessionHasNoErrors();

    $saved = Setting::get(Setting::LANDING_PAGE);
    expect($saved['plans'][0]['features'])->toBe(['Loyalty card', 'NFC tap'])
        ->and($saved['plans'][0]['note'])->toBeNull()
        ->and($saved['currencies'])->toBe(['GBP', 'EUR']);

    auth()->logout();
    $this->withHeader('CF-IPCountry', 'DE')->get('/')->assertInertia(fn ($page) => $page
        ->where('title', 'Loyalty for independents')
        ->where('currency', 'EUR')
        ->where('plans.0.symbol', '€')
        ->where('plans.0.price', '5.49')
        ->where('youtube_id', 'dQw4w9WgXcQ')
    );
});

test('every plan needs a price in every offered currency', function () {
    $form = landingForm();
    $form['plans'][0]['prices'] = ['GBP' => '4.99'];

    $this->actingAs(landingAdmin())->put('/admin/landing-page', $form)->assertSessionHasErrors('plans.0.prices.EUR');
});

test('the landing page form is validated', function (array $overrides, string $field) {
    $this->actingAs(landingAdmin())->put('/admin/landing-page', landingForm($overrides))->assertSessionHasErrors($field);
})->with([
    'no title' => [['title' => ''], 'title'],
    'not youtube' => [['youtube_url' => 'https://vimeo.com/1'], 'youtube_url'],
    'unknown currency' => [['currencies' => ['GBP', 'XYZ']], 'currencies.1'],
    'default not offered' => [['default_currency' => 'USD'], 'default_currency'],
    'no plans' => [['plans' => []], 'plans'],
]);
