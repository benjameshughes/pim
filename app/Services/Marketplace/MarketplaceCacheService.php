<?php

namespace App\Services\Marketplace;

use App\Models\SyncAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 📦 MARKETPLACE CACHE SERVICE
 *
 * Manages 24-hour cache validation for marketplace data synchronization.
 * Prevents unnecessary API calls by checking if sync data is still fresh.
 */
class MarketplaceCacheService
{
    public const CACHE_TTL = 86400; // 24 hours in seconds

    public const CACHE_PREFIX = 'marketplace_sync_';

    /**
     * Check if marketplace data needs refreshing based on 24-hour cache policy
     */
    public function needsRefresh(SyncAccount $syncAccount): bool
    {
        $cacheKey = $this->getCacheKey($syncAccount);
        $lastSyncTime = $this->getLastSyncTime($syncAccount);

        Log::debug('Checking marketplace cache status', [
            'sync_account_id' => $syncAccount->id,
            'cache_key' => $cacheKey,
            'last_sync_time' => $lastSyncTime?->toISOString(),
        ]);

        // If no sync time recorded, we definitely need to refresh
        if (! $lastSyncTime) {
            Log::info('No previous sync found - refresh needed', [
                'sync_account_id' => $syncAccount->id,
            ]);

            return true;
        }

        // Check if 24 hours have passed since last sync
        $hoursSinceLastSync = Carbon::now()->diffInHours($lastSyncTime);
        $needsRefresh = $hoursSinceLastSync >= 24;

        Log::info('Cache validation result', [
            'sync_account_id' => $syncAccount->id,
            'hours_since_last_sync' => $hoursSinceLastSync,
            'needs_refresh' => $needsRefresh,
        ]);

        return $needsRefresh;
    }

    /**
     * Get the last sync time from cache or database
     */
    public function getLastSyncTime(SyncAccount $syncAccount): ?Carbon
    {
        // First check cache for the most recent sync time
        $cacheKey = $this->getCacheKey($syncAccount);
        $cachedTime = Cache::get($cacheKey);

        if ($cachedTime) {
            return Carbon::parse($cachedTime);
        }

        // Fallback to database settings
        $settings = $syncAccount->settings ?? [];
        $lastMarketplaceSync = $settings['last_marketplace_sync'] ?? null;

        if ($lastMarketplaceSync) {
            $syncTime = Carbon::parse($lastMarketplaceSync);

            // Store in cache for faster future lookups
            Cache::put($cacheKey, $syncTime->toISOString(), self::CACHE_TTL);

            return $syncTime;
        }

        return null;
    }

    /**
     * Update the last sync time in both cache and database
     */
    public function updateLastSyncTime(SyncAccount $syncAccount): void
    {
        $now = Carbon::now();
        $cacheKey = $this->getCacheKey($syncAccount);

        // Update cache immediately
        Cache::put($cacheKey, $now->toISOString(), self::CACHE_TTL);

        // Update database settings
        $syncAccount->update([
            'settings' => array_merge(
                $syncAccount->settings ?? [],
                ['last_marketplace_sync' => $now->toISOString()]
            ),
        ]);

        Log::info('Updated marketplace sync timestamp', [
            'sync_account_id' => $syncAccount->id,
            'timestamp' => $now->toISOString(),
        ]);
    }

    /**
     * Get cache status information for display
     *
     * @return array<string, mixed>
     */
    public function getCacheStatus(SyncAccount $syncAccount): array
    {
        $lastSyncTime = $this->getLastSyncTime($syncAccount);

        if (! $lastSyncTime) {
            return [
                'status' => 'never_synced',
                'message' => 'No previous sync found',
                'needs_refresh' => true,
                'hours_since_sync' => null,
            ];
        }

        $hoursSinceLastSync = Carbon::now()->diffInHours($lastSyncTime);
        $needsRefresh = $hoursSinceLastSync >= 24;

        return [
            'status' => $needsRefresh ? 'expired' : 'fresh',
            'message' => $needsRefresh
                ? "Data is {$hoursSinceLastSync} hours old - refresh needed"
                : "Data is {$hoursSinceLastSync} hours old - still fresh",
            'needs_refresh' => $needsRefresh,
            'hours_since_sync' => $hoursSinceLastSync,
            'last_sync_time' => $lastSyncTime,
        ];
    }

    /**
     * Clear cache for a specific sync account
     */
    public function clearCache(SyncAccount $syncAccount): void
    {
        $cacheKey = $this->getCacheKey($syncAccount);
        Cache::forget($cacheKey);

        Log::info('Cleared marketplace sync cache', [
            'sync_account_id' => $syncAccount->id,
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Generate cache key for sync account
     */
    protected function getCacheKey(SyncAccount $syncAccount): string
    {
        return self::CACHE_PREFIX.$syncAccount->channel.'_'.$syncAccount->id;
    }

    /**
     * Get human-readable time until next refresh
     */
    public function getTimeUntilRefresh(SyncAccount $syncAccount): ?string
    {
        $lastSyncTime = $this->getLastSyncTime($syncAccount);

        if (! $lastSyncTime) {
            return null;
        }

        $nextRefreshTime = $lastSyncTime->addHours(24);
        $now = Carbon::now();

        if ($now->gte($nextRefreshTime)) {
            return 'Refresh available now';
        }

        return $now->diffForHumans($nextRefreshTime, ['parts' => 1]).' until next refresh';
    }
}
