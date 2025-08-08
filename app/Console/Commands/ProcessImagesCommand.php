<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageToR2;
use App\Models\ProductImage;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class ProcessImagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:process 
                            {--all : Process all pending images}
                            {--failed : Process only failed images}
                            {--limit=50 : Maximum number of images to process}
                            {--dry-run : Show what would be processed without actually processing}';

    /**
     * The console command description.
     */
    protected $description = 'Process pending images to CloudFlare R2 with multiple size variants';

    /**
     * Execute the console command.
     */
    public function handle(ImageProcessingService $processingService): int
    {
        $this->info('🖼️  Product Image Processing Tool');
        $this->newLine();

        // Show current processing statistics
        $stats = $processingService->getProcessingStats();
        $this->displayStats($stats);

        // Determine what images to process
        $query = ProductImage::query();

        if ($this->option('failed')) {
            $query->where('processing_status', ProductImage::PROCESSING_FAILED);
            $this->info('🔄 Processing failed images only...');
        } elseif ($this->option('all')) {
            $query->whereIn('processing_status', [
                ProductImage::PROCESSING_PENDING,
                ProductImage::PROCESSING_FAILED,
            ]);
            $this->info('🚀 Processing all pending and failed images...');
        } else {
            $query->where('processing_status', ProductImage::PROCESSING_PENDING);
            $this->info('⏳ Processing pending images...');
        }

        $limit = (int) $this->option('limit');
        $images = $query->limit($limit)->get();

        if ($images->isEmpty()) {
            $this->info('✅ No images to process!');

            return self::SUCCESS;
        }

        $this->info("📋 Found {$images->count()} images to process");

        if ($this->option('dry-run')) {
            $this->info('🧪 DRY RUN - No images will actually be processed');
            $this->table(
                ['ID', 'Path', 'Type', 'Status', 'Product/Variant'],
                $images->map(function ($image) {
                    return [
                        $image->id,
                        basename($image->image_path),
                        $image->image_type,
                        $image->processing_status,
                        $image->product ? "Product: {$image->product->name}" :
                            ($image->variant ? "Variant: {$image->variant->product->name} - {$image->variant->color}" : 'Unassigned'),
                    ];
                })
            );

            return self::SUCCESS;
        }

        // Confirm before processing
        if (! $this->confirm("Process {$images->count()} images?")) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Process images
        $bar = $this->output->createProgressBar($images->count());
        $bar->setFormat('verbose');
        $bar->start();

        $processed = 0;
        $failed = 0;

        foreach ($images as $image) {
            $bar->setMessage("Processing {$image->original_filename}...");

            if ($this->option('failed')) {
                // Reset failed status before reprocessing
                $image->update(['processing_status' => ProductImage::PROCESSING_PENDING]);
            }

            // Queue for processing
            ProcessImageToR2::dispatch($image);
            $processed++;

            $bar->advance();

            // Add small delay to prevent overwhelming the queue
            usleep(100000); // 0.1 seconds
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Queued {$processed} images for processing!");
        $this->info('🔄 Images are being processed in the background. Use php artisan queue:work to process them.');
        $this->info('📊 Use images:stats to check progress.');

        return self::SUCCESS;
    }

    private function displayStats(array $stats): void
    {
        $this->info('📊 Current Processing Statistics:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Total Images', $stats['total']],
                ['Pending', $stats['pending']],
                ['Processing', $stats['processing']],
                ['Completed', $stats['completed']],
                ['Failed', $stats['failed']],
            ]
        );
        $this->newLine();
    }
}
