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
        'crown', 'trophy', 'medal', 'diamond', 'lightning', 'fire', 'smiley', 'thumbs-up', 'hand-heart',
        'rainbow', 'sun', 'snowflake', 'butterfly',
        'coffee-bean', 'tea', 'teapot', 'cocktail', 'champagne',
        'cake-slice', 'cupcake', 'bread', 'pretzel', 'chocolate', 'cherries', 'strawberry', 'popcorn',
        'burger', 'fries', 'hot-dog', 'chicken', 'taco', 'sushi', 'noodles', 'bowl', 'egg', 'avocado', 'pepper',
        'razor', 'comb', 'hair-dryer', 'lipstick', 'drop', 'lotus',
        'sneaker', 't-shirt', 'dress', 'book', 'music', 'game', 'leaf', 'plant', 'bicycle', 'car', 'wrench',
        'tooth', 'pill', 'cat', 'dog', 'fish',
    ];

    /** Every shop may pick these; the rest open with a plan that has all themes (Shop::hasAllThemes()). */
    public const FREE = [
        'check', 'star', 'heart', 'sparkles', 'gift',
        // One per business type, so every shop has a fitting free one.
        'coffee', 'croissant', 'utensils', 'scissors', 'shopping-bag',
    ];

    public static function resolve(?string $key): string
    {
        return in_array($key, self::KEYS, true) ? $key : self::DEFAULT;
    }
}
