<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ImageProcessingService;
use Illuminate\Console\Command;

class ProcessPendingImages extends Command
{
    protected $signature = 'images:process-pending';

    protected $description = 'Process all pending images synchronously';

    public function handle(ImageProcessingService $processingService): int
    {
        $pendingImages = ProductImage::pending()->get();

        if ($pendingImages->isEmpty()) {
            $this->info('No pending images found.');

            return 0;
        }

        $this->info("Found {$pendingImages->count()} pending images to process...");

        $processed = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($pendingImages->count());
        $bar->start();

        foreach ($pendingImages as $image) {
            try {
                if ($processingService->processImage($image)) {
                    $processed++;
                    $this->line(" ✓ Processed: {$image->original_filename}");
                } else {
                    $failed++;
                    $this->line(" ✗ Failed: {$image->original_filename}");
                }
            } catch (\Exception $e) {
                $failed++;
                $this->line(" ✗ Error processing {$image->original_filename}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Processing complete!');
        $this->info("✓ Processed: {$processed}");
        $this->info("✗ Failed: {$failed}");

        return 0;
    }
}
