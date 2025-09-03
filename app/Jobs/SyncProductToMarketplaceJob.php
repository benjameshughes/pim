<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Models\SyncLog;
use App\Services\Marketplace\Facades\Sync;
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

    public string $operationType;

    public array $operationData;

    /**
     * Job timeout in seconds
     */
    public int $timeout = 300;

    /**
     * Number of retries
     */
    public int $tries = 3;

    public function __construct(Product $product, SyncAccount $syncAccount, string $operationType, array $operationData = [])
    {
        $this->product = $product;
        $this->syncAccount = $syncAccount;
        $this->operationType = $operationType;
        $this->operationData = $operationData;

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
                'channel' => $this->syncAccount->channel,
                'operation' => $this->operationType,
                'sync_type' => 'job',
                'status' => 'processing',
                'metadata' => [
                    'job_id' => $this->job->getJobId(),
                    'operation_data' => $this->operationData,
                    'started_at' => now()->toISOString(),
                ],
            ]);

            // Route to the appropriate Action based on marketplace
            $result = $this->routeToMarketplaceAction();

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Update sync log with results
            $syncLog->update([
                'status' => $result['success'] ? 'success' : 'failed',
                'duration_ms' => (int) $duration,
                'response_data' => json_encode($result['data'] ?? []),
                'error_message' => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
                'metadata' => array_merge($syncLog->metadata ?? [], [
                    'result' => $result,
                    'duration_ms' => $duration,
                    'completed_at' => now()->toISOString(),
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
                    'error_message' => 'Job failed: '.$e->getMessage(),
                    'duration_ms' => (int) $duration,
                    'metadata' => array_merge($syncLog->metadata ?? [], [
                        'error' => [
                            'type' => get_class($e),
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ],
                        'duration_ms' => $duration,
                        'completed_at' => now()->toISOString(),
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
     * Route to the appropriate marketplace using Sync facade
     */
    protected function routeToMarketplaceAction(): array
    {
        try {
            // Get the marketplace adapter
            $adapter = Sync::marketplace($this->syncAccount->channel, $this->syncAccount->name);
            
            // Configure the operation based on type and data
            $adapter = $this->configureAdapter($adapter);
            
            // Execute the operation
            $result = $adapter->push();
            
            return [
                'success' => $result->isSuccess(),
                'message' => $result->getMessage(),
                'data' => $result->getData(),
                'errors' => $result->getErrors(),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Sync operation failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()],
            ];
        }
    }
    
    /**
     * Configure the adapter with the operation type and data
     */
    protected function configureAdapter($adapter)
    {
        return match ($this->operationType) {
            'create' => $adapter->create($this->product->id),
            'update' => $this->configureUpdateOperation($adapter),
            'fullUpdate' => $adapter->fullUpdate($this->product->id),
            'delete' => $adapter->delete($this->product->id),
            'link' => $adapter->link($this->product->id),
            default => throw new \InvalidArgumentException("Unsupported operation type: {$this->operationType}")
        };
    }
    
    /**
     * Configure update operation with specific fields
     */
    protected function configureUpdateOperation($adapter)
    {
        $adapter = $adapter->update($this->product->id);
        
        // Apply specific update fields if provided
        foreach ($this->operationData as $field => $value) {
            match ($field) {
                'pricing' => $adapter = $adapter->pricing($value),
                'title' => $adapter = $adapter->title($value),
                'images' => $adapter = $adapter->images($value),
                default => null // Ignore unknown fields
            };
        }
        
        return $adapter;
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
            'channel' => $this->syncAccount->channel,
            'operation' => $this->operationType,
            'sync_type' => 'job',
            'status' => 'failed',
            'error_message' => 'Job permanently failed after '.$this->attempts().' attempts',
            'metadata' => [
                'final_error' => [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                'attempts' => $this->attempts(),
                'permanently_failed' => true,
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
