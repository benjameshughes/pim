<?php

namespace App\Livewire\Images;

use App\Actions\Images\DeleteImageAction;
use App\Models\Image;
use Livewire\Component;

class ImageEditCore extends Component
{
    public Image $image;

    public function mount(Image $image): void
    {
        $this->image = $image;
    }

    public function deleteImage(DeleteImageAction $deleteImageAction)
    {
        $deleteImageAction->execute($this->image);
        return $this->redirect('/dam');
    }

    public function cancel(): void
    {
        $this->redirect('/dam');
    }

    public function render()
    {
        return view('livewire.images.image-edit-core');
    }
}