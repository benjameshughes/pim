<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\DeleteImageAction;
use App\Models\Image;

/**
 * 🗑️ DELETE ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for DeleteImageAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::delete($image)->withVariants()->permanently()
 * Images::delete($image)->confirm()->execute()
 */
class DeleteAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected DeleteImageAction $legacyAction;
    protected ?Image $image = null;
    protected bool $includeVariants = true;
    protected bool $confirmed = false;
    protected bool $permanently = false;
    protected array $deletionStats = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(DeleteImageAction::class);
    }

    /**
     * 🗑️ Execute image deletion
     *
     * @param mixed ...$parameters - [image]
     */
    public function execute(...$parameters): mixed
    {
        [$image] = $parameters + [null];

        if (!$this->canExecute($image)) {
            return $this->handleReturn(false);
        }

        // Store image for fluent API
        $this->image = $image;

        // Collect stats before deletion
        $this->collectDeletionStats($image);

        try {
            // Use the existing action for actual deletion
            $this->legacyAction->execute($image);

            $this->logAction('delete_image', [
                'success' => true,
                'image_id' => $image->id,
                'filename' => $image->filename,
                'include_variants' => $this->includeVariants,
                'permanently' => $this->permanently,
                'stats' => $this->deletionStats,
            ]);

            return $this->handleReturn(true);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('delete_image_failed', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn(false);
        }
    }

    /**
     * ✅ Validate deletion parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image] = $parameters + [null];

        if (!($image instanceof Image)) {
            $this->errors[] = "First parameter must be an Image instance";
            return false;
        }

        if (!$this->confirmed) {
            $this->errors[] = "Deletion must be confirmed before execution";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 🗑️ Include variants in deletion
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
     * 📄 Delete only the main image (skip variants)
     */
    public function imageOnly(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("imageOnly() requires fluent mode");
        }
        
        $this->includeVariants = false;
        return $this;
    }

    /**
     * ⚡ Permanent deletion (cannot be recovered)
     */
    public function permanently(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("permanently() requires fluent mode");
        }
        
        $this->permanently = true;
        $this->confirmed = true; // Auto-confirm for permanent deletion
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for deletion");
        }
        
        return $this->execute($this->image);
    }

    /**
     * ✅ Confirm deletion intent
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
     * 🚀 Execute confirmed deletion
     */
    public function now(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("now() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for deletion");
        }
        
        return $this->execute($this->image);
    }

    /**
     * 📊 Get deletion statistics
     */
    public function getStats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getStats() requires fluent mode");
        }
        
        return $this->deletionStats;
    }

    /**
     * 🔢 Get deletion preview (what will be deleted)
     */
    public function preview(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("preview() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for deletion preview");
        }
        
        $this->collectDeletionStats($this->image);
        
        return [
            'image' => [
                'id' => $this->image->id,
                'filename' => $this->image->filename,
                'size' => $this->image->size,
                'display_title' => $this->image->display_title,
            ],
            'variants' => $this->deletionStats['variants_count'] ?? 0,
            'products' => $this->deletionStats['attached_products'] ?? 0,
            'product_variants' => $this->deletionStats['attached_variants'] ?? 0,
            'total_files' => 1 + ($this->deletionStats['variants_count'] ?? 0),
            'include_variants' => $this->includeVariants,
        ];
    }

    /**
     * ⚠️ Check if deletion is safe (no important relationships)
     */
    public function isSafe(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isSafe() requires fluent mode");
        }
        
        if (!$this->image) {
            return false;
        }
        
        // Consider deletion "safe" if image isn't attached to products or variants
        return !$this->image->products()->exists() && !$this->image->variants()->exists();
    }

    /**
     * ⚠️ Get deletion warnings
     */
    public function getWarnings(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getWarnings() requires fluent mode");
        }
        
        if (!$this->image) {
            return [];
        }
        
        $warnings = [];
        
        if ($this->image->products()->exists()) {
            $count = $this->image->products()->count();
            $warnings[] = "Image is attached to {$count} product(s)";
        }
        
        if ($this->image->variants()->exists()) {
            $count = $this->image->variants()->count();
            $warnings[] = "Image is attached to {$count} product variant(s)";
        }
        
        if ($this->image->isOriginal()) {
            $variantCount = $this->getVariantsCount($this->image);
            if ($variantCount > 0) {
                $warnings[] = "Image has {$variantCount} generated variant(s) that will also be deleted";
            }
        }
        
        return $warnings;
    }

    /**
     * 📊 Collect deletion statistics
     */
    protected function collectDeletionStats(Image $image): void
    {
        $this->deletionStats = [
            'attached_products' => $image->products()->count(),
            'attached_variants' => $image->variants()->count(),
            'variants_count' => $this->getVariantsCount($image),
            'is_original' => $image->isOriginal(),
            'file_size' => $image->size,
        ];
    }

    /**
     * 🔢 Get variants count for an image
     */
    protected function getVariantsCount(Image $image): int
    {
        if (!$image->isOriginal()) {
            return 0;
        }
        
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$image->id}")
            ->count();
    }
}