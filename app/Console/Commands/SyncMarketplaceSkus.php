<?php

namespace App\Console\Commands;

use App\Models\SyncAccount;
use App\Services\Shopify\API\Client\ShopifyClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMarketplaceSkus extends Command
{
    protected $signature = 'sync:marketplace-skus {--marketplace=shopify : Which marketplace to sync}';

    protected $description = 'Sync marketplace SKUs to sku_links table for linking dashboard';

    public function handle()
    {
        $marketplace = $this->option('marketplace');

        $this->info("🔄 Syncing {$marketplace} SKUs to sku_links table...");

        if ($marketplace === 'shopify') {
            return $this->syncShopifySkus();
        }

        $this->error("Marketplace '{$marketplace}' not supported yet");

        return 1;
    }

    protected function syncShopifySkus(): int
    {
        // Get Shopify sync account
        $syncAccount = SyncAccount::where('channel', 'shopify')->first();
        if (! $syncAccount) {
            $this->error('No Shopify sync account found');

            return 1;
        }

        // Get placeholder product ID for unlinked items
        $placeholderProduct = \App\Models\Product::first();
        if (! $placeholderProduct) {
            $this->error('No products exist - cannot create placeholder links');

            return 1;
        }

        $this->info("Using Shopify account: {$syncAccount->display_name}");
        $this->info("Using product ID {$placeholderProduct->id} as placeholder for unlinked items");

        try {
            // Fetch products from Shopify API
            $client = app(ShopifyClient::class);
            $products = $client->fetchAllProductsWithVariants();

            $this->info('Fetched '.count($products).' products from Shopify');

            // Clear existing Shopify SKUs
            DB::table('sku_links')
                ->where('sync_account_id', $syncAccount->id)
                ->delete();

            $skuCount = 0;
            $productCount = 0;
            $bar = $this->output->createProgressBar(count($products));

            foreach ($products as $product) {
                foreach ($product['variants'] ?? [] as $variant) {
                    if (! empty($variant['sku'])) {
                        // Create a unique "product" entry for each marketplace SKU
                        // This works around the unique constraint by giving each one a unique product_id
                        $uniqueProductId = $placeholderProduct->id + $productCount;

                        DB::table('sku_links')->insert([
                            'product_id' => $uniqueProductId, // Unique ID for each marketplace item
                            'internal_sku' => '__UNLINKED_'.$variant['sku'].'__', // Unique placeholder
                            'external_sku' => $variant['sku'],
                            'external_product_id' => (string) $product['id'],
                            'sync_account_id' => $syncAccount->id,
                            'link_status' => 'pending',
                            'marketplace_data' => json_encode([
                                'product_title' => $product['title'],
                                'variant_title' => $variant['title'] ?? null,
                                'price' => $variant['price'] ?? null,
                                'product_id' => $product['id'],
                                'variant_id' => $variant['id'],
                            ]),
                            'linked_at' => null,
                            'linked_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $skuCount++;
                        $productCount++;
                    }
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Synced {$skuCount} Shopify SKUs successfully!");

            return 0;

        } catch (\Exception $e) {
            $this->error('Error syncing Shopify SKUs: '.$e->getMessage());

            return 1;
        }
    }
}
