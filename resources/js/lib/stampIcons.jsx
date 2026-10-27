import {
    GiCakeSlice,
    GiChickenLeg,
    GiChocolateBar,
    GiComb,
    GiCroissant,
    GiCupcake,
    GiDonut,
    GiFrenchFries,
    GiHotDog,
    GiLipstick,
    GiNoodles,
    GiPretzel,
    GiRazor,
    GiSandwich,
    GiSodaCan,
    GiStrawberry,
    GiSushis,
    GiTacos,
    GiTeapot,
} from 'react-icons/gi';
import {
    PiAvocadoFill,
    PiBarbellFill,
    PiBeerSteinFill,
    PiBicycleFill,
    PiBookOpenFill,
    PiBowlFoodFill,
    PiBreadFill,
    PiButterflyFill,
    PiCakeFill,
    PiCarFill,
    PiCatFill,
    PiChampagneFill,
    PiCheckFatFill,
    PiCherriesFill,
    PiCoffeeBeanFill,
    PiCoffeeFill,
    PiCookieFill,
    PiCrownFill,
    PiDiamondFill,
    PiDogFill,
    PiDressFill,
    PiDropFill,
    PiEggFill,
    PiFireFill,
    PiFishFill,
    PiFlowerLotusFill,
    PiFlowerTulipFill,
    PiForkKnifeFill,
    PiGameControllerFill,
    PiGiftFill,
    PiHairDryerFill,
    PiHamburgerFill,
    PiHandHeartFill,
    PiHeartFill,
    PiIceCreamFill,
    PiLeafFill,
    PiLightningFill,
    PiMartiniFill,
    PiMedalFill,
    PiMusicNotesFill,
    PiPawPrintFill,
    PiPepperFill,
    PiPillFill,
    PiPizzaFill,
    PiPlantFill,
    PiPopcornFill,
    PiRainbowFill,
    PiScissorsFill,
    PiShoppingBagFill,
    PiSmileyFill,
    PiSneakerFill,
    PiSnowflakeFill,
    PiSparkleFill,
    PiStarFill,
    PiSunFill,
    PiTeaBagFill,
    PiThumbsUpFill,
    PiToothFill,
    PiTrophyFill,
    PiTShirtFill,
    PiWineFill,
    PiWrenchFill,
} from 'react-icons/pi';

// What a shop can show inside a filled stamp. Keys must match
// App\Support\StampIcons::KEYS (the server only accepts those).
// Solid ("fill") icons - they read clearly inside a filled stamp, where thin
// line icons looked weak. Phosphor fill, with solid game-icons where Phosphor
// has no match. `group` is the heading the owner's picker shows it under.
export const STAMP_ICONS = [
    { key: 'check', label: 'Tick', Icon: PiCheckFatFill, group: 'Rewards' },
    { key: 'star', label: 'Star', Icon: PiStarFill, group: 'Rewards' },
    { key: 'heart', label: 'Heart', Icon: PiHeartFill, group: 'Rewards' },
    { key: 'sparkles', label: 'Sparkle', Icon: PiSparkleFill, group: 'Rewards' },
    { key: 'gift', label: 'Gift', Icon: PiGiftFill, group: 'Rewards' },
    { key: 'crown', label: 'Crown', Icon: PiCrownFill, group: 'Rewards' },
    { key: 'trophy', label: 'Trophy', Icon: PiTrophyFill, group: 'Rewards' },
    { key: 'medal', label: 'Medal', Icon: PiMedalFill, group: 'Rewards' },
    { key: 'diamond', label: 'Diamond', Icon: PiDiamondFill, group: 'Rewards' },
    { key: 'lightning', label: 'Lightning', Icon: PiLightningFill, group: 'Rewards' },
    { key: 'fire', label: 'Fire', Icon: PiFireFill, group: 'Rewards' },
    { key: 'smiley', label: 'Smiley', Icon: PiSmileyFill, group: 'Rewards' },
    { key: 'thumbs-up', label: 'Thumbs up', Icon: PiThumbsUpFill, group: 'Rewards' },
    { key: 'hand-heart', label: 'Thank you', Icon: PiHandHeartFill, group: 'Rewards' },
    { key: 'rainbow', label: 'Rainbow', Icon: PiRainbowFill, group: 'Rewards' },
    { key: 'sun', label: 'Sun', Icon: PiSunFill, group: 'Rewards' },
    { key: 'snowflake', label: 'Snowflake', Icon: PiSnowflakeFill, group: 'Rewards' },
    { key: 'butterfly', label: 'Butterfly', Icon: PiButterflyFill, group: 'Rewards' },

    { key: 'coffee', label: 'Coffee', Icon: PiCoffeeFill, group: 'Coffee & drinks' },
    { key: 'coffee-bean', label: 'Coffee bean', Icon: PiCoffeeBeanFill, group: 'Coffee & drinks' },
    { key: 'tea', label: 'Tea', Icon: PiTeaBagFill, group: 'Coffee & drinks' },
    { key: 'teapot', label: 'Teapot', Icon: GiTeapot, group: 'Coffee & drinks' },
    { key: 'cup-soda', label: 'Cold drink', Icon: GiSodaCan, group: 'Coffee & drinks' },
    { key: 'beer', label: 'Beer', Icon: PiBeerSteinFill, group: 'Coffee & drinks' },
    { key: 'wine', label: 'Wine', Icon: PiWineFill, group: 'Coffee & drinks' },
    { key: 'cocktail', label: 'Cocktail', Icon: PiMartiniFill, group: 'Coffee & drinks' },
    { key: 'champagne', label: 'Bubbly', Icon: PiChampagneFill, group: 'Coffee & drinks' },

    { key: 'croissant', label: 'Croissant', Icon: GiCroissant, group: 'Bakery & sweets' },
    { key: 'cake', label: 'Cake', Icon: PiCakeFill, group: 'Bakery & sweets' },
    { key: 'cake-slice', label: 'Cake slice', Icon: GiCakeSlice, group: 'Bakery & sweets' },
    { key: 'cupcake', label: 'Cupcake', Icon: GiCupcake, group: 'Bakery & sweets' },
    { key: 'donut', label: 'Donut', Icon: GiDonut, group: 'Bakery & sweets' },
    { key: 'cookie', label: 'Cookie', Icon: PiCookieFill, group: 'Bakery & sweets' },
    { key: 'bread', label: 'Bread', Icon: PiBreadFill, group: 'Bakery & sweets' },
    { key: 'pretzel', label: 'Pretzel', Icon: GiPretzel, group: 'Bakery & sweets' },
    { key: 'chocolate', label: 'Chocolate', Icon: GiChocolateBar, group: 'Bakery & sweets' },
    { key: 'ice-cream', label: 'Ice cream', Icon: PiIceCreamFill, group: 'Bakery & sweets' },
    { key: 'cherries', label: 'Cherries', Icon: PiCherriesFill, group: 'Bakery & sweets' },
    { key: 'strawberry', label: 'Strawberry', Icon: GiStrawberry, group: 'Bakery & sweets' },
    { key: 'popcorn', label: 'Popcorn', Icon: PiPopcornFill, group: 'Bakery & sweets' },

    { key: 'utensils', label: 'Meal', Icon: PiForkKnifeFill, group: 'Food' },
    { key: 'sandwich', label: 'Sandwich', Icon: GiSandwich, group: 'Food' },
    { key: 'pizza', label: 'Pizza', Icon: PiPizzaFill, group: 'Food' },
    { key: 'burger', label: 'Burger', Icon: PiHamburgerFill, group: 'Food' },
    { key: 'fries', label: 'Fries', Icon: GiFrenchFries, group: 'Food' },
    { key: 'hot-dog', label: 'Hot dog', Icon: GiHotDog, group: 'Food' },
    { key: 'chicken', label: 'Chicken', Icon: GiChickenLeg, group: 'Food' },
    { key: 'taco', label: 'Taco', Icon: GiTacos, group: 'Food' },
    { key: 'sushi', label: 'Sushi', Icon: GiSushis, group: 'Food' },
    { key: 'noodles', label: 'Noodles', Icon: GiNoodles, group: 'Food' },
    { key: 'bowl', label: 'Bowl', Icon: PiBowlFoodFill, group: 'Food' },
    { key: 'egg', label: 'Egg', Icon: PiEggFill, group: 'Food' },
    { key: 'avocado', label: 'Avocado', Icon: PiAvocadoFill, group: 'Food' },
    { key: 'pepper', label: 'Chilli', Icon: PiPepperFill, group: 'Food' },

    { key: 'scissors', label: 'Scissors', Icon: PiScissorsFill, group: 'Beauty & barber' },
    { key: 'razor', label: 'Razor', Icon: GiRazor, group: 'Beauty & barber' },
    { key: 'comb', label: 'Comb', Icon: GiComb, group: 'Beauty & barber' },
    { key: 'hair-dryer', label: 'Hair dryer', Icon: PiHairDryerFill, group: 'Beauty & barber' },
    { key: 'lipstick', label: 'Lipstick', Icon: GiLipstick, group: 'Beauty & barber' },
    { key: 'drop', label: 'Drop', Icon: PiDropFill, group: 'Beauty & barber' },
    { key: 'lotus', label: 'Lotus', Icon: PiFlowerLotusFill, group: 'Beauty & barber' },
    { key: 'flower', label: 'Flower', Icon: PiFlowerTulipFill, group: 'Beauty & barber' },

    { key: 'shopping-bag', label: 'Shopping', Icon: PiShoppingBagFill, group: 'Shops & services' },
    { key: 'sneaker', label: 'Trainer', Icon: PiSneakerFill, group: 'Shops & services' },
    { key: 't-shirt', label: 'T-shirt', Icon: PiTShirtFill, group: 'Shops & services' },
    { key: 'dress', label: 'Dress', Icon: PiDressFill, group: 'Shops & services' },
    { key: 'book', label: 'Book', Icon: PiBookOpenFill, group: 'Shops & services' },
    { key: 'music', label: 'Music', Icon: PiMusicNotesFill, group: 'Shops & services' },
    { key: 'game', label: 'Games', Icon: PiGameControllerFill, group: 'Shops & services' },
    { key: 'leaf', label: 'Leaf', Icon: PiLeafFill, group: 'Shops & services' },
    { key: 'plant', label: 'Plant', Icon: PiPlantFill, group: 'Shops & services' },
    { key: 'dumbbell', label: 'Gym', Icon: PiBarbellFill, group: 'Shops & services' },
    { key: 'bicycle', label: 'Bicycle', Icon: PiBicycleFill, group: 'Shops & services' },
    { key: 'car', label: 'Car', Icon: PiCarFill, group: 'Shops & services' },
    { key: 'wrench', label: 'Repairs', Icon: PiWrenchFill, group: 'Shops & services' },
    { key: 'tooth', label: 'Dental', Icon: PiToothFill, group: 'Shops & services' },
    { key: 'pill', label: 'Pharmacy', Icon: PiPillFill, group: 'Shops & services' },
    { key: 'paw', label: 'Paw', Icon: PiPawPrintFill, group: 'Shops & services' },
    { key: 'cat', label: 'Cat', Icon: PiCatFill, group: 'Shops & services' },
    { key: 'dog', label: 'Dog', Icon: PiDogFill, group: 'Shops & services' },
    { key: 'fish', label: 'Fish', Icon: PiFishFill, group: 'Shops & services' },
];

/** STAMP_ICONS by group, in order: [{ group, icons }]. */
export const STAMP_ICON_GROUPS = STAMP_ICONS.reduce((groups, icon) => {
    const last = groups[groups.length - 1];
    if (last?.group === icon.group) last.icons.push(icon);
    else groups.push({ group: icon.group, icons: [icon] });
    return groups;
}, []);

/** Icons that say nothing about the business: the card page / menu header shows a storefront instead. */
export const GENERIC_STAMP_ICONS = STAMP_ICONS.filter((icon) => icon.group === 'Rewards').map((icon) => icon.key);

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
