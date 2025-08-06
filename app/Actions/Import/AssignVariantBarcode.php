<?php

namespace App\Actions\Import;

use App\Models\ProductVariant;
use App\Models\Barcode;

class AssignVariantBarcode
{
    public function execute(ProductVariant $variant, string $barcodeValue): void
    {
        // Detect barcode type
        $barcodeType = $this->detectBarcodeType($barcodeValue);
        
        // Create barcode record
        Barcode::create([
            'variant_id' => $variant->id,
            'barcode' => $barcodeValue,
            'type' => $barcodeType,
            'auto_detected' => true,
        ]);
    }
    
    private function detectBarcodeType(string $barcode): string
    {
        $length = strlen($barcode);
        
        return match($length) {
            8 => 'EAN8',
            12 => 'UPC-A',
            13 => 'EAN13',
            6 => 'UPC-E',
            default => 'CODE128'
        };
    }
}