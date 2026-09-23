<?php

namespace App\Support;

/**
 * Icons a shop can show inside a filled stamp. Keys only - the icons
 * themselves are react-icons, mapped in resources/js/lib/stampIcons.jsx.
 * Keep the two lists in sync when adding one.
 */
class StampIcons
{
    public const DEFAULT = 'check';

    public const KEYS = [
        'check', 'star', 'heart', 'sparkles', 'gift',
        'coffee', 'cup-soda', 'croissant', 'cake', 'donut', 'cookie', 'ice-cream',
        'sandwich', 'pizza', 'utensils', 'beer', 'wine',
        'scissors', 'dumbbell', 'paw', 'flower', 'shopping-bag',
    ];

    public static function resolve(?string $key): string
    {
        return in_array($key, self::KEYS, true) ? $key : self::DEFAULT;
    }
}
