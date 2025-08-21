<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Shopify\ShopifyColorSeparationService;
use App\Services\Shopify\ShopifyProductCreationService;
use App\Services\Shopify\ShopifyTaxonomyService;
use Illuminate\Console\Command;

/**
 * 🧪 TEST SHOPIFY COLOR SYSTEM COMMAND
 *
 * Tests the complete color-based Shopify integration system
 */
class TestShopifyColorSystem extends Command
{
    protected $signature = 'shopify:test-colors {product_id? : Test specific product ID}';

    protected $description = 'Test the complete Shopify color-based product system';

    public function handle(): int
    {
        $this->info('🎨 SHOPIFY COLOR SYSTEM TEST');
        $this->newLine();

        // Test 1: Taxonomy Service
        $this->info('1️⃣ Testing Shopify Taxonomy Service...');
        if (! $this->testTaxonomyService()) {
            return 1;
        }
        $this->info('✅ Taxonomy service working!');
        $this->newLine();

        // Test 2: Color Separation Service
        $this->info('2️⃣ Testing Color Separation Service...');
        $productId = $this->argument('product_id');
        $product = $productId ? Product::find($productId) : Product::whereHas('variants')->first();

        if (! $product) {
            $this->error('❌ No product found with variants!');

            return 1;
        }

        if (! $this->testColorSeparationService($product)) {
            return 1;
        }
        $this->info('✅ Color separation working!');
        $this->newLine();

        // Test 3: Product Creation Service (Preview Only)
        $this->info('3️⃣ Testing Product Creation Preview...');
        if (! $this->testProductCreationPreview($product)) {
            return 1;
        }
        $this->info('✅ Product creation preview working!');
        $this->newLine();

        $this->info('🎉 ALL TESTS PASSED! Color system is ready for use.');

        return 0;
    }

    /**
     * 🏷️ Test taxonomy service
     */
    private function testTaxonomyService(): bool
    {
        try {
            $taxonomyService = app(ShopifyTaxonomyService::class);

            // Test stats
            $stats = $taxonomyService->getTaxonomyStats();
            $this->line("   Categories: {$stats['categories_count']}");
            $this->line("   Attributes: {$stats['attributes_count']}");
            $this->line("   Cache Status: {$stats['cache_status']}");

            // Test search
            $blindCategories = $taxonomyService->searchCategories('blind');
            $this->line("   Blind categories found: {$blindCategories->count()}");

            // Test window treatment categories
            $windowCategories = $taxonomyService->getWindowTreatmentCategories();
            $this->line("   Window treatment categories: {$windowCategories->count()}");

            return true;
        } catch (\Exception $e) {
            $this->error("   Taxonomy Error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * 🎨 Test color separation service
     */
    private function testColorSeparationService(Product $product): bool
    {
        try {
            $separationService = app(ShopifyColorSeparationService::class);

            $this->line("   Testing Product: {$product->name} (ID: {$product->id})");

            // Test color separation
            $colorProducts = $separationService->separateByColors($product);
            $this->line("   Shopify products to create: {$colorProducts->count()}");

            foreach ($colorProducts as $colorProduct) {
                $this->line("   🎨 {$colorProduct['color']}:");
                $this->line("      Title: {$colorProduct['title']}");
                $this->line("      Handle: {$colorProduct['handle']}");
                $this->line('      Variants: '.count($colorProduct['variants']));
                $this->line("      Price Range: {$colorProduct['stats']['price_range']['formatted']}");
                $this->line("      SEO Title: {$colorProduct['seo_title']}");
                $this->line('      Tags: '.implode(', ', array_slice($colorProduct['tags'], 0, 3)).'...');
                $this->newLine();
            }

            // Test preview
            $preview = $separationService->previewSeparation($product);
            $this->line('   📊 Summary:');
            $this->line('      Colors: '.implode(', ', $preview['summary']['colors']));
            $this->line("      Total Shopify Products: {$preview['summary']['total_shopify_products']}");
            $this->line("      Total Variants: {$preview['summary']['total_variants']}");

            return true;
        } catch (\Exception $e) {
            $this->error("   Color Separation Error: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * 🚀 Test product creation preview
     */
    private function testProductCreationPreview(Product $product): bool
    {
        try {
            $creationService = app(ShopifyProductCreationService::class);

            $preview = $creationService->getDetailedPreview($product);

            $this->line("   PIM Parent: {$preview['pim_parent']['name']} ({$preview['pim_parent']['parent_sku']})");
            $this->line("   Shopify Products to Create: {$preview['summary']['shopify_products_to_create']}");
            $this->line("   Total Variants: {$preview['summary']['total_variants']}");
            $this->line("   Overall Price Range: {$preview['summary']['price_range_overall']}");
            $this->line('   Colors: '.implode(', ', $preview['summary']['colors']));

            // Show preview details
            foreach ($preview['shopify_products_to_create'] as $shopifyProduct) {
                $this->line("   🎨 {$shopifyProduct['color']}:");
                $this->line("      Title: {$shopifyProduct['title']}");
                $this->line("      Handle: {$shopifyProduct['handle']}");
                $this->line("      SEO Title: {$shopifyProduct['seo_title']}");

                $category = $shopifyProduct['suggested_category'];
                if ($category) {
                    $this->line("      Category: {$category['path']} (Score: {$category['relevance_score']})");
                } else {
                    $this->line('      Category: No suggestion found');
                }

                $this->line("      Variants: {$shopifyProduct['variants_count']} ({$shopifyProduct['price_range']})");
                $this->line('      Tags: '.implode(', ', array_slice($shopifyProduct['tags'], 0, 4)).'...');

                // Show sample variants
                $this->line('      Sample Variants:');
                foreach ($shopifyProduct['variants_preview'] as $variant) {
                    $this->line("        - {$variant['title']}: {$variant['price']} ({$variant['inventory']} in stock)");
                }
                $this->newLine();
            }

            return true;
        } catch (\Exception $e) {
            $this->error("   Product Creation Error: {$e->getMessage()}");

            return false;
        }
    }
}
