<?php

namespace App\Traits;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Model;

trait HasImageUpload
{
    /**
     * Get the image uploader configuration for this component
     */
    public function getImageUploaderConfig(string $imageType = 'main', ?Model $model = null): array
    {
        $config = [
            'model_type' => null,
            'model_id' => null,
            'image_type' => $imageType,
            'multiple' => true,
            'max_files' => 10,
            'max_size' => 10240, // 10MB in KB
            'accept_types' => ['jpg', 'jpeg', 'png', 'webp'],
            'process_immediately' => true,
            'show_preview' => true,
            'allow_reorder' => true,
            'show_upload_area' => true,
            'show_existing_images' => true,
            'view_mode' => 'grid',
        ];

        // Set model context if provided
        if ($model) {
            if ($model instanceof \App\Models\Product) {
                $config['model_type'] = 'product';
                $config['model_id'] = $model->id;
            } elseif ($model instanceof \App\Models\ProductVariant) {
                $config['model_type'] = 'variant';
                $config['model_id'] = $model->id;
            }
        }

        return $config;
    }

    /**
     * Get image types configuration
     */
    public function getImageTypes(): array
    {
        return [
            'main' => [
                'label' => 'Main Images',
                'description' => 'Primary product images shown in listings and detail pages',
                'max_files' => 5,
                'allow_reorder' => true,
            ],
            'detail' => [
                'label' => 'Detail Images',
                'description' => 'Close-up and detailed views of the product',
                'max_files' => 10,
                'allow_reorder' => true,
            ],
            'swatch' => [
                'label' => 'Swatch Images',
                'description' => 'Color/material swatches for variant selection',
                'max_files' => 20,
                'max_size' => 2048, // 2MB for swatches
                'allow_reorder' => false,
            ],
            'lifestyle' => [
                'label' => 'Lifestyle Images',
                'description' => 'Images showing product in use or styled contexts',
                'max_files' => 8,
                'allow_reorder' => true,
            ],
            'installation' => [
                'label' => 'Installation Images',
                'description' => 'Setup, installation, or assembly instructions',
                'max_files' => 15,
                'allow_reorder' => true,
            ],
        ];
    }

    /**
     * Get image uploader component with custom configuration
     */
    public function renderImageUploader(array $config = []): string
    {
        $defaultConfig = $this->getImageUploaderConfig();
        $mergedConfig = array_merge($defaultConfig, $config);

        return view('livewire.components.image-uploader', $mergedConfig)->render();
    }

    /**
     * Handle image upload completion event
     */
    public function onImagesUploaded(array $data): void
    {
        // Override in components that need custom handling
        if (method_exists($this, 'handleImageUpload')) {
            $this->handleImageUpload($data);
        }

        // Refresh component data
        $this->dispatch('$refresh');
    }

    /**
     * Handle image deletion event
     */
    public function onImageDeleted(array $data): void
    {
        // Override in components that need custom handling
        if (method_exists($this, 'handleImageDeletion')) {
            $this->handleImageDeletion($data);
        }

        // Refresh component data
        $this->dispatch('$refresh');
    }

    /**
     * Handle image reordering event
     */
    public function onImagesReordered(array $data): void
    {
        // Override in components that need custom handling
        if (method_exists($this, 'handleImageReorder')) {
            $this->handleImageReorder($data);
        }

        // Refresh component data
        $this->dispatch('$refresh');
    }

    /**
     * Get image statistics for a model
     */
    public function getImageStats(?Model $model = null): array
    {
        if (! $model) {
            return [
                'total' => 0,
                'by_type' => [],
                'processing_stats' => [
                    'pending' => 0,
                    'processing' => 0,
                    'completed' => 0,
                    'failed' => 0,
                ],
            ];
        }

        $query = ProductImage::query();

        if ($model instanceof \App\Models\Product) {
            $query->where('product_id', $model->id)->whereNull('variant_id');
        } elseif ($model instanceof \App\Models\ProductVariant) {
            $query->where('variant_id', $model->id)->whereNull('product_id');
        }

        $images = $query->get();

        return [
            'total' => $images->count(),
            'by_type' => $images->groupBy('image_type')->map->count()->toArray(),
            'processing_stats' => [
                'pending' => $images->where('processing_status', ProductImage::PROCESSING_PENDING)->count(),
                'processing' => $images->where('processing_status', ProductImage::PROCESSING_IN_PROGRESS)->count(),
                'completed' => $images->where('processing_status', ProductImage::PROCESSING_COMPLETED)->count(),
                'failed' => $images->where('processing_status', ProductImage::PROCESSING_FAILED)->count(),
            ],
        ];
    }
}
