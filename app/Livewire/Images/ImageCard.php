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

    protected $listeners = [
        'echo:images,ImageProcessingCompleted' => 'onImageProcessed',
        'echo:images,ImageVariantsGenerated' => 'onVariantsGenerated',
    ];

    public function mount(Image $image): void
    {
        $this->image = $image;
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
     * 📻 IMAGE PROCESSING COMPLETED - Update card state
     */
    public function onImageProcessed($event): void
    {
        if ($event['image_id'] == $this->image->id) {
            $this->isProcessing = false;
            $this->image = $this->image->fresh();
        }
    }

    /**
     * 🎨 VARIANTS GENERATED - Update variant count
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