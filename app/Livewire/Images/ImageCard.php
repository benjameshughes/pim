<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ImageCard extends Component
{
    public Image $image;
    public bool $showVariants = false;
    public $variants = null;

    public function mount(Image $image): void
    {
        $this->image = $image;
    }

    public function getListeners()
    {
        return [];
    }

    public function toggleVariants(): void
    {
        $this->showVariants = !$this->showVariants;
        
        if ($this->showVariants && !$this->variants) {
            $this->variants = Image::where('folder', 'variants')
                ->whereJsonContains('tags', "original-{$this->image->id}")
                ->orderBy('created_at', 'asc')
                ->get();
        }
    }

    public function getVariantCount(): int
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