<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductImage>
 */
class ProductImageFactory extends Factory
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
            'variant_id' => null,
            'image_path' => 'product-images/test-image.jpg',
            'original_filename' => $this->faker->word().'.jpg',
            'image_type' => 'detail',
            'sort_order' => 1,
            'processing_status' => ProductImage::PROCESSING_COMPLETED,
            'storage_disk' => 'public',
            'file_size' => $this->faker->numberBetween(100000, 5000000),
            'mime_type' => 'image/jpeg',
            'dimensions' => [
                'width' => 800,
                'height' => 600,
            ],
            'metadata' => [],
            'alt_text' => $this->faker->sentence(3),
        ];
    }

    /**
     * Create a variant image instead of product image
     */
    public function forVariant(?ProductVariant $variant = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => null,
            'variant_id' => $variant?->id ?? ProductVariant::factory(),
            'image_type' => 'detail',
        ]);
    }

    /**
     * Create an image with pending status
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProductImage::PROCESSING_PENDING,
        ]);
    }

    /**
     * Create an image with failed status
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'processing_status' => ProductImage::PROCESSING_FAILED,
        ]);
    }
}
