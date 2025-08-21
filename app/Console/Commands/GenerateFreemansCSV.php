<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * 📊 GENERATE FREEMANS CSV
 *
 * Generate CSV data to compare with API approach and potentially use file upload method
 */
class GenerateFreemansCSV extends Command
{
    protected $signature = 'freemans:generate-csv {sku? : Product SKU to export}';

    protected $description = 'Generate CSV data for Freemans marketplace upload (to compare with API approach)';

    public function handle(): int
    {
        $this->info('📊 Generating Freemans CSV Data');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $sku = $this->argument('sku') ?: 'TINKER-007';

        // Find the product
        $product = Product::where('parent_sku', $sku)->with('variants')->first();

        if (! $product) {
            $this->error("❌ Product with SKU {$sku} not found");

            return 1;
        }

        $this->info("📦 Found product: {$product->parent_sku} - {$product->name}");
        $this->info("📊 Variants: {$product->variants->count()}");
        $this->newLine();

        // Generate both Products and Offers CSV data
        $this->generateProductsCSV($product);
        $this->newLine();
        $this->generateOffersCSV($product);
        $this->newLine();

        // Analyze the differences
        $this->analyzeUploadMethods();

        return 0;
    }

    /**
     * 📋 GENERATE PRODUCTS CSV
     */
    private function generateProductsCSV(Product $product): void
    {
        $this->info('📋 PRODUCTS CSV DATA (Catalog):');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Typical Mirakl products CSV headers
        $headers = [
            'product-id',           // family SKU
            'product-title',
            'product-description',
            'brand',
            'category-code',
            'category-label',
            'product-references',
            'media-url-1',
            'media-url-2',
            'media-url-3',
        ];

        $this->table($headers, [[
            $product->parent_sku,
            $product->name,
            $product->description,
            $product->brand,
            'H02',  // Curtains & Blinds category
            'Curtains & Blinds',
            $product->parent_sku,
            '', // Would need actual image URLs
            '',
            '',
        ]]);

        $csvData = [
            implode(',', $headers),
            implode(',', [
                '"'.$product->parent_sku.'"',
                '"'.$product->name.'"',
                '"'.$product->description.'"',
                '"'.$product->brand.'"',
                '"H02"',
                '"Curtains & Blinds"',
                '"'.$product->parent_sku.'"',
                '""',
                '""',
                '""',
            ]),
        ];

        $this->info('💾 CSV Content:');
        foreach ($csvData as $row) {
            $this->line($row);
        }
    }

    /**
     * 💰 GENERATE OFFERS CSV
     */
    private function generateOffersCSV(Product $product): void
    {
        $this->info('💰 OFFERS CSV DATA (Pricing/Inventory):');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Typical Mirakl offers CSV headers
        $headers = [
            'shop-sku',
            'product-id',
            'product-id-type',
            'description',
            'price',
            'quantity',
            'leadtime-to-ship',
            'logistic-class',
            'state',
        ];

        $offersData = [];
        foreach ($product->variants as $variant) {
            $offersData[] = [
                $variant->sku,
                $product->parent_sku,
                'SHOP_SKU',
                $variant->title ?? $product->name,
                $variant->price ?? 0,
                $variant->stock_level ?? 0,
                '2', // 2 days leadtime
                'DL', // Evri shipping
                '11', // Active state
            ];
        }

        $this->table($headers, $offersData);

        $this->info('💾 CSV Content:');
        $this->line(implode(',', $headers));
        foreach ($offersData as $row) {
            $csvRow = [];
            foreach ($row as $value) {
                $csvRow[] = '"'.$value.'"';
            }
            $this->line(implode(',', $csvRow));
        }
    }

    /**
     * 🔍 ANALYZE UPLOAD METHODS
     */
    private function analyzeUploadMethods(): void
    {
        $this->info('🔍 ANALYSIS: CSV vs API Upload Methods');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $this->info('📊 CSV Upload Advantages:');
        $this->info('   ✅ Immediate visibility (as you observed)');
        $this->info('   ✅ Batch processing of multiple products');
        $this->info('   ✅ Familiar format for marketplace teams');
        $this->info('   ✅ Can bypass some API validation rules');
        $this->info('   ✅ Shows even invalid data initially');

        $this->newLine();
        $this->info('🌐 API Upload Characteristics:');
        $this->info('   ⏳ May require approval workflow');
        $this->info('   🔍 Stricter validation before display');
        $this->info('   📋 Real-time integration possible');
        $this->info('   ⚡ Better for programmatic updates');

        $this->newLine();
        $this->info('💡 RECOMMENDATION:');
        $this->info('   Consider implementing BOTH approaches:');
        $this->info('   1. CSV generation for bulk uploads');
        $this->info('   2. Keep API for real-time updates');
        $this->info('   3. Check if Freemans has file upload API endpoint');

        $this->newLine();
        $this->info('🔄 NEXT STEPS:');
        $this->info('   1. Research Freemans file upload API endpoints');
        $this->info('   2. Test CSV upload via API if available');
        $this->info('   3. Compare approval workflows between methods');
        $this->info('   4. Implement hybrid approach for best of both');
    }
}
