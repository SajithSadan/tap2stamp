<?php

namespace Database\Factories;

use App\Enums\ActionType;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\StampLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StampLog>
 */
class StampLogFactory extends Factory
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
            'action_type' => ActionType::StampAdded,
        ];
    }
}
