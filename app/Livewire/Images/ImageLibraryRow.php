<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Livewire\Component;

class ImageLibraryRow extends Component
{
    public Image $image;
    public bool $isSelected = false;
    public bool $isProcessing = false;

    protected $listeners = [
        'selection-updated' => 'updateSelection',
    ];

    public function mount(Image $image, array $selectedImages = [])
    {
        $this->image = $image;
        $this->isSelected = in_array($image->id, $selectedImages);
        $this->isProcessing = $image->created_at == $image->updated_at;
    }

    /**
     * ☑️ TOGGLE SELECTION
     */
    public function toggleSelection()
    {
        $this->dispatch('image-selection-toggled', $this->image->id);
    }

    /**
     * 📋 COPY URL TO CLIPBOARD (handled by Alpine.js)
     */
    public function copyUrl()
    {
        // This is handled by Alpine.js in the template
        // Just here for documentation
    }

    /**
     * ✏️ NAVIGATE TO EDIT
     */
    public function editImage()
    {
        return $this->redirect(route('images.show.edit', $this->image), navigate: true);
    }

    /**
     * 🗑️ DELETE IMAGE
     */
    public function deleteImage()
    {
        $this->dispatch('delete-image-requested', $this->image->id);
    }

    /**
     * 📡 UPDATE SELECTION STATE
     */
    public function updateSelection($selectedImages)
    {
        $this->isSelected = in_array($this->image->id, $selectedImages);
    }

    /**
     * 🖼️ GET THUMBNAIL URL
     */
    public function getThumbnailUrl(): string
    {
        $thumbnailImage = Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$this->image->id}")
            ->whereJsonContains('tags', 'thumb')
            ->first();

        return $thumbnailImage ? $thumbnailImage->url : $this->image->url;
    }

    /**
     * 🔢 GET VARIANT COUNT
     */
    public function getVariantCount(): int
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$this->image->id}")
            ->count();
    }

    public function render()
    {
        return view('livewire.images.image-library-row');
    }
}