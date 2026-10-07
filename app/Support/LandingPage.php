<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The public landing page at "/" (signed-out visitors): title, description,
 * a YouTube video, and the pricing plans. Edited by the admin (Admin →
 * Landing page) and kept as one JSON setting (Setting::LANDING_PAGE); until
 * then the defaults below apply.
 *
 * Prices are typed per currency by the admin (no conversion). A visitor sees
 * their country's currency (App\Services\VisitorCountry) when it's offered,
 * else the default currency.
 */
class LandingPage
{
    public const MAX_PLANS = 4;

    public const MAX_FEATURES = 12;

    public const MAX_CURRENCIES = 12;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'title' => 'The digital loyalty card your customers will actually use',
            'description' => 'Replace paper punch cards with a tap or a scan. No app, no passwords - just more visits, reviews and regulars.',
            'youtube_url' => null,
            'default_currency' => 'GBP',
            'currencies' => ['GBP'],
            'plans' => [
                [
                    'name' => 'Starter',
                    'description' => 'For small businesses starting digital loyalty.',
                    'note' => 'Free for Year 1 when you buy hardware above',
                    'badge' => null,
                    'highlighted' => false,
                    'cta_label' => 'Start Free',
                    'period' => '/ month',
                    'billing_note' => 'Billed annually',
                    'prices' => ['GBP' => '4.99'],
                    'features' => ['Digital loyalty card', 'NFC tap-to-stamp', 'Custom stamp count & reward', 'Up to 500 active customers', 'Email support', 'Google Reviews Collection', 'Instagram Link'],
                ],
                [
                    'name' => 'Growth',
                    'description' => 'For businesses ready to engage customers.',
                    'note' => null,
                    'badge' => 'Most Popular',
                    'highlighted' => true,
                    'cta_label' => 'Start Free',
                    'period' => '/ month',
                    'billing_note' => 'Billed annually',
                    'prices' => ['GBP' => '9.99'],
                    'features' => ['Everything in Starter', 'AI Featured Google Reviews', 'Digital menu & offers page', 'Unlimited active customers', 'Customer insights dashboard', 'Priority email support'],
                ],
                [
                    'name' => 'Business',
                    'description' => 'For growing, multi-location businesses.',
                    'note' => null,
                    'badge' => null,
                    'highlighted' => false,
                    'cta_label' => 'Get Started',
                    'period' => '/ month',
                    'billing_note' => 'Billed annually',
                    'prices' => ['GBP' => '15.99'],
                    'features' => ['Everything in Growth', 'Multiple locations', 'Advanced analytics & reporting', 'Custom branding options', 'Whatsapp API Integration', 'Priority phone & email support'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> the saved page, or the defaults */
    public static function content(): array
    {
        return Setting::get(Setting::LANDING_PAGE) ?? self::defaults();
    }

    /**
     * The YouTube video id from any usual link: watch?v=, youtu.be/,
     * /shorts/, /embed/, /live/ - or null when it isn't a YouTube link.
     */
    public static function youtubeId(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $pattern = '~^(?:https?://)?(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})~';

        return preg_match($pattern, trim($url), $m) ? $m[1] : null;
    }

    /** Which offered currency a visitor from $country sees ($requested wins when offered, e.g. ?currency=USD). */
    public static function currencyFor(array $content, ?string $country, ?string $requested = null): string
    {
        $offered = $content['currencies'] ?? [];
        $requested = strtoupper((string) $requested);

        if ($requested !== '' && in_array($requested, $offered, true)) {
            return $requested;
        }

        $own = Currencies::forCountry($country);

        return $own && in_array($own, $offered, true) ? $own : ($content['default_currency'] ?? 'GBP');
    }

    /**
     * What the page shows: the plans with one price each, in $currency.
     *
     * @return array<string, mixed>
     */
    public static function forVisitor(array $content, string $currency): array
    {
        $symbol = Currencies::symbol($currency);

        return [
            'title' => $content['title'],
            'description' => $content['description'],
            'youtube_id' => self::youtubeId($content['youtube_url'] ?? null),
            'currency' => $currency,
            'plans' => collect($content['plans'])->map(fn (array $plan) => [
                ...collect($plan)->except('prices')->all(),
                // A plan with no price in this currency falls back to the default one.
                'price' => $plan['prices'][$currency] ?? $plan['prices'][$content['default_currency']] ?? null,
                'symbol' => isset($plan['prices'][$currency]) ? $symbol : Currencies::symbol($content['default_currency']),
            ])->values()->all(),
        ];
    }
}
