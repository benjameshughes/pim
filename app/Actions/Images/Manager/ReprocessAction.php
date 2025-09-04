<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\ReprocessImageAction;
use App\Models\Image;
use App\Services\ImageUploadService;

/**
 * 🔄 REPROCESS ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for ReprocessImageAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::process($image)->reprocess()->force()
 * Images::process($image)->reprocess()->withComparison()
 */
class ReprocessAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected ReprocessImageAction $legacyAction;
    protected ?Image $image = null;
    protected array $reprocessData = [];
    protected bool $forceReprocess = false;

    public function __construct()
    {
        $this->legacyAction = app()->make(ReprocessImageAction::class);
    }

    /**
     * 🔄 Execute image reprocessing
     *
     * @param mixed ...$parameters - [image]
     */
    public function execute(...$parameters): mixed
    {
        [$image] = $parameters + [null];

        if (!$this->canExecute($image)) {
            return $this->handleReturn([]);
        }

        // Store image for fluent API
        $this->image = $image;

        // Use the existing action for actual reprocessing
        $result = $this->legacyAction->execute($image);

        $this->logAction('reprocess_image', [
            'success' => $result['success'] ?? false,
            'image_id' => $image->id,
            'dimensions_changed' => $result['data']['dimensions_changed'] ?? false,
            'forced' => $this->forceReprocess,
        ]);

        // Store reprocess data for fluent API
        $this->reprocessData = [
            'image' => $result['data']['image'] ?? $image,
            'dimensions_changed' => $result['data']['dimensions_changed'] ?? false,
            'original_dimensions' => $result['data']['original_dimensions'] ?? [],
            'new_dimensions' => $result['data']['new_dimensions'] ?? [],
        ];
        
        return $this->handleReturn($this->reprocessData);
    }

    /**
     * ✅ Validate reprocessing parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image] = $parameters + [null];

        if (!($image instanceof Image)) {
            $this->errors[] = "First parameter must be an Image instance";
            return false;
        }

        if (!$image->filename || !$image->url) {
            $this->errors[] = "Image must have filename and url";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 🔄 Reprocess image (main operation)
     */
    public function reprocess(): Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("reprocess() requires fluent mode");
        }
        
        if (!$this->image) {
            throw new \BadMethodCallException("No image set for reprocessing");
        }
        
        $this->execute($this->image);
        return $this->reprocessData['image'] ?? $this->image;
    }

    /**
     * ⚡ Force reprocessing (bypass checks)
     */
    public function force(): Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("force() requires fluent mode");
        }
        
        $this->forceReprocess = true;
        return $this->reprocess();
    }

    /**
     * 📊 Get reprocessing comparison data
     */
    public function withComparison(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withComparison() requires fluent mode");
        }
        
        if (empty($this->reprocessData) && $this->image) {
            $this->execute($this->image);
        }
        
        return [
            'image' => $this->reprocessData['image'] ?? null,
            'dimensions_changed' => $this->reprocessData['dimensions_changed'] ?? false,
            'comparison' => [
                'before' => $this->reprocessData['original_dimensions'] ?? [],
                'after' => $this->reprocessData['new_dimensions'] ?? [],
            ]
        ];
    }

    /**
     * ✅ Check if dimensions changed during reprocessing
     */
    public function dimensionsChanged(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("dimensionsChanged() requires fluent mode");
        }
        
        if (empty($this->reprocessData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->reprocessData['dimensions_changed'] ?? false;
    }

    /**
     * 📏 Get original dimensions
     */
    public function getOriginalDimensions(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getOriginalDimensions() requires fluent mode");
        }
        
        if (empty($this->reprocessData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->reprocessData['original_dimensions'] ?? [];
    }

    /**
     * 📐 Get new dimensions
     */
    public function getNewDimensions(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getNewDimensions() requires fluent mode");
        }
        
        if (empty($this->reprocessData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->reprocessData['new_dimensions'] ?? [];
    }

    /**
     * 🖼️ Get updated image model
     */
    public function getImage(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getImage() requires fluent mode");
        }
        
        if (empty($this->reprocessData) && $this->image) {
            $this->execute($this->image);
        }
        
        return $this->reprocessData['image'] ?? $this->image;
    }

    /**
     * 📈 Get size difference
     */
    public function getSizeDifference(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("getSizeDifference() requires fluent mode");
        }
        
        $original = $this->getOriginalDimensions();
        $new = $this->getNewDimensions();
        
        if (empty($original) || empty($new)) {
            return ['bytes' => 0, 'percentage' => 0];
        }
        
        $originalSize = $original['size'] ?? 0;
        $newSize = $new['size'] ?? 0;
        $difference = $newSize - $originalSize;
        $percentage = $originalSize > 0 ? ($difference / $originalSize) * 100 : 0;
        
        return [
            'bytes' => $difference,
            'percentage' => round($percentage, 2),
            'increased' => $difference > 0,
            'decreased' => $difference < 0,
        ];
    }
}