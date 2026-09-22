<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'max_stamps' => 6,
            'reward_title' => 'Free '.fake()->randomElement(['coffee', 'haircut', 'pastry', 'drink']),
            'google_review_url' => 'https://g.page/r/'.Str::random(20).'/review',
            'instagram_url' => 'https://instagram.com/'.Str::slug($name),
            'wifi_ssid' => Str::slug($name, '').'-Guest',
            'wifi_password' => Str::password(10, symbols: false),
        ];
    }
}
