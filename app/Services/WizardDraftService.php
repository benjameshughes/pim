<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * 🎯 SIMPLE WIZARD DRAFT SERVICE
 * 
 * Clean, simple draft system for wizard data:
 * - One draft per product per user
 * - Cache-based storage (1 day TTL)
 * - Auto-save capabilities
 * - Easy retrieval and cleanup
 */
class WizardDraftService
{
    protected int $ttl = 86400; // 1 day in seconds
    
    /**
     * 💾 SAVE DRAFT DATA
     */
    public function save(string $userId, ?int $productId = null, array $data = []): bool
    {
        $key = $this->getDraftKey($userId, $productId);
        
        $draftData = [
            'data' => $data,
            'updated_at' => now()->toISOString(),
            'product_id' => $productId,
            'user_id' => $userId,
        ];
        
        return Cache::put($key, $draftData, $this->ttl);
    }
    
    /**
     * 📖 GET DRAFT DATA
     */
    public function get(string $userId, ?int $productId = null): ?array
    {
        $key = $this->getDraftKey($userId, $productId);
        return Cache::get($key);
    }
    
    /**
     * 🗑️ DELETE DRAFT
     */
    public function delete(string $userId, ?int $productId = null): bool
    {
        $key = $this->getDraftKey($userId, $productId);
        return Cache::forget($key);
    }
    
    /**
     * ✨ CHECK IF DRAFT EXISTS
     */
    public function exists(string $userId, ?int $productId = null): bool
    {
        $key = $this->getDraftKey($userId, $productId);
        return Cache::has($key);
    }
    
    /**
     * 📝 UPDATE SPECIFIC STEP DATA
     */
    public function updateStep(string $userId, ?int $productId = null, int $step = 1, array $stepData = []): bool
    {
        $draft = $this->get($userId, $productId) ?? [
            'data' => [],
            'updated_at' => now()->toISOString(),
            'product_id' => $productId,
            'user_id' => $userId,
        ];
        
        $draft['data']["step_{$step}"] = $stepData;
        $draft['updated_at'] = now()->toISOString();
        
        return $this->save($userId, $productId, $draft['data']);
    }
    
    /**
     * 📋 LIST USER'S DRAFTS
     */
    public function getUserDrafts(string $userId): array
    {
        $pattern = "wizard_draft:{$userId}:*";
        $keys = [];
        
        // Get all cache keys for this user (implementation depends on cache driver)
        // For now, return empty array - this would need cache driver specific implementation
        return [];
    }
    
    /**
     * 🔑 GENERATE DRAFT CACHE KEY
     */
    protected function getDraftKey(string $userId, ?int $productId = null): string
    {
        $productPart = $productId ? "product:{$productId}" : 'new';
        return "wizard_draft:{$userId}:{$productPart}";
    }
    
    /**
     * 🧹 CLEANUP OLD DRAFTS (called via scheduled command)
     */
    public function cleanupExpired(): int
    {
        // This would need cache driver specific implementation
        // For now return 0 - Redis/database drivers could implement pattern matching
        return 0;
    }
}