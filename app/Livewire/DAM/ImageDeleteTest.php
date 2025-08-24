<?php

namespace App\Livewire\DAM;

use App\Models\Image;
use Livewire\Component;

class ImageDeleteTest extends Component
{
    public $imageId;

    public function mount($imageId)
    {
        $this->imageId = $imageId;
    }

    public function delete()
    {
        Image::find($this->imageId)->delete();
        return redirect('/dam');
    }

    public function render()
    {
        return '<div><button wire:click="delete">DELETE</button></div>';
    }
}