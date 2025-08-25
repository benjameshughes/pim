<?php

namespace App\Jobs;

use App\Actions\Shopify\Sync\SimplifiedSyncProductToShopifyAction;
use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 📤 UPDATE MARKETPLACE LISTING JOB
 *
 * Async job for updating existing marketplace listings.
 * Uses your existing Actions with update-specific options to keep code DRY.
 */
class UpdateMarketplaceListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product $product;

    public SyncAccount $syncAccount;

    public array $updateOptions;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 200;

    /**
     * Number of retries
     */
    public int $tries = 2;

    public function __construct(Product $product, SyncAccount $syncAccount, array $updateOptions = [])
    {
        $this->product = $product;
        $this->syncAccount = $syncAccount;
        $this->updateOptions = $updateOptions;

        // Set queue based on channel for better organization
        $this->onQueue("update-{$syncAccount->channel}");
    }

    /**
     * Execute the job using your existing Actions
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('📤 Starting marketplace listing update job', [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'channel' => $this->syncAccount->channel,
            'account' => $this->syncAccount->name,
            'update_options' => $this->updateOptions,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            // Create sync log entry
            $syncLog = SyncLog::create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'action' => 'update_marketplace_listing',
                'status' => 'started',
                'started_at' => now(),
                'message' => "Starting listing update for {$this->syncAccount->channel}",
                'details' => [
                    'job_id' => $this->job->getJobId(),
                    'update_options' => $this->updateOptions,
                ],
            ]);

            // Route to the appropriate Action based on marketplace
            $result = $this->routeToMarketplaceAction();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Update sync log with results
            $syncLog->update([
                'status' => $result['success'] ? 'success' : 'failed',
                'completed_at' => now(),
                'duration_ms' => (int) $duration,
                'message' => $result['message'] ?? ($result['success'] ? 'Listing updated successfully' : 'Update failed'),
                'details' => array_merge($syncLog->details ?? [], [
                    'result' => $result,
                    'duration_ms' => $duration,
                ]),
            ]);

            if ($result['success']) {
                Log::info('✅ Marketplace listing update job completed successfully', [
                    'product_id' => $this->product->id,
                    'channel' => $this->syncAccount->channel,
                    'duration_ms' => $duration,
                    'changes_made' => $result['data']['changes_made'] ?? [],
                ]);
            } else {
                Log::warning('⚠️ Marketplace listing update job completed with errors', [
                    'product_id' => $this->product->id,
                    'channel' => $this->syncAccount->channel,
                    'error' => $result['message'] ?? 'Unknown error',
                    'duration_ms' => $duration,
                ]);
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Update sync log with failure
            if (isset($syncLog)) {
                $syncLog->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                    'duration_ms' => (int) $duration,
                    'message' => 'Update job failed: '.$e->getMessage(),
                    'details' => array_merge($syncLog->details ?? [], [
                        'error' => [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ],
                        'duration_ms' => $duration,
                    ]),
                ]);
            }

            Log::error('❌ Marketplace listing update job failed', [
                'product_id' => $this->product->id,
                'channel' => $this->syncAccount->channel,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            throw $e;
        }
    }

    /**
     * Route to the appropriate marketplace Action
     */
    protected function routeToMarketplaceAction(): array
    {
        return match ($this->syncAccount->channel) {
            'shopify' => $this->updateShopifyListing(),
            'ebay' => $this->updateEbayListing(),
            'amazon' => $this->updateAmazonListing(),
            'mirakl' => $this->updateMiraklListing(),
            default => [
                'success' => false,
                'message' => "Unsupported marketplace channel: {$this->syncAccount->channel}",
            ]
        };
    }

    /**
     * Update Shopify listing using your existing Action
     */
    protected function updateShopifyListing(): array
    {
        $action = app(SimplifiedSyncProductToShopifyAction::class);

        // Use the same Action but with update-specific options
        $actionOptions = array_merge($this->updateOptions, [
            'method' => 'update_job', // Indicate this is an update from a job
            'force' => true, // Force update even if product seems up-to-date
            'sync_account_id' => $this->syncAccount->id,
            'update_type' => $this->determineUpdateType(),
        ]);

        return $action->execute($this->product, $actionOptions);
    }

    /**
     * Update eBay listing (placeholder for future implementation)
     */
    protected function updateEbayListing(): array
    {
        // TODO: Implement when eBay update Action is created
        return [
            'success' => false,
            'message' => 'eBay listing update not yet implemented',
        ];
    }

    /**
     * Update Amazon listing (placeholder for future implementation)
     */
    protected function updateAmazonListing(): array
    {
        // TODO: Implement when Amazon update Action is created
        return [
            'success' => false,
            'message' => 'Amazon listing update not yet implemented',
        ];
    }

    /**
     * Update Mirakl listing (placeholder for future implementation)
     */
    protected function updateMiraklListing(): array
    {
        // TODO: Implement when Mirakl update Action is created
        return [
            'success' => false,
            'message' => 'Mirakl listing update not yet implemented',
        ];
    }

    /**
     * Determine the type of update based on options
     */
    protected function determineUpdateType(): string
    {
        if (isset($this->updateOptions['update_type'])) {
            return $this->updateOptions['update_type'];
        }

        // Determine update type based on what's being updated
        if (isset($this->updateOptions['update_pricing'])) {
            return 'pricing';
        }

        if (isset($this->updateOptions['update_inventory'])) {
            return 'inventory';
        }

        if (isset($this->updateOptions['update_content'])) {
            return 'content';
        }

        return 'full'; // Default to full update
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🔥 Marketplace listing update job permanently failed', [
            'product_id' => $this->product->id,
            'channel' => $this->syncAccount->channel,
            'account' => $this->syncAccount->name,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Create final failure log entry
        SyncLog::create([
            'product_id' => $this->product->id,
            'sync_account_id' => $this->syncAccount->id,
            'action' => 'update_marketplace_listing',
            'status' => 'failed',
            'started_at' => now(),
            'completed_at' => now(),
            'message' => 'Update job permanently failed after '.$this->attempts().' attempts',
            'details' => [
                'final_error' => [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                'attempts' => $this->attempts(),
                'update_options' => $this->updateOptions,
            ],
        ]);
    }

    /**
     * Get job tags for monitoring
     */
    public function tags(): array
    {
        return [
            'update',
            'marketplace',
            $this->syncAccount->channel,
            "product:{$this->product->id}",
            "account:{$this->syncAccount->id}",
            $this->determineUpdateType(),
        ];
    }
}
