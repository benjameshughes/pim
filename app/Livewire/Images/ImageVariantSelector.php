<?php

namespace App\Livewire\Images;

use App\Actions\Images\GetImageFamilyAction;
use App\Models\Image;
use App\Services\ImageProcessingTracker;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * 🎨 IMAGE VARIANT SELECTOR
 *
 * Component for switching between original image and its variants
 * Shows thumbnails with metadata and processing status
 */
class ImageVariantSelector extends Component
{
    public Image $currentImage;

    public $imageFamily = [];

    public $originalImage = null;

    public $variants = [];

    public function mount(Image $image): void
    {
        $this->currentImage = $image;
        $this->loadImageFamily();
    }

    public function selectImage(int $imageId): void
    {
        $selectedImage = collect($this->imageFamily)->firstWhere('id', $imageId);

        if ($selectedImage) {
            // Redirect to the selected image's show page
            $this->redirect(route('images.show', $selectedImage['id']), navigate: true);
        }
    }

    public function generateVariants(): void
    {
        // Dispatch variant generation for the original image
        $originalId = $this->originalImage['id'] ?? $this->currentImage->id;

        $this->dispatch('generate-variants', imageId: $originalId);

        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Generating variants in background... 🎨',
        ]);

        // Refresh family data
        $this->loadImageFamily();
    }

    protected function loadImageFamily(): void
    {
        $action = new GetImageFamilyAction;
        $result = $action->execute($this->currentImage);

        if ($result['success']) {
            $this->originalImage = $result['data']['original']->toArray();
            $this->variants = $result['data']['variants']->toArray();
            $this->imageFamily = $result['data']['family']->toArray();
        }
    }

    public function getProcessingStatusesProperty(): array
    {
        $tracker = app(ImageProcessingTracker::class);
        $images = collect($this->imageFamily)->map(fn ($img) => Image::find($img['id'])
        )->filter();

        return $tracker->getMultipleStatuses($images->all());
    }

    public function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public function render(): View
    {
        return view('livewire.images.image-variant-selector');
    }
}
