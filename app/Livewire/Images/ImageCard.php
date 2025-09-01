<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * 🖼️ IMAGE CARD COMPONENT
 *
 * Shows original image with variant count and preview functionality
 */
class ImageCard extends Component
{
    public Image $image;
    public bool $showVariants = false;
    public $variants = null;
    public bool $isProcessing = false;
    public string $processingStatus = '';

    public function mount(Image $image): void
    {
        $this->image = $image;
        
        // Check if image is still processing
        $this->checkProcessingState();
    }

    /**
     * 🔍 CHECK PROCESSING STATE
     */
    protected function checkProcessingState(): void
    {
        // If image has no dimensions, it's likely still processing
        if ($this->image->width <= 0 || $this->image->height <= 0) {
            $this->isProcessing = true;
            $this->processingStatus = 'Processing...';
        }
    }

    /**
     * Get real-time event listeners - Global channel with ID filtering
     */
    public function getListeners()
    {
        return [
            // Legacy events
            'echo:images,ImageProcessingCompleted' => 'onImageProcessed',
            'echo:images,ImageVariantsGenerated' => 'onVariantsGenerated',
            // New progress events on global channel
            'echo:images,ImageProcessingProgress' => 'updateProcessingProgress',
        ];
    }

    public function toggleVariants(): void
    {
        $this->showVariants = !$this->showVariants;
        
        // Load variants when showing them
        if ($this->showVariants && !$this->variants) {
            $this->loadVariants();
        }
    }

    protected function loadVariants(): void
    {
        $this->variants = Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$this->image->id}")
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getVariantCountProperty(): int
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$this->image->id}")
            ->count();
    }

    /**
     * 📻 HANDLE REAL-TIME PROCESSING PROGRESS  
     */
    public function updateProcessingProgress($event): void
    {
        if ($event['imageId'] == $this->image->id) {
            $this->isProcessing = !in_array($event['status'], ['success', 'failed']);
            $this->processingStatus = $event['statusLabel'] ?? '';
            
            // Refresh image when completed
            if ($event['status'] === 'success') {
                $this->image = $this->image->fresh();
                
                // Clear cached variants so they reload if we generated new ones
                if (isset($event['stats']['variants_generated']) && $event['stats']['variants_generated'] > 0) {
                    $this->variants = null;
                    
                    // If showing variants, reload them
                    if ($this->showVariants) {
                        $this->loadVariants();
                    }
                }
            }
        }
    }

    /**
     * 📻 IMAGE PROCESSING COMPLETED - Update card state (legacy)
     */
    public function onImageProcessed($event): void
    {
        if ($event['image_id'] == $this->image->id) {
            $this->isProcessing = false;
            $this->image = $this->image->fresh();
        }
    }

    /**
     * 🎨 VARIANTS GENERATED - Update variant count (legacy)
     */
    public function onVariantsGenerated($event): void
    {
        if ($event['original_image_id'] == $this->image->id) {
            $this->isProcessing = false;
            
            // Clear cached variants so they reload
            $this->variants = null;
            
            // If showing variants, reload them
            if ($this->showVariants) {
                $this->loadVariants();
            }
        }
    }

    public function render(): View
    {
        return view('livewire.images.image-card');
    }
}