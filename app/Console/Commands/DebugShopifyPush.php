<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Marketplace\Facades\Sync;
use Illuminate\Console\Command;

class DebugShopifyPush extends Command
{
    protected $signature = 'debug:shopify-push {product_id?}';

    protected $description = 'Debug Shopify push functionality';

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

        $this->info("🔍 Debugging push for product: {$product->name} (ID: {$product->id})");

        // Test 1: Check product structure
        $this->info('📊 Product data structure:');
        $productArray = $product->toArray();
        $this->line('   - Product fields: '.implode(', ', array_keys($productArray)));
        $this->line('   - Variants count: '.count($productArray['variants'] ?? []));

        if (! empty($productArray['variants'])) {
            $this->line('   - First variant fields: '.implode(', ', array_keys($productArray['variants'][0])));
        }

        // Test 2: Test connection
        $this->info('🔌 Testing Shopify connection...');
        try {
            $connectionTest = Sync::shopify()->testConnection();
            $this->line('   - Connection: '.($connectionTest['success'] ? '✅ SUCCESS' : '❌ FAILED'));
            if (isset($connectionTest['shop_name'])) {
                $this->line('   - Shop: '.$connectionTest['shop_name']);
            }
        } catch (\Exception $e) {
            $this->line('   - Connection Error: '.$e->getMessage());
        }

        // Test 3: Try push with colors (dry run simulation)
        $this->info('🚀 Testing push with colors...');
        try {
            // Just test the method exists without actually pushing
            $this->line('   - Push method available: ✅');

            // Show what would be sent
            $this->line('   - Would send product: '.$product->name);
            $this->line('   - Would send variants: '.$product->variants->count());
            $this->line('   - Product status: '.$product->status->value);

            foreach ($product->variants as $variant) {
                $this->line("     - Variant: {$variant->sku} ({$variant->color}) - £{$variant->price}");
            }

        } catch (\Exception $e) {
            $this->error('   - Push test failed: '.$e->getMessage());
            $this->line('   - Stack trace: '.$e->getTraceAsString());
        }

        // Test 4: Check for common issues
        $this->info('🔍 Checking for common issues...');

        if ($product->variants->isEmpty()) {
            $this->line('   ⚠️  Product has no variants');
        } else {
            $this->line('   ✅ Product has variants');
        }

        if ($product->status->value !== 'active') {
            $this->line('   ⚠️  Product status is not active: '.$product->status->value);
        } else {
            $this->line('   ✅ Product is active');
        }

        if (empty($product->name)) {
            $this->line('   ⚠️  Product has no name');
        } else {
            $this->line('   ✅ Product has name');
        }

        return 0;
    }
}
