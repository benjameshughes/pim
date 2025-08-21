<?php

namespace Database\Factories;

use App\Models\Barcode;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class BarcodeFactory extends Factory
{
    protected $model = Barcode::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'barcode' => $this->faker->unique()->ean13(),
            'type' => $this->faker->randomElement(['EAN13', 'UPC', 'CODE128']),
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

    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn (array $attributes) => [
            'product_variant_id' => $variant->id,
        ]);
    }
}
