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
    $content['default_currency'] = 'GBP';
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
        'kicker' => '  For cafes  ',
        'title' => 'Loyalty for independents',
        'description' => 'Tap to stamp.',
        'primary_cta' => 'Start free trial',
        'demo_cta' => '',
        'trust_title' => 'First month free',
        'trust_description' => '',
        'trust_points' => ['No app needed', '', '  Cancel anytime  '],
        'pricing_title' => 'Pricing',
        'pricing_subtitle' => '',
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

test('visitors see the default page (hero, trust banner, 3 plans in INR / AED) until the admin saves one', function () {
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('Landing')
        ->where('kicker', 'Digital Loyalty & Growth Engine for Retail, Dining & Salons')
        ->where('title', 'Get Back-to-Back Repeat Customers to Your Shop with TaDa Tap')
        ->where('primary_cta', 'Start 1-Month Free Trial')
        ->where('trust_title', 'Register for Free - Your First 30 Days Are On Us')
        ->has('trust_points', 3)
        ->where('pricing_title', 'Simple, Transparent Annual Pricing')
        ->where('currency', 'INR')
        ->where('youtube_id', null)
        ->has('plans', 3)
        ->where('plans.0.name', 'Starter')
        ->where('plans.0.symbol', '₹')
        ->where('plans.0.price', '2999')
        ->where('plans.0.period', '/ year')
        ->where('plans.1.name', 'Growth')
        ->where('plans.1.highlighted', true)
        ->where('plans.1.badge', 'Most Popular')
        ->where('plans.2.price', '7999')
    );
});

test('visitors only ever get their own currency\'s prices - never the others, not even in the page data', function () {
    landingWithCurrencies();

    $page = $this->withHeader('CF-IPCountry', 'IN')->get('/')->viewData('page');
    $data = json_encode($page['props']);

    expect($page['props']['currency'])->toBe('INR')
        ->and($page['props'])->not->toHaveKey('currencies')
        ->and($page['props']['plans'][0])->not->toHaveKey('prices')
        // The GBP / USD prices of the saved page appear nowhere in what's sent.
        ->and($data)->not->toContain('4.99')->not->toContain('5.99')->not->toContain('15.99');
});

test('a page saved before the new sections existed gets their defaults', function () {
    $old = LandingPage::defaults();
    unset($old['kicker'], $old['trust_title'], $old['trust_points'], $old['pricing_title'], $old['primary_cta']);
    $old['title'] = 'Our own title';
    Setting::set(Setting::LANDING_PAGE, $old);

    $this->get('/')->assertInertia(fn ($page) => $page
        ->where('title', 'Our own title')
        ->where('pricing_title', 'Simple, Transparent Annual Pricing')
        ->where('primary_cta', 'Start 1-Month Free Trial')
        ->has('trust_points', 3));
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

test('?currency= is ignored for the public, so nobody can look up another country\'s prices', function () {
    landingWithCurrencies();

    $this->withHeader('CF-IPCountry', 'GB')->get('/?currency=INR')->assertInertia(fn ($page) => $page
        ->where('currency', 'GBP')
        ->where('plans.2.price', '15.99'));
});

test('the admin\'s preview can show any offered currency, and ignores others', function () {
    landingWithCurrencies();
    $this->actingAs(landingAdmin());

    $this->get('/?preview=1&currency=inr')->assertInertia(fn ($page) => $page->where('currency', 'INR')->where('plans.2.price', '1299'));
    $this->get('/?preview=1&currency=JPY')->assertInertia(fn ($page) => $page->where('currency', 'GBP'));
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
        ->and($saved['currencies'])->toBe(['GBP', 'EUR'])
        ->and($saved['kicker'])->toBe('For cafes')
        ->and($saved['demo_cta'])->toBeNull()
        ->and($saved['trust_points'])->toBe(['No app needed', 'Cancel anytime'])
        ->and($saved['pricing_subtitle'])->toBeNull();

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
    'no main button' => [['primary_cta' => ''], 'primary_cta'],
    'no pricing title' => [['pricing_title' => ''], 'pricing_title'],
    'too many trust badges' => [['trust_points' => array_fill(0, 7, 'Badge')], 'trust_points'],
]);

test('feature lines can carry a longer detail', function () {
    $form = landingForm();
    $form['plans'][0]['features'] = ['AI Smart Menu Builder ('.str_repeat('x', 150).')'];

    $this->actingAs(landingAdmin())->put('/admin/landing-page', $form)->assertSessionHasNoErrors();

    $form['plans'][0]['features'] = [str_repeat('x', 201)];
    $this->put('/admin/landing-page', $form)->assertSessionHasErrors('plans.0.features.0');
});

test('the editor gets the defaults for "Load default content"', function () {
    $this->actingAs(landingAdmin())->get('/admin/landing-page')->assertInertia(fn ($page) => $page
        ->component('Admin/LandingPage')
        ->where('defaults.plans.2.name', 'Elite Pro')
        ->where('limits.trustPoints', LandingPage::MAX_TRUST_POINTS));
});
