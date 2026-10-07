<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * A shop's business contact + location: who to talk to, how to reach them,
 * where the shop is (incl. its country), and where post should go. Shared by
 * shop setup (onboarding), the owner's Settings and the admin's shop
 * settings, so all of them validate and store it the same way.
 *
 * The phone is two fields: `contact_phone_code` (the dialling code, digits
 * only, e.g. "44" - its own picker on the forms) and `contact_phone` (the
 * number without it, e.g. "7700900123"), shown together as "+44 7700900123"
 * (Shop::contactPhone()). The code picks the number rules: +44 and +91
 * exactly, anything else 4-14 digits. The country picks the postcode rules:
 * UK and India exactly; elsewhere optional, since many countries have none.
 * Mirrored client-side in resources/js/lib/validation.js.
 */
class ShopContact
{
    /** Columns on `shops` this owns. */
    public const FIELDS = [
        'contact_name', 'contact_email', 'contact_phone_code', 'contact_phone',
        'address_line1', 'address_line2', 'town', 'postcode', 'country', 'delivery_address',
    ];

    public const UK_CODE = '44';

    public const INDIA_CODE = '91';

    /** The number after its code, by code. */
    private const PHONE = [
        self::UK_CODE => '/^[1-9]\d{8,9}$/',
        self::INDIA_CODE => '/^[6-9]\d{9}$/',
    ];

    private const ANY_PHONE = '/^\d{4,14}$/';

    private const POSTCODE = [
        Countries::UK => '/^[A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}$/',
        Countries::INDIA => '/^\d{6}$/',
    ];

    /** A sent country code tidied ("gb" -> "GB"); empty = the UK, the default. */
    public static function country(mixed $raw): string
    {
        $code = strtoupper(trim((string) $raw));

        return $code === '' ? Countries::UK : $code;
    }

    /** A sent dialling code as digits ("+44" -> "44"); empty = the country's own. */
    public static function phoneCode(mixed $raw, string $country = Countries::UK): string
    {
        $code = preg_replace('/\D/', '', (string) $raw) ?? '';

        return $code !== '' ? $code : (Countries::dialCode($country) ?? self::UK_CODE);
    }

    /**
     * Tidies raw form input before validation: the country, the dialling
     * code, the number without its code, the postcode upper-cased (UK: with
     * its space).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalise(array $input): array
    {
        $country = self::country($input['country'] ?? null);
        $code = self::phoneCode($input['contact_phone_code'] ?? null, $country);
        // "+91 98765 43210" typed while +44 was picked: the typed code wins.
        $code = self::typedCode((string) ($input['contact_phone'] ?? ''), $code) ?? $code;

        return [
            'country' => $country,
            'contact_phone_code' => $code,
            'contact_phone' => self::phone((string) ($input['contact_phone'] ?? ''), $code),
            'postcode' => self::postcode((string) ($input['postcode'] ?? ''), $country),
            'contact_email' => strtolower(trim((string) ($input['contact_email'] ?? ''))),
        ];
    }

    /** The dialling code at the front of a number typed as +code / 00code, if it isn't $picked. */
    private static function typedCode(string $raw, string $picked): ?string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (! str_starts_with($raw, '+') && ! str_starts_with($digits, '00')) {
            return null;
        }

        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, $picked)) {
            return null;
        }

        // Longest first, so +971 isn't read as +9.
        $codes = Countries::dialCodes();
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($digits, $code)) {
                return $code;
            }
        }

        return null;
    }

    /**
     * The number without its dialling code, digits only: "07700 900123",
     * "+44 7700 900123", "0044 (0)7700…" all -> "7700900123" for code 44.
     * A pasted +code / 00code in front is dropped, then the trunk 0 (except
     * Italy's, which is part of the number).
     */
    public static function phone(string $raw, string $code = self::UK_CODE): string
    {
        $n = trim($raw);
        $international = str_starts_with($n, '+') || str_starts_with(preg_replace('/\D/', '', $n) ?? '', '00');
        $n = preg_replace('/\D/', '', $n) ?? '';

        if ($international) {
            $n = ltrim($n, '0');
            if (str_starts_with($n, $code)) {
                $n = substr($n, strlen($code));
            }
        }

        return $code === '39' ? $n : preg_replace('/^0/', '', $n);
    }

    /** UK: "sw1a1aa" -> "SW1A 1AA". Elsewhere: trimmed and upper-cased. */
    public static function postcode(string $raw, string $country = Countries::UK): string
    {
        if ($country !== Countries::UK) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', $raw) ?? ''));
        }

        $p = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');

        return preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $p) ? substr($p, 0, -3).' '.substr($p, -3) : $p;
    }

    /**
     * @param  bool  $required  false for the admin's form, where any field may be left empty
     * @return array<string, mixed>
     */
    public static function rules(?string $country = Countries::UK, bool $required = true, ?string $phoneCode = null): array
    {
        $country = self::country($country);
        $code = self::phoneCode($phoneCode, $country);
        $need = $required ? 'required' : 'nullable';
        $postcode = self::POSTCODE[$country] ?? null;

        return [
            'country' => [$need, 'string', Rule::in(Countries::codes())],
            'contact_name' => [$need, 'string', 'max:150'],
            'contact_email' => [$need, 'string', 'email', 'max:255'],
            'contact_phone_code' => [$need, 'string', Rule::in(Countries::dialCodes())],
            'contact_phone' => [$need, 'string', 'regex:'.(self::PHONE[$code] ?? self::ANY_PHONE)],
            'address_line1' => [$need, 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'town' => [$need, 'string', 'max:100'],
            // Countries without a known format: optional, any short text.
            'postcode' => $postcode ? [$need, 'string', 'regex:'.$postcode] : ['nullable', 'string', 'max:20'],
            'delivery_same' => $required ? ['boolean'] : ['nullable', 'boolean'],
            'delivery_address' => ['nullable', 'required_if:delivery_same,false', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(?string $country = Countries::UK, ?string $phoneCode = null): array
    {
        $country = self::country($country);
        $code = self::phoneCode($phoneCode, $country);

        return [
            'country.in' => 'Choose your country from the list.',
            'contact_phone_code.in' => 'Choose the country code from the list.',
            'contact_phone.regex' => match ($code) {
                self::UK_CODE => 'Enter a valid UK phone number, e.g. 020 7946 0000 or 07700 900123.',
                self::INDIA_CODE => 'Enter a valid Indian mobile number, e.g. 98765 43210.',
                default => 'Enter a valid phone number.',
            },
            'postcode.regex' => $country === Countries::INDIA
                ? 'Enter a valid 6-digit PIN code.'
                : 'Enter a valid postcode, e.g. SW1A 1AA.',
            'delivery_address.required_if' => 'Enter the delivery address, or tick "Same as the shop address".',
        ];
    }

    /**
     * Validated form data -> the columns to save. A delivery address "same
     * as the shop" is stored as null, so it follows the shop address.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributes(array $validated): array
    {
        $values = array_intersect_key($validated, array_flip(self::FIELDS));

        if (($validated['delivery_same'] ?? true) || blank($validated['delivery_address'] ?? null)) {
            $values['delivery_address'] = null;
        }

        return $values;
    }
}
