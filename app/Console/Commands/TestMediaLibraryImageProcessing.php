<?php

namespace App\Console\Commands;

use App\Events\ProductVariantImported;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class TestMediaLibraryImageProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:media-library-image-processing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the enhanced Media Library image processing flow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing enhanced Media Library image processing flow...');

        // Create or get a test product
        $product = Product::firstOrCreate(
            ['name' => 'Test Product for Media Library'],
            [
                'description' => 'Testing Media Library image processing flow',
                'status' => 'active'
            ]
        );

        // Create a test variant
        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'MEDIA-TEST-001'],
            [
                'product_id' => $product->id,
                'color' => 'Blue',
                'size' => 'L',
                'status' => 'active'
            ]
        );

        $this->info("Using variant: {$variant->sku} (ID: {$variant->id})");

        // Mock import data with image URLs (working URLs)
        $importData = [
            'variant_sku' => 'MEDIA-TEST-001',
            'color' => 'Blue',
            'size' => 'L',
            'image_urls' => 'https://picsum.photos/600/600,https://picsum.photos/700/500'
        ];

        $this->info('Dispatching ProductVariantImported event with Media Library processing...');

        // Dispatch the event
        ProductVariantImported::dispatch($variant, $importData);

        $this->info('✅ Event dispatched successfully!');
        $this->info('The enhanced Media Library image processing job has been queued.');
        $this->info('');
        $this->warn('What happens next:');
        $this->line('1. Images download with proper user agent');
        $this->line('2. Media Library creates multiple sizes automatically:');
        $this->line('   - Thumb: 150x150 (immediate)');
        $this->line('   - Medium: 400x400 (immediate)');
        $this->line('   - Large: 800x800 (background)');
        $this->line('   - WebP: 600x600 (background)');
        $this->line('3. Check: php artisan queue:work --queue=image-processing');
        $this->line('4. Verify: Access variant media with $variant->getMedia("images")');

        return 0;
    }
}