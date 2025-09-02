<?php

namespace App\Jobs;

use App\Enums\ImageProcessingStatus;
use App\Exceptions\ImageReprocessException;
use App\Models\Image;
use App\Services\ImageProcessingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProcessImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for image processing

    public function __construct(
        public Image $image,
        public ?string $processingId = null
    ) {
        $this->onQueue('images');
        $this->processingId = $processingId ?: 'process_'.$image->id.'_'.time();
    }

    public function handle(
        \App\Actions\Images\ExtractImageMetadataAction $extractMetadataAction,
        ImageProcessingTracker $tracker
    ): void {
        Log::info('🎨 Starting background image processing using Action', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'processing_id' => $this->processingId,
        ]);

        // Set initial processing status and dispatch event
        $tracker->setStatus($this->image, ImageProcessingStatus::PROCESSING);
        
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            $this->image->uuid,
            ImageProcessingStatus::PROCESSING,
            'Extracting image metadata...',
            50
        );

        try {
            // Extract metadata using Action
            $result = $extractMetadataAction->execute($this->image);
            
            if (!$result['success']) {
                throw new \RuntimeException('Metadata extraction failed: ' . $result['message']);
            }

            // Mark as completed and dispatch success
            $tracker->setStatus($this->image, ImageProcessingStatus::SUCCESS);

            \App\Events\Images\ImageProcessingProgress::dispatch(
                $this->image->id,
                $this->image->uuid,
                ImageProcessingStatus::SUCCESS,
                'Image processing completed',
                100
            );

            // Dispatch legacy event for backward compatibility
            \App\Events\Images\ImageProcessingCompleted::dispatch($this->image->fresh());

            // Chain variant generation after processing completes
            \App\Jobs\GenerateImageVariantsJob::dispatch($this->image)
                ->delay(now()->addSeconds(2)); // Small delay to let UI update

            Log::info('✅ Background image processing completed successfully', [
                'image_id' => $this->image->id,
                'width' => $result['data']['width'],
                'height' => $result['data']['height'],
                'processing_id' => $this->processingId,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Image processing failed', [
                'image_id' => $this->image->id,
                'error' => $e->getMessage(),
                'processing_id' => $this->processingId,
            ]);

            throw $e;
        }
    }


    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Image processing job failed', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'error' => $exception->getMessage(),
            'processing_id' => $this->processingId,
        ]);

        // Dispatch failure progress
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            $this->image->uuid,
            ImageProcessingStatus::FAILED,
            'Image processing failed: ' . $exception->getMessage(),
            0
        );

        // Mark as failed in cache
        $tracker = app(ImageProcessingTracker::class);
        $tracker->setStatus($this->image, ImageProcessingStatus::FAILED);
    }
}
