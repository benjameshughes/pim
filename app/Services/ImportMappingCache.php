<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class ImportMappingCache
{
    private const CACHE_PREFIX = 'import_mappings';
    private const CACHE_TTL = 60 * 60 * 24 * 30; // 30 days

    /**
     * Get the cache key for a user's import mappings
     */
    private function getCacheKey(int $userId): string
    {
        return self::CACHE_PREFIX . ':user:' . $userId;
    }

    /**
     * Save import mappings for the current user
     */
    public function saveMapping(array $columnMapping, array $importSettings = []): void
    {
        if (!Auth::check()) {
            return;
        }

        $userId = Auth::id();
        $cacheKey = $this->getCacheKey($userId);

        $mappingData = [
            'column_mapping' => $columnMapping,
            'import_settings' => $importSettings,
            'created_at' => now()->toISOString(),
            'user_id' => $userId,
        ];

        Cache::put($cacheKey, $mappingData, self::CACHE_TTL);
    }

    /**
     * Get saved import mappings for the current user
     */
    public function getMapping(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $userId = Auth::id();
        $cacheKey = $this->getCacheKey($userId);

        return Cache::get($cacheKey);
    }

    /**
     * Get only the column mapping for the current user
     */
    public function getColumnMapping(): array
    {
        $mappingData = $this->getMapping();
        return $mappingData['column_mapping'] ?? [];
    }

    /**
     * Get only the import settings for the current user
     */
    public function getImportSettings(): array
    {
        $mappingData = $this->getMapping();
        return $mappingData['import_settings'] ?? [];
    }

    /**
     * Apply saved mappings to column headers based on similarity
     */
    public function applyMappingsToHeaders(array $headers): array
    {
        $savedMapping = $this->getColumnMapping();
        if (empty($savedMapping)) {
            return [];
        }

        $mappings = [];
        
        // Try to match headers with saved mappings
        foreach ($headers as $index => $header) {
            $normalizedHeader = $this->normalizeHeader($header);
            
            // Check if we have an exact match from previous mappings
            foreach ($savedMapping as $savedIndex => $savedField) {
                if (isset($this->getLastHeadersUsed()[$savedIndex])) {
                    $savedHeader = $this->normalizeHeader($this->getLastHeadersUsed()[$savedIndex]);
                    
                    // If headers match, apply the saved mapping
                    if ($normalizedHeader === $savedHeader) {
                        $mappings[$index] = $savedField;
                        break;
                    }
                }
            }
        }

        return $mappings;
    }

    /**
     * Save the headers used in this import for future matching
     */
    public function saveHeaders(array $headers): void
    {
        if (!Auth::check()) {
            return;
        }

        $userId = Auth::id();
        $cacheKey = $this->getCacheKey($userId) . ':headers';

        Cache::put($cacheKey, $headers, self::CACHE_TTL);
    }

    /**
     * Get the last headers used for import
     */
    public function getLastHeadersUsed(): array
    {
        if (!Auth::check()) {
            return [];
        }

        $userId = Auth::id();
        $cacheKey = $this->getCacheKey($userId) . ':headers';

        return Cache::get($cacheKey, []);
    }

    /**
     * Normalize header for comparison
     */
    private function normalizeHeader(string $header): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $header)));
    }

    /**
     * Clear all mappings for the current user
     */
    public function clearMapping(): void
    {
        if (!Auth::check()) {
            return;
        }

        $userId = Auth::id();
        $cacheKey = $this->getCacheKey($userId);
        $headersKey = $cacheKey . ':headers';

        Cache::forget($cacheKey);
        Cache::forget($headersKey);
    }

    /**
     * Get mapping statistics for the current user
     */
    public function getMappingStats(): array
    {
        $mappingData = $this->getMapping();
        
        if (!$mappingData) {
            return [
                'has_saved_mapping' => false,
                'created_at' => null,
                'total_mappings' => 0,
            ];
        }

        return [
            'has_saved_mapping' => true,
            'created_at' => $mappingData['created_at'] ?? null,
            'total_mappings' => count(array_filter($mappingData['column_mapping'] ?? [])),
        ];
    }
}