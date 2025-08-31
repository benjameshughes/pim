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

    public function render(): View
    {
        return view('livewire.images.image-card');
    }
}