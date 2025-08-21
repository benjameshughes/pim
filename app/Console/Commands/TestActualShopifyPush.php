<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Illuminate\Console\Command;

class TestActualShopifyPush extends Command
{
    protected $signature = 'test:actual-shopify-push {product_id?}';

    protected $description = 'Test actual Shopify push with detailed error reporting';

    public function handle()
    {
        $productId = $this->argument('product_id');

        if ($productId) {
            $product = Product::with('variants')->find($productId);
        } else {
            $product = Product::with('variants')->where('status', 'active')->first();
        }

        if (! $product) {
            $this->error('No product found');

            return 1;
        }

        $this->info("🚀 Testing actual push for: {$product->name} (ID: {$product->id})");

        // Test both push methods
        $this->testPushMethod($product, 'colors');
        $this->testPushMethod($product, 'regular');

        return 0;
    }

    private function testPushMethod($product, $method)
    {
        $this->info("\n📤 Testing {$method} push method...");

        try {
            if ($method === 'colors') {
                // For colors method, pass the model directly
                $result = Sync::shopify()->pushWithColors([$product]);
            } else {
                // For regular method, convert to array and add title if missing
                $productArray = $product->toArray();
                if (! isset($productArray['title'])) {
                    $productArray['title'] = $productArray['name'] ?? 'Untitled Product';
                }
                $result = Sync::shopify()->push([$productArray]);
            }

            $this->line('📝 Data format sent:');
            if ($method === 'colors') {
                $this->line('   - Sent Product model directly');
                $this->line('   - Product name: '.$product->name);
                $this->line('   - Variant count: '.$product->variants->count());
            } else {
                $this->line('   - Sent array with title: '.($productArray['title'] ?? 'none'));
                $this->line('   - Array keys: '.implode(', ', array_keys($productArray)));
            }

            $this->line('✅ Push completed without exceptions');

            // Analyze results
            if (is_array($result)) {
                $this->line('📊 Results analysis:');
                $this->line('   - Total results: '.count($result));

                foreach ($result as $i => $item) {
                    $this->line("   - Result {$i}:");
                    if (is_array($item)) {
                        foreach ($item as $key => $value) {
                            if (is_scalar($value)) {
                                $this->line("     - {$key}: {$value}");
                            } elseif (is_array($value) && count($value) < 5) {
                                $this->line("     - {$key}: ".json_encode($value));
                            } else {
                                $this->line("     - {$key}: ".gettype($value));
                            }
                        }
                    } else {
                        $this->line('     - '.json_encode($item));
                    }
                }
            } else {
                $this->line('📊 Result type: '.gettype($result));
                $this->line('📊 Result: '.json_encode($result));
            }

        } catch (\Exception $e) {
            $this->error("❌ Push failed with {$method} method:");
            $this->line('   Error: '.$e->getMessage());
            $this->line('   File: '.$e->getFile().':'.$e->getLine());
            $this->line('   Class: '.get_class($e));

            // Show more detailed error if available
            if (method_exists($e, 'getResponse')) {
                $this->line('   Response: '.$e->getResponse());
            }

            if ($e->getPrevious()) {
                $this->line('   Previous: '.$e->getPrevious()->getMessage());
            }
        }
    }
}
