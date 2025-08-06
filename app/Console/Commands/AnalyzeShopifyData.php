<?php

namespace App\Console\Commands;

use App\Services\ShopifyConnectService;
use Illuminate\Console\Command;
use Exception;

class AnalyzeShopifyData extends Command
{
    protected $signature = 'shopify:analyze-data {--export-csv : Export to CSV file} {--limit=100 : Number of products to analyze}';
    protected $description = 'Analyze existing Shopify products and extract data patterns';

    public function handle()
    {
        try {
            $service = new ShopifyConnectService();
            $limit = (int) $this->option('limit');
            
            $this->info("🔍 Analyzing up to {$limit} Shopify products...");
            
            $result = $service->getProducts($limit);
            
            if (!$result['success']) {
                $this->error('Failed to fetch products: ' . $result['error']);
                return 1;
            }
            
            $products = $result['data']['products'];
            $this->info("Found " . count($products) . " products to analyze");
            
            // Analyze patterns
            $analysis = $this->analyzeProducts($products);
            
            // Display results
            $this->displayAnalysis($analysis);
            
            // Export to CSV if requested
            if ($this->option('export-csv')) {
                $this->exportToCsv($products);
            }
            
        } catch (Exception $e) {
            $this->error('❌ Analysis failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    private function analyzeProducts(array $products): array
    {
        $analysis = [
            'total_products' => count($products),
            'base_names' => [],
            'colors' => [],
            'sizes' => [],
            'sku_patterns' => [],
            'price_ranges' => [],
            'product_types' => []
        ];
        
        foreach ($products as $product) {
            // Extract base name and color
            $title = $product['title'];
            $productType = $product['product_type'] ?? 'Unknown';
            
            // Pattern: "Base Name Color" (e.g., "Blackout Roller Blind Aubergine")
            if (preg_match('/^(.+?)\s+([A-Za-z\s]+)$/', $title, $matches)) {
                $baseName = trim($matches[1]);
                $color = trim($matches[2]);
                
                $analysis['base_names'][$baseName] = ($analysis['base_names'][$baseName] ?? 0) + 1;
                $analysis['colors'][$color] = ($analysis['colors'][$color] ?? 0) + 1;
            }
            
            $analysis['product_types'][$productType] = ($analysis['product_types'][$productType] ?? 0) + 1;
            
            // Analyze variants
            foreach ($product['variants'] as $variant) {
                // SKU patterns
                $sku = $variant['sku'];
                if (preg_match('/^(\d{3})-(\d{3})$/', $sku, $matches)) {
                    $prefix = $matches[1];
                    $analysis['sku_patterns'][$prefix] = ($analysis['sku_patterns'][$prefix] ?? 0) + 1;
                }
                
                // Sizes from options
                if ($variant['option1']) {
                    $analysis['sizes'][$variant['option1']] = ($analysis['sizes'][$variant['option1']] ?? 0) + 1;
                }
                
                // Price ranges
                $price = (float) $variant['price'];
                $analysis['price_ranges'][] = $price;
            }
        }
        
        return $analysis;
    }
    
    private function displayAnalysis(array $analysis): void
    {
        $this->newLine();
        $this->line('📊 <info>SHOPIFY DATA ANALYSIS REPORT</info>');
        $this->line('================================');
        
        $this->line("Total Products: {$analysis['total_products']}");
        $this->newLine();
        
        // Base product names
        $this->line('🏷️  <comment>BASE PRODUCT NAMES:</comment>');
        arsort($analysis['base_names']);
        foreach (array_slice($analysis['base_names'], 0, 10, true) as $name => $count) {
            $this->line("   {$name} ({$count} colors)");
        }
        
        $this->newLine();
        
        // Colors
        $this->line('🎨 <comment>COLORS FOUND:</comment>');
        arsort($analysis['colors']);
        foreach (array_slice($analysis['colors'], 0, 15, true) as $color => $count) {
            $this->line("   {$color} ({$count} products)");
        }
        
        $this->newLine();
        
        // Sizes
        $this->line('📏 <comment>SIZES FOUND:</comment>');
        ksort($analysis['sizes']);
        foreach ($analysis['sizes'] as $size => $count) {
            $this->line("   {$size} ({$count} variants)");
        }
        
        $this->newLine();
        
        // SKU patterns
        $this->line('🔢 <comment>SKU PATTERNS:</comment>');
        ksort($analysis['sku_patterns']);
        foreach (array_slice($analysis['sku_patterns'], 0, 10, true) as $prefix => $count) {
            $this->line("   {$prefix}-XXX ({$count} variants)");
        }
        
        $this->newLine();
        
        // Price analysis
        if (!empty($analysis['price_ranges'])) {
            $minPrice = min($analysis['price_ranges']);
            $maxPrice = max($analysis['price_ranges']);
            $avgPrice = array_sum($analysis['price_ranges']) / count($analysis['price_ranges']);
            
            $this->line('💰 <comment>PRICING ANALYSIS:</comment>');
            $this->line("   Min Price: £{$minPrice}");
            $this->line("   Max Price: £{$maxPrice}");
            $this->line("   Avg Price: £" . number_format($avgPrice, 2));
        }
        
        $this->newLine();
        
        // Product types
        $this->line('📦 <comment>PRODUCT TYPES:</comment>');
        arsort($analysis['product_types']);
        foreach ($analysis['product_types'] as $type => $count) {
            $this->line("   {$type} ({$count} products)");
        }
    }
    
    private function exportToCsv(array $products): void
    {
        $filename = storage_path('app/shopify_products_export_' . date('Y-m-d_H-i-s') . '.csv');
        
        $handle = fopen($filename, 'w');
        
        // CSV Headers
        fputcsv($handle, [
            'shopify_id', 'title', 'base_name', 'color', 'product_type', 'status',
            'variant_id', 'sku', 'variant_title', 'option1', 'price', 'inventory', 'barcode'
        ]);
        
        foreach ($products as $product) {
            // Extract base name and color
            $title = $product['title'];
            $baseName = '';
            $color = '';
            
            if (preg_match('/^(.+?)\s+([A-Za-z\s]+)$/', $title, $matches)) {
                $baseName = trim($matches[1]);
                $color = trim($matches[2]);
            }
            
            foreach ($product['variants'] as $variant) {
                fputcsv($handle, [
                    $product['id'],
                    $product['title'],
                    $baseName,
                    $color,
                    $product['product_type'] ?? '',
                    $product['status'],
                    $variant['id'],
                    $variant['sku'],
                    $variant['title'],
                    $variant['option1'] ?? '',
                    $variant['price'],
                    $variant['inventory_quantity'],
                    $variant['barcode'] ?? ''
                ]);
            }
        }
        
        fclose($handle);
        
        $this->info("📁 Data exported to: {$filename}");
    }
}