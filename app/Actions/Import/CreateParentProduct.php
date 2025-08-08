<?php

namespace App\Actions\Import;

use App\Models\Product;

class CreateParentProduct
{
    public function execute(array $variantData): Product
    {
        $productName = $variantData['product_name'] ?? '';
        $variantSku = $variantData['variant_sku'] ?? '';

        // Generate a clean parent name
        $parentName = app(GenerateParentName::class)->execute($productName, $variantSku);

        // Generate unique slug
        $slug = app(GenerateProductSlug::class)->execute($parentName);

        return Product::create([
            'name' => $parentName,
            'slug' => $slug,
            'description' => $variantData['description'] ?? "Parent product for {$parentName}",
            'status' => 'active',
            'parent_sku' => null, // This is a parent product
        ]);
    }
}
