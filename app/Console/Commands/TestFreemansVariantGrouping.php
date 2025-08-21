<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Mirakl\UniversalMiraklCsvGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 🧪 TEST FREEMANS VARIANT GROUPING
 *
 * Test the corrected field mapping for Freemans variant grouping
 */
class TestFreemansVariantGrouping extends Command
{
    protected $signature = 'test:freemans-grouping';

    protected $description = 'Test the corrected field mapping for Freemans variant grouping';

    public function handle(): int
    {
        $this->info('🧪 Testing Freemans Variant Grouping with Corrected Field Mapping');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Create sample products with variants for testing
        $products = $this->createSampleProductsForGroupingTest();

        $this->info("✅ Created {$products->count()} sample products for testing");
        $this->newLine();

        // Generate CSV using the universal generator
        $generator = UniversalMiraklCsvGenerator::for('freemans');
        $result = $generator->generateProductsCsv($products, 'H02');

        $this->info('📄 CSV Generation Results:');
        $this->info("   📁 File: {$result['filename']}");
        $this->info("   📊 Products: {$result['products_count']}");
        $this->info("   🎯 Variants: {$result['variants_count']}");
        $this->info("   📋 Required fields: {$result['required_fields']}");
        $this->info("   📝 Optional fields: {$result['optional_fields']}");
        $this->newLine();

        // Read and analyze the generated CSV
        $this->analyzeCsvContent($result['absolute_path']);

        return 0;
    }

    /**
     * Create sample products with variants for testing variant grouping
     */
    private function createSampleProductsForGroupingTest(): Collection
    {
        return DB::transaction(function () {
            $products = collect();

            // Sample Product 1: Day & Night Blind with multiple color variants
            $product1 = Product::firstOrCreate([
                'parent_sku' => 'DAYNIGHT-001',
            ], [
                'name' => 'Premium Day & Night Blind',
                'description' => 'High-quality day and night blind with dual fabric layers',
                'brand' => 'BlindsCo',
                'status' => 'active',
            ]);

            // Create multiple variants for the same product (should be grouped)
            ProductVariant::firstOrCreate([
                'product_id' => $product1->id,
                'sku' => 'DAYNIGHT-001-WHITE',
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
                'sku' => 'DAYNIGHT-001-CREAM',
            ], [
                'title' => 'Premium Day & Night Blind - Cream 120x160cm',
                'color' => 'Cream',
                'width' => 120,
                'drop' => 160,
                'price' => 89.99,
                'stock_level' => 20,
            ]);

            ProductVariant::firstOrCreate([
                'product_id' => $product1->id,
                'sku' => 'DAYNIGHT-001-GREY',
            ], [
                'title' => 'Premium Day & Night Blind - Grey 120x160cm',
                'color' => 'Grey',
                'width' => 120,
                'drop' => 160,
                'price' => 89.99,
                'stock_level' => 18,
            ]);

            $products->push($product1->load('variants'));

            // Sample Product 2: Roller Blind with different sizes and colors
            $product2 = Product::firstOrCreate([
                'parent_sku' => 'ROLLER-002',
            ], [
                'name' => 'Blackout Roller Blind',
                'description' => 'Complete blackout roller blind perfect for bedrooms',
                'brand' => 'BlindsCo',
                'status' => 'active',
            ]);

            ProductVariant::firstOrCreate([
                'product_id' => $product2->id,
                'sku' => 'ROLLER-002-BLACK-100',
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
                'sku' => 'ROLLER-002-BLACK-120',
            ], [
                'title' => 'Blackout Roller Blind - Black 120x160cm',
                'color' => 'Black',
                'width' => 120,
                'drop' => 160,
                'price' => 74.99,
                'stock_level' => 25,
            ]);

            ProductVariant::firstOrCreate([
                'product_id' => $product2->id,
                'sku' => 'ROLLER-002-NAVY-120',
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

    /**
     * Analyze the generated CSV content to verify correct field mapping
     */
    private function analyzeCsvContent(string $filePath): void
    {
        $this->info('🔍 Analyzing CSV Content for Variant Grouping:');

        if (! file_exists($filePath)) {
            $this->error('❌ CSV file not found');

            return;
        }

        $csvData = array_map('str_getcsv', file($filePath));
        $headers = $csvData[0];
        $rows = array_slice($csvData, 1);

        // Find key columns
        $variantGroupCol = array_search('Variant_Group_Code', $headers);
        $supplierProductRefCol = array_search('Supplier_Product_Reference', $headers);
        $supplierSkuRefCol = array_search('Supplier_SKU_Reference', $headers);
        $colourCol = array_search('Colour', $headers);
        $sizeCol = array_search('FGH_Size', $headers);
        $titleCol = array_search('Product_Description_for_Websites', $headers);

        $this->info('📋 Key Column Positions:');
        $this->info('   🎯 Variant_Group_Code: '.($variantGroupCol !== false ? $variantGroupCol : 'NOT FOUND'));
        $this->info('   📦 Supplier_Product_Reference: '.($supplierProductRefCol !== false ? $supplierProductRefCol : 'NOT FOUND'));
        $this->info('   🏷️  Supplier_SKU_Reference: '.($supplierSkuRefCol !== false ? $supplierSkuRefCol : 'NOT FOUND'));
        $this->info('   🎨 Colour: '.($colourCol !== false ? $colourCol : 'NOT FOUND'));
        $this->info('   📏 FGH_Size: '.($sizeCol !== false ? $sizeCol : 'NOT FOUND'));
        $this->newLine();

        // Group rows by Variant_Group_Code to verify grouping
        $groups = [];
        foreach ($rows as $row) {
            if (count($row) > $variantGroupCol && $variantGroupCol !== false) {
                $groupCode = $row[$variantGroupCol];
                if (! isset($groups[$groupCode])) {
                    $groups[$groupCode] = [];
                }
                $groups[$groupCode][] = $row;
            }
        }

        $this->info('🎯 Variant Grouping Analysis:');
        foreach ($groups as $groupCode => $variants) {
            $this->info("   📦 Group '{$groupCode}': {".count($variants).'} variants');

            foreach ($variants as $i => $variant) {
                $sku = $supplierSkuRefCol !== false ? $variant[$supplierSkuRefCol] : 'N/A';
                $color = $colourCol !== false ? $variant[$colourCol] : 'N/A';
                $size = $sizeCol !== false ? $variant[$sizeCol] : 'N/A';
                $this->info("      {$i}. SKU: {$sku}, Color: {$color}, Size: {$size}");
            }
            $this->newLine();
        }

        // Verify the mapping is correct
        $this->info('✅ Field Mapping Verification:');
        if (! empty($rows)) {
            $firstRow = $rows[0];

            if ($variantGroupCol !== false && $supplierProductRefCol !== false) {
                $variantGroup = $firstRow[$variantGroupCol];
                $productRef = $firstRow[$supplierProductRefCol];

                if ($variantGroup === $productRef) {
                    $this->info('   ✅ Variant_Group_Code matches Supplier_Product_Reference (parent SKU)');
                } else {
                    $this->error('   ❌ Variant_Group_Code does not match Supplier_Product_Reference');
                    $this->error("      Variant_Group_Code: {$variantGroup}");
                    $this->error("      Supplier_Product_Reference: {$productRef}");
                }
            }

            if ($supplierSkuRefCol !== false) {
                $variantSku = $firstRow[$supplierSkuRefCol];
                $this->info("   🏷️  Supplier_SKU_Reference (variant SKU): {$variantSku}");
            }
        }

        $this->newLine();
        $this->info('🎉 CSV Analysis Complete! Check the field mapping above.');
        $this->info("📁 Full CSV available at: {$filePath}");
    }
}
