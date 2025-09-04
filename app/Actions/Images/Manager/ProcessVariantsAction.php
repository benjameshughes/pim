<?php

namespace App\Actions\Images\Manager;

use App\Actions\Images\ProcessImageVariantsAction;
use App\Models\Image;
use Illuminate\Support\Collection;

/**
 * 🎨 PROCESS VARIANTS ACTION - FACADE INTEGRATION
 *
 * Facade-friendly wrapper for ProcessImageVariantsAction
 * Maintains full backwards compatibility while adding fluent API
 * 
 * Usage:
 * Images::process($image)->variants(['thumb', 'small'])
 * Images::process($image)->thumbnails()
 * Images::process($image)->all()
 */
class ProcessVariantsAction implements ImageActionInterface
{
    use ImageActionTrait;

    protected ProcessImageVariantsAction $legacyAction;
    protected ?Image $contextImage = null;

    public function __construct()
    {
        $this->legacyAction = new ProcessImageVariantsAction();
    }

    /**
     * 🎨 Execute variant processing
     *
     * @param mixed ...$parameters - [image, variantTypes]
     */
    public function execute(...$parameters): mixed
    {
        [$image, $variantTypes] = $parameters + [null, ['thumb', 'small', 'medium']];

        if (!$this->canExecute($image, $variantTypes)) {
            return $this->handleReturn([]);
        }

        // Store image for fluent API
        $this->contextImage = $image;

        // Use the existing action for actual processing
        $result = $this->legacyAction->execute($image, $variantTypes);

        $this->logAction('process_variants', [
            'image_id' => $image->id,
            'variant_types' => $variantTypes,
            'success' => $result['success'] ?? false,
            'variants_generated' => $result['data']['variants_generated'] ?? 0,
        ]);

        // Return variants collection for fluent API
        $variants = collect($result['data']['variants'] ?? []);
        
        return $this->handleReturn($variants);
    }

    /**
     * ✅ Validate processing parameters
     */
    public function canExecute(...$parameters): bool
    {
        [$image, $variantTypes] = $parameters + [null, []];

        if (!($image instanceof Image)) {
            $this->errors[] = "First parameter must be an Image instance";
            return false;
        }

        if (!is_array($variantTypes)) {
            $this->errors[] = "Variant types must be an array";
            return false;
        }

        return true;
    }

    /**
     * 🎯 Get context image for fluent API
     */
    protected function getContextImage(): Image
    {
        if (!$this->contextImage) {
            throw new \BadMethodCallException("No image context available. Call execute() first.");
        }
        return $this->contextImage;
    }

    /**
     * 🎯 FLUENT API METHODS (when in fluent mode)
     */

    /**
     * 📷 Generate thumbnails only
     */
    public function thumbnails(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("thumbnails() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        $result = $this->legacyAction->execute($image, ['thumb']);
        
        return collect($result['data']['variants'] ?? []);
    }

    /**
     * 🎨 Generate specific variants
     */
    public function variants(array $types = ['thumb', 'small', 'medium']): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variants() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        $result = $this->legacyAction->execute($image, $types);
        
        $this->logAction('process_variants_fluent', [
            'image_id' => $image->id,
            'variant_types' => $types,
            'success' => $result['success'] ?? false,
            'variants_generated' => $result['data']['variants_generated'] ?? 0,
        ]);
        
        return collect($result['data']['variants'] ?? []);
    }

    /**
     * 🌟 Generate all available variants
     */
    public function all(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("all() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        $result = $this->legacyAction->execute($image, ['thumb', 'small', 'medium', 'large', 'extra-large']);
        
        return collect($result['data']['variants'] ?? []);
    }

    /**
     * 🔍 Get existing variants
     */
    public function existing(): Collection
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("existing() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        return $this->legacyAction->getVariants($image);
    }

    /**
     * 🗑️ Delete all variants
     */
    public function deleteAll(): bool
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("deleteAll() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        $this->legacyAction->deleteVariants($image);
        
        return true;
    }

    /**
     * 🎯 Get specific variant type
     */
    public function variant(string $type): ?Image
    {
        if (!$this->fluent) {
            throw new \BadMethodCallException("variant() requires fluent mode");
        }
        
        $image = $this->getContextImage();
        return $this->legacyAction->getVariant($image, $type);
    }
}