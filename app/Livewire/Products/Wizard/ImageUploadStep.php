<?php

namespace App\Livewire\Products\Wizard;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageUploadService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * 🔥✨ IMAGE UPLOAD STEP - PROFESSIONAL R2 UPLOAD ✨🔥
 *
 * Professional image upload step with drag-and-drop, previews, and R2 storage
 * Supports both Product and ProductVariant image attachments
 */
class ImageUploadStep extends Component
{
    use WithFileUploads;

    // Component state
    public array $stepData = [];

    public bool $isActive = false;

    public int $currentStep = 3;

    public bool $isEditMode = false;

    public ?Product $product = null;

    // File upload properties
    public $newProductImages = [];

    public $newVariantImages = [];

    // UI state
    public bool $isUploading = false;

    public int $uploadProgress = 0;

    public bool $enableVariantImages = false;

    // Data collections
    public Collection $productImages;

    public Collection $variantImages;

    public Collection $availableVariants;

    // Services (initialize with getter)
    protected ?ImageUploadService $imageUploadService = null;

    // Event listeners
    protected $listeners = [
        'images-linked' => 'handleImagesLinked',
    ];

    /**
     * 🛠️ GET IMAGE UPLOAD SERVICE
     */
    protected function getImageUploadService(): ImageUploadService
    {
        if (! $this->imageUploadService) {
            $this->imageUploadService = new ImageUploadService;
        }

        return $this->imageUploadService;
    }

    /**
     * 🎪 MOUNT
     */
    public function mount(
        array $stepData = [],
        bool $isActive = false,
        int $currentStep = 3,
        bool $isEditMode = false,
        ?Product $product = null
    ): void {
        $this->stepData = $stepData;
        $this->isActive = $isActive;
        $this->currentStep = $currentStep;
        $this->isEditMode = $isEditMode;
        $this->product = $product;

        // Initialize collections
        $this->productImages = collect();
        $this->variantImages = collect();
        $this->availableVariants = collect();

        // Load existing data
        $this->loadExistingImages();
        $this->loadAvailableVariants();
    }

    /**
     * 📂 LOAD EXISTING IMAGES
     */
    protected function loadExistingImages(): void
    {
        if ($this->isEditMode && $this->product) {
            $this->productImages = $this->product->images()->ordered()->get();

            // Load variant images if any variants exist
            $this->variantImages = collect();
            foreach ($this->product->variants as $variant) {
                $variantImages = $variant->images()->ordered()->get();
                $this->variantImages = $this->variantImages->merge($variantImages);
            }
        }
    }

    /**
     * 🎨 LOAD AVAILABLE VARIANTS
     */
    protected function loadAvailableVariants(): void
    {
        // Get variants from previous wizard step or from product
        if (isset($this->stepData['variants']['generated_variants'])) {
            $variants = collect($this->stepData['variants']['generated_variants']);
            $this->availableVariants = $variants->map(function ($variant) {
                return [
                    'id' => $variant['id'] ?? uniqid(),
                    'sku' => $variant['sku'],
                    'color' => $variant['color'],
                    'width' => $variant['width'],
                    'drop' => $variant['drop'],
                ];
            });
        } elseif ($this->isEditMode && $this->product) {
            $this->availableVariants = $this->product->variants()->get()->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'color' => $variant->color,
                    'width' => $variant->width,
                    'drop' => $variant->drop,
                ];
            });
        }
    }

    /**
     * 📤 SIMPLE FILE UPLOAD FOR LIVEWIRE 3
     */
    public function updatedNewProductImages(): void
    {
        if (! $this->newProductImages) {
            return;
        }

        $this->isUploading = true;

        try {
            $files = is_array($this->newProductImages) ? $this->newProductImages : [$this->newProductImages];

            if ($this->isEditMode && $this->product) {
                // Upload directly to existing product
                $newImages = $this->getImageUploadService()->uploadToProduct($this->product, $files);
                $this->loadExistingImages(); // Reload to show new images
            } else {
                // Store temporarily for new product creation
                foreach ($files as $file) {
                    $this->productImages->push([
                        'id' => uniqid('temp_', true),
                        'filename' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'url' => $file->temporaryUrl(),
                        'is_primary' => $this->productImages->isEmpty(),
                        'is_temporary' => true,
                        'file' => $file,
                    ]);
                }
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => count($files).' image(s) added!',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Upload failed: '.$e->getMessage(),
            ]);
        } finally {
            $this->isUploading = false;
            $this->newProductImages = [];
        }
    }

    /**
     * ⭐ SET PRIMARY IMAGE - SIMPLIFIED
     */
    public function setPrimaryImage(string $imageId): void
    {
        if ($this->isEditMode && $this->product) {
            $this->getImageUploadService()->setPrimaryImage($this->product, (int) $imageId);
            $this->loadExistingImages();
        } else {
            // Handle temporary images
            $this->productImages = $this->productImages->map(function ($image) use ($imageId) {
                $image['is_primary'] = ($image['id'] === $imageId);

                return $image;
            });
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Primary image set!',
        ]);
    }

    /**
     * 🗑️ REMOVE IMAGE - SIMPLIFIED
     */
    public function removeImage(string $imageId): void
    {
        if ($this->isEditMode && $this->product && ctype_digit($imageId)) {
            // Handle database images
            $image = Image::find($imageId);
            if ($image) {
                $this->getImageUploadService()->deleteImage($image);
                $this->loadExistingImages();
            }
        } else {
            // Handle temporary images
            $this->productImages = $this->productImages->reject(fn ($img) => $img['id'] === $imageId);

            // Set first image as primary if no primary exists
            if ($this->productImages->isNotEmpty() && ! $this->productImages->where('is_primary', true)->count()) {
                $first = $this->productImages->first();
                $first['is_primary'] = true;
                $this->productImages = $this->productImages->put(0, $first);
            }
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Image removed!',
        ]);
    }

    /**
     * 🧹 CLEAR ALL IMAGES - SIMPLIFIED
     */
    public function clearAllImages(): void
    {
        if ($this->isEditMode && $this->product) {
            foreach ($this->productImages as $image) {
                if ($image instanceof Image) {
                    $this->getImageUploadService()->deleteImage($image);
                }
            }
            $this->loadExistingImages();
        }

        $this->productImages = collect();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'All images cleared!',
        ]);
    }

    /**
     * 🎯 COMPLETE STEP
     */
    public function completeStep(): void
    {
        $this->dispatch('step-completed', 3, $this->getFormData());
    }

    /**
     * 📊 GET FORM DATA
     */
    protected function getFormData(): array
    {
        return [
            'product_images' => $this->productImages->toArray(),
            'variant_images' => $this->variantImages->toArray(),
            'enable_variant_images' => $this->enableVariantImages,
            'total_product_images' => $this->productImages->count(),
            'total_variant_images' => $this->variantImages->count(),
        ];
    }

    /**
     * 📊 SIMPLE COMPUTED PROPERTIES
     */
    #[Computed]
    public function imageStats(): array
    {
        return [
            'total_images' => $this->productImages->count(),
            'has_primary_image' => $this->productImages->where('is_primary', true)->isNotEmpty(),
        ];
    }

    #[Computed]
    public function validationErrors(): Collection
    {
        return collect(); // Implement validation as needed
    }

    /**
     * 🔗 HANDLE IMAGES LINKED FROM DAM
     */
    public function handleImagesLinked(array $data): void
    {
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "{$data['count']} image(s) linked from DAM library!",
        ]);

        // Reload existing images to show the newly linked ones
        $this->loadExistingImages();
    }

    /**
     * 🎨 RENDER
     */
    public function render()
    {
        return view('livewire.products.wizard.image-upload-step');
    }
}
