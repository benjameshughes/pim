<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplace\ShopifyAPI;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestShopifyUpdateExisting extends Command
{
    protected $signature = 'shopify:test-update-existing {--id=256 : Product ID to test} {--sku= : Product SKU to test}';

    protected $description = 'Test updating existing color-split products on Shopify';

    public function handle()
    {
        $productId = $this->option('id');
        $sku = $this->option('sku');

        $this->info('🧪 Testing Shopify update functionality for existing products');

        // Find the product by ID or SKU
        if ($sku) {
            $product = Product::where('sku', $sku)->with('variants.barcode')->first();
            $this->info("Searching by SKU: $sku");
        } else {
            $product = Product::where('id', $productId)->with('variants.barcode')->first();
            $this->info("Searching by ID: $productId");
        }

        if (! $product) {
            $this->error('❌ Product not found');

            return 1;
        }

        $this->info("📦 Found product: {$product->name}");
        $this->info("🔢 Variants: {$product->variants->count()}");

        // Get unique colors
        $colors = $product->variants->pluck('color')->filter()->unique()->sort();
        $this->info('🎨 Colors: '.$colors->implode(', '));

        // Test the update functionality by pushing again
        $this->info("\n🚀 Testing color-splitting push (should update existing products)...");

        try {
            $shopifyAPI = app(ShopifyAPI::class);
            $results = $shopifyAPI->pushWithColors([$product]);

            $this->info("\n📊 RESULTS:");
            $this->info('Total operations: '.count($results));

            $successful = 0;
            $failed = 0;
            $created = 0;
            $updated = 0;
            $errors = [];

            foreach ($results as $index => $result) {
                $this->info("\nResult $index:");

                if ($result['success']) {
                    $successful++;
                    $action = $result['action'] ?? 'unknown';
                    if ($action === 'created') {
                        $created++;
                    }
                    if ($action === 'updated') {
                        $updated++;
                    }

                    $this->info('  ✅ SUCCESS');
                    $this->info('  - shopify_id: '.($result['shopify_id'] ?? 'N/A'));
                    $this->info('  - title: '.($result['title'] ?? 'N/A'));
                    $this->info('  - color: '.($result['color'] ?? 'N/A'));
                    $this->info('  - variant_count: '.($result['variant_count'] ?? 'N/A'));
                    $this->info('  - action: '.$action);
                } else {
                    $failed++;
                    $this->error('  ❌ FAILED');
                    $this->error('  - title: '.($result['title'] ?? 'N/A'));
                    $this->error('  - color: '.($result['color'] ?? 'N/A'));
                    $this->error('  - error: '.($result['error'] ?? 'Unknown error'));
                    $errors[] = $result['error'] ?? 'Unknown error';
                }
            }

            $this->info("\n🎯 SUMMARY:");
            $this->info("✅ Successful: $successful");
            $this->info("❌ Failed: $failed");
            $this->info("🆕 Created: $created");
            $this->info("🔄 Updated: $updated");

            if (! empty($errors)) {
                $this->error("\n⚠️ ERRORS:");
                foreach (array_unique($errors) as $error) {
                    $this->error("- $error");
                }
            }

            // Test findProductByTitle functionality
            $this->info("\n🔍 Testing findProductByTitle functionality...");

            // Test with a few known product titles
            $testTitles = [
                'Straight Edge Roller Blind - Black',
                'Straight Edge Roller Blind - White',
                'Straight Edge Roller Blind - Blue',
            ];

            foreach ($testTitles as $title) {
                try {
                    $reflection = new \ReflectionClass($shopifyAPI);
                    $method = $reflection->getMethod('findProductByTitle');
                    $method->setAccessible(true);
                    $existing = $method->invoke($shopifyAPI, $title);

                    if ($existing) {
                        $this->info("✅ Found existing product: $title (ID: {$existing['id']})");
                    } else {
                        $this->warn("⚠️ No existing product found: $title");
                    }
                } catch (\Exception $e) {
                    $this->error("❌ Error searching for '$title': ".$e->getMessage());
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Push failed: '.$e->getMessage());
            Log::error('Shopify push test failed', [
                'sku' => $sku,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        $this->info("\n🎉 Test completed!");

        return 0;
    }
}
