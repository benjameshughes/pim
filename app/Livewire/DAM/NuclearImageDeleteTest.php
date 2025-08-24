<?php

namespace App\Livewire\DAM;

use Livewire\Component;

class NuclearImageDeleteTest extends Component
{
    public $imageId;

    public function mount($imageId)
    {
        $this->imageId = $imageId;
    }

    public function delete()
    {
        // Just return a simple redirect - no model interaction at all
        return redirect('/dam');
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            <h1>Nuclear Test</h1>
            <button wire:click="delete">Delete (No Model)</button>
        </div>
        HTML;
    }
}