<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * What kind of business a shop is (shops.industry), for following up and
 * selling to shops by type. Asked on the sign-up card step, and once from
 * owners of older shops (a popup on the dashboard until it's answered).
 * "other" comes with the owner's own words in shops.industry_other.
 *
 * Icons: keys ↔ resources/js/lib/industryIcons.js - keep in sync.
 * Reward ideas are offered as one-tap answers on the sign-up card step.
 */
class Industries
{
    public const OTHER = 'other';

    public const GROUPS = [
        'food' => 'Food & drink',
        'beauty' => 'Beauty & wellbeing',
        'retail' => 'Shops',
        'services' => 'Services',
        'other' => 'Other',
    ];

    /** key => [label, group, reward ideas] */
    public const ALL = [
        'cafe' => ['Café / coffee shop', 'food', ['Free coffee', 'Free hot drink', 'Free cake or pastry']],
        'restaurant' => ['Restaurant', 'food', ['Free dessert', 'Free main course', '10% off your bill']],
        'takeaway' => ['Takeaway / fast food', 'food', ['Free meal', 'Free side', 'Free drink']],
        'bakery' => ['Bakery / sweet shop', 'food', ['Free pastry', 'Free slice of cake', 'Free loaf']],
        'dessert' => ['Juice / ice cream / dessert bar', 'food', ['Free juice', 'Free scoop', 'Free dessert']],
        'pub_bar' => ['Pub / bar', 'food', ['Free drink', 'Free pint', 'Free bar snack']],
        'barber' => ['Barber', 'beauty', ['Free haircut', 'Free beard trim', '50% off your next cut']],
        'salon' => ['Hair salon', 'beauty', ['Free blow-dry', 'Free haircut', '20% off colour']],
        'beauty' => ['Beauty parlour / nails / spa', 'beauty', ['Free manicure', 'Free facial', '20% off a treatment']],
        'gym' => ['Gym / fitness / yoga', 'beauty', ['Free class', 'Free day pass', 'Free PT session']],
        'clinic' => ['Health clinic / dental / physio', 'beauty', ['Free check-up', '10% off your next visit']],
        'pharmacy' => ['Pharmacy', 'beauty', ['10% off your next shop', 'Free health check']],
        'grocery' => ['Grocery / supermarket', 'retail', ['10% off your shop', 'Free item of your choice']],
        'butcher' => ['Butcher / fish / meat shop', 'retail', ['10% off your next order', 'Free item of your choice']],
        'mobile' => ['Mobile phones & electronics', 'retail', ['Free screen protector', 'Free phone case', '10% off accessories']],
        'fashion' => ['Clothing & fashion', 'retail', ['10% off your next buy', 'Free accessory']],
        'florist_gifts' => ['Florist / gift shop', 'retail', ['Free bouquet', '10% off your next buy']],
        'pet' => ['Pet shop / grooming', 'retail', ['Free groom', 'Free treats', '10% off your next shop']],
        'wholesale' => ['Wholesale / delivery', 'retail', ['Free delivery', '5% off your next order']],
        'laundry' => ['Laundry / dry cleaning', 'services', ['Free wash', 'Free shirt clean']],
        'car' => ['Car wash / garage', 'services', ['Free car wash', 'Free valet']],
        'repairs' => ['Repairs / tailoring', 'services', ['Free repair', '10% off your next job']],
        'classes' => ['Classes / tuition', 'services', ['Free class', '10% off next month']],
        self::OTHER => ['Something else', 'other', ['10% off your next visit', 'Free item of your choice']],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /** "Barber", or the owner's own words for "other"; null when not answered. */
    public static function label(?string $key, ?string $other = null): ?string
    {
        if ($key === null || ! isset(self::ALL[$key])) {
            return null;
        }

        return $key === self::OTHER && filled($other) ? $other : self::ALL[$key][0];
    }

    /** For the forms: [{key, label, group, group_label, rewards}], in display order. */
    public static function options(): array
    {
        return collect(self::ALL)->map(fn (array $industry, string $key) => [
            'key' => $key,
            'label' => $industry[0],
            'group' => $industry[1],
            'group_label' => self::GROUPS[$industry[1]],
            'rewards' => $industry[2],
        ])->values()->all();
    }

    /**
     * `$prefix`: the admin "Add shop" form names its fields shop_industry / shop_industry_other.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(bool $required = true, string $prefix = ''): array
    {
        return [
            "{$prefix}industry" => [$required ? 'required' : 'nullable', 'string', Rule::in(self::keys())],
            "{$prefix}industry_other" => ['nullable', "required_if:{$prefix}industry,".self::OTHER, 'string', 'max:80'],
        ];
    }

    public static function messages(string $prefix = ''): array
    {
        return [
            "{$prefix}industry.required" => 'Choose what kind of business it is.',
            "{$prefix}industry.in" => 'Choose what kind of business it is.',
            "{$prefix}industry_other.required_if" => 'Say what kind of business it is.',
        ];
    }

    /** The two columns to save: industry_other only goes with "other". */
    public static function attributes(array $validated, string $prefix = ''): array
    {
        $industry = $validated["{$prefix}industry"] ?? null;
        $other = trim((string) ($validated["{$prefix}industry_other"] ?? ''));

        return [
            'industry' => $industry,
            'industry_other' => $industry === self::OTHER && $other !== '' ? $other : null,
        ];
    }
}
