<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pricing>
 */
class PricingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => \App\Models\ProductVariant::factory(),
            'sales_channel_id' => \App\Models\SalesChannel::factory(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'cost_price' => $this->faker->randomFloat(2, 5, 250),
            'discount_price' => $this->faker->optional()->randomFloat(2, 8, 400),
            'margin_percentage' => $this->faker->randomFloat(2, 10, 70),
            'currency' => 'GBP',
        ];
    }
}
