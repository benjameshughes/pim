<?php

namespace App\Actions\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use Exception;

class AssignBarcode
{
    public function execute(ProductVariant $variant): ?Barcode
    {
        try {
            // Find the next available barcode
            $barcode = Barcode::where('is_assigned', false)
                ->whereNull('product_variant_id')
                ->orderBy('id')
                ->first();

            if (!$barcode) {
                throw new Exception('No available barcodes found. Please import more barcodes.');
            }

            // Assign the barcode to the variant
            $barcode->update([
                'product_variant_id' => $variant->id,
                'is_assigned' => true,
                'updated_at' => now()
            ]);

            return $barcode;

        } catch (Exception $e) {
            throw new Exception('Failed to assign barcode: ' . $e->getMessage());
        }
    }
}