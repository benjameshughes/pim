<?php

namespace App\Extractors;

use App\Models\Product;

class ProductExtractor
{
    public static function extract(Product $product): array
    {
        return [
            'id' => $product->id,
            'type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'status' => $product->status,
            'category_id' => $product->category_id,
            'variant_count' => $product->variants_count ?? $product->variants()->count(),
        ];
    }
}
