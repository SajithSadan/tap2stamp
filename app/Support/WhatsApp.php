<?php

namespace App\Support;

/**
 * Phone numbers as WhatsApp wants them in a wa.me link: the full
 * international number, digits only ("447700900123").
 */
class WhatsApp
{
    /**
     * From a dialling code + number as stored on shops / the sign-up draft.
     * Older shops kept the whole number in one field ("+44 7700…" or a UK
     * "07700…") - those are understood too. Null when it can't be a number.
     */
    public static function number(?string $code, ?string $number): ?string
    {
        $number = trim((string) $number);
        $digits = preg_replace('/\D/', '', $number);
        $code = preg_replace('/\D/', '', (string) $code);

        if ($digits === '') {
            return null;
        }

        $full = match (true) {
            $code !== '' => $code.ltrim($digits, '0'),
            str_starts_with($number, '+') => $digits,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '0') => '44'.substr($digits, 1),
            default => $digits,
        };

        return preg_match('/^[1-9]\d{7,14}$/', $full) ? $full : null;
    }
}
