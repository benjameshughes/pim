<?php

namespace App\Actions\Import;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

class CreateParentFromGroup
{
    public function execute(string $parentKey, array $variantDataArray): Product
    {
        $firstVariant = $variantDataArray[0];

        // Generate parent name from the group
        $parentName = $this->generateParentNameFromGroup($variantDataArray);
        $slug = app(GenerateUniqueSlug::class)->execute($parentName, 'product');

        // Create parent product using data from first variant
        $product = Product::create([
            'name' => $parentName,
            'slug' => $slug,
            'description' => $firstVariant['description'] ?? null,
            'is_parent' => true,
            'status' => $firstVariant['status'] ?? 'active',
            'product_features_1' => $firstVariant['product_features_1'] ?? null,
            'product_features_2' => $firstVariant['product_features_2'] ?? null,
            'product_features_3' => $firstVariant['product_features_3'] ?? null,
            'product_features_4' => $firstVariant['product_features_4'] ?? null,
            'product_features_5' => $firstVariant['product_features_5'] ?? null,
            'product_details_1' => $firstVariant['product_details_1'] ?? null,
            'product_details_2' => $firstVariant['product_details_2'] ?? null,
            'product_details_3' => $firstVariant['product_details_3'] ?? null,
            'product_details_4' => $firstVariant['product_details_4'] ?? null,
            'product_details_5' => $firstVariant['product_details_5'] ?? null,
        ]);

        // Handle product attributes
        app(HandleProductAttributes::class)->execute($product, $firstVariant);

        Log::info('Created parent from group', [
            'parent_id' => $product->id,
            'parent_name' => $parentName,
            'parent_key' => $parentKey,
            'variant_count' => count($variantDataArray),
        ]);

        return $product;
    }

    private function generateParentNameFromGroup(array $variantDataArray): string
    {
        // Get the most common base name from the group
        $names = array_column($variantDataArray, 'product_name');
        $cleanedNames = array_map([$this, 'cleanNameForParent'], $names);

        // Find the most frequent cleaned name
        $nameCounts = array_count_values($cleanedNames);
        arsort($nameCounts);

        $parentName = array_key_first($nameCounts);

        return $parentName ?: ($variantDataArray[0]['product_name'] ?? 'Auto-Generated Parent');
    }

    private function cleanNameForParent(string $name): string
    {
        // Remove size and color variations from name
        $patterns = [
            '/\s*\[.*?\]\s*/',  // Remove bracketed content
            '/\s*\(.*?\)\s*/',  // Remove parenthetical content
            '/\s*-\s*[A-Z]{1,2}$/',  // Remove size codes at end
            '/\s+(Small|Medium|Large|XL|XXL|S|M|L)$/i',
            '/\s+(Red|Blue|Green|White|Black|Yellow|Pink|Purple|Orange|Brown|Grey|Gray)$/i',
        ];

        $cleaned = $name;
        foreach ($patterns as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        return trim($cleaned);
    }
}
