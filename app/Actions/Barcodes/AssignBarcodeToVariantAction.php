<?php

namespace App\Actions\Barcodes;

use App\Actions\Base\BaseAction;
use App\Exceptions\BarcodePoolExhaustedException;
use App\Models\BarcodePool;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🏊‍♂️ ASSIGN BARCODE TO VARIANT ACTION
 *
 * Smart barcode assignment with:
 * - Quality-based selection from row 40,000+
 * - Transaction safety
 * - Pool exhaustion handling
 * - Assignment tracking
 */
class AssignBarcodeToVariantAction extends BaseAction
{
    /**
     * Assign a high-quality barcode to a variant
     *
     * @param ProductVariant $variant The variant to assign barcode to
     * @param string $type Barcode type (default: EAN13)
     * @return array Action result with assigned barcode
     */
    protected function performAction(...$params): array
    {
        $variant = $params[0] ?? null;
        $type = $params[1] ?? 'EAN13';

        if (!$variant instanceof ProductVariant) {
            throw new \InvalidArgumentException('First parameter must be a ProductVariant instance');
        }

        Log::info("Starting barcode assignment", [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'type' => $type,
        ]);

        return DB::transaction(function () use ($variant, $type) {
            // Check if variant already has a barcode of this type
            $existingBarcode = $variant->barcodes()
                ->where('type', strtolower($type))
                ->first();

            if ($existingBarcode) {
                Log::info("Variant already has barcode", [
                    'variant_id' => $variant->id,
                    'existing_barcode' => $existingBarcode->barcode,
                ]);

                return [
                    'barcode_pool' => null,
                    'existing_barcode' => $existingBarcode,
                    'assigned' => false,
                    'message' => 'Variant already has a barcode of this type'
                ];
            }

            // Find next available high-quality barcode
            $barcodePool = BarcodePool::readyForAssignment($type)
                ->highQuality(7) // Minimum quality score
                ->assignmentPriority()
                ->first();

            if (!$barcodePool) {
                Log::warning("No barcodes available for assignment", [
                    'variant_id' => $variant->id,
                    'type' => $type,
                ]);

                throw new BarcodePoolExhaustedException(
                    "No high-quality {$type} barcodes available for assignment. Please import more barcodes or lower quality requirements."
                );
            }

            // Assign the barcode
            $success = $barcodePool->assignTo($variant);

            if (!$success) {
                Log::error("Failed to assign barcode", [
                    'variant_id' => $variant->id,
                    'barcode_pool_id' => $barcodePool->id,
                ]);

                throw new \Exception("Failed to assign barcode to variant");
            }

            Log::info("Barcode assigned successfully", [
                'variant_id' => $variant->id,
                'barcode' => $barcodePool->barcode,
                'quality_score' => $barcodePool->quality_score,
            ]);

            return [
                'barcode_pool' => $barcodePool->fresh(),
                'existing_barcode' => null,
                'assigned' => true,
                'message' => "Assigned barcode {$barcodePool->barcode} to variant {$variant->sku}"
            ];
        });
    }
}