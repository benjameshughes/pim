<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Models\SyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncLog>
 */
class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    public function definition(): array
    {
        $actions = ['push', 'pull', 'create', 'update', 'delete'];
        $duration = fake()->numberBetween(100, 30000); // 100ms to 30s

        return [
            'sync_account_id' => SyncAccount::factory(),
            'product_id' => Product::factory(),
            'sync_status_id' => null,
            'action' => fake()->randomElement($actions),
            'status' => fake()->randomElement(['success', 'failed', 'warning']),
            'message' => fake()->optional()->sentence(),
            'details' => [
                'external_id' => fake()->optional()->uuid(),
                'batch_size' => fake()->optional()->numberBetween(1, 100),
            ],
            'started_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'completed_at' => fake()->optional()->dateTimeBetween('-1 hour', 'now'),
            'duration_ms' => $duration,
            'batch_id' => fake()->optional()->uuid(),
            'items_processed' => fake()->numberBetween(1, 10),
            'items_successful' => fake()->numberBetween(0, 10),
            'items_failed' => fake()->numberBetween(0, 3),
        ];
    }

    /**
     * Successful sync log
     */
    public function successful(): static
    {
        return $this->state([
            'status' => 'success',
            'message' => 'Operation completed successfully',
            'items_failed' => 0,
        ]);
    }

    /**
     * Failed sync log
     */
    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'message' => fake()->sentence(),
            'items_successful' => 0,
            'items_failed' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Push action
     */
    public function push(): static
    {
        return $this->state(['action' => 'push']);
    }

    /**
     * Pull action
     */
    public function pull(): static
    {
        return $this->state(['action' => 'pull']);
    }

    /**
     * Fast operation (under 1 second)
     */
    public function fast(): static
    {
        return $this->state([
            'duration_ms' => fake()->numberBetween(100, 1000),
        ]);
    }

    /**
     * Slow operation (over 10 seconds)
     */
    public function slow(): static
    {
        return $this->state([
            'duration_ms' => fake()->numberBetween(10000, 60000),
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

    /**
     * With sync status
     */
    public function withSyncStatus(): static
    {
        return $this->state([
            'sync_status_id' => SyncStatus::factory(),
        ]);
    }
}
