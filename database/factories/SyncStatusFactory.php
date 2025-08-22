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
            'channel' => fake()->randomElement(['shopify', 'ebay', 'amazon']),
            'status' => fake()->randomElement(['never_synced', 'synced', 'needs_update', 'sync_failed']),
            'external_id' => fake()->optional()->uuid(),
            'last_synced_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'last_attempted_at' => fake()->optional()->dateTimeBetween('-1 day', 'now'),
            'last_error' => fake()->optional()->sentence(),
            'error_count' => fake()->numberBetween(0, 3),
            'product_checksum' => fake()->optional()->md5(),
            'pricing_checksum' => fake()->optional()->md5(),
            'inventory_checksum' => fake()->optional()->md5(),
            'sync_metadata' => [
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
            'status' => 'synced',
            'last_synced_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'last_error' => null,
            'error_count' => 0,
        ]);
    }

    /**
     * Failed status
     */
    public function failed(): static
    {
        return $this->state([
            'status' => 'sync_failed',
            'last_error' => fake()->sentence(),
            'error_count' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Pending status
     */
    public function pending(): static
    {
        return $this->state([
            'status' => 'never_synced',
            'last_synced_at' => null,
            'last_error' => null,
            'error_count' => 0,
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
