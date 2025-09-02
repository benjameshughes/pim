<?php

namespace App\Jobs;

use App\Actions\Images\CreateImageRecordAction;
use App\Actions\Images\UploadImageToStorageAction;
use App\Actions\Images\ValidateImageFileAction;
use App\Enums\ImageProcessingStatus;
use App\Services\ImageProcessingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 📤 UPLOAD IMAGE JOB
 * 
 * Orchestrates Actions for complete image upload workflow:
 * 1. Upload to R2 storage (UploadImageToStorageAction)
 * 2. Create image record (CreateImageRecordAction)
 * 3. Dispatch processing job (ProcessImageJob)
 * 4. Dispatch variant generation job (GenerateImageVariantsJob)
 */
class UploadImageJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes total

    public function __construct(
        public string $tempFilePath,
        public string $originalFilename,
        public string $mimeType,
        public int $fileSize,
        public array $metadata = [],
        public bool $generateVariants = true,
        public ?string $jobId = null
    ) {
        $this->onQueue('images');
        $this->jobId = $jobId ?: 'upload_' . Str::uuid();
    }

    public function handle(
        UploadImageToStorageAction $uploadAction,
        CreateImageRecordAction $createRecordAction,
        ImageProcessingTracker $tracker
    ): void {
        Log::info('🚀 Starting background image upload using Actions', [
            'original_filename' => $this->originalFilename,
            'job_id' => $this->jobId,
        ]);

        try {
            // Step 1: Upload to R2 storage using Action
            $this->dispatchProgressEvent(\App\Enums\ImageProcessingStatus::PENDING, 'Uploading to cloud storage...', 25);
            
            $file = new File($this->tempFilePath);
            $uploadResult = $uploadAction->execute($file);
            
            if (!$uploadResult['success']) {
                throw new \RuntimeException('Storage upload failed: ' . $uploadResult['message']);
            }

            // Step 2: Create image record using Action
            $this->dispatchProgressEvent(\App\Enums\ImageProcessingStatus::PENDING, 'Creating image record...', 50);
            
            $recordResult = $createRecordAction->execute(
                $uploadResult['data'], // storage data
                $this->originalFilename,
                $this->mimeType,
                $this->metadata
            );

            if (!$recordResult['success']) {
                throw new \RuntimeException('Image record creation failed: ' . $recordResult['message']);
            }

            $image = $recordResult['data']['image'];

            // Step 3: Dispatch processing job
            $tracker->setStatus($image, \App\Enums\ImageProcessingStatus::PROCESSING);
            $this->dispatchProgressEvent(\App\Enums\ImageProcessingStatus::PROCESSING, 'Queuing metadata extraction...', 75, $image->id);
            
            ProcessImageJob::dispatch($image, $this->jobId . '_process');

            // Step 4: Generate variants if requested
            if ($this->generateVariants) {
                GenerateImageVariantsJob::dispatch($image, ['thumb', 'small', 'medium'], $this->jobId . '_variants')
                    ->delay(now()->addSeconds(5));
            }

            Log::info('✅ Image upload job completed successfully', [
                'image_id' => $image->id,
                'filename' => $uploadResult['data']['filename'],
                'job_id' => $this->jobId,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Image upload job failed', [
                'original_filename' => $this->originalFilename,
                'error' => $e->getMessage(),
                'job_id' => $this->jobId,
            ]);

            $this->dispatchProgressEvent(\App\Enums\ImageProcessingStatus::FAILED, 'Upload failed: ' . $e->getMessage(), 0);
            throw $e;
        } finally {
            // Clean up temp file
            if (file_exists($this->tempFilePath)) {
                unlink($this->tempFilePath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Image upload job failed completely', [
            'original_filename' => $this->originalFilename,
            'error' => $exception->getMessage(),
            'job_id' => $this->jobId,
        ]);

        $this->dispatchProgressEvent(\App\Enums\ImageProcessingStatus::FAILED, 'Upload failed: ' . $exception->getMessage(), 0);

        // Clean up temp file
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }

    private function dispatchProgressEvent(\App\Enums\ImageProcessingStatus $status, string $message, int $percentage, int $imageId = 0): void
    {
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $imageId,
            $this->jobId, // Use jobId as UUID for tracking
            $status,
            $message,
            $percentage
        );
    }
}