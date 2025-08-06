<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\MiraklConnectService;
use Illuminate\Console\Command;
use Exception;

class PushProductsToMirakl extends Command
{
    protected $signature = 'mirakl:push-products {--product-id=* : Specific product IDs to push} {--limit=10 : Limit number of products to push}';
    protected $description = 'Push products to Mirakl Connect';

    public function handle()
    {
        try {
            $service = new MiraklConnectService();
            
            // Test connection first
            $this->info('Testing Mirakl Connect connection...');
            $connectionTest = $service->testConnection();
            
            if (!$connectionTest['success']) {
                $this->error('❌ Connection failed: ' . $connectionTest['message']);
                return 1;
            }
            
            $this->info('✅ Connected to Mirakl Connect');
            
            // Get products to push
            $productIds = $this->option('product-id');
            $limit = $this->option('limit');
            
            if (!empty($productIds)) {
                $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                    ->whereIn('id', $productIds)
                    ->get();
                $this->info("Pushing " . count($productIds) . " specific products...");
            } else {
                $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                    ->limit($limit)
                    ->get();
                $this->info("Pushing first {$limit} products...");
            }
            
            if ($products->isEmpty()) {
                $this->warn('No products found to push.');
                return 0;
            }
            
            $this->withProgressBar($products, function ($product) use ($service) {
                $results = $service->pushProduct($product);
                
                $successCount = collect($results)->where('success', true)->count();
                $totalCount = count($results);
                
                if ($successCount === $totalCount) {
                    $this->newLine();
                    $this->info("✅ {$product->name}: {$successCount}/{$totalCount} variants pushed successfully");
                    
                    // Show some successful responses for debugging
                    foreach (array_slice($results, 0, 2) as $result) {
                        if ($result['success'] && isset($result['response'])) {
                            $this->line("   Response for {$result['variant_sku']}: " . json_encode($result['response'], JSON_PRETTY_PRINT));
                        }
                    }
                } else {
                    $this->newLine();
                    $this->warn("⚠️  {$product->name}: {$successCount}/{$totalCount} variants pushed successfully");
                    
                    // Show failed variants with detailed errors
                    foreach ($results as $result) {
                        if (!$result['success']) {
                            if (isset($result['error'])) {
                                $error = $result['error'];
                            } elseif (isset($result['response']) && is_array($result['response'])) {
                                $error = json_encode($result['response'], JSON_PRETTY_PRINT);
                            } else {
                                $error = 'Unknown error - Status: ' . ($result['status_code'] ?? 'N/A');
                            }
                            $this->error("   ❌ {$result['variant_sku']}: {$error}");
                        }
                    }
                }
            });
            
            $this->newLine();
            $this->info('🎉 Product push completed!');
            
        } catch (Exception $e) {
            $this->error('❌ Push failed: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}