<?php

namespace App\Livewire\Examples;

use App\Models\ProductVariant;
use App\Traits\HasImageUpload;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('components.layouts.app')]
class VariantImageManager extends Component
{
    use HasImageUpload;

    public ProductVariant $variant;

    public string $activeImageType = 'main';

    public array $imageStats = [];

    public function mount(ProductVariant $variant): void
    {
        $this->variant = $variant->load('product');
        $this->loadImageStats();
    }

    private function loadImageStats(): void
    {
        $this->imageStats = $this->getImageStats($this->variant);
    }

    public function setActiveImageType(string $type): void
    {
        $this->activeImageType = $type;
    }

    #[On('images-uploaded')]
    public function handleImageUpload(array $data): void
    {
        $this->loadImageStats();

        session()->flash('success',
            "Uploaded {$data['count']} images successfully! ".
            "{$data['processed']} images queued for processing."
        );
    }

    #[On('image-deleted')]
    public function handleImageDeletion(array $data): void
    {
        $this->loadImageStats();
        session()->flash('success', 'Image deleted successfully.');
    }

    #[On('images-reordered')]
    public function handleImageReorder(array $data): void
    {
        session()->flash('success', 'Images reordered successfully.');
    }

    public function render()
    {
        $imageTypes = $this->getImageTypes();
        $uploaderConfig = $this->getImageUploaderConfig($this->activeImageType, $this->variant);

        // Customize config for variant context
        $uploaderConfig['max_files'] = $imageTypes[$this->activeImageType]['max_files'] ?? 10;
        $uploaderConfig['allow_reorder'] = $imageTypes[$this->activeImageType]['allow_reorder'] ?? true;

        if ($this->activeImageType === 'swatch') {
            $uploaderConfig['max_size'] = $imageTypes['swatch']['max_size'];
            $uploaderConfig['allow_reorder'] = false;
        }

        return view('livewire.examples.variant-image-manager', [
            'imageTypes' => $imageTypes,
            'uploaderConfig' => $uploaderConfig,
        ]);
    }
}
