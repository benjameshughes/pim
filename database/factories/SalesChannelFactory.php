<?php

namespace Database\Factories;

use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesChannelFactory extends Factory
{
    protected $model = SalesChannel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Shopify', 'eBay', 'Direct Sales', 'Amazon']),
            'code' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'config' => json_encode([]),
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}