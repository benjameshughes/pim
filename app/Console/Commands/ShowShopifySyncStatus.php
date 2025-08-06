<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ShopifyProductSync;
use Illuminate\Console\Command;

class ShowShopifySyncStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-status {--product= : Specific product ID to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show Shopify sync status for products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛍️  Shopify Sync Status');
        
        if ($productId = $this->option('product')) {
            $this->showProductStatus((int)$productId);
        } else {
            $this->showOverallStatus();
        }
        
        return 0;
    }

    private function showProductStatus(int $productId): void
    {
        $product = Product::find($productId);
        
        if (!$product) {
            $this->error("Product with ID {$productId} not found");
            return;
        }
        
        $this->line("📦 Product: {$product->name}");
        $this->newLine();
        
        $syncs = ShopifyProductSync::where('product_id', $productId)->get();
        
        if ($syncs->isEmpty()) {
            $this->warn('   No sync records found - product not synced to Shopify');
            return;
        }
        
        foreach ($syncs as $sync) {
            $statusIcon = match($sync->sync_status) {
                'synced' => '✅',
                'failed' => '❌',
                'pending' => '⏳',
                default => '❓'
            };
            
            $this->line("   {$statusIcon} Color: {$sync->color}");
            $this->line("      Shopify ID: {$sync->shopify_product_id}");
            $this->line("      Status: {$sync->sync_status}");
            $this->line("      Last synced: {$sync->last_synced_at->diffForHumans()}");
            
            if ($sync->shopify_handle) {
                $this->line("      Handle: {$sync->shopify_handle}");
            }
            
            $this->newLine();
        }
    }

    private function showOverallStatus(): void
    {
        $totalProducts = Product::count();
        $syncedProducts = ShopifyProductSync::distinct('product_id')->count();
        $totalSyncs = ShopifyProductSync::where('sync_status', 'synced')->count();
        $failedSyncs = ShopifyProductSync::where('sync_status', 'failed')->count();
        
        $this->line("📊 Overall Statistics:");
        $this->line("   Total Laravel Products: {$totalProducts}");
        $this->line("   Products with Shopify syncs: {$syncedProducts}");
        $this->line("   Total Shopify products: {$totalSyncs}");
        
        if ($failedSyncs > 0) {
            $this->line("   Failed syncs: {$failedSyncs}");
        }
        
        $this->newLine();
        
        // Show recent syncs
        $this->info('🕒 Recent Syncs:');
        $recentSyncs = ShopifyProductSync::with('product')
            ->orderBy('last_synced_at', 'desc')
            ->limit(10)
            ->get();
            
        foreach ($recentSyncs as $sync) {
            $statusIcon = match($sync->sync_status) {
                'synced' => '✅',
                'failed' => '❌',
                'pending' => '⏳',
                default => '❓'
            };
            
            $this->line("   {$statusIcon} {$sync->product->name} ({$sync->color}) → #{$sync->shopify_product_id} - {$sync->last_synced_at->diffForHumans()}");
        }
    }
}
