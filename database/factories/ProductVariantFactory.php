<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'title' => $this->faker->words(2, true),
            'sku' => $this->faker->unique()->numerify('VAR-###'),
            'color' => $this->faker->randomElement(['Red', 'Blue', 'Green', 'Black', 'White']),
            'width' => $this->faker->numberBetween(50, 200),
            'drop' => $this->faker->numberBetween(100, 300),
            'price' => $this->faker->randomFloat(2, 10, 100),
            'stock_level' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['active', 'inactive']),
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

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
