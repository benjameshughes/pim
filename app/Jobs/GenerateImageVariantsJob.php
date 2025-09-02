<?php

namespace App\Jobs;

use App\Actions\Images\ProcessImageVariantsAction;
use App\Enums\ImageProcessingStatus;
use App\Models\Image;
use App\Services\ImageProcessingTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateImageVariantsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes for variant generation

    public function __construct(
        public Image $image,
        public array $variants = ['thumb', 'small', 'medium', 'large', 'extra-large'],
        public ?string $processingId = null
    ) {
        $this->onQueue('images');
        $this->processingId = $processingId ?: 'variants_'.$image->id.'_'.time();
    }

    public function handle(
        ProcessImageVariantsAction $action,
        ImageProcessingTracker $tracker
    ): void {
        Log::info('🎨 Starting image variant generation', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'variants' => $this->variants,
            'processing_id' => $this->processingId,
        ]);

        // Update status to optimizing and dispatch event
        $tracker->setStatus($this->image, ImageProcessingStatus::OPTIMISING);
        
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            $this->image->uuid,
            ImageProcessingStatus::OPTIMISING,
            'Generating image variants...',
            75
        );

        // Generate variants using the action
        $result = $action->execute($this->image, $this->variants);

        // Mark as completed
        $tracker->setStatus($this->image, ImageProcessingStatus::SUCCESS);

        // Dispatch success progress
        \App\Events\Images\ImageProcessingProgress::dispatch(
            $this->image->id,
            $this->image->uuid,
            ImageProcessingStatus::SUCCESS,
            'All variants generated successfully',
            100
        );

        // Dispatch legacy event for real-time UI updates if variants were generated
        if ($result['success'] && isset($result['data']['generated_variants'])) {
            \App\Events\Images\ImageVariantsGenerated::dispatch(
                $this->image->fresh(),
                $result['data']['generated_variants']
            );
        }

        Log::info('✅ Image variant generation completed', [
            'image_id' => $this->image->id,
            'action_result' => $result['success'] ? 'success' : 'failed',
            'variants_generated' => $result['data']['variants_generated'] ?? 0,
            'message' => $result['message'] ?? 'Unknown result',
            'processing_id' => $this->processingId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Image variant generation failed', [
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
            'Variant generation failed: ' . $exception->getMessage(),
            0
        );

        // Mark as failed in cache
        $tracker = app(ImageProcessingTracker::class);
        $tracker->setStatus($this->image, ImageProcessingStatus::FAILED);
    }
}
