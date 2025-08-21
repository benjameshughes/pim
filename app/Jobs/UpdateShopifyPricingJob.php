<?php

namespace App\Jobs;

use App\Actions\Shopify\Sync\UpdateShopifyPricingAction;
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
 * 💰 UPDATE SHOPIFY PRICING JOB
 *
 * Async job for updating Shopify product pricing.
 * Uses UpdateShopifyPricingAction to leverage MarketplaceLinks system.
 * Runs on dedicated pricing queue for better organization.
 */
class UpdateShopifyPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Product $product;
    public SyncAccount $syncAccount;
    public array $pricingOptions;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 180;

    /**
     * Number of retries
     */
    public int $tries = 2;

    public function __construct(Product $product, SyncAccount $syncAccount, array $pricingOptions = [])
    {
        $this->product = $product;
        $this->syncAccount = $syncAccount;
        $this->pricingOptions = $pricingOptions;

        // Set queue for pricing operations
        $this->onQueue("pricing-{$syncAccount->channel}");
    }

    /**
     * Execute the pricing update job
     */
    public function handle(UpdateShopifyPricingAction $pricingAction): void
    {
        Log::info('💰 Starting Shopify pricing update job', [
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'sync_account' => $this->syncAccount->name,
            'pricing_options' => $this->pricingOptions,
        ]);

        $startTime = microtime(true);

        // Create sync log entry
        $syncLog = SyncLog::createEntry(
            $this->syncAccount, 
            'pricing_update', 
            $this->product,
            null // No sync status for pricing updates
        );

        try {
            // Execute the pricing update action
            $result = $pricingAction->execute($this->product, array_merge(
                $this->pricingOptions,
                [
                    'sync_account_id' => $this->syncAccount->id,
                    'initiated_by' => 'pricing_update_job',
                ]
            ));

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            if ($result['success']) {
                // Mark sync log as successful
                $syncLog->markAsSuccessful(
                    $result['message'],
                    array_merge($result['data'], ['duration_ms' => $duration])
                );

                Log::info('✅ Shopify pricing update job completed successfully', [
                    'product_id' => $this->product->id,
                    'variants_updated' => $result['data']['variants_updated'] ?? 0,
                    'colors_updated' => $result['data']['colors_updated'] ?? 0,
                    'duration_ms' => $duration,
                ]);

            } else {
                // Mark sync log as failed
                $syncLog->markAsFailed(
                    $result['message'],
                    array_merge($result['data'] ?? [], ['duration_ms' => $duration])
                );

                Log::error('❌ Shopify pricing update job failed', [
                    'product_id' => $this->product->id,
                    'error' => $result['message'],
                    'duration_ms' => $duration,
                ]);

                // Don't throw exception - let the job complete
                // The UI will show the failure through sync logs
            }

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Mark sync log as failed
            $syncLog->markAsFailed(
                'Job exception: ' . $e->getMessage(),
                ['duration_ms' => $duration, 'exception' => get_class($e)]
            );

            Log::error('❌ Shopify pricing update job threw exception', [
                'product_id' => $this->product->id,
                'sync_account_id' => $this->syncAccount->id,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ]);

            // Re-throw to trigger job retry mechanism
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('💥 Shopify pricing update job failed permanently', [
            'product_id' => $this->product->id,
            'sync_account_id' => $this->syncAccount->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // You could dispatch a notification here if needed
        // Or trigger a webhook to notify external systems
    }

    /**
     * Get the tags for the job
     */
    public function tags(): array
    {
        return [
            'pricing-update',
            'shopify',
            "product:{$this->product->id}",
            "account:{$this->syncAccount->id}",
        ];
    }
}