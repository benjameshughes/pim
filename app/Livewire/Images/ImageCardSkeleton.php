<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ImageCardSkeleton extends Component
{
    public Image $image;
    public bool $showActualCard = false;

    public function mount(Image $image): void
    {
        $this->image = $image;
        $this->checkIfProcessed();
    }

    protected function checkIfProcessed(): void
    {
        // If timestamps are different, image is processed - show actual card
        if (!$this->image->created_at->equalTo($this->image->updated_at)) {
            $this->showActualCard = true;
        }
    }

    public function getListeners()
    {
        return [];
    }


    public function render(): View
    {
        return view('livewire.images.image-card-skeleton');
    }
}