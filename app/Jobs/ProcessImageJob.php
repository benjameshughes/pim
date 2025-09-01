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

    public function handle(ImageProcessingTracker $tracker): void
    {
        Log::info('🎨 Starting background image processing', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'processing_id' => $this->processingId,
        ]);

        // Dispatch uploading progress
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            ImageProcessingStatus::UPLOADING,
            'Image uploaded, preparing for processing...',
            0,
            2
        );

        // Update status to processing
        $tracker->setStatus($this->image, ImageProcessingStatus::PROCESSING);

        // Dispatch processing progress
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            ImageProcessingStatus::PROCESSING,
            'Extracting image metadata and dimensions...',
            1,
            2
        );

        $this->extractImageMetadata();

        // Mark as completed and clear cache
        $tracker->setStatus($this->image, ImageProcessingStatus::SUCCESS);

        // Dispatch success progress
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            ImageProcessingStatus::SUCCESS,
            'Image processing completed successfully',
            2,
            2,
            [
                'metadata_extracted' => true,
                'width' => $this->image->fresh()->width,
                'height' => $this->image->fresh()->height,
            ]
        );

        // Dispatch legacy event for backward compatibility
        \App\Events\Images\ImageProcessingCompleted::dispatch($this->image->fresh());

        Log::info('✅ Background image processing completed', [
            'image_id' => $this->image->id,
            'width' => $this->image->width,
            'height' => $this->image->height,
            'processing_id' => $this->processingId,
        ]);
    }

    protected function extractImageMetadata(): void
    {
        if (! $this->image->filename || ! $this->image->url) {
            throw ImageReprocessException::invalidImage();
        }

        // Download image content temporarily
        $imageContent = Storage::disk('images')->get($this->image->filename);

        if (! $imageContent) {
            throw ImageReprocessException::storageRetrievalFailed();
        }

        // Create temporary file for processing
        $tempFile = tmpfile();
        fwrite($tempFile, $imageContent);
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // Use Intervention Image for better metadata extraction
        $manager = new ImageManager(new Driver);
        $interventionImage = $manager->read($tempPath);

        $updates = [
            'width' => $interventionImage->width(),
            'height' => $interventionImage->height(),
            'mime_type' => $interventionImage->origin()->mediaType(),
        ];

        // Get file size from storage
        $size = Storage::disk('images')->size($this->image->filename);
        if ($size) {
            $updates['size'] = $size;
        }

        // Clean up temp file
        fclose($tempFile);

        // Update image record
        $this->image->update($updates);
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
            ImageProcessingStatus::FAILED,
            'Image processing failed: ' . $exception->getMessage(),
            0,
            2
        );

        // Mark as failed in cache
        $tracker = app(ImageProcessingTracker::class);
        $tracker->setStatus($this->image, ImageProcessingStatus::FAILED);
    }
}
