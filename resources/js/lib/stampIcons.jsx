import { GiCroissant, GiDonut, GiSandwich, GiSodaCan } from 'react-icons/gi';
import {
    PiBarbellFill,
    PiBeerSteinFill,
    PiCakeFill,
    PiCheckFatFill,
    PiCoffeeFill,
    PiCookieFill,
    PiFlowerTulipFill,
    PiForkKnifeFill,
    PiGiftFill,
    PiHeartFill,
    PiIceCreamFill,
    PiPawPrintFill,
    PiPizzaFill,
    PiScissorsFill,
    PiShoppingBagFill,
    PiSparkleFill,
    PiStarFill,
    PiWineFill,
} from 'react-icons/pi';

// What a shop can show inside a filled stamp. Keys must match
// App\Support\StampIcons::KEYS (the server only accepts those).
// Solid ("fill") icons - they read clearly inside a filled stamp, where thin
// line icons looked weak. Phosphor fill, with four solid game-icons where
// Phosphor has no match (croissant, donut, sandwich, cold drink).
export const STAMP_ICONS = [
    { key: 'check', label: 'Tick', Icon: PiCheckFatFill },
    { key: 'star', label: 'Star', Icon: PiStarFill },
    { key: 'heart', label: 'Heart', Icon: PiHeartFill },
    { key: 'sparkles', label: 'Sparkle', Icon: PiSparkleFill },
    { key: 'gift', label: 'Gift', Icon: PiGiftFill },
    { key: 'coffee', label: 'Coffee', Icon: PiCoffeeFill },
    { key: 'cup-soda', label: 'Cold drink', Icon: GiSodaCan },
    { key: 'croissant', label: 'Croissant', Icon: GiCroissant },
    { key: 'cake', label: 'Cake', Icon: PiCakeFill },
    { key: 'donut', label: 'Donut', Icon: GiDonut },
    { key: 'cookie', label: 'Cookie', Icon: PiCookieFill },
    { key: 'ice-cream', label: 'Ice cream', Icon: PiIceCreamFill },
    { key: 'sandwich', label: 'Sandwich', Icon: GiSandwich },
    { key: 'pizza', label: 'Pizza', Icon: PiPizzaFill },
    { key: 'utensils', label: 'Meal', Icon: PiForkKnifeFill },
    { key: 'beer', label: 'Beer', Icon: PiBeerSteinFill },
    { key: 'wine', label: 'Wine', Icon: PiWineFill },
    { key: 'scissors', label: 'Scissors', Icon: PiScissorsFill },
    { key: 'dumbbell', label: 'Gym', Icon: PiBarbellFill },
    { key: 'paw', label: 'Paw', Icon: PiPawPrintFill },
    { key: 'flower', label: 'Flower', Icon: PiFlowerTulipFill },
    { key: 'shopping-bag', label: 'Shopping', Icon: PiShoppingBagFill },
];

const BY_KEY = Object.fromEntries(STAMP_ICONS.map((icon) => [icon.key, icon.Icon]));

/** The shop's stamp icon; unknown keys fall back to the tick. */
export function StampIcon({ icon, ...props }) {
    const Icon = BY_KEY[icon] ?? PiCheckFatFill;

    return <Icon aria-hidden="true" {...props} />;
}

/**
 * Stamps per row: always fill a row of 6 first; the rest carry on in the next
 * row from the left. So 8 -> 6 + 2, 10 -> 6 + 4, 20 -> 6 + 6 + 6 + 2.
 */
export function stampColumns(total) {
    return Math.min(total, 6);
}

/** A small, fixed tilt per stamp (-7° … 7°) so filled stamps look hand-stamped, the same on every visit. */
export function stampTilt(index) {
    return ((index * 37) % 15) - 7;
}
