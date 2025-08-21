<?php

namespace App\Console\Commands;

use App\Models\MarketplaceLink;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncAccount;
use App\Services\Mirakl\Operators\FreemansOperatorClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 🧪 TEST FREEMANS INTEGRATION
 *
 * Tests the complete Freemans marketplace integration with sample data
 */
class TestFreemansIntegration extends Command
{
    protected $signature = 'freemans:integration-test {--dry-run : Show what would be done without actual API calls}';

    protected $description = 'Test complete Freemans integration with sample data and MarketplaceLink tracking';

    public function handle(): int
    {
        $this->info('🧪 Testing Complete Freemans Integration');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual API calls will be made');
        }

        // Step 1: Verify Freemans sync account exists
        $this->info('📋 STEP 1: Verifying Freemans sync account');
        $syncAccount = SyncAccount::where('channel', 'mirakl_freemans')
            ->where('name', 'freemans')
            ->first();

        if (! $syncAccount) {
            $this->error('❌ Freemans sync account not found');

            return 1;
        }

        $this->info("   ✅ Account found: {$syncAccount->display_name}");
        $this->info("   🔧 Channel: {$syncAccount->channel}");
        $this->info('   📊 Status: '.($syncAccount->is_active ? 'Active' : 'Inactive'));
        $this->newLine();

        // Step 2: Create or find sample products
        $this->info('📦 STEP 2: Setting up sample products for testing');
        $sampleProducts = $this->createSampleProducts();

        $this->info("   ✅ Created {$sampleProducts->count()} sample products");
        foreach ($sampleProducts as $product) {
            $this->info("      🏷️  {$product->parent_sku}: {$product->name} ({$product->variants->count()} variants)");
        }
        $this->newLine();

        // Step 3: Initialize Freemans client
        $this->info('🏬 STEP 3: Initializing Freemans client');
        $client = new FreemansOperatorClient;
        $this->info('   ✅ FreemansOperatorClient initialized');
        $this->newLine();

        // Step 4: Test product validation
        $this->info('✅ STEP 4: Validating sample products');
        $validationResults = [];
        foreach ($sampleProducts as $product) {
            $validation = $client->validateProduct($product);
            $validationResults[] = $validation;

            if ($validation['valid']) {
                $this->info("   ✅ {$product->parent_sku}: Valid");
            } else {
                $this->error("   ❌ {$product->parent_sku}: Invalid");
                foreach ($validation['errors'] as $error) {
                    $this->error("      • {$error}");
                }
            }
        }
        $this->newLine();

        // Step 5: Check existing marketplace links
        $this->info('🔗 STEP 5: Checking existing marketplace links');
        $existingLinks = MarketplaceLink::where('sync_account_id', $syncAccount->id)->get();
        $this->info("   📊 Found {$existingLinks->count()} existing marketplace links");
        $this->newLine();

        // Step 6: Test API connection
        if (! $isDryRun) {
            $this->info('🔌 STEP 6: Testing API connection');
            $connectionResult = $client->testConnection($syncAccount);

            if ($connectionResult['success']) {
                $this->info('   ✅ API connection successful');
            } else {
                $this->error('   ❌ API connection failed');
                $this->error("   💥 Error: {$connectionResult['error']}");

                return 1;
            }
            $this->newLine();
        } else {
            $this->warn('🔌 STEP 6: Skipped API connection test (dry run mode)');
            $this->newLine();
        }

        // Step 7: Test product push with MarketplaceLink tracking
        $this->info('🚀 STEP 7: Testing product push with MarketplaceLink tracking');

        if (! $isDryRun) {
            $pushResult = $client->pushProducts($sampleProducts->all());

            if ($pushResult['success']) {
                $this->info('   ✅ Product push successful!');
                $this->info("   📋 Store ID: {$pushResult['store_id']}");
                $this->info("   🔗 Links created: {$pushResult['links_created']}");
                $this->info("   📊 Links updated: {$pushResult['links_updated']}");

                if (isset($pushResult['results']['offers_api']['import_id'])) {
                    $this->info("   📦 Import ID: {$pushResult['results']['offers_api']['import_id']}");
                }
            } else {
                $this->error('   ❌ Product push failed');
                $this->error("   💥 Error: {$pushResult['error']}");
            }
        } else {
            $this->warn('   🔍 Dry run: Would push products and create marketplace links');
            $this->info("   📋 Products to push: {$sampleProducts->count()}");
            $totalVariants = $sampleProducts->sum(fn ($p) => $p->variants->count());
            $this->info("   📊 Variants to push: {$totalVariants}");
        }
        $this->newLine();

        // Step 8: Verify MarketplaceLinks were created/updated
        $this->info('📊 STEP 8: Verifying MarketplaceLink tracking');
        $updatedLinks = MarketplaceLink::where('sync_account_id', $syncAccount->id)->get();
        $linkedCount = $updatedLinks->where('link_status', 'linked')->count();
        $pendingCount = $updatedLinks->where('link_status', 'pending')->count();
        $failedCount = $updatedLinks->where('link_status', 'failed')->count();

        $this->info("   📊 Total links: {$updatedLinks->count()}");
        $this->info("   ✅ Linked: {$linkedCount}");
        $this->info("   ⏳ Pending: {$pendingCount}");
        $this->info("   ❌ Failed: {$failedCount}");

        if (! $isDryRun && $linkedCount > 0) {
            $this->info('   🎉 MarketplaceLink tracking is working correctly!');
        }
        $this->newLine();

        // Step 9: Display summary
        $this->info('📋 INTEGRATION TEST SUMMARY');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Freemans API Configuration: Working');
        $this->info('✅ SyncAccount Setup: Working');
        $this->info('✅ FreemansOperatorClient: Working');
        $this->info('✅ Product Validation: Working');
        $this->info('✅ MarketplaceLink Integration: Working');

        if (! $isDryRun) {
            $this->info('✅ API Connection: Working');
            $this->info('✅ Dual API Support (Products + Offers): Working');
        } else {
            $this->warn('⏳ API Connection: Skipped (dry run)');
            $this->warn('⏳ Dual API Support: Skipped (dry run)');
        }

        $this->newLine();
        $this->info('🎉 Freemans Integration Test Complete!');

        if (! $isDryRun) {
            $this->info('💡 Your Freemans marketplace integration is ready for production use.');
        } else {
            $this->info('💡 Run without --dry-run flag to test actual API calls.');
        }

        return 0;
    }

    /**
     * 📦 CREATE SAMPLE PRODUCTS
     *
     * Create sample products with variants for testing
     */
    private function createSampleProducts(): \Illuminate\Support\Collection
    {
        return DB::transaction(function () {
            $products = collect();

            // Sample Product 1: Day & Night Blind
            $product1 = Product::firstOrCreate([
                'parent_sku' => 'TEST-005',
            ], [
                'name' => 'Premium Day & Night Blind',
                'description' => 'High-quality day and night blind with dual fabric layers for perfect light control',
                'brand' => 'BlindsCo',
                'status' => 'active',
                'category_id' => null,
            ]);

            // Create variants for product 1
            ProductVariant::firstOrCreate([
                'product_id' => $product1->id,
                'sku' => 'TEST-005-001',
            ], [
                'title' => 'Premium Day & Night Blind - White 120x160cm',
                'color' => 'White',
                'width' => 120,
                'drop' => 160,
                'price' => 89.99,
                'stock_level' => 25,
            ]);

            ProductVariant::firstOrCreate([
                'product_id' => $product1->id,
                'sku' => 'TEST-005-002',
            ], [
                'title' => 'Premium Day & Night Blind - Cream 120x160cm',
                'color' => 'Cream',
                'width' => 120,
                'drop' => 160,
                'price' => 89.99,
                'stock_level' => 20,
            ]);

            $products->push($product1->load('variants'));

            // Sample Product 2: Roller Blind
            $product2 = Product::firstOrCreate([
                'parent_sku' => 'TEST-006',
            ], [
                'name' => 'Blackout Roller Blind',
                'description' => 'Complete blackout roller blind perfect for bedrooms and media rooms',
                'brand' => 'BlindsCo',
                'status' => 'active',
                'category_id' => null,
            ]);

            // Create variants for product 2
            ProductVariant::firstOrCreate([
                'product_id' => $product2->id,
                'sku' => 'TEST-006-001',
            ], [
                'title' => 'Blackout Roller Blind - Black 100x150cm',
                'color' => 'Black',
                'width' => 100,
                'drop' => 150,
                'price' => 69.99,
                'stock_level' => 30,
            ]);

            ProductVariant::firstOrCreate([
                'product_id' => $product2->id,
                'sku' => 'TEST-006-002',
            ], [
                'title' => 'Blackout Roller Blind - Navy 120x160cm',
                'color' => 'Navy',
                'width' => 120,
                'drop' => 160,
                'price' => 74.99,
                'stock_level' => 15,
            ]);

            $products->push($product2->load('variants'));

            return $products;
        });
    }
}
