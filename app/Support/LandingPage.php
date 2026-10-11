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

    public const MAX_FAQS = 10;

    /** Shared on social media / chat apps: 1200 x 630, in public/images. */
    public const OG_IMAGE = '/images/og-image.jpg';

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
            // Search engines and link previews (worldwide - no prices: they differ by country).
            'seo_title' => 'Digital Loyalty Card App for Small Businesses | Tada Tap',
            'seo_description' => "Replace paper stamp cards with a digital loyalty card on your customers' phones. They scan your QR code to collect stamps - no app to download. Start free.",
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
            // FAQ: shown on the page and sent to Google as FAQPage structured data.
            'faq_title' => 'Frequently asked questions',
            'faqs' => [
                ['question' => 'What is a digital loyalty card?', 'answer' => "A digital loyalty card is a stamp card that lives on your customer's phone instead of in their wallet. Every visit adds a stamp, and when the card is full the customer gets the reward you chose - no paper cards to print, lose or forget."],
                ['question' => 'How does Tada Tap work?', 'answer' => "Customers scan your shop's QR code to get their card - just their name and mobile number, once. On each visit your staff scan the QR code on the customer's card with the Tada Tap staff app to add a stamp. When the card is full, the reward is ready to give."],
                ['question' => 'Do customers need to download an app?', 'answer' => "No. The loyalty card opens in the phone's web browser on iPhone and Android. Customers can add it to their home screen in one tap, so it works like an app without a download."],
                ['question' => 'What kinds of businesses use Tada Tap?', 'answer' => 'Any business with repeat customers: cafes, restaurants, bakeries, takeaways, barbers, hair and beauty salons, gyms, clinics, pharmacies, grocery and retail shops, laundries and more.'],
                ['question' => 'Can I choose the reward and how many stamps it takes?', 'answer' => 'Yes. Pick any reward - a free coffee, a free haircut, 10% off - and anywhere from 3 to 50 stamps. You can change both at any time from your dashboard.'],
                ['question' => 'Does Tada Tap work in my country?', 'answer' => 'Yes. Tada Tap runs in any web browser, so it works for businesses anywhere in the world, with prices in your local currency. Download and print your QR code to put on the counter, or order a ready-made counter display where it is available.'],
                ['question' => 'What do my staff need?', 'answer' => 'Just a smartphone. Staff open the Tada Tap staff app in the browser, sign in with their name and PIN, and scan customer cards. Every stamp records who gave it.'],
                ['question' => 'Can Tada Tap help me get more Google reviews?', 'answer' => 'Yes. Customers can rate their visit and leave feedback from their loyalty card, then share a review on your Google Business Profile in one tap.'],
                ['question' => 'Is there a free trial?', 'answer' => 'Yes. Every new shop starts with a free trial, and you can sign up without any payment details. Set up your card in about five minutes and see how your customers respond.'],
            ],
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
                'seo_title', 'faq_title', 'faqs',
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

    /**
     * The landing page's <head> for search engines and link previews. The
     * app renders in the browser, so these are written into the HTML by the
     * server (app.blade.php) - crawlers and chat-app previews don't run JS.
     * One worldwide site: the canonical is this app's own URL, for every
     * country (UK shops sign up here too).
     *
     * @return array<string, mixed>
     */
    public static function seo(array $content): array
    {
        $url = rtrim((string) config('app.url'), '/').'/';
        $title = ($content['seo_title'] ?? null) ?: $content['title'];
        $description = ($content['seo_description'] ?? null) ?: $content['description'];
        $image = rtrim($url, '/').self::OG_IMAGE;
        $faqs = collect($content['faqs'] ?? [])->filter(fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))->values();

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => $url.'#organization',
                'name' => 'Tada Tap',
                'url' => $url,
                'logo' => rtrim($url, '/').'/images/favicon-512.png',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $url.'#website',
                'name' => 'Tada Tap',
                'url' => $url,
                'inLanguage' => 'en',
                'publisher' => ['@id' => $url.'#organization'],
            ],
            [
                '@type' => 'SoftwareApplication',
                'name' => 'Tada Tap',
                'url' => $url,
                'description' => $description,
                'applicationCategory' => 'BusinessApplication',
                'operatingSystem' => 'Web browser, iOS, Android',
                'publisher' => ['@id' => $url.'#organization'],
            ],
        ];

        if ($faqs->isNotEmpty()) {
            $graph[] = [
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => $faqs->map(fn (array $faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->all(),
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'image' => $image,
            'image_alt' => 'Tada Tap - digital loyalty cards customers keep on their phone',
            // Escaped for a <script> tag: admin-typed text can't close it.
            'json_ld' => json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP),
        ];
    }
}
