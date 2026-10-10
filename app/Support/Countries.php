<?php

namespace App\Support;

/**
 * The countries a shop can be in (ISO 3166-1 alpha-2 => [name, dialling
 * code]). Only the server holds the list; forms get it as a prop via
 * options(). The country decides the shop's phone / postcode rules
 * (App\Support\ShopContact) and whether it can order our hardware
 * (Shop::canOrderProducts() - UK only; elsewhere the owner downloads the QR
 * codes the admin assigned instead).
 */
class Countries
{
    public const UK = 'GB';

    public const INDIA = 'IN';

    /** @var array<string, array{0: string, 1: string}> */
    public const ALL = [
        'AF' => ['Afghanistan', '93'], 'AL' => ['Albania', '355'], 'DZ' => ['Algeria', '213'], 'AD' => ['Andorra', '376'],
        'AO' => ['Angola', '244'], 'AG' => ['Antigua and Barbuda', '1'], 'AR' => ['Argentina', '54'], 'AM' => ['Armenia', '374'],
        'AU' => ['Australia', '61'], 'AT' => ['Austria', '43'], 'AZ' => ['Azerbaijan', '994'], 'BS' => ['Bahamas', '1'],
        'BH' => ['Bahrain', '973'], 'BD' => ['Bangladesh', '880'], 'BB' => ['Barbados', '1'], 'BY' => ['Belarus', '375'],
        'BE' => ['Belgium', '32'], 'BZ' => ['Belize', '501'], 'BJ' => ['Benin', '229'], 'BT' => ['Bhutan', '975'],
        'BO' => ['Bolivia', '591'], 'BA' => ['Bosnia and Herzegovina', '387'], 'BW' => ['Botswana', '267'], 'BR' => ['Brazil', '55'],
        'BN' => ['Brunei', '673'], 'BG' => ['Bulgaria', '359'], 'BF' => ['Burkina Faso', '226'], 'BI' => ['Burundi', '257'],
        'KH' => ['Cambodia', '855'], 'CM' => ['Cameroon', '237'], 'CA' => ['Canada', '1'], 'CV' => ['Cape Verde', '238'],
        'CF' => ['Central African Republic', '236'], 'TD' => ['Chad', '235'], 'CL' => ['Chile', '56'], 'CN' => ['China', '86'],
        'CO' => ['Colombia', '57'], 'KM' => ['Comoros', '269'], 'CG' => ['Congo', '242'], 'CD' => ['Congo (DRC)', '243'],
        'CR' => ['Costa Rica', '506'], 'CI' => ["Côte d'Ivoire", '225'], 'HR' => ['Croatia', '385'], 'CU' => ['Cuba', '53'],
        'CY' => ['Cyprus', '357'], 'CZ' => ['Czechia', '420'], 'DK' => ['Denmark', '45'], 'DJ' => ['Djibouti', '253'],
        'DM' => ['Dominica', '1'], 'DO' => ['Dominican Republic', '1'], 'EC' => ['Ecuador', '593'], 'EG' => ['Egypt', '20'],
        'SV' => ['El Salvador', '503'], 'GQ' => ['Equatorial Guinea', '240'], 'ER' => ['Eritrea', '291'], 'EE' => ['Estonia', '372'],
        'SZ' => ['Eswatini', '268'], 'ET' => ['Ethiopia', '251'], 'FJ' => ['Fiji', '679'], 'FI' => ['Finland', '358'],
        'FR' => ['France', '33'], 'GA' => ['Gabon', '241'], 'GM' => ['Gambia', '220'], 'GE' => ['Georgia', '995'],
        'DE' => ['Germany', '49'], 'GH' => ['Ghana', '233'], 'GI' => ['Gibraltar', '350'], 'GR' => ['Greece', '30'],
        'GD' => ['Grenada', '1'], 'GT' => ['Guatemala', '502'], 'GG' => ['Guernsey', '44'], 'GN' => ['Guinea', '224'],
        'GW' => ['Guinea-Bissau', '245'], 'GY' => ['Guyana', '592'], 'HT' => ['Haiti', '509'], 'HN' => ['Honduras', '504'],
        'HK' => ['Hong Kong', '852'], 'HU' => ['Hungary', '36'], 'IS' => ['Iceland', '354'], 'IN' => ['India', '91'],
        'ID' => ['Indonesia', '62'], 'IR' => ['Iran', '98'], 'IQ' => ['Iraq', '964'], 'IE' => ['Ireland', '353'],
        'IM' => ['Isle of Man', '44'], 'IL' => ['Israel', '972'], 'IT' => ['Italy', '39'], 'JM' => ['Jamaica', '1'],
        'JP' => ['Japan', '81'], 'JE' => ['Jersey', '44'], 'JO' => ['Jordan', '962'], 'KZ' => ['Kazakhstan', '7'],
        'KE' => ['Kenya', '254'], 'KI' => ['Kiribati', '686'], 'XK' => ['Kosovo', '383'], 'KW' => ['Kuwait', '965'],
        'KG' => ['Kyrgyzstan', '996'], 'LA' => ['Laos', '856'], 'LV' => ['Latvia', '371'], 'LB' => ['Lebanon', '961'],
        'LS' => ['Lesotho', '266'], 'LR' => ['Liberia', '231'], 'LY' => ['Libya', '218'], 'LI' => ['Liechtenstein', '423'],
        'LT' => ['Lithuania', '370'], 'LU' => ['Luxembourg', '352'], 'MO' => ['Macao', '853'], 'MG' => ['Madagascar', '261'],
        'MW' => ['Malawi', '265'], 'MY' => ['Malaysia', '60'], 'MV' => ['Maldives', '960'], 'ML' => ['Mali', '223'],
        'MT' => ['Malta', '356'], 'MH' => ['Marshall Islands', '692'], 'MR' => ['Mauritania', '222'], 'MU' => ['Mauritius', '230'],
        'MX' => ['Mexico', '52'], 'FM' => ['Micronesia', '691'], 'MD' => ['Moldova', '373'], 'MC' => ['Monaco', '377'],
        'MN' => ['Mongolia', '976'], 'ME' => ['Montenegro', '382'], 'MA' => ['Morocco', '212'], 'MZ' => ['Mozambique', '258'],
        'MM' => ['Myanmar', '95'], 'NA' => ['Namibia', '264'], 'NR' => ['Nauru', '674'], 'NP' => ['Nepal', '977'],
        'NL' => ['Netherlands', '31'], 'NZ' => ['New Zealand', '64'], 'NI' => ['Nicaragua', '505'], 'NE' => ['Niger', '227'],
        'NG' => ['Nigeria', '234'], 'KP' => ['North Korea', '850'], 'MK' => ['North Macedonia', '389'], 'NO' => ['Norway', '47'],
        'OM' => ['Oman', '968'], 'PK' => ['Pakistan', '92'], 'PW' => ['Palau', '680'], 'PS' => ['Palestine', '970'],
        'PA' => ['Panama', '507'], 'PG' => ['Papua New Guinea', '675'], 'PY' => ['Paraguay', '595'], 'PE' => ['Peru', '51'],
        'PH' => ['Philippines', '63'], 'PL' => ['Poland', '48'], 'PT' => ['Portugal', '351'], 'PR' => ['Puerto Rico', '1'],
        'QA' => ['Qatar', '974'], 'RO' => ['Romania', '40'], 'RU' => ['Russia', '7'], 'RW' => ['Rwanda', '250'],
        'KN' => ['Saint Kitts and Nevis', '1'], 'LC' => ['Saint Lucia', '1'], 'VC' => ['Saint Vincent and the Grenadines', '1'],
        'WS' => ['Samoa', '685'], 'SM' => ['San Marino', '378'], 'ST' => ['São Tomé and Príncipe', '239'],
        'SA' => ['Saudi Arabia', '966'], 'SN' => ['Senegal', '221'], 'RS' => ['Serbia', '381'], 'SC' => ['Seychelles', '248'],
        'SL' => ['Sierra Leone', '232'], 'SG' => ['Singapore', '65'], 'SK' => ['Slovakia', '421'], 'SI' => ['Slovenia', '386'],
        'SB' => ['Solomon Islands', '677'], 'SO' => ['Somalia', '252'], 'ZA' => ['South Africa', '27'], 'KR' => ['South Korea', '82'],
        'SS' => ['South Sudan', '211'], 'ES' => ['Spain', '34'], 'LK' => ['Sri Lanka', '94'], 'SD' => ['Sudan', '249'],
        'SR' => ['Suriname', '597'], 'SE' => ['Sweden', '46'], 'CH' => ['Switzerland', '41'], 'SY' => ['Syria', '963'],
        'TW' => ['Taiwan', '886'], 'TJ' => ['Tajikistan', '992'], 'TZ' => ['Tanzania', '255'], 'TH' => ['Thailand', '66'],
        'TL' => ['Timor-Leste', '670'], 'TG' => ['Togo', '228'], 'TO' => ['Tonga', '676'], 'TT' => ['Trinidad and Tobago', '1'],
        'TN' => ['Tunisia', '216'], 'TR' => ['Türkiye', '90'], 'TM' => ['Turkmenistan', '993'], 'TV' => ['Tuvalu', '688'],
        'UG' => ['Uganda', '256'], 'UA' => ['Ukraine', '380'], 'AE' => ['United Arab Emirates', '971'],
        'GB' => ['United Kingdom', '44'], 'US' => ['United States', '1'], 'UY' => ['Uruguay', '598'], 'UZ' => ['Uzbekistan', '998'],
        'VU' => ['Vanuatu', '678'], 'VA' => ['Vatican City', '39'], 'VE' => ['Venezuela', '58'], 'VN' => ['Vietnam', '84'],
        'YE' => ['Yemen', '967'], 'ZM' => ['Zambia', '260'], 'ZW' => ['Zimbabwe', '263'],
    ];

    /** @return list<string> */
    /**
     * States (or similar) asked for in the address, by country - only where
     * the address needs one. India: its 28 states and 8 union territories.
     */
    public const STATES = [
        self::INDIA => [
            'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar',
            'Chandigarh', 'Chhattisgarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa',
            'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir', 'Jharkhand', 'Karnataka',
            'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
            'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
            'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
        ],
    ];

    /** @return list<string>|null the country's states, or null when its addresses don't ask for one */
    public static function states(?string $code): ?array
    {
        return self::STATES[$code] ?? null;
    }

    public static function codes(): array
    {
        return array_keys(self::ALL);
    }

    public static function name(?string $code): ?string
    {
        return self::ALL[$code][0] ?? null;
    }

    public static function dialCode(?string $code): ?string
    {
        return self::ALL[$code][1] ?? null;
    }

    /** @return list<string> every dialling code (digits only), each once */
    public static function dialCodes(): array
    {
        return array_values(array_unique(array_column(self::ALL, 1)));
    }

    /**
     * For a <select>: the UK first (most shops), then A-Z.
     *
     * @return list<array{code: string, name: string, dial: string}>
     */
    public static function options(): array
    {
        return collect(self::ALL)
            ->map(fn (array $c, string $code) => ['code' => $code, 'name' => $c[0], 'dial' => $c[1], 'states' => self::states($code)])
            ->sortBy(fn (array $c) => [$c['code'] === self::UK ? 0 : 1, $c['name']])
            ->values()
            ->all();
    }
}
