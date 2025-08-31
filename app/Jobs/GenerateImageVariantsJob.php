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
        public array $variants = ['thumb', 'small', 'medium']
    ) {
        $this->onQueue('images');
    }

    public function handle(
        ProcessImageVariantsAction $action,
        ImageProcessingTracker $tracker
    ): void {
        Log::info('🎨 Starting image variant generation', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'variants' => $this->variants,
        ]);

        // Update status to processing variants
        $tracker->setStatus($this->image, ImageProcessingStatus::PROCESSING);

        // Generate variants using the action
        $result = $action->execute($this->image, $this->variants);

        // Mark as completed
        $tracker->setStatus($this->image, ImageProcessingStatus::COMPLETED);

        Log::info('✅ Image variant generation completed', [
            'image_id' => $this->image->id,
            'action_result' => $result['success'] ? 'success' : 'failed',
            'variants_generated' => $result['data']['variants_generated'] ?? 0,
            'message' => $result['message'] ?? 'Unknown result',
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Image variant generation failed', [
            'image_id' => $this->image->id,
            'filename' => $this->image->filename,
            'error' => $exception->getMessage(),
        ]);

        // Mark as failed in cache
        $tracker = app(ImageProcessingTracker::class);
        $tracker->setStatus($this->image, ImageProcessingStatus::FAILED);
    }
}
