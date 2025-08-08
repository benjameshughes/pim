<?php

namespace App\Services\Import\Conflicts;

use App\Models\VariantBarcode;
use Illuminate\Support\Facades\Log;

class DuplicateBarcodeResolver implements ConflictResolverInterface
{
    private string $strategy;
    private bool $allowReassignment;

    public function __construct(array $config = [])
    {
        $this->strategy = $config['strategy'] ?? 'skip';
        $this->allowReassignment = $config['allow_reassignment'] ?? false;
    }

    public function canResolve(array $conflictData): bool
    {
        return isset($conflictData['constraint']) 
            && strpos($conflictData['constraint'], 'barcode') !== false;
    }

    public function resolve(array $conflictData, array $context = []): ConflictResolution
    {
        $barcode = $conflictData['conflicting_value'] ?? $context['barcode'] ?? null;
        
        if (!$barcode) {
            return ConflictResolution::failed('No barcode found in conflict data or context');
        }

        Log::debug('Resolving duplicate barcode conflict', [
            'barcode' => $barcode,
            'strategy' => $this->strategy,
            'variant_sku' => $context['variant_sku'] ?? 'unknown',
        ]);

        // Find existing barcode
        $existingBarcode = VariantBarcode::where('barcode', $barcode)->first();
        if (!$existingBarcode) {
            return ConflictResolution::failed('Barcode conflict reported but no existing barcode found');
        }

        switch ($this->strategy) {
            case 'skip':
                return ConflictResolution::skip(
                    "Barcode already exists, skipping: {$barcode}",
                    [
                        'existing_variant_id' => $existingBarcode->product_variant_id,
                        'existing_barcode_id' => $existingBarcode->id,
                    ]
                );

            case 'reassign':
                return $this->handleReassignment($existingBarcode, $context);

            case 'remove_barcode':
                return ConflictResolution::retryWithModifiedData(
                    ['barcode' => null],
                    "Removed conflicting barcode from import data: {$barcode}",
                    [
                        'original_barcode' => $barcode,
                        'existing_variant_id' => $existingBarcode->product_variant_id,
                    ]
                );

            case 'use_existing_assignment':
                return ConflictResolution::useExisting(
                    "Barcode already assigned, using existing assignment: {$barcode}",
                    [
                        'existing_variant_id' => $existingBarcode->product_variant_id,
                        'barcode_already_assigned' => true,
                    ]
                );

            default:
                return ConflictResolution::failed("Unknown barcode resolution strategy: {$this->strategy}");
        }
    }

    private function handleReassignment(VariantBarcode $existingBarcode, array $context): ConflictResolution
    {
        if (!$this->allowReassignment) {
            return ConflictResolution::skip(
                "Barcode reassignment not allowed: {$existingBarcode->barcode}",
                ['existing_variant_id' => $existingBarcode->product_variant_id]
            );
        }

        $currentVariantSku = $context['variant_sku'] ?? null;
        if (!$currentVariantSku) {
            return ConflictResolution::failed('Cannot reassign barcode: no variant SKU provided');
        }

        // Check if we're trying to assign to the same variant
        $currentVariant = \App\Models\ProductVariant::where('sku', $currentVariantSku)->first();
        if ($currentVariant && $currentVariant->id === $existingBarcode->product_variant_id) {
            return ConflictResolution::useExisting(
                "Barcode already assigned to this variant: {$existingBarcode->barcode}",
                ['variant_already_owns_barcode' => true]
            );
        }

        // Reassign the barcode to the new variant
        return ConflictResolution::resolved(
            ConflictResolution::ACTION_UPDATE,
            ConflictResolution::STRATEGY_MERGE_DATA,
            "Reassigning barcode from variant {$existingBarcode->product_variant_id} to current variant",
            [],
            [
                'reassign_barcode' => true,
                'barcode' => $existingBarcode->barcode,
                'from_variant_id' => $existingBarcode->product_variant_id,
                'to_variant_sku' => $currentVariantSku,
            ]
        );
    }

    /**
     * Get suggested barcode resolution strategies based on context
     */
    public static function getSuggestedStrategies(array $context = []): array
    {
        $strategies = [
            'skip' => [
                'name' => 'Skip Row',
                'description' => 'Skip importing this row due to barcode conflict',
                'risk' => 'low',
                'data_loss' => false,
            ],
            'remove_barcode' => [
                'name' => 'Remove Barcode',
                'description' => 'Import the variant without the conflicting barcode',
                'risk' => 'low',
                'data_loss' => true,
            ],
            'use_existing_assignment' => [
                'name' => 'Use Existing',
                'description' => 'Accept the existing barcode assignment',
                'risk' => 'low',
                'data_loss' => false,
            ],
        ];

        // Only suggest reassignment if explicitly allowed
        if (($context['allow_barcode_reassignment'] ?? false)) {
            $strategies['reassign'] = [
                'name' => 'Reassign Barcode',
                'description' => 'Move barcode from existing variant to new variant',
                'risk' => 'high',
                'data_loss' => false,
            ];
        }

        return $strategies;
    }
}