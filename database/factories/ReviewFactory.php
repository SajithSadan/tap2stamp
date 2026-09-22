<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Review;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
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
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
