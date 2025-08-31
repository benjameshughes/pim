<?php

namespace App\Actions\Import;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * 🏭 CREATE OR UPDATE PRODUCT ACTION
 *
 * Handles creation and updating of parent products during import
 */
class CreateOrUpdateProduct
{
    /**
     * Create or update parent product using updateOrCreate
     *
     * @param  array  $parentInfo  Extracted parent product information
     */
    public function execute(array $parentInfo): Product
    {
        $product = Product::updateOrCreate(
            [
                'parent_sku' => $parentInfo['parent_sku'],
            ],
            [
                'name' => $parentInfo['product_name'],
                'status' => 'active',
                'description' => "Imported product - {$parentInfo['product_name']}",
            ]
        );

        if ($product->wasRecentlyCreated) {
            Log::debug('Created parent product', [
                'parent_sku' => $parentInfo['parent_sku'],
                'name' => $parentInfo['product_name'],
            ]);
        } else {
            Log::debug('Updated parent product', [
                'parent_sku' => $parentInfo['parent_sku'],
                'name' => $parentInfo['product_name'],
            ]);
        }

        return $product;
    }
}
