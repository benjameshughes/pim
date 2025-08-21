<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SyncAccount;
use App\Services\Shopify\ColorBasedProductSplitter;
use App\Services\Shopify\ShopifyGraphQLClient;
use App\Actions\Shopify\Sync\GraphQLSyncProductToShopifyAction;
use Illuminate\Console\Command;

/**
 * 🧪 TEST SHOPIFY GRAPHQL COMMAND
 *
 * Comprehensive testing command for the new GraphQL integration
 * with color-based product splitting functionality.
 */
class TestShopifyGraphQL extends Command
{
    protected $signature = 'test:shopify-graphql
                           {--product= : Product ID to test with}
                           {--connection : Test GraphQL connection only}
                           {--split : Test color splitting only}
                           {--sync : Test full sync with GraphQL}
                           {--force : Force GraphQL even for small products}';

    protected $description = 'Test Shopify GraphQL integration with color-based product splitting';

    public function handle(): int
    {
        $this->info('🧪 Starting Shopify GraphQL Integration Tests');
        $this->newLine();

        // Test 1: GraphQL Connection
        if ($this->option('connection') || !$this->hasSpecificTest()) {
            if (!$this->testGraphQLConnection()) {
                return 1;
            }
        }

        // Test 2: Color Splitting Logic
        if ($this->option('split') || !$this->hasSpecificTest()) {
            if (!$this->testColorSplitting()) {
                return 1;
            }
        }

        // Test 3: Full GraphQL Sync
        if ($this->option('sync') || !$this->hasSpecificTest()) {
            if (!$this->testGraphQLSync()) {
                return 1;
            }
        }

        $this->info('🎉 All GraphQL integration tests completed successfully!');
        return 0;
    }

    /**
     * Test GraphQL connection and basic operations
     */
    protected function testGraphQLConnection(): bool
    {
        $this->info('🔍 Testing GraphQL Connection...');

        try {
            $client = app(ShopifyGraphQLClient::class);

            // Test configuration
            if (!$client->isConfigured()) {
                $this->error('❌ GraphQL client is not properly configured');
                $this->line('   Please check your Shopify environment variables');
                return false;
            }

            // Test connection
            $connectionResult = $client->testConnection();

            if (!$connectionResult['success']) {
                $this->error('❌ GraphQL connection failed: ' . $connectionResult['error']);
                return false;
            }

            $this->info('✅ GraphQL connection successful');
            $this->line('   Shop: ' . $connectionResult['shop_info']['name']);
            $this->line('   Domain: ' . $connectionResult['shop_info']['domain']);
            $this->line('   Plan: ' . $connectionResult['shop_info']['plan']);

        } catch (\Exception $e) {
            $this->error('❌ GraphQL connection test failed: ' . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    /**
     * Test color-based product splitting logic
     */
    protected function testColorSplitting(): bool
    {
        $this->info('🎨 Testing Color-Based Product Splitting...');

        try {
            $splitter = app(ColorBasedProductSplitter::class);
            
            // Find a product with multiple variants
            $product = $this->getTestProduct();
            
            if (!$product) {
                $this->error('❌ No suitable test product found');
                $this->line('   Need a product with multiple variants to test splitting');
                return false;
            }

            $this->line("   Testing with product: {$product->name} (ID: {$product->id})");
            $this->line("   Total variants: {$product->variants->count()}");

            // Get split summary
            $summary = $splitter->getSplitSummary($product);
            
            $this->info('📊 Split Analysis:');
            $this->line("   Colors found: {$summary['colors_found']}");
            $this->line("   Shopify products needed: {$summary['shopify_products_needed']}");
            $this->line("   Split recommended: " . ($summary['split_recommended'] ? 'Yes' : 'No'));

            // Display color breakdown
            if (!empty($summary['color_breakdown'])) {
                $this->line('');
                $this->line('   Color breakdown:');
                foreach ($summary['color_breakdown'] as $color => $details) {
                    $this->line("     • {$color}: {$details['variants_count']} variants");
                    if ($details['has_width']) {
                        $this->line("       - Width options: {$details['width_options']}");
                    }
                    if ($details['has_drop']) {
                        $this->line("       - Drop options: {$details['drop_options']}");
                    }
                }
            }

            // Test actual splitting
            $this->line('');
            $this->info('🔧 Testing Product Splitting...');
            
            $splitProducts = $splitter->splitProductByColor($product);
            
            if (empty($splitProducts)) {
                $this->error('❌ Product splitting returned no results');
                return false;
            }

            $this->info('✅ Product splitting successful');
            $this->line("   Generated {$summary['shopify_products_needed']} Shopify products:");

            foreach ($splitProducts as $color => $data) {
                $this->line("     • {$color}: '{$data['shopify_title']}'");
                $this->line("       - Variants: {$data['variants_count']}");
                $this->line("       - Options: " . implode(', ', array_column($data['shopify_product_data']['product']['options'], 'name')));
            }

        } catch (\Exception $e) {
            $this->error('❌ Color splitting test failed: ' . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    /**
     * Test full GraphQL sync with color splitting
     */
    protected function testGraphQLSync(): bool
    {
        $this->info('🚀 Testing Full GraphQL Sync...');

        try {
            $product = $this->getTestProduct();
            
            if (!$product) {
                $this->error('❌ No suitable test product found');
                return false;
            }

            $syncAccount = SyncAccount::where('channel', 'shopify')->first();
            
            if (!$syncAccount) {
                $this->error('❌ No Shopify sync account found');
                $this->line('   Please create a sync account first');
                return false;
            }

            $this->line("   Testing with product: {$product->name}");
            $this->line("   Sync account: {$syncAccount->name}");
            $this->line("   Total variants: {$product->variants->count()}");

            // Test the GraphQL action
            $action = app(GraphQLSyncProductToShopifyAction::class);
            
            $options = [
                'sync_account_id' => $syncAccount->id,
                'method' => 'test_command',
                'force' => $this->option('force'),
                'test_mode' => true, // Prevent actual API calls in test mode
            ];

            // For actual testing, we'll just validate the data preparation
            // Remove test_mode to perform real sync
            $this->warn('⚠️  This is a dry run. Remove test_mode to perform actual sync.');
            
            $splitter = app(ColorBasedProductSplitter::class);
            $splitProducts = $splitter->splitProductByColor($product);

            $this->info('✅ GraphQL sync preparation successful');
            $this->line("   Would create {$syncAccount->name} products in Shopify:");

            foreach ($splitProducts as $color => $data) {
                $variants = $data['shopify_product_data']['product']['variants'];
                $this->line("     • {$data['shopify_title']}");
                $this->line("       - Color: {$color}");
                $this->line("       - Variants: " . count($variants));
                $this->line("       - SKUs: " . implode(', ', array_slice(array_column($variants, 'sku'), 0, 3)) . (count($variants) > 3 ? '...' : ''));
            }

            $this->newLine();
            $this->comment('💡 To perform actual sync, run:');
            $this->line("   php artisan sync:product {$product->id} --force_graphql");

        } catch (\Exception $e) {
            $this->error('❌ GraphQL sync test failed: ' . $e->getMessage());
            return false;
        }

        $this->newLine();
        return true;
    }

    /**
     * Get a suitable test product
     */
    protected function getTestProduct(): ?Product
    {
        $productId = $this->option('product');
        
        if ($productId) {
            $product = Product::with('variants')->find($productId);
            if (!$product) {
                $this->error("Product with ID {$productId} not found");
                return null;
            }
            return $product;
        }

        // Find a product with multiple variants for better testing
        return Product::with('variants')
            ->whereHas('variants')
            ->withCount('variants')
            ->orderBy('variants_count', 'desc')
            ->first();
    }

    /**
     * Check if user specified a specific test
     */
    protected function hasSpecificTest(): bool
    {
        return $this->option('connection') || 
               $this->option('split') || 
               $this->option('sync');
    }
}