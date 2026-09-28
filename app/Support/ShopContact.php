<?php

namespace App\Support;

/**
 * A shop's business contact + location: who to talk to, how to reach them,
 * where the shop is, and where post should go. Shared by shop setup
 * (onboarding) and the owner's Settings, so both validate and store it the
 * same way.
 */
class ShopContact
{
    /** Columns on `shops` this owns. */
    public const FIELDS = [
        'contact_name', 'contact_email', 'contact_phone',
        'address_line1', 'address_line2', 'town', 'postcode', 'delivery_address',
    ];

    /**
     * Tidies raw form input before validation: phone into +44 / +91 form,
     * postcode upper-cased with its space in place.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalise(array $input): array
    {
        return [
            'contact_phone' => self::phone((string) ($input['contact_phone'] ?? '')),
            'postcode' => self::postcode((string) ($input['postcode'] ?? '')),
            'contact_email' => strtolower(trim((string) ($input['contact_email'] ?? ''))),
        ];
    }

    /**
     * UK landline or mobile -> +44XXXXXXXXXX (accepts 020 7946 0000,
     * 07700 900123, +44 (0)20..., 0044..., 44...). Indian mobiles arrive as
     * +91 and are kept, matching what customers may register with.
     */
    public static function phone(string $raw): string
    {
        $n = preg_replace('/[\s\-().]/', '', $raw) ?? '';

        return match (true) {
            str_starts_with($n, '+440') => '+44'.substr($n, 4), // "+44 (0)20 ..."
            str_starts_with($n, '+44'), str_starts_with($n, '+91') => $n,
            str_starts_with($n, '0044') => '+44'.ltrim(substr($n, 4), '0'),
            str_starts_with($n, '44') && strlen($n) >= 11 => '+'.$n,
            str_starts_with($n, '0') => '+44'.substr($n, 1),
            default => $n,
        };
    }

    /** "sw1a1aa" -> "SW1A 1AA". Indian 6-digit PIN codes are left as they are. */
    public static function postcode(string $raw): string
    {
        $p = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');

        return preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/', $p) ? substr($p, 0, -3).' '.substr($p, -3) : $p;
    }

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'contact_name' => ['required', 'string', 'max:150'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            // UK landline/mobile (+44 then 9-10 digits) or Indian mobile.
            'contact_phone' => ['required', 'string', 'regex:/^(\+44[1-9]\d{8,9}|\+91[6-9]\d{9})$/'],
            'address_line1' => ['required', 'string', 'max:150'],
            'address_line2' => ['nullable', 'string', 'max:150'],
            'town' => ['required', 'string', 'max:100'],
            // UK postcode, or an Indian 6-digit PIN code.
            'postcode' => ['required', 'string', 'regex:/^([A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}|\d{6})$/'],
            'delivery_same' => ['boolean'],
            'delivery_address' => ['nullable', 'required_if:delivery_same,false', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'contact_phone.regex' => 'Enter a valid UK phone number, e.g. 020 7946 0000 or 07700 900123.',
            'postcode.regex' => 'Enter a valid postcode, e.g. SW1A 1AA.',
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
