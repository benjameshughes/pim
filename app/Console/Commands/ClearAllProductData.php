<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Barcode;
use App\Models\Pricing;
use App\Models\BarcodePool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearAllProductData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:products {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '🗑️  Nuclear reset: Delete ALL products, variants, barcodes, pricing, and images (DEVELOPMENT ONLY)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🗑️  PRODUCT DATA NUCLEAR RESET');
        $this->info('================================');
        
        // Show what will be deleted
        $stats = $this->getDataStats();
        
        $this->warn('⚠️  This will permanently delete:');
        $this->line("   • {$stats['products']} Products");
        $this->line("   • {$stats['variants']} Product Variants");
        $this->line("   • {$stats['barcodes']} Barcodes");
        $this->line("   • {$stats['pricing']} Pricing Records");
        $this->line("   • All product images from storage");
        $this->line("   • Reset barcode pool usage");
        $this->line("   • Reset auto-increment counters");
        
        if ($stats['products'] === 0 && $stats['variants'] === 0) {
            $this->info('✅ No product data found. Nothing to delete!');
            return 0;
        }
        
        // Confirmation (unless --force flag is used)
        if (!$this->option('force')) {
            $this->newLine();
            $this->error('⚠️  THIS CANNOT BE UNDONE!');
            
            if (!$this->confirm('Are you absolutely sure you want to delete ALL product data?')) {
                $this->info('❌ Operation cancelled. Your data is safe!');
                return 0;
            }
            
            if (!$this->confirm('Last chance! This will wipe EVERYTHING. Continue?')) {
                $this->info('❌ Operation cancelled. Your data is safe!');
                return 0;
            }
        }
        
        $this->newLine();
        $this->info('🚀 Starting nuclear reset...');
        
        // Start deletion process
        DB::beginTransaction();
        
        try {
            $this->deleteAllData();
            DB::commit();
            
            $this->newLine();
            $this->info('✅ Nuclear reset completed successfully!');
            $this->info('🎉 Your database is now squeaky clean and ready for fresh imports!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error during deletion: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Get current data statistics
     */
    private function getDataStats(): array
    {
        return [
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
            'barcodes' => Barcode::count(),
            'pricing' => Pricing::count(),
        ];
    }
    
    /**
     * Delete all product-related data
     */
    private function deleteAllData(): void
    {
        $this->info('🗑️  Deleting pricing records...');
        Pricing::truncate();
        
        $this->info('🗑️  Deleting barcodes...');
        Barcode::truncate();
        
        $this->info('🗑️  Deleting product variants...');
        ProductVariant::truncate();
        
        $this->info('🗑️  Deleting products...');
        Product::truncate();
        
        $this->info('🗑️  Clearing product images from storage...');
        $this->clearProductImages();
        
        $this->info('🗑️  Resetting barcode pool usage...');
        $this->resetBarcodePool();
        
        $this->info('🗑️  Resetting auto-increment counters...');
        $this->resetAutoIncrements();
    }
    
    /**
     * Clear all product images from storage
     */
    private function clearProductImages(): void
    {
        try {
            if (Storage::disk('public')->exists('products')) {
                Storage::disk('public')->deleteDirectory('products');
                $this->line('   ✓ Deleted products directory from storage');
            } else {
                $this->line('   ℹ No products directory found in storage');
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Could not delete images: {$e->getMessage()}");
        }
    }
    
    /**
     * Reset barcode pool usage
     */
    private function resetBarcodePool(): void
    {
        try {
            $resetCount = BarcodePool::where('is_used', true)->update([
                'is_used' => false,
                'used_by_variant_id' => null,
                'used_at' => null
            ]);
            
            $this->line("   ✓ Reset {$resetCount} barcode pool entries");
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Could not reset barcode pool: {$e->getMessage()}");
        }
    }
    
    /**
     * Reset auto-increment counters for fresh starts
     */
    private function resetAutoIncrements(): void
    {
        try {
            $tables = ['products', 'product_variants', 'barcodes', 'pricing'];
            
            foreach ($tables as $table) {
                DB::statement("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
            }
            
            $this->line('   ✓ Reset auto-increment counters');
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Could not reset auto-increments: {$e->getMessage()}");
        }
    }
}
