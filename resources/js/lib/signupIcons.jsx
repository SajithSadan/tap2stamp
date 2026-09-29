import {
    GiBeerStein,
    GiBread,
    GiCakeSlice,
    GiCoffeeBeans,
    GiCoffeeCup,
    GiComb,
    GiCroissant,
    GiCrown,
    GiCupcake,
    GiDonut,
    GiFlowerPot,
    GiFrenchFries,
    GiGems,
    GiHamburger,
    GiHearts,
    GiIceCreamCone,
    GiLeafSwirl,
    GiLipstick,
    GiMartini,
    GiNoodles,
    GiPawPrint,
    GiPerfumeBottle,
    GiPizzaSlice,
    GiPresent,
    GiRazor,
    GiRose,
    GiScissors,
    GiShoppingBag,
    GiSodaCan,
    GiSparkles,
    GiStarsStack,
    GiSunflower,
    GiSushis,
    GiTShirt,
    GiTeapot,
    GiThreeLeaves,
    GiWeightLiftingUp,
    GiWineGlass,
} from 'react-icons/gi';

// The decorative icon on the customer sign-up screen (shops.signup_icon) -
// solid "game icons", the same style as the original coffee beans. Keys must
// match App\Support\SignupIcons::KEYS - keep the two lists in sync.
export const SIGNUP_ICON_GROUPS = [
    {
        label: 'Neutral',
        icons: [
            ['sparkles', 'Sparkles', GiSparkles],
            ['leaf-swirl', 'Swirl', GiLeafSwirl],
            ['leaves', 'Leaves', GiThreeLeaves],
            ['hearts', 'Hearts', GiHearts],
            ['stars', 'Stars', GiStarsStack],
            ['crown', 'Crown', GiCrown],
            ['gems', 'Gems', GiGems],
            ['gift', 'Gift', GiPresent],
        ],
    },
    {
        label: 'Cafe & bakery',
        icons: [
            ['coffee-beans', 'Coffee beans', GiCoffeeBeans],
            ['coffee-cup', 'Coffee', GiCoffeeCup],
            ['teapot', 'Tea', GiTeapot],
            ['croissant', 'Croissant', GiCroissant],
            ['bread', 'Bread', GiBread],
            ['cupcake', 'Cupcake', GiCupcake],
            ['cake', 'Cake', GiCakeSlice],
            ['donut', 'Donut', GiDonut],
            ['ice-cream', 'Ice cream', GiIceCreamCone],
        ],
    },
    {
        label: 'Food',
        icons: [
            ['pizza', 'Pizza', GiPizzaSlice],
            ['burger', 'Burger', GiHamburger],
            ['fries', 'Fries', GiFrenchFries],
            ['noodles', 'Noodles', GiNoodles],
            ['sushi', 'Sushi', GiSushis],
        ],
    },
    {
        label: 'Drinks',
        icons: [
            ['beer', 'Beer', GiBeerStein],
            ['wine', 'Wine', GiWineGlass],
            ['cocktail', 'Cocktail', GiMartini],
            ['soda', 'Soda', GiSodaCan],
        ],
    },
    {
        label: 'Beauty & barber',
        icons: [
            ['scissors', 'Scissors', GiScissors],
            ['razor', 'Razor', GiRazor],
            ['comb', 'Comb', GiComb],
            ['lipstick', 'Lipstick', GiLipstick],
            ['perfume', 'Perfume', GiPerfumeBottle],
        ],
    },
    {
        label: 'Other shops',
        icons: [
            ['fitness', 'Fitness', GiWeightLiftingUp],
            ['paw', 'Pets', GiPawPrint],
            ['flower', 'Flowers', GiSunflower],
            ['rose', 'Rose', GiRose],
            ['plant', 'Plants', GiFlowerPot],
            ['shopping-bag', 'Shopping', GiShoppingBag],
            ['t-shirt', 'Clothing', GiTShirt],
        ],
    },
];

const BY_KEY = Object.fromEntries(SIGNUP_ICON_GROUPS.flatMap((g) => g.icons.map(([key, , Icon]) => [key, Icon])));

/** The shop's sign-up icon; unknown keys fall back to the neutral sparkles. */
export function SignupIcon({ icon, ...props }) {
    const Icon = BY_KEY[icon] ?? GiSparkles;
    return <Icon aria-hidden="true" {...props} />;
}
