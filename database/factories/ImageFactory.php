<?php

namespace Database\Factories;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Image>
 */
class ImageFactory extends Factory
{
    protected $model = Image::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = $this->faker->slug(2) . '.jpg';
        
        return [
            'filename' => $filename,
            'path' => 'images/' . $filename,
            'url' => 'https://example.com/storage/images/' . $filename,
            'size' => $this->faker->numberBetween(10240, 5242880), // 10KB to 5MB
            'mime_type' => $this->faker->randomElement(['image/jpeg', 'image/png', 'image/webp', 'image/gif']),
            'is_primary' => false,
            'sort_order' => $this->faker->numberBetween(0, 10),
            // DAM metadata
            'title' => $this->faker->optional(0.7)->sentence(3),
            'alt_text' => $this->faker->optional(0.5)->sentence(4),
            'description' => $this->faker->optional(0.3)->paragraph(),
            'folder' => $this->faker->randomElement(['products', 'variants', 'general', 'uncategorized', 'hero', 'gallery']),
            'tags' => $this->faker->optional(0.6)->randomElements([
                'product', 'hero', 'gallery', 'banner', 'lifestyle', 'detail', 'swatch', 'color', 'texture', 'environment'
            ], $this->faker->numberBetween(1, 3)),
            'created_by_user_id' => \App\Models\User::factory(),
        ];
    }

    /**
     * Create a primary image
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * Indicate that the image is unattached (standalone in DAM)
     */
    public function unattached(): static
    {
        return $this->state(fn (array $attributes) => [
            'imageable_type' => null,
            'imageable_id' => null,
            'is_primary' => false,
        ]);
    }

    /**
     * Indicate that the image is in a specific folder
     */
    public function inFolder(string $folder): static
    {
        return $this->state(fn (array $attributes) => [
            'folder' => $folder,
        ]);
    }

    /**
     * Indicate that the image has specific tags
     */
    public function withTags(array $tags): static
    {
        return $this->state(fn (array $attributes) => [
            'tags' => $tags,
        ]);
    }

    /**
     * Indicate that the image has full metadata
     */
    public function withMetadata(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => $this->faker->sentence(3),
            'alt_text' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'tags' => $this->faker->randomElements([
                'product', 'hero', 'gallery', 'banner', 'lifestyle', 'detail'
            ], 2),
        ]);
    }

    /**
     * Create image with minimal metadata (like legacy uploads)
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'title' => null,
            'alt_text' => null,
            'description' => null,
            'folder' => 'uncategorized',
            'tags' => null,
        ]);
    }

    /**
     * Create image for a specific product
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'imageable_type' => Product::class,
            'imageable_id' => $product->id,
        ]);
    }

    /**
     * Create image for a specific variant
     */
    public function forVariant(ProductVariant $variant): static
    {
        return $this->state(fn (array $attributes) => [
            'imageable_type' => ProductVariant::class,
            'imageable_id' => $variant->id,
        ]);
    }

    /**
     * Create PNG image
     */
    public function png(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->word().'.png',
            'path' => $this->faker->uuid().'.png',
            'mime_type' => 'image/png',
        ]);
    }

    /**
     * Create WebP image
     */
    public function webp(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->word().'.webp',
            'path' => $this->faker->uuid().'.webp',
            'mime_type' => 'image/webp',
        ]);
    }

    /**
     * Create large image
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $this->faker->numberBetween(5000000, 10000000), // 5MB to 10MB
            'width' => $this->faker->numberBetween(2000, 4000),
            'height' => $this->faker->numberBetween(1500, 3000),
        ]);
    }
}
