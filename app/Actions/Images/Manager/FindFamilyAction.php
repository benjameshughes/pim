<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\GetImageFamilyAction;
use App\Models\Image;
use Illuminate\Support\Collection;

/**
 * 👪 FIND FAMILY ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for GetImageFamilyAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::find($image)->family()
 * Images::find($image)->relatives()->ordered()
 */
class FindFamilyAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected GetImageFamilyAction $legacyAction;
    protected ?Image $image = null;
    protected array $familyData = [];

    public function __construct()
    {
        $this->legacyAction = app()->make(GetImageFamilyAction::class);
    }

    /**
     * 👪 Execute image family retrieval
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
            // Use the existing action for actual family retrieval
            $result = $this->legacyAction->execute($image);

            $this->familyData = $result['data'] ?? [];

            $this->logAction('find_image_family', [
                'success' => $result['success'] ?? false,
                'image_id' => $image->id,
                'family_size' => $this->familyData['total_images'] ?? 0,
                'context' => $this->familyData['context'] ?? 'unknown',
            ]);

            return $this->handleReturn($this->familyData);

        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            
            $this->logAction('find_family_failed', [
                'image_id' => $image->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return $this->handleReturn([]);
        }
    }

    /**
     * ✅ Validate family finding parameters
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
     * 👪 Get complete image family
     */
    public function family(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("family() requires fluent mode");
        }
        
        return $this;
    }

    /**
     * 👥 Get related images (same as family)
     */
    public function relatives(): self
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("relatives() requires fluent mode");
        }
        
        return $this;
    }

    /**
     * ⭐ Get original parent image
     */
    public function original(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("original() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['original'] ?? null;
    }

    /**
     * 👶 Get child variants only
     */
    public function variants(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variants() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['variants'] ?? collect();
    }

    /**
     * 🏠 Get complete family (original + variants)
     */
    public function all(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("all() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['family'] ?? collect();
    }

    /**
     * 🎯 Get active image (the one we started with)
     */
    public function active(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("active() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['active_image'] ?? null;
    }

    /**
     * 📏 Get family ordered by size (thumb → large)
     */
    public function ordered(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("ordered() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        // Variants are already ordered by the legacy action
        $family = $this->familyData['family'] ?? collect();
        return $family;
    }

    /**
     * 🔢 Get family size count
     */
    public function count(): int
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("count() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['total_images'] ?? 0;
    }

    /**
     * 📋 Get specific variant by type
     */
    public function variant(string $type): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variant() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        $variants = $this->familyData['variants'] ?? collect();
        return $variants->first(function ($variant) use ($type) {
            return $variant->hasTag($type);
        });
    }

    /**
     * 🔍 Find all variants by type
     */
    public function variantsByType(string $type): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variantsByType() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        $variants = $this->familyData['variants'] ?? collect();
        return $variants->filter(function ($variant) use ($type) {
            return $variant->hasTag($type);
        });
    }

    /**
     * ✅ Check if family has variants
     */
    public function hasVariants(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("hasVariants() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return ($this->familyData['variants'] ?? collect())->isNotEmpty();
    }

    /**
     * 🔄 Check if current image is variant or original
     */
    public function context(): string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("context() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        return $this->familyData['context'] ?? 'unknown';
    }

    /**
     * ⭐ Check if current image is the original
     */
    public function isOriginal(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isOriginal() requires fluent mode");
        }
        
        return $this->context() === 'original';
    }

    /**
     * 👶 Check if current image is a variant
     */
    public function isVariant(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("isVariant() requires fluent mode");
        }
        
        return $this->context() === 'variant';
    }

    /**
     * 📊 Get comprehensive family stats
     */
    public function stats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("stats() requires fluent mode");
        }
        
        if (empty($this->familyData)) {
            $this->execute($this->image);
        }
        
        $variants = $this->familyData['variants'] ?? collect();
        $variantTypes = $variants->map(function ($variant) {
            return $variant->getVariantType();
        })->filter()->unique();
        
        return [
            'family_size' => $this->familyData['total_images'] ?? 0,
            'variant_count' => $variants->count(),
            'variant_types' => $variantTypes->toArray(),
            'original_id' => $this->familyData['original']->id ?? null,
            'active_id' => $this->familyData['active_image']->id ?? null,
            'context' => $this->familyData['context'] ?? 'unknown',
            'has_thumb' => $variants->contains(function ($v) { return $v->hasTag('thumb'); }),
            'has_small' => $variants->contains(function ($v) { return $v->hasTag('small'); }),
            'has_medium' => $variants->contains(function ($v) { return $v->hasTag('medium'); }),
            'has_large' => $variants->contains(function ($v) { return $v->hasTag('large'); }),
        ];
    }
}