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

    public const MAX_TRUST_POINTS = 6;

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $plan = fn (array $plan) => [
            'note' => null,
            'badge' => null,
            'highlighted' => false,
            'cta_label' => 'Start Free 30-Day Trial',
            'period' => '/ year',
            'billing_note' => 'Billed annually after 30-day free trial',
            ...$plan,
        ];

        return [
            // Hero
            'kicker' => 'Digital Loyalty & Growth Engine for Retail, Dining & Salons',
            'title' => 'Get Back-to-Back Repeat Customers to Your Shop with TaDa Tap',
            'description' => 'Turn first-time visitors into regular customers. Launch instant stamp cards, AI menus, interactive dine-in games, and automated WhatsApp re-engagement in less than 5 minutes - no app download required.',
            'primary_cta' => 'Start 1-Month Free Trial',
            'demo_cta' => 'Watch 60-Sec Demo',
            'youtube_url' => null,
            // Trust banner, under the video
            'trust_title' => 'Register for Free - Your First 30 Days Are On Us',
            'trust_description' => 'Test TaDa Tap directly at your counter for 30 full days with zero risk. If you see customer retention increase, switch to our paid annual plan. No lock-in, cancel anytime.',
            'trust_points' => [
                'Instant Self-Serve Setup (Ready in 5 mins)',
                'No Mobile App Download Needed (Works in Mobile Browser)',
                'Dedicated Support for Kerala & GCC Businesses',
            ],
            // Pricing
            'pricing_title' => 'Simple, Transparent Annual Pricing',
            'pricing_subtitle' => 'Invest once a year, grow footfall daily. All plans include your first month completely free.',
            'default_currency' => 'INR',
            'currencies' => ['INR', 'AED'],
            'plans' => [
                $plan([
                    'name' => 'Starter',
                    'description' => 'Best for Juice Bars, Small Salons & Retail Kiosks',
                    'prices' => ['INR' => '2999', 'AED' => '220'],
                    'features' => [
                        'Unlimited Customer Scans & Digital Loyalty Stamp Cards',
                        'One-Tap Google Review & Instagram Profile Booster',
                        'Basic Customer Database & Daily Footfall Analytics',
                        'Ready-to-Print QR Code Counter Stand (High-res PDF)',
                        'Standard WhatsApp Chat Support',
                    ],
                ]),
                $plan([
                    'name' => 'Growth',
                    'description' => 'Best for Cafes, Restaurants & Busy Spas',
                    'badge' => 'Most Popular',
                    'highlighted' => true,
                    'prices' => ['INR' => '4999', 'AED' => '360'],
                    'features' => [
                        'Everything in Starter, plus:',
                        'AI Smart Menu Builder (Snap a photo of your paper menu to generate an interactive digital menu instantly)',
                        'Automated WhatsApp Win-Back Campaigns (Automatically re-engage customers absent for 30+ days via TaDa Tap platform)',
                        'Repeat Visitor Tracking & Frequency Metrics',
                        "Custom Branding (Add your shop's logo & brand colors to the web app interface)",
                        'Priority Email & WhatsApp Support',
                    ],
                ]),
                $plan([
                    'name' => 'Elite Pro',
                    'description' => 'For Premium Resto-Bars, Lounge Cafes & Multi-Chain Outlets',
                    'cta_label' => 'Contact / Start Free Trial',
                    'prices' => ['INR' => '7999', 'AED' => '580'],
                    'features' => [
                        'Everything in Growth, plus:',
                        'Interactive Dine-In Mini Games (Spin-the-Wheel, Scratch & Win to engage customers while waiting for food)',
                        'Instant Table Rewards & Coupon Generation',
                        "Dedicated WhatsApp Business API Integration (Send re-engagement campaigns directly under your shop's official WhatsApp name & number)",
                        'Multi-Outlet / Multi-Branch Centralized Dashboard',
                        'Acrylic NFC / QR Counter Stand Shipped to Your Doorstep',
                        '1-on-1 Onboarding Specialist',
                    ],
                ]),
            ],
        ];
    }

    /**
     * The saved page, or the defaults. A page saved before a field existed
     * (e.g. the trust banner) gets that field's default, not nothing.
     *
     * @return array<string, mixed>
     */
    public static function content(): array
    {
        return array_replace(self::defaults(), Setting::get(Setting::LANDING_PAGE) ?? []);
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
     * What the page shows: the plans priced in $currency only - the visitor's
     * own (from their IP). Other countries' prices never reach the page, not
     * even in its data, so nobody can compare them and pick a cheaper
     * country. A plan without a price in $currency shows the default one.
     *
     * @return array<string, mixed>
     */
    public static function forVisitor(array $content, string $currency): array
    {
        $default = $content['default_currency'] ?? 'GBP';

        return [
            ...collect($content)->only([
                'kicker', 'title', 'description', 'primary_cta', 'demo_cta',
                'trust_title', 'trust_description', 'trust_points', 'pricing_title', 'pricing_subtitle',
            ])->all(),
            'youtube_id' => self::youtubeId($content['youtube_url'] ?? null),
            'currency' => $currency,
            'plans' => collect($content['plans'])->map(fn (array $plan) => [
                ...collect($plan)->except('prices')->all(),
                'price' => $plan['prices'][$currency] ?? $plan['prices'][$default] ?? null,
                'symbol' => Currencies::symbol(isset($plan['prices'][$currency]) ? $currency : $default),
            ])->values()->all(),
        ];
    }
}
