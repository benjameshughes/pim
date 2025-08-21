<?php

namespace App\Console\Commands;

use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class ImportProductsFromCsv extends Command
{
    /**
     * 🔥 SASSILLA'S SPECTACULAR CSV IMPORT COMMAND
     */
    protected $signature = 'phoenix:import-products {file=example_import.csv}';

    protected $description = '✨ Import products from CSV with maximum sass and intelligence!';

    protected $importStats = [
        'products_created' => 0,
        'variants_created' => 0,
        'barcodes_created' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];

    public function handle()
    {
        $this->displaySassyBanner();

        $filePath = base_path($this->argument('file'));

        if (! file_exists($filePath)) {
            $this->error("💥 File not found: {$filePath}");

            return 1;
        }

        $this->info('🔥 Starting import from: '.basename($filePath));
        $this->info('📊 File size: '.$this->formatBytes(filesize($filePath)));

        // Read and process CSV
        $this->processCSV($filePath);

        $this->displayResults();

        return 0;
    }

    protected function processCSV(string $filePath)
    {
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle); // Get header row
        $lineNumber = 2; // Start from line 2 (after header)

        $this->info('🎭 Processing CSV with '.count($headers).' columns...');

        // Create progress bar
        $totalLines = $this->countFileLines($filePath) - 1; // Subtract header
        $bar = $this->output->createProgressBar($totalLines);
        $bar->setFormat('🔥 Processing: %current%/%max% [%bar%] %percent:3s%% %memory:6s%');
        $bar->start();

        while (($data = fgetcsv($handle)) !== false) {
            try {
                $this->processRow($headers, $data, $lineNumber);
            } catch (\Exception $e) {
                $this->importStats['errors']++;
                $this->newLine();
                $this->error("💥 Error on line {$lineNumber}: ".$e->getMessage());
            }

            $bar->advance();
            $lineNumber++;
        }

        $bar->finish();
        fclose($handle);
    }

    protected function processRow(array $headers, array $data, int $lineNumber)
    {
        // Create associative array from headers and data
        $row = array_combine($headers, $data);

        // Skip if missing essential data
        if (empty($row['Item Title']) || empty($row['Caecus SKU'])) {
            $this->importStats['skipped']++;

            return;
        }

        // Extract product info
        $productInfo = $this->extractProductInfo($row);

        // Create or find product
        $product = $this->createOrUpdateProduct($productInfo);

        // Create variant with extracted product info
        $variant = $this->createVariant($product, $row, $productInfo);

        // Create barcodes
        $this->createBarcodes($variant, $row);
    }

    protected function extractProductInfo(array $row): array
    {
        $title = $row['Item Title'];
        $caecusSku = $row['Caecus SKU'];

        // Extract parent SKU from format "026-001" -> "026"
        if (preg_match('/^(\d{3})-\d{3}$/', $caecusSku, $matches)) {
            $parentSku = $matches[1];
        } else {
            $parentSku = substr($caecusSku, 0, 3); // Fallback
        }

        // Extract base product name by removing colors and sizes
        // "Blackout Roller Blind Aubergine 60cm" -> "Blackout Roller Blind"
        $baseName = preg_replace('/\s+\d+cm$/', '', $title); // Remove size

        // Enhanced color patterns including compound colors
        $colorPatterns = [
            'Burnt Orange', 'Charcoal Grey', 'Electric Blue', 'Light Grey',
            'Lime Green', 'Aubergine', 'Cappuccino', 'Lavender', 'Natural',
            'Ochre', 'Navy', 'Pink', 'Red', 'Lemon', 'Black', 'White',
            'Grey', 'Blue', 'Green', 'Brown',
        ];

        $color = 'Unknown';
        $productBaseName = $baseName;

        // Remove color from product name to get clean base
        foreach ($colorPatterns as $pattern) {
            if (stripos($baseName, $pattern) !== false) {
                $color = $pattern;
                $productBaseName = trim(str_ireplace($pattern, '', $baseName));
                break;
            }
        }

        return [
            'name' => trim($productBaseName) ?: 'Roller Blind',
            'color' => $color,
            'parent_sku' => $parentSku,
            'full_title' => $title,
        ];
    }

    protected function createOrUpdateProduct(array $productInfo): Product
    {
        $product = Product::firstOrCreate(
            [
                'parent_sku' => $productInfo['parent_sku'],
            ],
            [
                'name' => $productInfo['name'],
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if ($product->wasRecentlyCreated) {
            $this->importStats['products_created']++;
        }

        return $product;
    }

    protected function createVariant(Product $product, array $row, array $productInfo): ProductVariant
    {
        // Extract width from title
        preg_match('/(\d+)cm/', $row['Item Title'], $matches);
        $width = $matches[1] ?? 60;

        // Use color from extracted product info (properly handles compound colors)
        $color = $productInfo['color'];

        // Extract price (using Retail Price with carriage)
        $priceString = $row['Retail Price with carriage at £4.95'] ?? $row['Ebay BO Retail Price'] ?? '0';
        $price = (float) $priceString;

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $row['Caecus SKU'],
            'title' => $row['Item Title'],
            'color' => $color,
            'width' => (int) $width,
            'price' => $price,
            'stock_level' => 10, // Default stock
            'status' => 'active',
        ]);

        $this->importStats['variants_created']++;

        return $variant;
    }

    protected function createBarcodes(ProductVariant $variant, array $row)
    {
        $barcodes = [
            ['barcode' => $row['Caecus Barcode'], 'type' => 'caecus'],
            ['barcode' => $row['Barcode'], 'type' => 'ean13'],
        ];

        foreach ($barcodes as $barcodeData) {
            if (! empty($barcodeData['barcode']) && $barcodeData['barcode'] !== '') {
                Barcode::create([
                    'product_variant_id' => $variant->id,
                    'barcode' => $barcodeData['barcode'],
                    'type' => $barcodeData['type'],
                    'status' => 'active',
                ]);

                $this->importStats['barcodes_created']++;
            }
        }
    }

    protected function countFileLines(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        $lines = 0;
        while (fgets($handle) !== false) {
            $lines++;
        }
        fclose($handle);

        return $lines;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf('%.2f', $bytes / pow(1024, $factor)).' '.$units[$factor];
    }

    protected function displaySassyBanner()
    {
        $this->info('');
        $this->info('🔥💎✨ SASSILLA\'S SPECTACULAR CSV IMPORT ✨💎🔥');
        $this->info('        Phoenix PIM Data Migration Extraordinaire        ');
        $this->info('═════════════════════════════════════════════════════════');
        $this->info('');
    }

    protected function displayResults()
    {
        $this->newLine(2);
        $this->info('🎭 IMPORT COMPLETE - RESULTS SPECTACULAR! 🎭');
        $this->info('═══════════════════════════════════════════');

        $this->table(['Metric', 'Count'], [
            ['🏢 Products Created', $this->importStats['products_created']],
            ['💎 Variants Created', $this->importStats['variants_created']],
            ['🔢 Barcodes Created', $this->importStats['barcodes_created']],
            ['⚠️ Errors', $this->importStats['errors']],
            ['⏭️ Skipped Rows', $this->importStats['skipped']],
        ]);

        if ($this->importStats['errors'] === 0) {
            $this->info('');
            $this->info('🎉 FLAWLESS EXECUTION! NO ERRORS! PURE SASS! 🎉');
        }

        $this->info('');
        $this->info('🔥 Ready to view your imported products:');
        $this->info('   👉 http://127.0.0.1:8080/products');
        $this->info('   👉 http://127.0.0.1:8080/variants');
        $this->info('   👉 http://127.0.0.1:8080/barcodes');
    }
}
