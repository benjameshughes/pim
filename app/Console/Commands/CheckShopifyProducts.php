<?php

namespace App\Console\Commands;

use App\Services\Marketplace\ShopifyAPI;
use Illuminate\Console\Command;

class CheckShopifyProducts extends Command
{
    protected $signature = 'shopify:check-products';

    protected $description = 'Check what products exist on Shopify';

    public function handle()
    {
        $this->info('🔍 Checking products on Shopify...');

        try {
            $shopifyAPI = app(ShopifyAPI::class);
            $products = $shopifyAPI->pull(['limit' => 250]);

            $this->info("Found {$products->count()} products on Shopify:");

            foreach ($products as $product) {
                $this->info("- {$product['title']} (ID: {$product['id']})");
            }

            // Look specifically for "Straight Edge" products
            $straightEdgeProducts = $products->filter(function ($product) {
                return str_contains($product['title'], 'Straight Edge');
            });

            if ($straightEdgeProducts->count() > 0) {
                $this->info("\n🎯 Found Straight Edge products:");
                foreach ($straightEdgeProducts as $product) {
                    $this->info("- {$product['title']} (ID: {$product['id']})");
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
