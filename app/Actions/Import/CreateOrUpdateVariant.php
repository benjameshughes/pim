<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;

/**
 * 🎨 CREATE OR UPDATE VARIANT ACTION
 *
 * Handles creation and updating of product variants during import
 */
class CreateOrUpdateVariant
{
    /**
     * Create or update variant using updateOrCreate
     *
     * @param Product $product Parent product
     * @param array $data Row data
     * @param array $parentInfo Extracted parent information
     * @return ProductVariant
     */
    public function execute(Product $product, array $data, array $parentInfo): ProductVariant
    {
        $variant = ProductVariant::updateOrCreate(
            [
                'sku' => $data['sku'],
            ],
            [
                'product_id' => $product->id,
                'external_sku' => $data['sku'],
                'title' => $data['title'],
                'color' => $parentInfo['color'],
                'width' => $parentInfo['width'] ?: 100, // Default width if null
                'drop' => $parentInfo['drop'] ?: 160,   // Default drop if null (updated from 150)
                'price' => 0, // Use decoupled pricing system instead
                'status' => 'active',
                'stock_level' => 0,
            ]
        );

        if ($variant->wasRecentlyCreated) {
            Log::debug('Created variant', [
                'sku' => $data['sku'],
                'product_id' => $product->id,
                'color' => $parentInfo['color'],
                'dimensions' => $parentInfo['width'] . 'cm x ' . ($parentInfo['drop'] ?: 160) . 'cm'
            ]);
        } else {
            Log::debug('Updated variant', [
                'sku' => $data['sku'],
                'product_id' => $product->id,
                'color' => $parentInfo['color'],
                'dimensions' => $parentInfo['width'] . 'cm x ' . ($parentInfo['drop'] ?: 160) . 'cm'
            ]);
        }

        return $variant;
    }
}