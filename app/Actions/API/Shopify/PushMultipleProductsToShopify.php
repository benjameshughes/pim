<?php

namespace App\Actions\API\Shopify;

use Illuminate\Support\Collection;

class PushMultipleProductsToShopify
{
    public function __construct(
        private PushProductToShopify $pushProductToShopify
    ) {}

    /**
     * Push multiple Laravel products to Shopify with color-based parent splitting
     * 
     * Processes a collection of products and returns comprehensive results
     */
    public function execute(Collection $products): array
    {
        $allResults = [];
        
        foreach ($products as $product) {
            $productResults = $this->pushProductToShopify->execute($product);
            
            $allResults[$product->id] = [
                'product_name' => $product->name,
                'color_groups' => count($productResults),
                'total_variants' => $product->variants->count(),
                'results' => $productResults,
                'summary' => $this->buildResultSummary($productResults)
            ];
        }

        return $allResults;
    }

    /**
     * Build a summary of results for a single product
     */
    private function buildResultSummary(array $results): array
    {
        $successful = collect($results)->where('success', true)->count();
        $failed = collect($results)->where('success', false)->count();
        $totalShopifyProducts = count($results);
        
        return [
            'total_shopify_products_created' => $totalShopifyProducts,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $totalShopifyProducts > 0 ? round(($successful / $totalShopifyProducts) * 100, 1) : 0
        ];
    }
}