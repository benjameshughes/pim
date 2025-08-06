<?php

namespace App\Services\SmartRecommendations\Actions;

use App\Models\ProductVariant;
use App\Models\BarcodePool;
use App\Models\VariantBarcode;
use Illuminate\Support\Facades\DB;

class AssignMissingBarcodesAction extends BaseRecommendationAction
{
    public function getType(): string
    {
        return 'assign_missing_barcodes';
    }

    public function getName(): string
    {
        return 'Assign Missing Barcodes';
    }

    public function getPreview(array $variantIds): array
    {
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->whereDoesntHave('barcodes')
            ->with('product')
            ->take(5)
            ->get();

        $availableBarcodes = BarcodePool::available()->count();

        return [
            'action' => 'Assign barcodes from your barcode pool',
            'affected_variants' => $variants->count(),
            'available_barcodes' => $availableBarcodes,
            'can_complete' => $availableBarcodes >= $variants->count(),
            'sample_variants' => $variants->map(fn($variant) => [
                'sku' => $variant->sku,
                'product' => $variant->product->name,
            ])->toArray(),
        ];
    }

    public function canExecute(array $variantIds): bool
    {
        $variantsNeedingBarcodes = ProductVariant::whereIn('id', $variantIds)
            ->whereDoesntHave('barcodes')
            ->count();

        $availableBarcodes = BarcodePool::available()->count();

        return $variantsNeedingBarcodes > 0 && $availableBarcodes >= $variantsNeedingBarcodes;
    }

    protected function performAction(array $variantIds): bool
    {
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->whereDoesntHave('barcodes')
            ->get();

        if ($variants->isEmpty()) {
            return true; // Nothing to do
        }

        return DB::transaction(function () use ($variants) {
            $assigned = 0;

            foreach ($variants as $variant) {
                // Get next available barcode
                $barcodePool = BarcodePool::available()->first();
                
                if (!$barcodePool) {
                    // No more barcodes available
                    break;
                }

                // Assign barcode to variant
                VariantBarcode::create([
                    'variant_id' => $variant->id,
                    'barcode' => $barcodePool->barcode,
                    'barcode_type' => $barcodePool->barcode_type ?? 'EAN13',
                    'is_primary' => true,
                ]);

                // Mark barcode as used
                $barcodePool->update([
                    'is_used' => true,
                    'assigned_to' => 'variant',
                    'assigned_id' => $variant->id,
                    'assigned_at' => now(),
                ]);

                $assigned++;
            }

            return $assigned > 0;
        });
    }
}