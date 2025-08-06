<?php

namespace App\Console\Commands;

use App\Events\ProductVariantImported;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class TestVariantImageProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:variant-image-processing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the event-driven variant image processing flow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing event-driven variant image processing flow...');

        // Create or get a test product
        $product = Product::firstOrCreate(
            ['name' => 'Test Product for Variant Images'],
            [
                'description' => 'Testing variant image processing flow',
                'status' => 'active'
            ]
        );

        // Create a test variant
        $variant = ProductVariant::firstOrCreate(
            ['sku' => 'TEST-VARIANT-001'],
            [
                'product_id' => $product->id,
                'color' => 'Red',
                'size' => 'M',
                'status' => 'active'
            ]
        );

        $this->info("Using variant: {$variant->sku} (ID: {$variant->id})");

        // Mock import data with image URLs
        $importData = [
            'variant_sku' => 'TEST-VARIANT-001',
            'color' => 'Red',
            'size' => 'M',
            'image_url' => 'https://picsum.photos/400/500',
            'image_1' => 'https://picsum.photos/400/501',
            'image_2' => 'https://picsum.photos/400/502'
        ];

        $this->info('Dispatching ProductVariantImported event...');

        // Dispatch the event
        ProductVariantImported::dispatch($variant, $importData);

        $this->info('✅ Event dispatched successfully!');
        $this->info('The variant image processing job has been queued.');
        $this->info('');
        $this->warn('Next steps:');
        $this->line('1. Run: php artisan queue:work --queue=image-processing');
        $this->line('2. Check: php artisan pail (for logs)');
        $this->line('3. Check the variant images after processing');

        return 0;
    }
}