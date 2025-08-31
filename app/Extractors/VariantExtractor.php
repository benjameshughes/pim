<?php

namespace App\Extractors;

use App\Models\Variant;

class VariantExtractor
{
    public static function extract(Variant $variant): array
    {
        return [
            'id' => $variant->id,
            'type' => 'Variant',
            'name' => $variant->name ?? $variant->product?->name,
            'sku' => $variant->sku,
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'stock_quantity' => $variant->stock_quantity,
            'retail_price' => $variant->retail_price,
        ];
    }
}
