<?php

namespace App\Actions\Images\Manager;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * 🎯 IMAGE ACTION TRAIT
 *
 * Shared functionality for all image actions
 * Provides common utilities, validation, and fluent API support
 */
trait ImageActionTrait
{
    protected mixed $result = null;
    protected bool $fluent = false;
    protected array $errors = [];

    /**
     * 🔄 Enable fluent mode
     */
    public function fluent(): static
    {
        $this->fluent = true;
        return $this;
    }

    /**
     * 📊 Get action result
     */
    public function getResult(): mixed
    {
        return $this->result;
    }

    /**
     * ❌ Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * ✅ Check if action was successful
     */
    public function wasSuccessful(): bool
    {
        return empty($this->errors);
    }

    /**
     * 🎯 SHARED VALIDATION METHODS
     */

    /**
     * ✅ Validate image exists
     */
    protected function validateImage($imageId): bool
    {
        if (!Image::where('id', $imageId)->exists()) {
            $this->errors[] = "Image {$imageId} not found";
            return false;
        }
        return true;
    }

    /**
     * ✅ Validate images exist (bulk)
     */
    protected function validateImages(array $imageIds): bool
    {
        $existingIds = Image::whereIn('id', $imageIds)->pluck('id')->toArray();
        $missingIds = array_diff($imageIds, $existingIds);
        
        if (!empty($missingIds)) {
            $this->errors[] = "Images not found: " . implode(', ', $missingIds);
            return false;
        }
        return true;
    }

    /**
     * ✅ Validate model is supported
     */
    protected function validateModel(Model $model): bool
    {
        if (!($model instanceof Product || $model instanceof ProductVariant)) {
            $this->errors[] = "Model must be Product or ProductVariant, got " . get_class($model);
            return false;
        }
        return true;
    }

    /**
     * ✅ Validate color is provided for color operations
     */
    protected function validateColor(?string $color): bool
    {
        if (empty($color)) {
            $this->errors[] = "Color is required for color group operations";
            return false;
        }
        return true;
    }

    /**
     * 🎯 SHARED UTILITY METHODS
     */

    /**
     * 🔍 Resolve image(s) to Image model(s)
     */
    protected function resolveImages($images): Collection
    {
        if ($images instanceof Collection) {
            return $images;
        }
        
        if ($images instanceof Image) {
            return collect([$images]);
        }
        
        if (is_array($images)) {
            return Image::whereIn('id', $images)->get();
        }
        
        if (is_numeric($images)) {
            $image = Image::find($images);
            return $image ? collect([$image]) : collect();
        }
        
        return collect();
    }

    /**
     * 📝 Log action for debugging
     */
    protected function logAction(string $action, array $data = []): void
    {
        if (config('app.debug')) {
            \Log::info("Image Action: {$action}", array_merge([
                'class' => static::class,
                'fluent' => $this->fluent,
            ], $data));
        }
    }

    /**
     * 🔄 Handle fluent vs direct return
     */
    protected function handleReturn($result): mixed
    {
        $this->result = $result;
        
        if ($this->fluent) {
            return $this;
        }
        
        return $result;
    }

    /**
     * 🎯 Context detection helpers
     */
    protected function getContext(Model $model, ?string $color = null): string
    {
        if ($color !== null) {
            return 'color';
        }
        
        if ($model instanceof ProductVariant) {
            return 'variant';
        }
        
        if ($model instanceof Product) {
            return 'product';
        }
        
        return 'unknown';
    }

    /**
     * 📊 Get relationship query for context
     */
    protected function getRelationshipQuery(Model $model, ?string $color = null)
    {
        $context = $this->getContext($model, $color);
        
        return match($context) {
            'product' => $model->images(),
            'variant' => $model->images(),
            'color' => $model->getImagesForColor($color),
            default => null
        };
    }
}