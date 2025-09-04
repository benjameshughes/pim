<?php

namespace App\Actions\Images\Manager;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

/**
 * 🔌 DETACH IMAGE ACTION
 *
 * Handles detaching images from products, variants, or color groups
 * Supports single/bulk operations with validation and fluent API
 *
 * Usage:
 * $action = new DetachImageAction();
 * $action->execute($image, $product);
 * 
 * // Fluent API:
 * $action->fluent()->execute($images, $product)->cleanup();
 */
class DetachImageAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected array $detachedImages = [];
    protected ?Model $targetModel = null;
    protected ?string $color = null;

    /**
     * 🔌 Execute the detach operation
     *
     * @param mixed ...$parameters - [images, model, color]
     * @return mixed
     */
    public function execute(...$parameters): mixed
    {
        // Extract parameters
        [$images, $model, $color] = $parameters + [null, null, null];
        
        $this->targetModel = $model;
        $this->color = $color;

        // Validate inputs
        if (!$this->canExecute($images, $model, $color)) {
            return $this->handleReturn(false);
        }

        // Resolve images to collection
        $imageCollection = $this->resolveImages($images);
        
        // Perform detachments
        $detachedCount = 0;
        foreach ($imageCollection as $image) {
            if ($this->detachSingleImage($image)) {
                $this->detachedImages[] = $image->id;
                $detachedCount++;
            }
        }

        $this->logAction('detach', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'color' => $color,
            'detached_count' => $detachedCount,
            'image_ids' => $this->detachedImages,
        ]);

        return $this->handleReturn($detachedCount);
    }

    /**
     * ✅ Validate the detach operation
     */
    public function canExecute(...$parameters): bool
    {
        [$images, $model, $color] = $parameters + [null, null, null];

        // Validate model
        if (!$this->validateModel($model)) {
            return false;
        }

        // Validate color if provided
        if ($color !== null && !$this->validateColor($color)) {
            return false;
        }

        // Validate images exist
        $imageCollection = $this->resolveImages($images);
        if ($imageCollection->isEmpty()) {
            $this->errors[] = "No valid images provided";
            return false;
        }

        return true;
    }

    /**
     * 🔌 Detach a single image
     */
    protected function detachSingleImage(Image $image): bool
    {
        try {
            $context = $this->getContext($this->targetModel, $this->color);
            
            switch ($context) {
                case 'product':
                    $image->detachFrom($this->targetModel);
                    break;
                    
                case 'variant':
                    $image->detachFrom($this->targetModel);
                    break;
                    
                case 'color':
                    $image->detachFromColorGroup($this->targetModel, $this->color);
                    break;
                    
                default:
                    $this->errors[] = "Unknown context: {$context}";
                    return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->errors[] = "Failed to detach image {$image->id}: " . $e->getMessage();
            return false;
        }
    }

    /**
     * 🎯 FLUENT API METHODS
     */

    /**
     * 🧹 Cleanup orphaned images after detaching
     */
    public function cleanup(): static
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("cleanup() requires fluent mode");
        }

        $orphanedCount = 0;
        foreach ($this->detachedImages as $imageId) {
            $image = Image::find($imageId);
            if ($image && !$image->isAttached()) {
                $image->delete();
                $orphanedCount++;
            }
        }

        $this->logAction('cleanup', [
            'orphaned_deleted' => $orphanedCount
        ]);

        return $this;
    }

    /**
     * 📊 Get detached image IDs
     */
    public function getDetachedImageIds(): array
    {
        return $this->detachedImages;
    }
}