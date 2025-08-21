<?php

namespace App\Services\Marketplace;

use App\Exceptions\MarketplaceSyncException;
use App\Models\SyncAccount;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 MARKETPLACE SYNC SERVICE
 *
 * Coordinates auto-sync functionality with cache validation.
 * Triggers marketplace data synchronization when needed.
 */
class MarketplaceSyncService
{
    public function __construct(
        protected MarketplaceCacheService $cacheService
    ) {}

    /**
     * Auto-sync marketplace data with cache validation
     *
     * @return array<string, mixed>
     */
    public function autoSync(SyncAccount $syncAccount): array
    {
        Log::info('Starting auto-sync for marketplace', [
            'sync_account_id' => $syncAccount->id,
            'channel' => $syncAccount->channel,
            'display_name' => $syncAccount->display_name,
        ]);

        // Check if sync is needed based on cache
        if (! $this->cacheService->needsRefresh($syncAccount)) {
            $cacheStatus = $this->cacheService->getCacheStatus($syncAccount);

            Log::info('Using cached data - no sync needed', [
                'sync_account_id' => $syncAccount->id,
                'cache_status' => $cacheStatus,
            ]);

            return [
                'success' => true,
                'used_cache' => true,
                'message' => 'Using cached data - still fresh',
                'cache_status' => $cacheStatus,
            ];
        }

        // Perform sync based on channel type
        return $this->performSync($syncAccount);
    }

    /**
     * Force sync regardless of cache status
     *
     * @return array<string, mixed>
     */
    public function forceSync(SyncAccount $syncAccount): array
    {
        Log::info('Force sync requested', [
            'sync_account_id' => $syncAccount->id,
            'channel' => $syncAccount->channel,
        ]);

        return $this->performSync($syncAccount, true);
    }

    /**
     * Perform the actual sync operation
     *
     * @return array<string, mixed>
     */
    protected function performSync(SyncAccount $syncAccount, bool $force = false): array
    {
        try {
            $result = match ($syncAccount->channel) {
                'shopify' => $this->syncShopify($syncAccount, $force),
                'ebay' => $this->syncEbay($syncAccount, $force),
                'amazon' => $this->syncAmazon($syncAccount, $force),
                default => throw new MarketplaceSyncException(
                    "Unsupported marketplace channel: {$syncAccount->channel}"
                ),
            };

            // Update cache timestamp on successful sync
            if ($result['success']) {
                $this->cacheService->updateLastSyncTime($syncAccount);
            }

            return $result;

        } catch (MarketplaceSyncException $e) {
            Log::error('Marketplace sync failed', [
                'sync_account_id' => $syncAccount->id,
                'channel' => $syncAccount->channel,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync Shopify marketplace data
     *
     * @return array<string, mixed>
     */
    protected function syncShopify(SyncAccount $syncAccount, bool $force = false): array
    {
        Log::info('Starting Shopify sync', [
            'sync_account_id' => $syncAccount->id,
            'force' => $force,
        ]);

        try {
            // Run the Shopify sync artisan command
            $exitCode = Artisan::call('shopify:sync-marketplace', [
                '--store' => [$syncAccount->name],
            ]);

            if ($exitCode === 0) {
                $artisanOutput = Artisan::output();

                Log::info('Shopify sync completed successfully', [
                    'sync_account_id' => $syncAccount->id,
                    'exit_code' => $exitCode,
                ]);

                return [
                    'success' => true,
                    'used_cache' => false,
                    'message' => 'Shopify data synchronized successfully',
                    'details' => $this->parseArtisanOutput($artisanOutput),
                ];
            }

            throw new MarketplaceSyncException(
                "Shopify sync failed with exit code: {$exitCode}"
            );

        } catch (\Exception $e) {
            throw new MarketplaceSyncException(
                "Shopify sync error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Sync eBay marketplace data
     *
     * @return array<string, mixed>
     */
    protected function syncEbay(SyncAccount $syncAccount, bool $force = false): array
    {
        Log::info('eBay sync not yet implemented', [
            'sync_account_id' => $syncAccount->id,
        ]);

        throw new MarketplaceSyncException(
            'eBay marketplace sync is not yet implemented'
        );
    }

    /**
     * Sync Amazon marketplace data
     *
     * @return array<string, mixed>
     */
    protected function syncAmazon(SyncAccount $syncAccount, bool $force = false): array
    {
        Log::info('Amazon sync not yet implemented', [
            'sync_account_id' => $syncAccount->id,
        ]);

        throw new MarketplaceSyncException(
            'Amazon marketplace sync is not yet implemented'
        );
    }

    /**
     * Parse artisan command output to extract useful information
     *
     * @return array<string, mixed>
     */
    protected function parseArtisanOutput(string $output): array
    {
        $details = [
            'raw_output' => $output,
            'products_processed' => 0,
            'variants_processed' => 0,
            'links_created' => 0,
            'links_updated' => 0,
            'errors' => 0,
        ];

        // Extract statistics from the output using regex
        $patterns = [
            'products_processed' => '/Products Processed.*?(\d+)/i',
            'variants_processed' => '/Variants Processed.*?(\d+)/i',
            'links_created' => '/Links Created.*?(\d+)/i',
            'links_updated' => '/Links Updated.*?(\d+)/i',
            'errors' => '/Errors.*?(\d+)/i',
        ];

        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $output, $matches)) {
                $details[$key] = (int) str_replace(',', '', $matches[1]);
            }
        }

        return $details;
    }

    /**
     * Get sync status information
     *
     * @return array<string, mixed>
     */
    public function getSyncStatus(SyncAccount $syncAccount): array
    {
        $cacheStatus = $this->cacheService->getCacheStatus($syncAccount);

        return [
            'sync_account' => [
                'id' => $syncAccount->id,
                'channel' => $syncAccount->channel,
                'display_name' => $syncAccount->display_name,
                'is_active' => $syncAccount->is_active,
            ],
            'cache_status' => $cacheStatus,
            'time_until_refresh' => $this->cacheService->getTimeUntilRefresh($syncAccount),
        ];
    }

    /**
     * Clear cache and force next sync
     */
    public function clearCache(SyncAccount $syncAccount): void
    {
        $this->cacheService->clearCache($syncAccount);

        Log::info('Marketplace sync cache cleared', [
            'sync_account_id' => $syncAccount->id,
            'channel' => $syncAccount->channel,
        ]);
    }
}
