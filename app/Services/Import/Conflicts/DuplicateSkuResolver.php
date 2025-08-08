<?php

namespace App\Services\Import\Conflicts;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class DuplicateSkuResolver implements ConflictResolverInterface
{
    private string $strategy;
    private bool $allowUpdates;
    private bool $generateUniqueSku;

    public function __construct(array $config = [])
    {
        $this->strategy = $config['strategy'] ?? 'use_existing';
        $this->allowUpdates = $config['allow_updates'] ?? false;
        $this->generateUniqueSku = $config['generate_unique_sku'] ?? false;
    }

    public function canResolve(array $conflictData): bool
    {
        return isset($conflictData['constraint']) 
            && strpos($conflictData['constraint'], 'sku') !== false;
    }

    public function resolve(array $conflictData, array $context = []): ConflictResolution
    {
        $sku = $conflictData['conflicting_value'] ?? $context['variant_sku'] ?? null;
        
        if (!$sku) {
            return ConflictResolution::failed('No SKU found in conflict data or context');
        }

        Log::debug('Resolving duplicate SKU conflict', [
            'sku' => $sku,
            'strategy' => $this->strategy,
            'import_mode' => $context['import_mode'] ?? 'unknown',
        ]);

        // Find existing variant
        $existingVariant = ProductVariant::where('sku', $sku)->first();
        if (!$existingVariant) {
            return ConflictResolution::failed('SKU conflict reported but no existing variant found');
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

    private function handleCreateOnly(ProductVariant $existing, array $context): ConflictResolution
    {
        if ($this->generateUniqueSku) {
            $uniqueSku = $this->generateUniqueSku($context['variant_sku']);
            return ConflictResolution::retryWithModifiedData(
                ['variant_sku' => $uniqueSku],
                "Generated unique SKU: {$uniqueSku}",
                [
                    'original_sku' => $context['variant_sku'],
                    'generated_sku' => $uniqueSku,
                    'existing_variant_id' => $existing->id,
                ]
            );
        }

        return ConflictResolution::skip(
            "SKU already exists in create_only mode: {$existing->sku}",
            [
                'existing_variant_id' => $existing->id,
                'existing_product_id' => $existing->product_id,
            ]
        );
    }

    private function handleUpdateExisting(ProductVariant $existing, array $context): ConflictResolution
    {
        // Update the existing variant with new data
        $updateData = $this->prepareUpdateData($context);
        
        return ConflictResolution::updateExisting(
            $updateData,
            "Updating existing variant: {$existing->sku}",
            [
                'existing_variant_id' => $existing->id,
                'update_fields' => array_keys($updateData),
            ]
        );
    }

    private function handleCreateOrUpdate(ProductVariant $existing, array $context): ConflictResolution
    {
        // This should normally be handled by updateOrCreate, but if we're here
        // it means there was a race condition or the query failed
        $updateData = $this->prepareUpdateData($context);
        
        return ConflictResolution::updateExisting(
            $updateData,
            "Race condition detected, updating existing variant: {$existing->sku}",
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
                    "Using existing variant: {$existing->sku}",
                    ['existing_variant_id' => $existing->id]
                );

            case 'update_existing':
                if ($this->allowUpdates) {
                    $updateData = $this->prepareUpdateData($context);
                    return ConflictResolution::updateExisting(
                        $updateData,
                        "Updating existing variant: {$existing->sku}"
                    );
                }
                return ConflictResolution::skip(
                    "Updates not allowed, skipping: {$existing->sku}"
                );

            case 'generate_unique':
                if ($this->generateUniqueSku) {
                    $uniqueSku = $this->generateUniqueSku($context['variant_sku']);
                    return ConflictResolution::retryWithModifiedData(
                        ['variant_sku' => $uniqueSku],
                        "Generated unique SKU: {$uniqueSku}"
                    );
                }
                return ConflictResolution::skip(
                    "Unique generation disabled, skipping: {$existing->sku}"
                );

            case 'skip':
            default:
                return ConflictResolution::skip(
                    "Duplicate SKU, skipping: {$existing->sku}",
                    ['existing_variant_id' => $existing->id]
                );
        }
    }

    private function prepareUpdateData(array $context): array
    {
        $updateData = [];
        
        // Only include fields that should be updated
        $updatableFields = [
            'stock_level',
            'package_length',
            'package_width', 
            'package_height',
            'package_weight',
        ];

        foreach ($updatableFields as $field) {
            if (isset($context[$field]) && $context[$field] !== '') {
                $updateData[$field] = $context[$field];
            }
        }

        return $updateData;
    }

    private function generateUniqueSku(string $baseSku): string
    {
        $counter = 1;
        $uniqueSku = $baseSku;

        while (ProductVariant::where('sku', $uniqueSku)->exists()) {
            $uniqueSku = $baseSku . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
            $counter++;
            
            if ($counter > 999) {
                // Prevent infinite loop
                $uniqueSku = $baseSku . '-' . uniqid();
                break;
            }
        }

        return $uniqueSku;
    }
}