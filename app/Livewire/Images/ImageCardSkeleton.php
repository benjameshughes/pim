<?php

namespace App\Livewire\Images;

use App\Enums\ImageProcessingStatus;
use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * 💀 IMAGE CARD SKELETON
 *
 * Shows loading/processing state while image is being processed and downloaded
 * Replaces itself with actual ImageCard only when fully ready
 */
class ImageCardSkeleton extends Component
{
    public Image $image;
    public ImageProcessingStatus $status = ImageProcessingStatus::PENDING;
    public string $statusMessage = 'Starting upload...';
    public int $progress = 0;
    public bool $imageLoaded = false;
    public bool $shouldShowActualCard = false;

    public function mount(Image $image): void
    {
        $this->image = $image;
        $this->checkInitialState();
    }

    /**
     * Get real-time event listeners for processing updates
     */
    public function getListeners()
    {
        return [
            'echo:images,ImageProcessingProgress' => 'updateProcessingProgress',
            'echo:images,ImageVariantsGenerated' => 'onVariantsGenerated',
        ];
    }

    /**
     * 📻 HANDLE PROCESSING PROGRESS UPDATES
     */
    public function updateProcessingProgress($event): void
    {
        // Only respond to events for this image
        if ($event['imageId'] != $this->image->id) {
            return;
        }
        
        $this->status = ImageProcessingStatus::from($event['status']);
        $this->statusMessage = $event['currentAction'];
        $this->progress = $event['percentage'];
        
        // If processing is complete, check if we should show actual card
        if ($this->status === ImageProcessingStatus::SUCCESS) {
            $this->checkImageAvailability();
        }
    }

    /**
     * 🎨 VARIANTS GENERATED - Final check for readiness
     */
    public function onVariantsGenerated($event): void
    {
        if ($event['original_image_id'] == $this->image->id) {
            // Check if we should show actual card
            $this->dispatch('check-image-ready', $this->image->id);
        }
    }

    /**
     * 🔍 CHECK IF IMAGE IS AVAILABLE FOR DISPLAY
     */
    public function checkImageAvailability(): void
    {
        // Refresh image model to get latest data
        $this->image = $this->image->fresh();
        
        // Check if processing is complete and image has dimensions
        if ($this->image->width > 0 && $this->image->height > 0) {
            // Try to verify image is accessible (simple check)
            $this->verifyImageDownload();
        }
    }

    /**
     * 🌐 VERIFY IMAGE CAN BE DOWNLOADED
     */
    public function verifyImageDownload(): void
    {
        // For now, just mark as ready after processing is complete
        // In production, you might want to do a HEAD request to verify image exists
        $this->imageLoaded = true;
        $this->shouldShowActualCard = true;
        
        // Dispatch event to parent component to replace skeleton
        $this->dispatch('image-ready', [
            'imageId' => $this->image->id,
            'replaceWith' => 'actual-card'
        ]);
    }

    /**
     * 🔄 CHECK INITIAL STATE - Maybe image is already processed
     */
    protected function checkInitialState(): void
    {
        // If image already has dimensions, it's been processed
        if ($this->image->width > 0 && $this->image->height > 0) {
            $this->status = ImageProcessingStatus::SUCCESS;
            $this->statusMessage = 'Loading image...';
            $this->progress = 100;
            $this->checkImageAvailability();
            return;
        }
        
        // Check processing tracker status
        $tracker = app(\App\Services\ImageProcessingTracker::class);
        $cachedStatus = $tracker->getStatus($this->image);
        
        if ($cachedStatus) {
            $this->status = $cachedStatus;
            $this->progress = match($cachedStatus) {
                ImageProcessingStatus::PENDING => 0,
                ImageProcessingStatus::PROCESSING => 50,
                ImageProcessingStatus::OPTIMISING => 75,
                ImageProcessingStatus::SUCCESS => 100,
                ImageProcessingStatus::FAILED => 0,
                default => 0
            };
            
            $this->statusMessage = match($cachedStatus) {
                ImageProcessingStatus::PENDING => 'Queued for processing...',
                ImageProcessingStatus::PROCESSING => 'Processing image...',
                ImageProcessingStatus::OPTIMISING => 'Generating variants...',
                ImageProcessingStatus::SUCCESS => 'Processing complete!',
                ImageProcessingStatus::FAILED => 'Processing failed',
                default => 'Processing...'
            };
        } else {
            // Default to pending if no status found
            $this->status = ImageProcessingStatus::PENDING;
            $this->statusMessage = 'Preparing for processing...';
            $this->progress = 0;
        }
    }

    public function render(): View
    {
        return view('livewire.images.image-card-skeleton');
    }
}