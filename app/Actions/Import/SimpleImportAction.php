<?php

namespace App\Actions\Import;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🚀 SIMPLE IMPORT ACTION - No Bloat Edition!
 *
 * Extracts parent SKUs, creates products and variants from CSV data
 * Based on your actual CSV format: "45120RWST-White" → parent "45120RWST" + variant "White"
 */
class SimpleImportAction
{
    private $progressCallback;

    private $createdProducts = 0;

    private $updatedProducts = 0;

    private $createdVariants = 0;

    private $updatedVariants = 0;

    private $skippedRows = 0;

    private $errors = [];

    public function execute(array $config): array
    {
        $filePath = $config['file'];
        $mappings = $config['mappings'];
        $this->progressCallback = $config['progressCallback'] ?? null;

        Log::info('🚀 Starting simple product import', [
            'file' => basename($filePath),
            'mappings' => $mappings,
        ]);

        $startTime = microtime(true);

        try {
            return DB::transaction(function () use ($filePath, $mappings, $startTime) {
                // Read and process CSV
                $csv = array_map(function ($line) {
                    return str_getcsv($line, ',', '"', '\\');
                }, file($filePath));
                $headers = array_shift($csv); // Remove header row

                $totalRows = count($csv);
                $processed = 0;

                foreach ($csv as $row) {
                    $this->processRow($row, $mappings);

                    $processed++;
                    if ($this->progressCallback) {
                        call_user_func($this->progressCallback, (int) round(($processed / $totalRows) * 100));
                    }
                }

                $duration = microtime(true) - $startTime;

                Log::info('✅ Simple import completed', [
                    'products_created' => $this->createdProducts,
                    'products_updated' => $this->updatedProducts,
                    'variants_created' => $this->createdVariants,
                    'variants_updated' => $this->updatedVariants,
                    'skipped_rows' => $this->skippedRows,
                    'duration_seconds' => round($duration, 2),
                ]);

                return [
                    'success' => true,
                    'message' => 'Import completed successfully',
                    'created_products' => $this->createdProducts,
                    'updated_products' => $this->updatedProducts,
                    'created_variants' => $this->createdVariants,
                    'updated_variants' => $this->updatedVariants,
                    'skipped_rows' => $this->skippedRows,
                    'total_processed' => $this->createdProducts + $this->updatedProducts + $this->createdVariants + $this->updatedVariants,
                    'duration' => round($duration, 2),
                    'errors' => $this->errors,
                ];
            });

        } catch (\Exception $e) {
            Log::error('💥 Simple import failed', [
                'error' => $e->getMessage(),
                'file' => basename($filePath),
            ]);

            throw $e;
        }
    }

    /**
     * Process a single CSV row
     */
    private function processRow(array $row, array $mappings): void
    {
        try {
            // Extract data from row using mappings
            $data = $this->extractRowData($row, $mappings);

            if (! $data['sku'] || ! $data['title']) {
                $this->skippedRows++;

                return;
            }

            // Extract parent SKU and product info
            $parentInfo = $this->extractParentInfo($data);

            // Create or update parent product
            $product = $this->createOrUpdateParentProduct($parentInfo);

            // Create or update variant
            $this->createOrUpdateVariant($product, $data, $parentInfo);

        } catch (\Exception $e) {
            $this->errors[] = 'Row error: '.$e->getMessage();
            $this->skippedRows++;
        }
    }

    /**
     * Extract data from CSV row using column mappings
     */
    private function extractRowData(array $row, array $mappings): array
    {
        $data = [];

        foreach ($mappings as $field => $columnIndex) {
            $data[$field] = ($columnIndex !== '') ? ($row[$columnIndex] ?? '') : '';
        }

        return $data;
    }

    /**
     * Extract parent SKU and product information from variant data
     * Supports two patterns:
     * Pattern 1: "45120RWST-White" → parent_sku: "45120RWST", color: "White"
     * Pattern 2: "001-002-003" → parent_sku: "001-002", variant: "003"
     */
    private function extractParentInfo(array $data): array
    {
        $sku = $data['sku'] ?? '';
        $title = $data['title'] ?? '';

        // Detect SKU pattern and extract accordingly
        if (preg_match('/^(\d{3}-\d{3})-(\d{3})$/', $sku, $matches)) {
            // Pattern 2: 001-002-003 (three-part numeric pattern)
            $parentSku = $matches[1]; // 001-002
            $variantId = $matches[2]; // 003
            $color = $this->extractColorFromTitle($title);
        } elseif (preg_match('/^(\d{3})-(\d{3})$/', $sku, $matches)) {
            // Pattern 3: 010-108 (parent 010, variant 108)
            $parentSku = $matches[1]; // 010 (parent)
            $variantId = $matches[2]; // 108 (variant number)
            $color = $this->extractColorFromTitle($title);
        } else {
            // Pattern 1: RB45120-White (alphanumeric-color pattern)
            $parentSku = preg_replace('/-[A-Za-z]+$/', '', $sku);
            $skuParts = explode('-', $sku);
            // Only use SKU part if it looks like a color (contains letters)
            if (count($skuParts) > 1 && preg_match('/[A-Za-z]/', end($skuParts))) {
                $color = end($skuParts);
            } else {
                $color = $this->extractColorFromTitle($title);
            }
        }

        // Extract dimensions from title: "45cm x 120cm" or "60cm x 160cm"
        $width = null;
        $drop = null;

        if (preg_match('/(\d+)cm x (\d+)cm/', $title, $matches)) {
            $width = (int) $matches[1];
            $drop = (int) $matches[2];
        } elseif (preg_match('/(\d+)cm/', $title, $matches)) {
            $width = (int) $matches[1];
        }

        // Generate product name by removing dimensions and color
        $baseName = preg_replace('/\d+cm( x \d+cm)?/', '', $title);
        $baseName = preg_replace('/\b'.preg_quote($color, '/').'\b/i', '', $baseName);
        $baseName = trim(preg_replace('/\s+/', ' ', $baseName));

        $result = [
            'parent_sku' => $parentSku,
            'product_name' => $baseName ?: 'Product '.$parentSku,
            'color' => $color,
            'width' => $width,
            'drop' => $drop,
            'brand' => $data['brand'] ?? 'Unknown',
        ];

        // Debug logging
        Log::debug('Extracted parent info', [
            'original_sku' => $sku,
            'extracted_parent_sku' => $parentSku,
            'extracted_color' => $color,
            'pattern_detected' => $this->detectSkuPattern($sku),
        ]);

        return $result;
    }

    /**
     * Create or update parent product using updateOrCreate
     */
    private function createOrUpdateParentProduct(array $parentInfo): Product
    {
        $product = Product::updateOrCreate(
            [
                'parent_sku' => $parentInfo['parent_sku'],
            ],
            [
                'name' => $parentInfo['product_name'],
                'brand' => $parentInfo['brand'],
                'status' => 'active',
                'description' => "Imported product - {$parentInfo['product_name']}",
            ]
        );

        if ($product->wasRecentlyCreated) {
            $this->createdProducts++;
            Log::debug('Created parent product', [
                'parent_sku' => $parentInfo['parent_sku'],
                'name' => $parentInfo['product_name'],
            ]);
        } else {
            $this->updatedProducts++;
            Log::debug('Updated parent product', [
                'parent_sku' => $parentInfo['parent_sku'],
                'name' => $parentInfo['product_name'],
            ]);
        }

        return $product;
    }

    /**
     * Create or update variant using updateOrCreate
     */
    private function createOrUpdateVariant(Product $product, array $data, array $parentInfo): void
    {
        $variant = ProductVariant::updateOrCreate(
            [
                'sku' => $data['sku'],
            ],
            [
                'product_id' => $product->id,
                'external_sku' => $data['sku'],
                'title' => $data['title'],
                'color' => $parentInfo['color'],
                'width' => $parentInfo['width'] ?: 100, // Default width if null
                'drop' => $parentInfo['drop'] ?: 150,   // Default drop if null
                'price' => $this->parsePrice($data['price'] ?? ''),
                'status' => 'active',
                'stock_level' => 0,
            ]
        );

        if ($variant->wasRecentlyCreated) {
            $this->createdVariants++;
            Log::debug('Created variant', [
                'sku' => $data['sku'],
                'product_id' => $product->id,
            ]);
        } else {
            $this->updatedVariants++;
            Log::debug('Updated variant', [
                'sku' => $data['sku'],
                'product_id' => $product->id,
            ]);
        }
    }

    /**
     * Parse price from string (handles various formats)
     */
    private function parsePrice(?string $priceString): ?float
    {
        if (empty($priceString)) {
            return null;
        }

        // Remove currency symbols and extract numeric value
        $cleaned = preg_replace('/[^\d.,]/', '', $priceString);
        $cleaned = str_replace(',', '.', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    /**
     * Extract color from product title using common patterns
     */
    private function extractColorFromTitle(string $title): string
    {
        $commonColors = [
            // Compound colors first (longer matches take priority)
            'burnt orange', 'dark grey', 'light grey', 'dark gray', 'light gray',
            'dark blue', 'light blue', 'navy blue', 'royal blue', 'sky blue',
            'dark green', 'light green', 'forest green', 'lime green',
            'dark red', 'light red', 'bright red', 'deep red',
            'off white', 'cream white', 'pure white',

            // Single word colors
            'white', 'black', 'grey', 'gray', 'brown', 'beige', 'cream', 'ivory',
            'blue', 'navy', 'red', 'green', 'yellow', 'orange', 'pink', 'purple',
            'aubergine', 'charcoal', 'taupe', 'linen', 'natural',
        ];

        // Sort by length descending so longer color names are matched first
        usort($commonColors, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($commonColors as $color) {
            if (stripos($title, $color) !== false) {
                return ucwords($color);  // Use ucwords for multi-word colors
            }
        }

        // Fallback: try to find any color-like word
        if (preg_match('/\b([A-Za-z]+)\s+\d+cm/', $title, $matches)) {
            return ucfirst($matches[1]);
        }

        return 'Default';
    }

    /**
     * Detect which SKU pattern is being used (for debugging)
     */
    private function detectSkuPattern(string $sku): string
    {
        if (preg_match('/^(\d{3}-\d{3})-(\d{3})$/', $sku)) {
            return 'three-part-numeric (001-002-003)';
        } elseif (preg_match('/^(\d{3})-(\d{3})$/', $sku)) {
            return 'two-part-numeric (010-108)';
        } else {
            return 'alphanumeric-color (RB45120-White)';
        }
    }
}
