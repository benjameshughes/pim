<?php

namespace Database\Factories;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'quantity' => fake()->numberBetween(0, 100),
            'reserved' => fake()->numberBetween(0, 20),
            'incoming' => fake()->numberBetween(0, 50),
            'minimum_level' => fake()->numberBetween(1, 10),
            'maximum_level' => fake()->numberBetween(50, 200),
            'location' => fake()->randomElement(['warehouse_a', 'warehouse_b', 'store_front', null]),
            'bin_location' => fake()->optional()->bothify('A##-B##'),
            'status' => fake()->randomElement(['available', 'reserved', 'damaged', 'pending']),
            'track_stock' => fake()->boolean(80), // 80% chance of true
            'last_counted_at' => fake()->optional()->dateTimeBetween('-6 months', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Stock that is available
     */
    public function available()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'available',
            ];
        });
    }

    /**
     * Stock with low quantity
     */
    public function lowStock()
    {
        return $this->state(function (array $attributes) {
            return [
                'quantity' => 2,
                'minimum_level' => 5,
                'status' => 'available',
            ];
        });
    }

    /**
     * Stock with high quantity
     */
    public function highStock()
    {
        return $this->state(function (array $attributes) {
            return [
                'quantity' => 150,
                'minimum_level' => 10,
                'maximum_level' => 100,
                'status' => 'available',
            ];
        });
    }
}
