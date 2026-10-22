<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SubscriptionPayment;

/**
 * Which payment window a shop's owner pays for a plan in: Stripe (card form
 * on our page) for the countries the admin lists - the UK to start with -
 * and Razorpay for every other country. Admin → Landing page → Payments
 * (Setting::PAYMENT_COUNTRIES = {stripe: [alpha-2, ...]}); adding a country
 * there moves it to Stripe.
 */
class PaymentProviders
{
    /** Until the admin changes it: only the UK pays with Stripe. */
    public const DEFAULT_STRIPE_COUNTRIES = [Countries::UK];

    /** @return list<string> */
    public static function stripeCountries(): array
    {
        $saved = Setting::get(Setting::PAYMENT_COUNTRIES)['stripe'] ?? null;

        return is_array($saved) ? array_values($saved) : self::DEFAULT_STRIPE_COUNTRIES;
    }

    /** stripe | razorpay for a shop in $country (GB if unknown). */
    public static function forCountry(?string $country): string
    {
        return in_array($country ?? Countries::UK, self::stripeCountries(), true)
            ? SubscriptionPayment::STRIPE
            : SubscriptionPayment::RAZORPAY;
    }
}
