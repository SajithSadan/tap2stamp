<?php

namespace App\Support;

/**
 * The decorative icon on the customer sign-up screen (shops.signup_icon),
 * separate from the stamp icon. Keys must match resources/js/lib/signupIcons.jsx
 * - keep the two lists in sync.
 */
class SignupIcons
{
    /** Neutral, so a shop that never picks one doesn't get something off-topic. */
    public const DEFAULT = 'sparkles';

    public const KEYS = [
        // Neutral
        'sparkles', 'leaf-swirl', 'leaves', 'hearts', 'stars', 'crown', 'gems', 'gift',
        // Cafe & bakery
        'coffee-beans', 'coffee-cup', 'teapot', 'croissant', 'bread', 'cupcake', 'cake', 'donut', 'ice-cream',
        // Food
        'pizza', 'burger', 'fries', 'noodles', 'sushi',
        // Drinks
        'beer', 'wine', 'cocktail', 'soda',
        // Beauty & barber
        'scissors', 'razor', 'comb', 'lipstick', 'perfume',
        // Other shops
        'fitness', 'paw', 'flower', 'rose', 'plant', 'shopping-bag', 't-shirt',
    ];

    public static function resolve(?string $key): string
    {
        return in_array($key, self::KEYS, true) ? $key : self::DEFAULT;
    }
}
