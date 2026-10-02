<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SAVE####')),
            'discount_type' => Coupon::PERCENT,
            'discount_value' => 10,
            'once_per_shop' => true,
            'is_active' => true,
        ];
    }

    /** £$pence off instead of a percentage. */
    public function fixed(int $pence): static
    {
        return $this->state(['discount_type' => Coupon::FIXED, 'discount_value' => $pence]);
    }
}
