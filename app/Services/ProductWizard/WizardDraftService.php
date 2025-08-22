<?php

namespace App\Services\ProductWizard;

use App\Contracts\DraftStorageInterface;
use App\Services\Draft\DraftManager;

/**
 * Wizard Draft Service
 * 
 * Specialized draft service for product wizard operations.
 * Decoupled from auth and specific storage implementations.
 */
class WizardDraftService
{
    private DraftStorageInterface $storage;
    
    public function __construct(DraftStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Load draft if available for the user and product context
     * 
     * @param string|null $userId User ID (null if not authenticated)
     * @param int|null $productId Product ID (null for new products)
     * @return array Standardized draft result
     */
    public function loadDraftIfAvailable(?string $userId, ?int $productId = null): array
    {
        if (!$userId) {
            return $this->getEmptyDraftResult();
        }

        $draftKey = $this->getDraftKey($productId);
        
        // Create a manager with the product-scoped key
        $draftManager = new DraftManager($this->storage, $draftKey);
        $result = $draftManager->loadDraft($userId);

        if (!$result['success'] || !($result['data']['exists'] ?? false)) {
            return $this->getEmptyDraftResult();
        }

        return [
            'exists' => true,
            'data' => $result['data']['data'],
            'completedSteps' => $result['data']['steps'] ?? [],
            'savedAt' => $result['data']['metadata']['updated_at'] ?? null,
            'metadata' => $result['data']['metadata'] ?? [],
            'productId' => $productId,
            'draftKey' => $draftKey,
        ];
    }

    /**
     * Save wizard draft for specific product context
     * 
     * @param string|null $userId User ID (null if not authenticated)
     * @param array $wizardData The wizard data to save
     * @param int|null $productId Product ID (null for new products)
     * @return array Result with success status
     */
    public function saveDraft(?string $userId, array $wizardData, ?int $productId = null): array
    {
        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Authentication required to save draft',
            ];
        }

        $draftKey = $this->getDraftKey($productId);
        $ttl = config('drafts.wizard_ttl', 604800); // 7 days
        
        $draftManager = new DraftManager($this->storage, $draftKey);
        $result = $draftManager->saveDraft($userId, $wizardData, $ttl);

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'savedAt' => $result['success'] ? now()->format('H:i:s') : null,
            'data' => $result['data'] ?? [],
            'productId' => $productId,
            'draftKey' => $draftKey,
        ];
    }

    /**
     * Clear draft for user and product context
     */
    public function clearDraft(?string $userId, ?int $productId = null): array
    {
        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Authentication required',
            ];
        }

        $draftKey = $this->getDraftKey($productId);
        $draftManager = new DraftManager($this->storage, $draftKey);
        
        return $draftManager->clearDraft($userId);
    }

    /**
     * Get draft status and info
     */
    public function getDraftStatus(?string $userId): array
    {
        if (!$userId) {
            return [
                'exists' => false,
                'message' => 'Login to use drafts',
                'metadata' => null,
            ];
        }

        $draftKey = $this->getDraftKey(null); // Default to 'new' product context
        $draftManager = new DraftManager($this->storage, $draftKey);
        
        return $draftManager->getDraftInfo($userId);
    }

    /**
     * Check if draft exists
     */
    public function draftExists(?string $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $draftKey = $this->getDraftKey(null); // Default to 'new' product context
        $draftManager = new DraftManager($this->storage, $draftKey);
        
        return $draftManager->draftExists($userId);
    }

    /**
     * Generate auto-save status message
     */
    public function getAutoSaveStatus(bool $isEditMode, bool $isSaving, ?string $lastSaveTime, ?string $userId): string
    {
        if ($isEditMode) {
            return 'Changes saved directly to product';
        }

        if (!$userId) {
            return 'Login required for auto-save';
        }

        if ($isSaving) {
            return 'Saving draft...';
        }

        if ($lastSaveTime) {
            return "Last saved at {$lastSaveTime}";
        }

        return 'Auto-save ready';
    }

    /**
     * Generate product-scoped draft key
     * 
     * @param int|null $productId Product ID (null for new products)
     * @return string Draft key
     */
    private function getDraftKey(?int $productId = null): string
    {
        return $productId ? "product_wizard_edit_{$productId}" : 'product_wizard_new';
    }

    /**
     * Get empty draft result structure
     */
    private function getEmptyDraftResult(): array
    {
        return [
            'exists' => false,
            'data' => [],
            'completedSteps' => [],
            'savedAt' => null,
            'metadata' => [],
        ];
    }

    /**
     * Batch operations for wizard steps
     */
    public function saveWizardStep(?string $userId, int $stepNumber, array $stepData): array
    {
        if (!$userId) {
            return ['success' => false, 'message' => 'Authentication required'];
        }

        // Load existing draft
        $current = $this->loadDraftIfAvailable($userId);
        $wizardData = $current['data'] ?? [];

        // Update the specific step
        $wizardData[(string)$stepNumber] = $stepData;

        // Save back to storage
        return $this->saveDraft($userId, $wizardData);
    }

    /**
     * Get specific step data
     */
    public function getWizardStep(?string $userId, int $stepNumber): ?array
    {
        if (!$userId) {
            return null;
        }

        $draft = $this->loadDraftIfAvailable($userId);
        return $draft['data'][(string)$stepNumber] ?? null;
    }

    /**
     * Remove specific step from draft
     */
    public function removeWizardStep(?string $userId, int $stepNumber): array
    {
        if (!$userId) {
            return ['success' => false, 'message' => 'Authentication required'];
        }

        $current = $this->loadDraftIfAvailable($userId);
        $wizardData = $current['data'] ?? [];

        unset($wizardData[(string)$stepNumber]);

        if (empty($wizardData)) {
            return $this->clearDraft($userId);
        }

        return $this->saveDraft($userId, $wizardData);
    }
}