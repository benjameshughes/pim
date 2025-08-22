<?php

namespace Database\Factories;

use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesChannel>
 */
class SalesChannelFactory extends Factory
{
    protected $model = SalesChannel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Shopify Store', 'eBay Shop', 'Amazon Marketplace', 'Direct Sales']),
            'type' => fake()->randomElement(['online', 'marketplace', 'retail']),
            'currency' => fake()->randomElement(['GBP', 'USD', 'EUR']),
            'is_active' => true,
        ];
    }

    public function shopify(): static
    {
        return $this->state([
            'name' => 'Shopify Store',
            'type' => 'online',
            'currency' => 'GBP',
        ]);
    }

    public function ebay(): static
    {
        return $this->state([
            'name' => 'eBay Shop',
            'type' => 'marketplace',
            'currency' => 'GBP',
        ]);
    }
}