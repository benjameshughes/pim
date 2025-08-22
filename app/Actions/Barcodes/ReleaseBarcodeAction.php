<?php

namespace App\Actions\Barcodes;

use App\Actions\Base\BaseAction;
use App\Models\BarcodePool;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🔄 RELEASE BARCODE ACTION
 *
 * Release a barcode from a variant and make it available again with:
 * - Safe release with validation
 * - Cleanup of barcode records
 * - Historical tracking preservation
 * - Transaction safety
 */
class ReleaseBarcodeAction extends BaseAction
{
    /**
     * Release a barcode from a variant
     *
     * @param ProductVariant $variant The variant to release barcode from
     * @param string $type Barcode type to release (default: EAN13)
     * @return array Action result with release status
     */
    protected function performAction(...$params): array
    {
        $variant = $params[0] ?? null;
        $type = $params[1] ?? 'EAN13';

        if (!$variant instanceof ProductVariant) {
            throw new \InvalidArgumentException('First parameter must be a ProductVariant instance');
        }

        Log::info("Starting barcode release", [
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'type' => $type,
        ]);

        return DB::transaction(function () use ($variant, $type) {
            // Find the assigned barcode in the barcodes table
            $barcode = $variant->barcodes()
                ->where('type', strtolower($type))
                ->first();

            if (!$barcode) {
                Log::info("No barcode found to release", [
                    'variant_id' => $variant->id,
                    'type' => $type,
                ]);

                return [
                    'released' => false,
                    'barcode_pool' => null,
                    'message' => "No {$type} barcode found for this variant"
                ];
            }

            // Find the corresponding barcode pool record
            $barcodePool = BarcodePool::where('barcode', $barcode->barcode)
                ->where('assigned_to_variant_id', $variant->id)
                ->first();

            if (!$barcodePool) {
                Log::warning("Barcode found in barcodes table but not in pool", [
                    'variant_id' => $variant->id,
                    'barcode' => $barcode->barcode,
                ]);

                // Clean up orphaned barcode record
                $barcode->delete();

                return [
                    'released' => true,
                    'barcode_pool' => null,
                    'message' => "Cleaned up orphaned barcode record"
                ];
            }

            // Release the barcode using the pool model's method
            $success = $barcodePool->release();

            if (!$success) {
                Log::error("Failed to release barcode", [
                    'variant_id' => $variant->id,
                    'barcode_pool_id' => $barcodePool->id,
                ]);

                throw new \Exception("Failed to release barcode from variant");
            }

            Log::info("Barcode released successfully", [
                'variant_id' => $variant->id,
                'barcode' => $barcodePool->barcode,
                'made_available' => true,
            ]);

            return [
                'released' => true,
                'barcode_pool' => $barcodePool->fresh(),
                'message' => "Released barcode {$barcodePool->barcode} from variant {$variant->sku}"
            ];
        });
    }
}