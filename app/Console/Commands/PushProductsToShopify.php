<?php

namespace App\Console\Commands;

use App\Actions\API\Shopify\PushMultipleProductsToShopify;
use App\Models\Product;
use Exception;
use Illuminate\Console\Command;

class PushProductsToShopify extends Command
{
    protected $signature = 'shopify:push-products {--product-id=* : Specific product IDs to push} {--limit=5 : Limit number of products to push}';

    protected $description = 'Push products to Shopify with color-based parent splitting';

    public function handle()
    {
        try {
            // Get products to push
            $productIds = $this->option('product-id');
            $limit = $this->option('limit');

            if (! empty($productIds)) {
                $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                    ->whereIn('id', $productIds)
                    ->get();
                $this->info('🛍️  Pushing '.count($productIds).' specific products to Shopify...');
            } else {
                $products = Product::with(['variants.attributes', 'variants.barcodes', 'variants.pricing'])
                    ->limit($limit)
                    ->get();
                $this->info("🛍️  Pushing first {$limit} products to Shopify...");
            }

            if ($products->isEmpty()) {
                $this->warn('No products found to push.');

                return 0;
            }

            $pushMultipleAction = app(PushMultipleProductsToShopify::class);
            $allResults = $pushMultipleAction->execute($products);

            // Display results
            foreach ($allResults as $productId => $result) {
                $this->newLine();
                $this->line("📦 <info>{$result['product_name']}</info>");
                $this->line("   Color Groups: {$result['color_groups']}");
                $this->line("   Total Variants: {$result['total_variants']}");
                $this->line("   Success Rate: {$result['summary']['success_rate']}%");

                foreach ($result['results'] as $colorResult) {
                    if ($colorResult['success']) {
                        $action = $colorResult['action'] ?? 'created';
                        $actionIcon = match ($action) {
                            'created' => '✅',
                            'updated' => '🔄',
                            'skipped' => '⏭️',
                            default => '✅'
                        };
                        $actionText = match ($action) {
                            'created' => 'created',
                            'updated' => 'updated',
                            'skipped' => 'skipped (no changes)',
                            default => 'processed'
                        };

                        $this->line("   {$actionIcon} <comment>{$colorResult['color']}</comment>: {$colorResult['variants_count']} variants → Shopify Product #{$colorResult['shopify_product_id']} ({$actionText})");
                    } else {
                        $this->line("   ❌ <comment>{$colorResult['color']}</comment>: {$colorResult['error']}");
                    }
                }
            }

            // Overall summary
            $totalProducts = count($allResults);
            $totalShopifyProducts = collect($allResults)->sum('color_groups');
            $totalSuccessful = collect($allResults)->sum('summary.successful');

            $this->newLine();
            $this->info('🎉 Push completed!');
            $this->line("Original Products: {$totalProducts}");
            $this->line("Shopify Products Created: {$totalSuccessful}/{$totalShopifyProducts}");

        } catch (Exception $e) {
            $this->error('❌ Push failed: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
