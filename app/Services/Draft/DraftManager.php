<?php

namespace App\Services\Draft;

use App\Contracts\DraftStorageInterface;

/**
 * Decoupled Draft Manager
 * 
 * Handles draft operations without tight coupling to auth, storage, or specific implementations.
 * Uses dependency injection for storage backend and user identification.
 */
class DraftManager
{
    public function __construct(
        private DraftStorageInterface $storage,
        private string $draftType = 'wizard'
    ) {}

    /**
     * Save draft data for a user
     */
    public function saveDraft(string $userId, array $data, ?int $ttl = null): array
    {
        if (empty($userId)) {
            return $this->error('User ID is required');
        }

        try {
            $success = $this->storage->store($userId, $this->draftType, $data, $ttl);
            
            if (!$success) {
                return $this->error('Failed to save draft');
            }

            return $this->success('Draft saved successfully', [
                'saved_at' => now()->toISOString(),
                'steps' => array_keys($data),
                'step_count' => count($data),
            ]);
        } catch (\Exception $e) {
            \Log::error("Draft save failed: {$e->getMessage()}", [
                'user_id' => $userId,
                'draft_type' => $this->draftType,
            ]);

            return $this->error('Failed to save draft');
        }
    }

    /**
     * Load draft data for a user
     */
    public function loadDraft(string $userId): array
    {
        if (empty($userId)) {
            return $this->error('User ID is required');
        }

        try {
            $data = $this->storage->retrieve($userId, $this->draftType);
            
            if ($data === null) {
                return $this->success('No draft found', [
                    'exists' => false,
                    'data' => [],
                    'steps' => [],
                ]);
            }

            return $this->success('Draft loaded successfully', [
                'exists' => true,
                'data' => $data,
                'steps' => array_keys($data),
                'step_count' => count($data),
            ]);
        } catch (\Exception $e) {
            \Log::error("Draft load failed: {$e->getMessage()}", [
                'user_id' => $userId,
                'draft_type' => $this->draftType,
            ]);

            return $this->error('Failed to load draft');
        }
    }

    /**
     * Check if draft exists for a user
     */
    public function draftExists(string $userId): bool
    {
        if (empty($userId)) {
            return false;
        }

        try {
            return $this->storage->exists($userId, $this->draftType);
        } catch (\Exception $e) {
            \Log::error("Draft exists check failed: {$e->getMessage()}", [
                'user_id' => $userId,
                'draft_type' => $this->draftType,
            ]);
            
            return false;
        }
    }

    /**
     * Clear draft for a user
     */
    public function clearDraft(string $userId): array
    {
        if (empty($userId)) {
            return $this->error('User ID is required');
        }

        try {
            $success = $this->storage->delete($userId, $this->draftType);
            
            if (!$success) {
                return $this->error('Failed to clear draft');
            }

            return $this->success('Draft cleared successfully', [
                'cleared_at' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error("Draft clear failed: {$e->getMessage()}", [
                'user_id' => $userId,
                'draft_type' => $this->draftType,
            ]);

            return $this->error('Failed to clear draft');
        }
    }

    /**
     * Get draft metadata and status
     */
    public function getDraftInfo(string $userId): array
    {
        if (empty($userId)) {
            return [
                'exists' => false,
                'message' => 'User ID required',
                'metadata' => null,
            ];
        }

        try {
            if (!$this->draftExists($userId)) {
                return [
                    'exists' => false,
                    'message' => 'No draft available',
                    'metadata' => null,
                ];
            }

            $metadata = $this->storage->getMetadata($userId, $this->draftType);
            $stepCount = $metadata['step_count'] ?? 0;

            return [
                'exists' => true,
                'message' => $stepCount === 1 ? '1 step saved' : "{$stepCount} steps saved",
                'metadata' => $metadata,
            ];
        } catch (\Exception $e) {
            \Log::error("Draft info failed: {$e->getMessage()}", [
                'user_id' => $userId,
                'draft_type' => $this->draftType,
            ]);

            return [
                'exists' => false,
                'message' => 'Error checking draft status',
                'metadata' => null,
            ];
        }
    }

    /**
     * Create a new draft manager instance for a specific draft type
     */
    public static function for(string $draftType, ?DraftStorageInterface $storage = null): self
    {
        $storage = $storage ?? app(DraftStorageInterface::class);
        return new self($storage, $draftType);
    }

    /**
     * Success response format
     */
    private function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Error response format
     */
    private function error(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}