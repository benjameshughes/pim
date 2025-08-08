<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\On;

trait HasImageUpload
{
    /**
     * Handle image upload completion events from the ImageUploader component
     */
    #[On('images-uploaded')]
    public function onImagesUploaded($data): void
    {
        // Refresh the component to show new images
        $this->dispatch('$refresh');

        // Call custom handler if it exists
        if (method_exists($this, 'handleImagesUploaded')) {
            $this->handleImagesUploaded($data);
        }
    }

    /**
     * Handle image deletion events from the ImageUploader component
     */
    #[On('image-deleted')]
    public function onImageDeleted($data): void
    {
        // Refresh the component to reflect deletion
        $this->dispatch('$refresh');

        // Call custom handler if it exists
        if (method_exists($this, 'handleImageDeleted')) {
            $this->handleImageDeleted($data);
        }
    }

    /**
     * Handle image reordering events from the ImageUploader component
     */
    #[On('images-reordered')]
    public function onImagesReordered($data): void
    {
        // Refresh the component to reflect new order
        $this->dispatch('$refresh');

        // Call custom handler if it exists
        if (method_exists($this, 'handleImagesReordered')) {
            $this->handleImagesReordered($data);
        }
    }

    /**
     * Handle image processing completion events
     */
    #[On('image-processed')]
    public function onImageProcessedInParent($imageData): void
    {
        // Refresh the component to show processing completion
        $this->dispatch('$refresh');

        // Call custom handler if it exists
        if (method_exists($this, 'handleImageProcessed')) {
            $this->handleImageProcessed($imageData);
        }
    }

    /**
     * Handle image processing failure events
     */
    #[On('image-processing-failed')]
    public function onImageProcessingFailedInParent($imageData): void
    {
        // Refresh the component to show processing failure
        $this->dispatch('$refresh');

        // Call custom handler if it exists
        if (method_exists($this, 'handleImageProcessingFailed')) {
            $this->handleImageProcessingFailed($imageData);
        }
    }

    /**
     * Get default image uploader configuration
     * Override this method in your component to customize settings
     */
    protected function getImageUploaderConfig(): array
    {
        return [
            'modelType' => null,
            'modelId' => null,
            'imageType' => 'main',
            'multiple' => true,
            'maxFiles' => 10,
            'maxSize' => 10240, // 10MB
            'acceptTypes' => ['jpg', 'jpeg', 'png', 'webp'],
            'processImmediately' => true,
            'showPreview' => true,
            'allowReorder' => true,
            'showExistingImages' => true,
            'uploadText' => 'Drag & drop images here or click to browse',
        ];
    }

    /**
     * Get configuration for a specific image type uploader
     */
    protected function getImageUploaderConfigForType(string $imageType): array
    {
        $baseConfig = $this->getImageUploaderConfig();

        return array_merge($baseConfig, [
            'imageType' => $imageType,
            'uploadText' => "Upload {$imageType} images",
        ]);
    }

    /**
     * Helper method to refresh image-related data
     * Override this in your component if needed
     */
    protected function refreshImageData(): void
    {
        // Default implementation - just refresh the component
        $this->dispatch('$refresh');
    }
}
