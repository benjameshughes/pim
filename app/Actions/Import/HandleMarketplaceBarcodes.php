<?php

namespace App\Actions\Import;

use App\Models\Marketplace;
use App\Models\MarketplaceBarcode;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

class HandleMarketplaceBarcodes
{
    public function execute(ProductVariant $variant, array $data): void
    {
        $barcodeFields = [
            'amazon_barcode', 'ebay_barcode', 'shopify_barcode',
        ];

        foreach ($barcodeFields as $field) {
            if (! empty($data[$field])) {
                $marketplaceName = str_replace('_barcode', '', $field);

                $marketplace = Marketplace::where('name', $marketplaceName)->first();
                if (! $marketplace) {
                    continue;
                }

                $barcodeType = $this->detectBarcodeType($data[$field]);

                MarketplaceBarcode::updateOrCreate([
                    'variant_id' => $variant->id,
                    'marketplace_id' => $marketplace->id,
                ], [
                    'barcode' => $data[$field],
                    'type' => $barcodeType,
                    'is_active' => true,
                ]);

                Log::info('Created/updated marketplace barcode', [
                    'variant_id' => $variant->id,
                    'marketplace' => $marketplaceName,
                    'barcode' => $data[$field],
                    'type' => $barcodeType,
                ]);
            }
        }
    }

    private function detectBarcodeType(string $barcode): string
    {
        $length = strlen($barcode);

        return match ($length) {
            8 => 'EAN8',
            12 => 'UPC-A',
            13 => 'EAN13',
            6 => 'UPC-E',
            default => 'CODE128'
        };
    }
}
