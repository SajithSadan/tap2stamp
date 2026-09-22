<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerShopCard;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerShopCard>
 */
class CustomerShopCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'shop_id' => Shop::factory(),
            'current_stamps' => 0,
            'rewards_claimed' => 0,
            'last_stamped_at' => null,
        ];
    }
}
