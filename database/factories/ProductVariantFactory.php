<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper($this->faker->bothify('SKU-####-???')),
            'color' => $this->faker->colorName(),
            'size' => $this->faker->randomElement(['XS', 'S', 'M', 'L', 'XL', 'XXL']),
            'stock_level' => $this->faker->numberBetween(0, 100),
            'package_length' => $this->faker->randomFloat(2, 10, 100),
            'package_width' => $this->faker->randomFloat(2, 10, 100),
            'package_height' => $this->faker->randomFloat(2, 5, 50),
            'package_weight' => $this->faker->randomFloat(2, 0.1, 10),
            'status' => $this->faker->randomElement(['active', 'inactive', 'out_of_stock']),
        ];
    }
}
