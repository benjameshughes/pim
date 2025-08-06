<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportDataCacheService
{
    private const CACHE_PREFIX = 'import_data_';
    private const DEFAULT_TTL = 3600; // 1 hour

    /**
     * Generate a unique session ID for the current import
     */
    public function getImportSessionId(): string
    {
        return session()->getId() . '_' . auth()->id() . '_' . now()->timestamp;
    }

    /**
     * Store worksheet analysis data in cache
     */
    public function storeWorksheetAnalysis(array $analysis): string
    {
        $cacheKey = self::CACHE_PREFIX . 'analysis_' . Str::random(16);
        
        Cache::put($cacheKey, $analysis, now()->addSeconds(self::DEFAULT_TTL));
        
        Log::info('Stored worksheet analysis in cache', [
            'cache_key' => $cacheKey,
            'worksheets_count' => count($analysis['worksheets'] ?? [])
        ]);
        
        return $cacheKey;
    }

    /**
     * Retrieve worksheet analysis from cache
     */
    public function getWorksheetAnalysis(string $cacheKey): array
    {
        return Cache::get($cacheKey, []);
    }

    /**
     * Store sample data in cache
     */
    public function storeSampleData(array $sampleData): string
    {
        $cacheKey = self::CACHE_PREFIX . 'sample_' . Str::random(16);
        
        Cache::put($cacheKey, $sampleData, now()->addSeconds(self::DEFAULT_TTL));
        
        Log::info('Stored sample data in cache', [
            'cache_key' => $cacheKey,
            'worksheets_count' => count($sampleData)
        ]);
        
        return $cacheKey;
    }

    /**
     * Retrieve sample data from cache
     */
    public function getSampleData(string $cacheKey): array
    {
        return Cache::get($cacheKey, []);
    }

    /**
     * Store validation results in cache
     */
    public function storeValidationResults(array $results): string
    {
        $cacheKey = self::CACHE_PREFIX . 'validation_' . Str::random(16);
        
        Cache::put($cacheKey, $results, now()->addSeconds(self::DEFAULT_TTL));
        
        Log::info('Stored validation results in cache', [
            'cache_key' => $cacheKey,
            'total_rows' => $results['total_rows'] ?? 0
        ]);
        
        return $cacheKey;
    }

    /**
     * Retrieve validation results from cache
     */
    public function getValidationResults(string $cacheKey): array
    {
        return Cache::get($cacheKey, []);
    }

    /**
     * Store import progress data
     */
    public function storeImportProgress(string $importId, array $progress): void
    {
        $cacheKey = self::CACHE_PREFIX . 'progress_' . $importId;
        
        Cache::put($cacheKey, $progress, now()->addHours(2));
    }

    /**
     * Retrieve import progress data
     */
    public function getImportProgress(string $importId): array
    {
        $cacheKey = self::CACHE_PREFIX . 'progress_' . $importId;
        
        return Cache::get($cacheKey, []);
    }

    /**
     * Clear all cached data for a specific import session
     */
    public function clearImportData(array $cacheKeys): void
    {
        foreach ($cacheKeys as $key) {
            if ($key) {
                Cache::forget($key);
            }
        }
        
        Log::info('Cleared import cache data', ['keys_cleared' => count(array_filter($cacheKeys))]);
    }

    /**
     * Clean up old cache entries (called by scheduled job)
     */
    public function cleanupOldEntries(): void
    {
        // This would typically be handled by cache expiration
        // But could be enhanced with manual cleanup if needed
        Log::info('Import cache cleanup completed');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        // Implementation depends on cache driver
        return [
            'cache_driver' => config('cache.default'),
            'prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL
        ];
    }
}