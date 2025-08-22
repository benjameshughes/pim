<?php

namespace App\Services\Draft;

use App\Contracts\DraftStorageInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cache-based Draft Storage
 * 
 * Stores drafts in Laravel cache for persistence across sessions
 * and better performance than session storage.
 */
class CacheDraftStorage implements DraftStorageInterface
{
    public function __construct(
        private string $keyPrefix = 'draft',
        private int $defaultTtl = 86400 // 24 hours
    ) {}

    public function store(string $userId, string $draftKey, array $data, ?int $ttl = null): bool
    {
        $cacheKey = $this->buildCacheKey($userId, $draftKey);
        $ttl = $ttl ?? $this->defaultTtl;
        
        $payload = [
            'data' => $data,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
            'user_id' => $userId,
            'draft_key' => $draftKey,
        ];

        return Cache::put($cacheKey, $payload, $ttl);
    }

    public function retrieve(string $userId, string $draftKey): ?array
    {
        $cacheKey = $this->buildCacheKey($userId, $draftKey);
        $payload = Cache::get($cacheKey);

        if (!$payload) {
            return null;
        }

        // Update last accessed timestamp
        $payload['last_accessed_at'] = now()->toISOString();
        Cache::put($cacheKey, $payload, $this->defaultTtl);

        return $payload['data'];
    }

    public function exists(string $userId, string $draftKey): bool
    {
        $cacheKey = $this->buildCacheKey($userId, $draftKey);
        return Cache::has($cacheKey);
    }

    public function delete(string $userId, string $draftKey): bool
    {
        $cacheKey = $this->buildCacheKey($userId, $draftKey);
        return Cache::forget($cacheKey);
    }

    public function clearAll(string $userId): bool
    {
        $pattern = $this->buildCacheKey($userId, '*');
        
        // For Redis or similar cache stores that support patterns
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $keys = Cache::getStore()->getRedis()->keys($pattern);
            if (!empty($keys)) {
                return Cache::getStore()->getRedis()->del($keys) > 0;
            }
        }

        // Fallback: we'll need to track keys separately for other cache stores
        // This is a limitation of some cache backends
        return true;
    }

    public function getMetadata(string $userId, string $draftKey): ?array
    {
        $cacheKey = $this->buildCacheKey($userId, $draftKey);
        $payload = Cache::get($cacheKey);

        if (!$payload) {
            return null;
        }

        return [
            'created_at' => $payload['created_at'],
            'updated_at' => $payload['updated_at'],
            'last_accessed_at' => $payload['last_accessed_at'] ?? null,
            'user_id' => $payload['user_id'],
            'draft_key' => $payload['draft_key'],
            'size' => strlen(json_encode($payload['data'])),
            'steps' => array_keys($payload['data']),
            'step_count' => count($payload['data']),
        ];
    }

    /**
     * Build cache key for user draft
     */
    private function buildCacheKey(string $userId, string $draftKey): string
    {
        return "{$this->keyPrefix}.{$userId}.{$draftKey}";
    }
}