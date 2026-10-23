<?php

namespace App\Support;

/**
 * How warm the admin's follow-up with a shop is (shops.lead_status), set from
 * the Shops grid's Follow-up column (double-click) or with a note. Each note keeps the status it was
 * written with (shop_notes.lead_status), so the notes read as a timeline of
 * the relationship. Null = not set. Colours: resources/js/lib/leadStatus.js -
 * keep the keys in sync.
 */
class LeadStatus
{
    /** key => label, in the dropdown's order. */
    public const ALL = [
        'hot' => 'Hot',
        'warm' => 'Warm',
        'cold' => 'Cold',
    ];

    /** The label, or null for none / a key no longer offered. */
    public static function label(?string $key): ?string
    {
        return self::ALL[$key ?? ''] ?? null;
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', array_keys(self::ALL));
    }
}
