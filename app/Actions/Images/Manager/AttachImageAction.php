<?php

namespace App\Actions\Images\Manager;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;

/**
 * 🔗 ATTACH IMAGE ACTION
 *
 * Handles attaching images to products, variants, or color groups
 * Supports single/bulk operations with validation and fluent API
 *
 * Usage:
 * $action = new AttachImageAction();
 * $action->execute($image, $product, options: ['is_primary' => true]);
 * 
 * // Fluent API:
 * $action->fluent()->execute($image, $product)->asPrimary()->sorted();
 */
class AttachImageAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected array $attachedImages = [];
    protected ?Model $targetModel = null;
    protected ?string $color = null;
    protected array $options = [];

    /**
     * 🔗 Execute the attach operation
     *
     * @param mixed ...$parameters - [images, model, color, options]
     * @return mixed
     */
    public function execute(...$parameters): mixed
    {
        // Extract parameters
        [$images, $model, $color, $options] = $parameters + [null, null, null, []];
        
        $this->targetModel = $model;
        $this->color = $color;
        $this->options = array_merge(['is_primary' => false, 'sort_order' => 0], $options);

        // Validate inputs
        if (!$this->canExecute($images, $model, $color, $options)) {
            return $this->handleReturn(false);
        }

        // Resolve images to collection
        $imageCollection = $this->resolveImages($images);
        
        // Perform attachments
        $attachedCount = 0;
        foreach ($imageCollection as $image) {
            if ($this->attachSingleImage($image)) {
                $this->attachedImages[] = $image->id;
                $attachedCount++;
            }
        }

        $this->logAction('attach', [
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'color' => $color,
            'attached_count' => $attachedCount,
            'image_ids' => $this->attachedImages,
        ]);

        return $this->handleReturn($attachedCount);
    }

    /**
     * ✅ Validate the attach operation
     */
    public function canExecute(...$parameters): bool
    {
        [$images, $model, $color, $options] = $parameters + [null, null, null, []];

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

        $imageIds = $imageCollection->pluck('id')->toArray();
        if (!$this->validateImages($imageIds)) {
            return false;
        }

        return true;
    }

    /**
     * 🔗 Attach a single image
     */
    protected function attachSingleImage(Image $image): bool
    {
        try {
            $context = $this->getContext($this->targetModel, $this->color);
            
            switch ($context) {
                case 'product':
                    $image->attachTo($this->targetModel, $this->options);
                    break;
                    
                case 'variant':
                    $image->attachTo($this->targetModel, $this->options);
                    break;
                    
                case 'color':
                    $image->attachToColorGroup($this->targetModel, $this->color, $this->options);
                    break;
                    
                default:
                    $this->errors[] = "Unknown context: {$context}";
                    return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->errors[] = "Failed to attach image {$image->id}: " . $e->getMessage();
            return false;
        }
    }

    /**
     * 🎯 FLUENT API METHODS
     */

    /**
     * ⭐ Set attached images as primary
     */
    public function asPrimary(): static
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("asPrimary() requires fluent mode");
        }

        if (empty($this->attachedImages)) {
            return $this;
        }

        // Set first attached image as primary
        $firstImage = Image::find($this->attachedImages[0]);
        if ($firstImage) {
            $this->setPrimaryImage($firstImage);
        }

        return $this;
    }

    /**
     * 📊 Set sort order for attached images
     */
    public function sorted(int $startOrder = 1): static
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("sorted() requires fluent mode");
        }

        // Update sort order for attached images
        foreach ($this->attachedImages as $index => $imageId) {
            $this->updateSortOrder($imageId, $startOrder + $index);
        }

        return $this;
    }

    /**
     * ⭐ Set image as primary
     */
    protected function setPrimaryImage(Image $image): void
    {
        $context = $this->getContext($this->targetModel, $this->color);
        
        switch ($context) {
            case 'product':
            case 'variant':
                $image->setPrimaryFor($this->targetModel);
                break;
                
            case 'color':
                $image->setPrimaryForColor($this->targetModel, $this->color);
                break;
        }
    }

    /**
     * 📊 Update sort order
     */
    protected function updateSortOrder(int $imageId, int $sortOrder): void
    {
        $context = $this->getContext($this->targetModel, $this->color);
        
        switch ($context) {
            case 'product':
                $this->targetModel->images()->updateExistingPivot($imageId, ['sort_order' => $sortOrder]);
                break;
                
            case 'variant':
                $this->targetModel->images()->updateExistingPivot($imageId, ['sort_order' => $sortOrder]);
                break;
                
            case 'color':
                $this->targetModel->colorGroupImages()->updateExistingPivot($imageId, ['sort_order' => $sortOrder]);
                break;
        }
    }

    /**
     * 📊 Get attached image IDs
     */
    public function getAttachedImageIds(): array
    {
        return $this->attachedImages;
    }
}