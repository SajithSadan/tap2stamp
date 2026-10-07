<?php

namespace App\Support;

/**
 * Currencies the landing page's pricing can be shown in, and which one each
 * country uses. The admin picks which of these to offer and types each
 * plan's price in them (Admin → Landing page); a visitor sees their own
 * country's currency when it's offered, else the admin's default.
 */
class Currencies
{
    /** @var array<string, array{0: string, 1: string}> ISO 4217 => [symbol, name] */
    public const ALL = [
        'GBP' => ['£', 'British pound'],
        'USD' => ['$', 'US dollar'],
        'EUR' => ['€', 'Euro'],
        'INR' => ['₹', 'Indian rupee'],
        'AED' => ['AED ', 'UAE dirham'],
        'SAR' => ['SAR ', 'Saudi riyal'],
        'QAR' => ['QAR ', 'Qatari riyal'],
        'KWD' => ['KWD ', 'Kuwaiti dinar'],
        'BHD' => ['BHD ', 'Bahraini dinar'],
        'OMR' => ['OMR ', 'Omani rial'],
        'CAD' => ['C$', 'Canadian dollar'],
        'AUD' => ['A$', 'Australian dollar'],
        'NZD' => ['NZ$', 'New Zealand dollar'],
        'SGD' => ['S$', 'Singapore dollar'],
        'HKD' => ['HK$', 'Hong Kong dollar'],
        'MYR' => ['RM', 'Malaysian ringgit'],
        'PKR' => ['Rs ', 'Pakistani rupee'],
        'BDT' => ['৳', 'Bangladeshi taka'],
        'LKR' => ['Rs ', 'Sri Lankan rupee'],
        'NGN' => ['₦', 'Nigerian naira'],
        'KES' => ['KSh ', 'Kenyan shilling'],
        'ZAR' => ['R', 'South African rand'],
        'CHF' => ['CHF ', 'Swiss franc'],
        'SEK' => ['kr ', 'Swedish krona'],
        'NOK' => ['kr ', 'Norwegian krone'],
        'DKK' => ['kr ', 'Danish krone'],
        'PLN' => ['zł ', 'Polish złoty'],
        'TRY' => ['₺', 'Turkish lira'],
        'JPY' => ['¥', 'Japanese yen'],
        'CNY' => ['¥', 'Chinese yuan'],
        'BRL' => ['R$', 'Brazilian real'],
        'MXN' => ['MX$', 'Mexican peso'],
    ];

    /** Countries (ISO alpha-2) whose currency isn't simply "not offered -> default". */
    private const BY_COUNTRY = [
        'GB' => 'GBP', 'GG' => 'GBP', 'JE' => 'GBP', 'IM' => 'GBP', 'GI' => 'GBP',
        'US' => 'USD', 'PR' => 'USD', 'EC' => 'USD', 'SV' => 'USD', 'PA' => 'USD', 'TL' => 'USD', 'ZW' => 'USD',
        'AT' => 'EUR', 'BE' => 'EUR', 'HR' => 'EUR', 'CY' => 'EUR', 'EE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR',
        'DE' => 'EUR', 'GR' => 'EUR', 'IE' => 'EUR', 'IT' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR',
        'MT' => 'EUR', 'NL' => 'EUR', 'PT' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'ES' => 'EUR', 'AD' => 'EUR',
        'MC' => 'EUR', 'SM' => 'EUR', 'VA' => 'EUR', 'ME' => 'EUR', 'XK' => 'EUR',
        'IN' => 'INR', 'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR',
        'CA' => 'CAD', 'AU' => 'AUD', 'NZ' => 'NZD', 'SG' => 'SGD', 'HK' => 'HKD', 'MY' => 'MYR',
        'PK' => 'PKR', 'BD' => 'BDT', 'LK' => 'LKR', 'NG' => 'NGN', 'KE' => 'KES', 'ZA' => 'ZAR',
        'CH' => 'CHF', 'LI' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN',
        'TR' => 'TRY', 'JP' => 'JPY', 'CN' => 'CNY', 'BR' => 'BRL', 'MX' => 'MXN',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function symbol(string $code): string
    {
        return self::ALL[$code][0] ?? "{$code} ";
    }

    public static function forCountry(?string $country): ?string
    {
        return self::BY_COUNTRY[strtoupper((string) $country)] ?? null;
    }

    /** @return list<array{code: string, symbol: string, name: string}> */
    public static function options(): array
    {
        return collect(self::ALL)->map(fn (array $c, string $code) => ['code' => $code, 'symbol' => $c[0], 'name' => $c[1]])->values()->all();
    }
}
