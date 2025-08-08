<?php

namespace App\Actions\Import;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProcessParentRow
{
    public function execute(array $data, int $rowNumber): Product
    {
        $name = $data['product_name'] ?? "Parent Product {$rowNumber}";
        $slug = app(GenerateUniqueSlug::class)->execute($name, 'product');

        $product = Product::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_parent' => true,
            'status' => $data['status'] ?? 'active',
            'product_features_1' => $data['product_features_1'] ?? null,
            'product_features_2' => $data['product_features_2'] ?? null,
            'product_features_3' => $data['product_features_3'] ?? null,
            'product_features_4' => $data['product_features_4'] ?? null,
            'product_features_5' => $data['product_features_5'] ?? null,
            'product_details_1' => $data['product_details_1'] ?? null,
            'product_details_2' => $data['product_details_2'] ?? null,
            'product_details_3' => $data['product_details_3'] ?? null,
            'product_details_4' => $data['product_details_4'] ?? null,
            'product_details_5' => $data['product_details_5'] ?? null,
        ]);

        Log::info('Created parent product', [
            'id' => $product->id,
            'name' => $product->name,
            'row' => $rowNumber,
        ]);

        return $product;
    }
}
