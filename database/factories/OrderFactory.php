<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'product_name' => 'All-in-One Multi Link Stand',
            'quantity' => 1,
            'total_pence' => 4000,
            'payment_method' => PaymentMethod::Stripe,
            'status' => OrderStatus::Pending,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }
}
