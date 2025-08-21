<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Models\SyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncStatus>
 */
class SyncStatusFactory extends Factory
{
    protected $model = SyncStatus::class;

    public function definition(): array
    {
        return [
            'sync_account_id' => SyncAccount::factory(),
            'product_id' => Product::factory(),
            'product_variant_id' => null,
            'color' => fake()->optional()->colorName(),
            'sync_status' => fake()->randomElement(['pending', 'synced', 'failed', 'out_of_sync']),
            'external_product_id' => fake()->optional()->uuid(),
            'external_variant_id' => fake()->optional()->uuid(),
            'external_handle' => fake()->optional()->slug(),
            'last_synced_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'sync_type' => fake()->randomElement(['standard', 'color_separated']),
            'error_message' => fake()->optional()->sentence(),
            'metadata' => [
                'attempts' => fake()->numberBetween(1, 3),
                'external_url' => fake()->optional()->url(),
            ],
        ];
    }

    /**
     * Synced status
     */
    public function synced(): static
    {
        return $this->state([
            'sync_status' => 'synced',
            'last_synced_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'error_message' => null,
        ]);
    }

    /**
     * Failed status
     */
    public function failed(): static
    {
        return $this->state([
            'sync_status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }

    /**
     * Pending status
     */
    public function pending(): static
    {
        return $this->state([
            'sync_status' => 'pending',
            'last_synced_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * With variant
     */
    public function withVariant(): static
    {
        return $this->state([
            'product_variant_id' => ProductVariant::factory(),
        ]);
    }

    /**
     * For Shopify channel
     */
    public function shopify(): static
    {
        return $this->for(SyncAccount::factory()->shopify());
    }

    /**
     * For eBay channel
     */
    public function ebay(): static
    {
        return $this->for(SyncAccount::factory()->ebay());
    }
}
