import {
    LuBeer,
    LuCakeSlice,
    LuCheck,
    LuCoffee,
    LuCookie,
    LuCroissant,
    LuCupSoda,
    LuDonut,
    LuDumbbell,
    LuFlower2,
    LuGift,
    LuHeart,
    LuIceCreamCone,
    LuPawPrint,
    LuPizza,
    LuSandwich,
    LuScissors,
    LuShoppingBag,
    LuSparkles,
    LuStar,
    LuUtensils,
    LuWine,
} from 'react-icons/lu';

// What a shop can show inside a filled stamp. Keys must match
// App\Support\StampIcons::KEYS (the server only accepts those).
export const STAMP_ICONS = [
    { key: 'check', label: 'Tick', Icon: LuCheck },
    { key: 'star', label: 'Star', Icon: LuStar },
    { key: 'heart', label: 'Heart', Icon: LuHeart },
    { key: 'sparkles', label: 'Sparkle', Icon: LuSparkles },
    { key: 'gift', label: 'Gift', Icon: LuGift },
    { key: 'coffee', label: 'Coffee', Icon: LuCoffee },
    { key: 'cup-soda', label: 'Cold drink', Icon: LuCupSoda },
    { key: 'croissant', label: 'Croissant', Icon: LuCroissant },
    { key: 'cake', label: 'Cake', Icon: LuCakeSlice },
    { key: 'donut', label: 'Donut', Icon: LuDonut },
    { key: 'cookie', label: 'Cookie', Icon: LuCookie },
    { key: 'ice-cream', label: 'Ice cream', Icon: LuIceCreamCone },
    { key: 'sandwich', label: 'Sandwich', Icon: LuSandwich },
    { key: 'pizza', label: 'Pizza', Icon: LuPizza },
    { key: 'utensils', label: 'Meal', Icon: LuUtensils },
    { key: 'beer', label: 'Beer', Icon: LuBeer },
    { key: 'wine', label: 'Wine', Icon: LuWine },
    { key: 'scissors', label: 'Scissors', Icon: LuScissors },
    { key: 'dumbbell', label: 'Gym', Icon: LuDumbbell },
    { key: 'paw', label: 'Paw', Icon: LuPawPrint },
    { key: 'flower', label: 'Flower', Icon: LuFlower2 },
    { key: 'shopping-bag', label: 'Shopping', Icon: LuShoppingBag },
];

const BY_KEY = Object.fromEntries(STAMP_ICONS.map((icon) => [icon.key, icon.Icon]));

/** The shop's stamp icon; unknown keys fall back to the tick. */
export function StampIcon({ icon, ...props }) {
    const Icon = BY_KEY[icon] ?? LuCheck;

    return <Icon aria-hidden="true" {...props} />;
}
