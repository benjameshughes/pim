<?php

namespace App\Console\Commands;

use App\Events\ProductImported;
use App\Models\Product;
use Illuminate\Console\Command;

class TestImageProcessing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:image-processing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the event-driven image processing flow';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing event-driven image processing flow...');

        // Create or get a test product
        $product = Product::firstOrCreate(
            ['name' => 'Test Product with Images'],
            [
                'description' => 'Testing image processing flow',
                'status' => 'active'
            ]
        );

        $this->info("Using product: {$product->name} (ID: {$product->id})");

        // Mock import data with image URLs
        $importData = [
            'product_name' => 'Test Product with Images',
            'description' => 'Testing image processing flow',
            'image_url' => 'https://picsum.photos/400/300',
            'image_1' => 'https://picsum.photos/400/301',
            'image_2' => 'https://picsum.photos/400/302'
        ];

        $this->info('Dispatching ProductImported event...');

        // Dispatch the event
        ProductImported::dispatch($product, $importData);

        $this->info('✅ Event dispatched successfully!');
        $this->info('The image processing job has been queued.');
        $this->info('');
        $this->warn('Next steps:');
        $this->line('1. Run: php artisan queue:work --queue=image-processing');
        $this->line('2. Check: php artisan pail (for logs)');
        $this->line('3. Check the product images after processing');

        return 0;
    }
}
