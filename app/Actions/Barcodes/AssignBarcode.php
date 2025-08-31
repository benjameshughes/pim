<?php

namespace App\Actions\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use Exception;

class AssignBarcode
{
    public function execute(ProductVariant $variant, ?string $csvBarcode = null): ?Barcode
    {
        try {
            // If CSV barcode provided, try to match existing barcode in database
            if (! empty($csvBarcode)) {
                $barcode = Barcode::where('barcode', $csvBarcode)->first();

                if ($barcode) {

                    // Check if already assigned to another variant (ignore is_assigned flag, only check actual variant relationship)
                    if ($barcode->product_variant_id !== null && $barcode->product_variant_id !== $variant->id) {
                        throw new Exception("Barcode '{$csvBarcode}' is already assigned to variant ID {$barcode->product_variant_id}.");
                    }

                    // Found matching barcode - assign it to this variant
                    $barcode->update([
                        'product_variant_id' => $variant->id,
                        'is_assigned' => true,
                        'sku' => $variant->sku,
                        'title' => $variant->title,
                        'updated_at' => now(),
                    ]);

                    return $barcode;
                } else {
                    throw new Exception("Barcode '{$csvBarcode}' from CSV not found in database. Please import this barcode first.");
                }
            }

            // No CSV barcode provided - auto-assign next available
            $barcode = Barcode::where('is_assigned', false)
                ->whereNull('product_variant_id')
                ->orderBy('id')
                ->first();

            if (! $barcode) {
                throw new Exception('No available barcodes found. Please import more barcodes.');
            }

            // Assign the barcode to the variant
            $barcode->update([
                'product_variant_id' => $variant->id,
                'is_assigned' => true,
                'sku' => $variant->sku,
                'title' => $variant->title,
                'updated_at' => now(),
            ]);

            return $barcode;

        } catch (Exception $e) {
            throw new Exception('Failed to assign barcode: '.$e->getMessage());
        }
    }
}
