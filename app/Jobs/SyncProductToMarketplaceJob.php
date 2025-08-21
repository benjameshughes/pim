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
 * 🚀 SYNC PRODUCT TO MARKETPLACE JOB
 *
 * Async job that uses your existing Actions to sync products to marketplaces.
 * Keeps code DRY by leveraging the comprehensive sync logic you already built.
 */
class SyncProductToMarketplaceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product $product;

    public SyncAccount $syncAccount;

    public array $options;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 300;

    /**
     * Number of retries
     */
    public int $tries = 3;

    public function __construct(Product $product, SyncAccount $syncAccount, array $options = [])
    {
        $this->product = $product;
        $this->syncAccount = $syncAccount;
        $this->options = $options;

        // Set queue based on channel for better organization
        $this->onQueue("sync-{$syncAccount->channel}");
    }

    /**
     * Execute the job using your existing Actions
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('🚀 Starting marketplace sync job', [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'channel' => $this->syncAccount->channel,
            'account' => $this->syncAccount->name,
            'job_id' => $this->job->getJobId(),
        ]);

        try {
            // Create sync log entry
            $syncLog = SyncLog::create([
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'action' => 'sync_to_marketplace',
                'status' => 'started',
                'started_at' => now(),
                'message' => "Starting sync to {$this->syncAccount->channel}",
                'details' => [
                    'job_id' => $this->job->getJobId(),
                    'options' => $this->options,
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
                'message' => $result['message'] ?? ($result['success'] ? 'Sync completed successfully' : 'Sync failed'),
                'details' => array_merge($syncLog->details ?? [], [
                    'result' => $result,
                    'duration_ms' => $duration,
                ]),
            ]);

            if ($result['success']) {
                Log::info('✅ Marketplace sync job completed successfully', [
                    'product_id' => $this->product->id,
                    'channel' => $this->syncAccount->channel,
                    'duration_ms' => $duration,
                    'external_id' => $result['data']['shopify_product_id'] ?? null,
                ]);
            } else {
                Log::warning('⚠️ Marketplace sync job completed with errors', [
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
                    'message' => 'Job failed: '.$e->getMessage(),
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

            Log::error('❌ Marketplace sync job failed', [
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
            'shopify' => $this->syncToShopify(),
            'ebay' => $this->syncToEbay(),
            'amazon' => $this->syncToAmazon(),
            'mirakl' => $this->syncToMirakl(),
            default => [
                'success' => false,
                'message' => "Unsupported marketplace channel: {$this->syncAccount->channel}",
            ]
        };
    }

    /**
     * Sync to Shopify using your existing Action
     */
    protected function syncToShopify(): array
    {
        $action = app(SimplifiedSyncProductToShopifyAction::class);

        $actionOptions = array_merge($this->options, [
            'method' => 'job', // Indicate this came from a job
            'sync_account_id' => $this->syncAccount->id,
        ]);

        return $action->execute($this->product, $actionOptions);
    }

    /**
     * Sync to eBay (placeholder for future implementation)
     */
    protected function syncToEbay(): array
    {
        // TODO: Implement when eBay sync Action is created
        return [
            'success' => false,
            'message' => 'eBay sync not yet implemented',
        ];
    }

    /**
     * Sync to Amazon (placeholder for future implementation)
     */
    protected function syncToAmazon(): array
    {
        // TODO: Implement when Amazon sync Action is created
        return [
            'success' => false,
            'message' => 'Amazon sync not yet implemented',
        ];
    }

    /**
     * Sync to Mirakl (placeholder for future implementation)
     */
    protected function syncToMirakl(): array
    {
        // TODO: Implement when Mirakl sync Action is created
        return [
            'success' => false,
            'message' => 'Mirakl sync not yet implemented',
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🔥 Marketplace sync job permanently failed', [
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
            'action' => 'sync_to_marketplace',
            'status' => 'failed',
            'started_at' => now(),
            'completed_at' => now(),
            'message' => 'Job permanently failed after '.$this->attempts().' attempts',
            'details' => [
                'final_error' => [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                'attempts' => $this->attempts(),
            ],
        ]);
    }

    /**
     * Get job tags for monitoring
     */
    public function tags(): array
    {
        return [
            'sync',
            'marketplace',
            $this->syncAccount->channel,
            "product:{$this->product->id}",
            "account:{$this->syncAccount->id}",
        ];
    }
}