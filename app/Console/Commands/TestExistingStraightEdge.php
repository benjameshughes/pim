<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplace\ShopifyAPI;
use Illuminate\Console\Command;

class TestExistingStraightEdge extends Command
{
    protected $signature = 'shopify:test-existing-straight-edge';

    protected $description = 'Test update functionality on known existing Straight Edge products';

    public function handle()
    {
        $this->info('🧪 Testing Shopify update for existing Straight Edge Roller Blind products');

        // Create a mock product that matches the structure we know exists
        $this->info('🔨 Creating mock product to test update logic...');

        try {
            $shopifyAPI = app(ShopifyAPI::class);

            // Test the findProductByTitle method directly with known existing products
            $knownProducts = [
                'Straight Edge Roller Blind - Aubergine',
                'Straight Edge Roller Blind - Navy',
                'Straight Edge Roller Blind - Teal',
            ];

            $this->info('🔍 Testing findProductByTitle with known products...');

            foreach ($knownProducts as $title) {
                // Use reflection to access private method
                $reflection = new \ReflectionClass($shopifyAPI);
                $method = $reflection->getMethod('findProductByTitle');
                $method->setAccessible(true);

                $existing = $method->invoke($shopifyAPI, $title);

                if ($existing) {
                    $this->info("✅ Found: $title (ID: {$existing['id']})");

                    // Show variant structure
                    if (isset($existing['variants']) && count($existing['variants']) > 0) {
                        $this->info('   Variants: '.count($existing['variants']));
                        $firstVariant = $existing['variants'][0];
                        $this->info('   Sample variant: '.($firstVariant['title'] ?? 'N/A'));
                        $this->info('   Options: option1='.($firstVariant['option1'] ?? 'N/A').
                                   ', option2='.($firstVariant['option2'] ?? 'N/A').
                                   ', option3='.($firstVariant['option3'] ?? 'N/A'));
                    }
                } else {
                    $this->error("❌ Not found: $title");
                }
            }

            // Test the Shopify API search directly
            $this->info("\n🔍 Testing direct Shopify product search...");
            try {
                $searchResults = $shopifyAPI->pull(['title' => 'Straight Edge Roller Blind - Aubergine', 'limit' => 5]);
                $this->info('Direct search results: '.$searchResults->count());

                foreach ($searchResults as $product) {
                    $this->info("- Found: {$product['title']} (ID: {$product['id']})");
                }

            } catch (\Exception $e) {
                $this->error('Search error: '.$e->getMessage());
            }

            // Test with broader search
            $this->info("\n🔍 Testing broader search...");
            try {
                $broadSearch = $shopifyAPI->pull(['limit' => 50]);
                $straightEdge = $broadSearch->filter(function ($p) {
                    return str_contains($p['title'], 'Straight Edge Roller Blind - Aubergine');
                });

                $this->info('Broad search found '.$straightEdge->count().' matching products:');
                foreach ($straightEdge as $product) {
                    $this->info("- {$product['title']} (ID: {$product['id']})");
                }

            } catch (\Exception $e) {
                $this->error('Broad search error: '.$e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('❌ Test failed: '.$e->getMessage());

            return 1;
        }

        $this->info("\n🎉 Test completed!");

        return 0;
    }
}
