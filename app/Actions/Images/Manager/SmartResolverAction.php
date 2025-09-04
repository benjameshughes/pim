<?php

namespace App\Actions\Images\Manager;

use App\Models\Image;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * 🧠 SMART RESOLVER ACTION
 *
 * Handles intelligent image resolution with three-tier fallback system:
 * 1. Variant-specific images (highest priority)
 * 2. Color group images (fallback for same color)  
 * 3. Product-level images (final fallback)
 *
 * Usage:
 * $action = new SmartResolverAction();
 * $image = $action->execute($variant, 'display');
 * 
 * // Fluent API:
 * $resolver = $action->fluent()->execute($variant);
 * $image = $resolver->display();
 * $stats = $resolver->stats();
 */
class SmartResolverAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected ?ProductVariant $variant = null;
    protected array $availability = [];

    /**
     * 🧠 Execute smart image resolution
     *
     * @param mixed ...$parameters - [variant, type]
     * @return mixed
     */
    public function execute(...$parameters): mixed
    {
        // Extract parameters
        [$variant, $type] = $parameters + [null, 'display'];
        
        $this->variant = $variant;

        // Validate inputs
        if (!$this->canExecute($variant, $type)) {
            return $this->handleReturn(null);
        }

        // Calculate availability once
        $this->availability = $variant->getImageAvailability();

        // Execute based on type
        $result = match($type) {
            'display' => $this->getDisplayImage(),
            'all' => $this->getAllImages(),
            'stats' => $this->getStats(),
            'availability' => $this->availability,
            'source' => $variant->getDisplayImageSource(),
            default => null
        };

        $this->logAction('smart_resolve', [
            'variant_id' => $variant->id,
            'type' => $type,
            'source' => $this->availability['display_image_source'] ?? 'none',
        ]);

        return $this->handleReturn($result);
    }

    /**
     * ✅ Validate the smart resolution
     */
    public function canExecute(...$parameters): bool
    {
        [$variant, $type] = $parameters + [null, 'display'];

        if (!($variant instanceof ProductVariant)) {
            $this->errors[] = "First parameter must be ProductVariant instance";
            return false;
        }

        $validTypes = ['display', 'all', 'stats', 'availability', 'source'];
        if (!in_array($type, $validTypes)) {
            $this->errors[] = "Invalid type '{$type}'. Must be: " . implode(', ', $validTypes);
            return false;
        }

        return true;
    }

    /**
     * 🎯 Get display image with smart fallback
     */
    protected function getDisplayImage(): ?Image
    {
        return $this->variant->getDisplayImage();
    }

    /**
     * 📋 Get all images with source tracking
     */
    protected function getAllImages(): Collection
    {
        return $this->variant->getDisplayImages();
    }

    /**
     * 📊 Get comprehensive stats
     */
    protected function getStats(): array
    {
        $baseStats = $this->availability;
        
        return array_merge($baseStats, [
            'hierarchy_breakdown' => $this->getHierarchyBreakdown(),
            'recommendations' => $this->getRecommendations(),
        ]);
    }

    /**
     * 📊 Get hierarchy breakdown
     */
    protected function getHierarchyBreakdown(): array
    {
        return [
            'variant' => [
                'count' => $this->availability['variant_images'],
                'has_primary' => $this->variant->primaryImage() !== null,
                'priority' => 1,
            ],
            'color_group' => [
                'count' => $this->availability['color_group_images'],
                'color' => $this->variant->color,
                'has_primary' => $this->variant->color && 
                    $this->variant->product->getPrimaryImageForColor($this->variant->color) !== null,
                'priority' => 2,
            ],
            'product' => [
                'count' => $this->availability['product_images'],
                'has_primary' => $this->variant->product->primaryImage() !== null,
                'priority' => 3,
            ],
        ];
    }

    /**
     * 💡 Get recommendations for image improvements
     */
    protected function getRecommendations(): array
    {
        $recommendations = [];

        // Check for missing variant images
        if ($this->availability['variant_images'] === 0) {
            $recommendations[] = [
                'type' => 'missing_variant_images',
                'message' => "Consider adding variant-specific images for {$this->variant->sku}",
                'priority' => 'medium',
            ];
        }

        // Check for missing color group images
        if ($this->availability['color_group_images'] === 0 && $this->variant->color) {
            $recommendations[] = [
                'type' => 'missing_color_images',
                'message' => "Consider adding {$this->variant->color} color group images",
                'priority' => 'low',
            ];
        }

        // Check for no images at all
        if (!$this->availability['has_any_images']) {
            $recommendations[] = [
                'type' => 'no_images',
                'message' => "This variant has no images available through any source",
                'priority' => 'high',
            ];
        }

        return $recommendations;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 🎯 Get display image
     */
    public function display(): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("display() requires fluent mode");
        }
        return $this->getDisplayImage();
    }

    /**
     * 📋 Get all images
     */
    public function images(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("images() requires fluent mode");
        }
        return $this->getAllImages();
    }

    /**
     * 📊 Get stats
     */
    public function stats(): array
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("stats() requires fluent mode");
        }
        return $this->getStats();
    }

    /**
     * 🎯 Get image source
     */
    public function source(): string
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("source() requires fluent mode");
        }
        return $this->availability['display_image_source'] ?? 'none';
    }

    /**
     * ✅ Check if has any images
     */
    public function hasImages(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("hasImages() requires fluent mode");
        }
        return $this->availability['has_any_images'] ?? false;
    }
}