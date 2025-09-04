<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\BulkDeleteImagesAction;
use App\Models\Image;
use Illuminate\Support\Collection;

/**
 * 🗑️ BULK DELETE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for BulkDeleteImagesAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::bulk()->delete([1,2,3])->confirm()
 * Images::bulk()->delete($imageIds)->withVariants()->preview()
 */
class BulkDeleteAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected BulkDeleteImagesAction $legacyAction;
    protected array $imageIds = [];
    protected bool $confirmed = false;
    protected bool $includeVariants = true;
    protected array $deletionStats = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(BulkDeleteImagesAction::class);
    }

    /**
     * 🗑️ Execute bulk image deletion
     *
     * @param mixed ...$parameters - [imageIds]
     */
    public function execute(...$parameters): mixed
    {
        [$imageIds] = $parameters + [[]];

        if (!$this->canExecute($imageIds)) {
            return $this->handleReturn([]);
        }

        // Store for fluent API
        $this->imageIds = $this->normalizeImageIds($imageIds);

        // Collect stats before deletion
        $this->collectBulkDeletionStats($this->imageIds);

        try {
            // Use the existing action for actual bulk deletion
            $result = $this->legacyAction->execute($this->imageIds);

            $this->logAction('bulk_delete_images', [
                'success' => $result['success'] ?? false,
                'requested_count' => count($this->imageIds),
                'deleted_count' => $result['data']['deleted_count'] ?? 0,
                'include_variants' => $this->includeVariants,
            ]);

            return $this->handleReturn($result['data'] ?? []);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('bulk_delete_failed', [
                'requested_count' => count($this->imageIds),
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn([]);
        }
    }

    /**
     * ✅ Validate bulk deletion parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$imageIds] = $parameters + [[]];

        if (empty($imageIds)) {
            $this->errors[] = "Image IDs array is required and cannot be empty";
            return false;
        }

        if (!$this->confirmed) {
            $this->errors[] = "Bulk deletion must be confirmed before execution";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 🗑️ Include variants in bulk deletion
     */
    public function withVariants(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withVariants() requires fluent mode");
        }
        
        $this->includeVariants = true;
        return $this;
    }

    /**
     * 📄 Delete only main images (skip variants)
     */
    public function imagesOnly(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("imagesOnly() requires fluent mode");
        }
        
        $this->includeVariants = false;
        return $this;
    }

    /**
     * ✅ Confirm bulk deletion intent
     */
    public function confirm(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("confirm() requires fluent mode");
        }
        
        $this->confirmed = true;
        return $this;
    }

    /**
     * ⚡ Execute confirmed bulk deletion
     */
    public function now(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("now() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            throw new \BadMethodCallException("No images set for bulk deletion");
        }
        
        return $this->execute($this->imageIds);
    }

    /**
     * 🔢 Preview bulk deletion (what will be deleted)
     */
    public function preview(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("preview() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            throw new \BadMethodCallException("No images set for bulk deletion preview");
        }
        
        $this->collectBulkDeletionStats($this->imageIds);
        
        return [
            'total_images' => count($this->imageIds),
            'valid_images' => $this->deletionStats['valid_images'] ?? 0,
            'invalid_ids' => $this->deletionStats['invalid_ids'] ?? [],
            'variants_affected' => $this->deletionStats['variants_count'] ?? 0,
            'products_affected' => $this->deletionStats['attached_products'] ?? 0,
            'total_files' => ($this->deletionStats['valid_images'] ?? 0) + ($this->deletionStats['variants_count'] ?? 0),
            'include_variants' => $this->includeVariants,
            'warnings' => $this->getBulkWarnings(),
        ];
    }

    /**
     * 📊 Get bulk deletion statistics
     */
    public function getStats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getStats() requires fluent mode");
        }
        
        return $this->deletionStats;
    }

    /**
     * ⚠️ Check if bulk deletion is safe
     */
    public function isSafe(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isSafe() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return false;
        }
        
        $this->collectBulkDeletionStats($this->imageIds);
        return ($this->deletionStats['attached_products'] ?? 0) === 0 && 
               ($this->deletionStats['attached_variants'] ?? 0) === 0;
    }

    /**
     * 🔢 Get count of images that will be deleted
     */
    public function count(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("count() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return 0;
        }
        
        return Image::whereIn('id', $this->imageIds)->count();
    }

    /**
     * 📋 Get list of images to be deleted
     */
    public function getImages(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getImages() requires fluent mode");
        }
        
        if (empty($this->imageIds)) {
            return collect();
        }
        
        return Image::whereIn('id', $this->imageIds)->get();
    }

    /**
     * 🔧 Helper Methods
     */

    /**
     * 🔢 Normalize image IDs from various inputs
     */
    protected function normalizeImageIds($imageIds): array
    {
        if ($imageIds instanceof Collection) {
            return $imageIds->pluck('id')->toArray();
        }
        
        if (is_array($imageIds)) {
            return array_map('intval', array_filter($imageIds, 'is_numeric'));
        }
        
        if (is_numeric($imageIds)) {
            return [(int)$imageIds];
        }
        
        return [];
    }

    /**
     * 📊 Collect bulk deletion statistics
     */
    protected function collectBulkDeletionStats(array $imageIds): void
    {
        $images = Image::whereIn('id', $imageIds)->get();
        $validIds = $images->pluck('id')->toArray();
        $invalidIds = array_diff($imageIds, $validIds);
        
        $attachedProducts = 0;
        $attachedVariants = 0;
        $variantsCount = 0;
        
        foreach ($images as $image) {
            $attachedProducts += $image->products()->count();
            $attachedVariants += $image->variants()->count();
            
            if ($image->isOriginal()) {
                $variantsCount += Image::where('folder', 'variants')
                    ->whereJsonContains('tags', "original-{$image->id}")
                    ->count();
            }
        }
        
        $this->deletionStats = [
            'requested_count' => count($imageIds),
            'valid_images' => count($validIds),
            'invalid_ids' => $invalidIds,
            'attached_products' => $attachedProducts,
            'attached_variants' => $attachedVariants,
            'variants_count' => $variantsCount,
            'total_files' => count($validIds) + $variantsCount,
        ];
    }

    /**
     * ⚠️ Get bulk deletion warnings
     */
    protected function getBulkWarnings(): array
    {
        $warnings = [];
        
        if (!empty($this->deletionStats['invalid_ids'])) {
            $count = count($this->deletionStats['invalid_ids']);
            $warnings[] = "{$count} image ID(s) not found: " . implode(', ', $this->deletionStats['invalid_ids']);
        }
        
        if ($this->deletionStats['attached_products'] > 0) {
            $warnings[] = "Images attached to {$this->deletionStats['attached_products']} product(s)";
        }
        
        if ($this->deletionStats['attached_variants'] > 0) {
            $warnings[] = "Images attached to {$this->deletionStats['attached_variants']} product variant(s)";
        }
        
        if ($this->deletionStats['variants_count'] > 0) {
            $warnings[] = "{$this->deletionStats['variants_count']} generated variant(s) will also be deleted";
        }
        
        return $warnings;
    }
}