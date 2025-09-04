<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\GetImageWithVariantsAction;
use App\Models\Image;

/**
 * 🖼️ RETRIEVE WITH VARIANTS ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for GetImageWithVariantsAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::retrieve($image)->withVariants()
 * Images::retrieve($image)->gallery()->current()
 */
class RetrieveWithVariantsAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected GetImageWithVariantsAction $legacyAction;
    protected ?Image $image = null;
    protected array $retrievalData = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(GetImageWithVariantsAction::class);
    }

    /**
     * 🖼️ Execute image retrieval with variants
     *
     * @param mixed ...$parameters - [image]
     */
    public function execute(...$parameters): mixed
    {
        [$image] = $parameters + [null];

        if (!$this->canExecute($image)) {
            return $this->handleReturn([]);
        }

        // Store for fluent API
        $this->image = $image;

        try {
            // Use the existing action for actual retrieval
            $result = $this->legacyAction->execute($image);

            $this->retrievalData = $result['data'] ?? [];

            $this->logAction('retrieve_image_with_variants', [
                'success' => $result['success'] ?? false,
                'image_id' => $image->id,
                'variant_count' => $this->retrievalData['variant_count'] ?? 0,
                'is_viewing_variant' => $this->retrievalData['is_viewing_variant'] ?? false,
            ]);

            return $this->handleReturn($this->retrievalData);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('retrieve_with_variants_failed', [
                'image_id' => $image->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn([]);
        }
    }

    /**
     * ✅ Validate retrieval parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image] = $parameters + [null];

        if (!$image instanceof Image) {
            $this->errors[] = "Image instance is required";
            return false;
        }

        return true;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 🖼️ Get all variants including original
     */
    public function withVariants(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("withVariants() requires fluent mode");
        }
        
        return $this;
    }

    /**
     * 🎨 Get gallery-ready data structure
     */
    public function gallery(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("gallery() requires fluent mode");
        }
        
        return $this;
    }

    /**
     * ⭐ Get original image
     */
    public function original(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("original() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['original_image'] ?? null;
    }

    /**
     * 🎯 Get current viewing image
     */
    public function current(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("current() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['current_image'] ?? null;
    }

    /**
     * 📸 Get all variants (without original)
     */
    public function variants()
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variants() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['variants'] ?? collect();
    }

    /**
     * 👪 Get all images in family (original + variants)
     */
    public function family()
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("family() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['all_variants'] ?? collect();
    }

    /**
     * 🔢 Get variant count
     */
    public function count(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("count() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['variant_count'] ?? 0;
    }

    /**
     * 🎨 Get variant type of current image
     */
    public function variantType(): ?string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variantType() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['current_variant_type'] ?? null;
    }

    /**
     * ✅ Check if currently viewing a variant
     */
    public function isViewingVariant(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isViewingVariant() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData['is_viewing_variant'] ?? false;
    }

    /**
     * 📊 Get comprehensive retrieval stats
     */
    public function stats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("stats() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return [
            'original_id' => $this->retrievalData['original_image']->id ?? null,
            'current_id' => $this->retrievalData['current_image']->id ?? null,
            'variant_count' => $this->retrievalData['variant_count'] ?? 0,
            'family_size' => ($this->retrievalData['all_variants'] ?? collect())->count(),
            'is_variant' => $this->retrievalData['is_viewing_variant'] ?? false,
            'variant_type' => $this->retrievalData['current_variant_type'] ?? null,
        ];
    }

    /**
     * 🚀 Get all data at once
     */
    public function all(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("all() requires fluent mode");
        }
        
        if (empty($this->retrievalData)) {
            $this->execute($this->image);
        }
        
        return $this->retrievalData;
    }
}