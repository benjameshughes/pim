<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Services\ImageProcessingService;
use App\Events\ImageProcessed;
use App\Events\ImageProcessingFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessImageToR2 implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public ProductImage $productImage
    ) {
        // Use single images queue for all image processing
        $this->onQueue('images');
    }

    /**
     * Execute the job.
     */
    public function handle(ImageProcessingService $processingService): void
    {
        // Refresh the model to get latest status
        $this->productImage->refresh();
        
        Log::info("Starting image processing job", [
            'image_id' => $this->productImage->id,
            'current_status' => $this->productImage->processing_status,
            'attempt' => $this->attempts(),
            'queue' => 'images'
        ]);

        // Check if image still exists and needs processing
        if ($this->productImage->isProcessed()) {
            Log::info("Image already processed, skipping", [
                'image_id' => $this->productImage->id,
                'status' => $this->productImage->processing_status
            ]);
            return;
        }

        // Check if image exists in database
        if (!$this->productImage->exists) {
            Log::warning("Image no longer exists in database", [
                'image_id' => $this->productImage->id
            ]);
            return;
        }

        // Process the image
        $success = $processingService->processImage($this->productImage);

        if ($success) {
            // Fire event for successful processing
            ImageProcessed::dispatch($this->productImage);
            
            Log::info("Image processing completed successfully", [
                'image_id' => $this->productImage->id,
                'storage_disk' => $this->productImage->storage_disk,
                'variants_count' => count(ProductImage::SIZES)
            ]);
        } else {
            // This will trigger the failed() method
            throw new \Exception('Image processing failed');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error("ProcessImageToR2 job failed", [
            'image_id' => $this->productImage->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark image as failed if this was the final attempt
        if ($this->attempts() >= $this->tries) {
            $this->productImage->markAsFailed($exception->getMessage());
            
            // Fire event for failed processing
            ImageProcessingFailed::dispatch($this->productImage, $exception->getMessage());
        }
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }


    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            // Rate limit image processing to prevent overwhelming R2
            new \Illuminate\Queue\Middleware\RateLimited('image-processing'),
        ];
    }
}