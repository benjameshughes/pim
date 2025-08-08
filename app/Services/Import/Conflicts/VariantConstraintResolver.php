<?php

namespace App\Services\Import\Conflicts;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class VariantConstraintResolver implements ConflictResolverInterface
{
    private string $strategy;
    private bool $allowMerging;
    private bool $allowDimensionUpdates;

    public function __construct(array $config = [])
    {
        $this->strategy = $config['strategy'] ?? 'use_existing';
        $this->allowMerging = $config['allow_merging'] ?? true;
        $this->allowDimensionUpdates = $config['allow_dimension_updates'] ?? true;
    }

    public function canResolve(array $conflictData): bool
    {
        return isset($conflictData['constraint']) 
            && (strpos($conflictData['constraint'], 'color_size') !== false 
                || strpos($conflictData['constraint'], 'product_id') !== false);
    }

    public function resolve(array $conflictData, array $context = []): ConflictResolution
    {
        Log::debug('Resolving variant constraint conflict', [
            'constraint' => $conflictData['constraint'] ?? 'unknown',
            'strategy' => $this->strategy,
            'context_keys' => array_keys($context),
        ]);

        // Extract constraint components
        $productId = $context['product_id'] ?? null;
        $color = $context['variant_color'] ?? null;
        $size = $context['variant_size'] ?? $context['extracted_size'] ?? null;

        if (!$productId) {
            return ConflictResolution::failed('No product ID available for variant constraint resolution');
        }

        // Find existing variant with same product_id + color + size combination
        $existingVariant = $this->findConflictingVariant($productId, $color, $size);
        if (!$existingVariant) {
            return ConflictResolution::failed('Variant constraint violation but no conflicting variant found');
        }

        $importMode = $context['import_mode'] ?? 'create_or_update';

        switch ($importMode) {
            case 'create_only':
                return $this->handleCreateOnly($existingVariant, $context);

            case 'update_existing':
                return $this->handleUpdateExisting($existingVariant, $context);

            case 'create_or_update':
                return $this->handleCreateOrUpdate($existingVariant, $context);

            default:
                return $this->handleByStrategy($existingVariant, $context);
        }
    }

    private function findConflictingVariant(int $productId, ?string $color, ?string $size): ?ProductVariant
    {
        $query = ProductVariant::where('product_id', $productId);

        // Handle color matching
        if ($color !== null) {
            $query->whereHas('variantAttributes', function ($q) use ($color) {
                $q->where('key', 'color')
                  ->where('value', $color);
            });
        } else {
            $query->whereDoesntHave('variantAttributes', function ($q) {
                $q->where('key', 'color');
            });
        }

        // Handle size matching
        if ($size !== null) {
            $query->whereHas('variantAttributes', function ($q) use ($size) {
                $q->where('key', 'size')
                  ->where('value', $size);
            });
        } else {
            $query->whereDoesntHave('variantAttributes', function ($q) {
                $q->where('key', 'size');
            });
        }

        return $query->first();
    }

    private function handleCreateOnly(ProductVariant $existing, array $context): ConflictResolution
    {
        return ConflictResolution::skip(
            "Variant with same color/size combination already exists in create_only mode",
            [
                'existing_variant_id' => $existing->id,
                'existing_sku' => $existing->sku,
                'color' => $context['variant_color'] ?? null,
                'size' => $context['variant_size'] ?? $context['extracted_size'] ?? null,
            ]
        );
    }

    private function handleUpdateExisting(ProductVariant $existing, array $context): ConflictResolution
    {
        if (!$this->allowMerging) {
            return ConflictResolution::skip(
                "Merging disabled, skipping variant with existing color/size combination"
            );
        }

        $mergeData = $this->prepareMergeData($existing, $context);
        
        return ConflictResolution::updateExisting(
            $mergeData,
            "Merging data with existing variant: {$existing->sku}",
            [
                'existing_variant_id' => $existing->id,
                'merge_fields' => array_keys($mergeData),
            ]
        );
    }

    private function handleCreateOrUpdate(ProductVariant $existing, array $context): ConflictResolution
    {
        // This is typically a race condition in updateOrCreate
        $mergeData = $this->prepareMergeData($existing, $context);
        
        return ConflictResolution::updateExisting(
            $mergeData,
            "Race condition detected, merging with existing variant: {$existing->sku}",
            [
                'existing_variant_id' => $existing->id,
                'race_condition' => true,
            ]
        );
    }

    private function handleByStrategy(ProductVariant $existing, array $context): ConflictResolution
    {
        switch ($this->strategy) {
            case 'use_existing':
                return ConflictResolution::useExisting(
                    "Using existing variant with same color/size: {$existing->sku}",
                    ['existing_variant_id' => $existing->id]
                );

            case 'merge_data':
                if ($this->allowMerging) {
                    $mergeData = $this->prepareMergeData($existing, $context);
                    return ConflictResolution::updateExisting(
                        $mergeData,
                        "Merging data with existing variant: {$existing->sku}"
                    );
                }
                return ConflictResolution::skip("Merging disabled");

            case 'modify_attributes':
                return $this->attemptAttributeModification($existing, $context);

            case 'skip':
            default:
                return ConflictResolution::skip(
                    "Color/size combination already exists, skipping",
                    ['existing_variant_id' => $existing->id]
                );
        }
    }

    private function prepareMergeData(ProductVariant $existing, array $context): array
    {
        $mergeData = [];

        // Merge basic variant fields
        $mergeableFields = [
            'stock_level',
            'package_length', 
            'package_width',
            'package_height',
            'package_weight',
        ];

        foreach ($mergeableFields as $field) {
            if (isset($context[$field]) && $context[$field] !== '') {
                $mergeData[$field] = $context[$field];
            }
        }

        // Handle dimension updates if allowed
        if ($this->allowDimensionUpdates) {
            if (isset($context['extracted_width'])) {
                $mergeData['variant_attributes']['width'] = $context['extracted_width'];
            }
            if (isset($context['extracted_drop'])) {
                $mergeData['variant_attributes']['drop'] = $context['extracted_drop'];
            }
        }

        // Handle MTM status
        if (isset($context['made_to_measure'])) {
            $mergeData['variant_attributes']['made_to_measure'] = $context['made_to_measure'];
        }

        return $mergeData;
    }

    private function attemptAttributeModification(ProductVariant $existing, array $context): ConflictResolution
    {
        // Try to modify attributes to avoid conflict
        $modifications = [];

        // If color is the conflict, try appending a suffix
        if (isset($context['variant_color'])) {
            $originalColor = $context['variant_color'];
            $modifiedColor = $this->generateUniqueColor($context['product_id'], $originalColor, $context['variant_size'] ?? null);
            
            if ($modifiedColor !== $originalColor) {
                $modifications['variant_color'] = $modifiedColor;
            }
        }

        // If size is the conflict, try appending a suffix
        if (isset($context['variant_size']) || isset($context['extracted_size'])) {
            $originalSize = $context['variant_size'] ?? $context['extracted_size'];
            $modifiedSize = $this->generateUniqueSize($context['product_id'], $context['variant_color'] ?? null, $originalSize);
            
            if ($modifiedSize !== $originalSize) {
                $modifications['variant_size'] = $modifiedSize;
            }
        }

        if (!empty($modifications)) {
            return ConflictResolution::retryWithModifiedData(
                $modifications,
                "Modified attributes to avoid conflict: " . implode(', ', array_keys($modifications)),
                ['original_attributes' => array_intersect_key($context, $modifications)]
            );
        }

        return ConflictResolution::skip("Could not modify attributes to avoid conflict");
    }

    private function generateUniqueColor(int $productId, string $baseColor, ?string $size): string
    {
        $counter = 1;
        $uniqueColor = $baseColor;

        while ($this->colorSizeCombinationExists($productId, $uniqueColor, $size) && $counter <= 10) {
            $uniqueColor = $baseColor . '-' . $counter;
            $counter++;
        }

        return $uniqueColor;
    }

    private function generateUniqueSize(int $productId, ?string $color, string $baseSize): string
    {
        $counter = 1;
        $uniqueSize = $baseSize;

        while ($this->colorSizeCombinationExists($productId, $color, $uniqueSize) && $counter <= 10) {
            $uniqueSize = $baseSize . '-' . $counter;
            $counter++;
        }

        return $uniqueSize;
    }

    private function colorSizeCombinationExists(int $productId, ?string $color, ?string $size): bool
    {
        return $this->findConflictingVariant($productId, $color, $size) !== null;
    }
}