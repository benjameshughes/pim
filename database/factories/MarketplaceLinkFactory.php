<?php

namespace Database\Factories;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MarketplaceLink>
 */
class MarketplaceLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Default to product level for simplicity
        $product = Product::factory()->create();

        return [
            'linkable_type' => Product::class,
            'linkable_id' => $product->id,
            'sync_account_id' => SyncAccount::factory(),
            'parent_link_id' => null,
            'internal_sku' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{3}'),
            'external_sku' => $this->faker->unique()->regexify('EXT-[A-Z]{3}-[0-9]{3}'),
            'external_product_id' => $this->faker->optional()->regexify('prod_[a-z0-9]{8}'),
            'external_variant_id' => null,
            'link_status' => $this->faker->randomElement(['pending', 'linked', 'failed', 'unlinked']),
            'link_level' => 'product',
            'marketplace_data' => $this->faker->optional()->randomElement([
                ['title' => $this->faker->sentence(), 'price' => $this->faker->numberBetween(100, 10000)],
                null,
            ]),
            'linked_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'linked_by' => $this->faker->optional()->name(),
        ];
    }

    /**
     * Create a product-level marketplace link
     */
    public function product(): static
    {
        return $this->state(function (array $attributes) {
            $product = Product::factory()->create();

            return [
                'linkable_type' => Product::class,
                'linkable_id' => $product->id,
                'link_level' => 'product',
                'parent_link_id' => null,
                'external_variant_id' => null,
            ];
        });
    }

    /**
     * Create a variant-level marketplace link
     */
    public function variant(?MarketplaceLink $parentLink = null): static
    {
        return $this->state(function (array $attributes) use ($parentLink) {
            if ($parentLink) {
                $product = $parentLink->linkable;
                $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
            } else {
                $product = Product::factory()->create();
                $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
            }

            return [
                'linkable_type' => ProductVariant::class,
                'linkable_id' => $variant->id,
                'sync_account_id' => $parentLink?->sync_account_id ?? $attributes['sync_account_id'],
                'link_level' => 'variant',
                'parent_link_id' => $parentLink?->id,
            ];
        });
    }

    /**
     * Create a linked marketplace link
     */
    public function linked(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'link_status' => 'linked',
                'linked_at' => now(),
                'linked_by' => $this->faker->name(),
            ];
        });
    }

    /**
     * Create a pending marketplace link
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'link_status' => 'pending',
                'linked_at' => null,
                'linked_by' => null,
            ];
        });
    }

    /**
     * Create a failed marketplace link
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'link_status' => 'failed',
                'linked_at' => null,
                'linked_by' => null,
            ];
        });
    }
}
