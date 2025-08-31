<?php

namespace App\Livewire\BulkOperations;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

/**
 * 🚀 BULK IMAGE OPERATION
 *
 * Dedicated full-page component for bulk image management operations.
 * Supports bulk image upload, assignment, and organization.
 * Handles both products and variants with appropriate image storage.
 */
class BulkImageOperation extends BaseBulkOperation
{
    use WithFileUploads;

    // Form data for image operations
    /** @var array<string, mixed> */
    public array $imageData = [
        'operation_type' => 'upload_assign',
        'assignment_type' => 'primary',
        'replace_existing' => false,
    ];

    // File uploads
    /** @var array<UploadedFile>|null */
    public $imageFiles = [];

    // Preview state
    /** @var array<string, string> */
    public array $imagePreviews = [];

    /**
     * 🎯 Initialize image operation
     */
    public function mount(string $targetType, mixed $selectedItems): void
    {
        parent::mount($targetType, $selectedItems);
    }

    /**
     * 📷 Apply bulk image operation
     */
    public function applyBulkImages(): void
    {
        if (empty($this->selectedItems)) {
            return;
        }

        // Validate based on operation type
        $rules = [
            'imageData.operation_type' => 'required|in:upload_assign,clear_images,reorganize',
            'imageData.assignment_type' => 'required_if:imageData.operation_type,upload_assign|in:primary,gallery,all',
        ];

        if ($this->imageData['operation_type'] === 'upload_assign') {
            $rules['imageFiles'] = 'required|array|min:1|max:10';
            $rules['imageFiles.*'] = 'image|mimes:jpeg,png,jpg,gif,webp|max:10240'; // 10MB max
        }

        $this->validate($rules);

        $this->executeBulkOperation(
            operation: fn (Model $item) => $this->processItemImages($item),
            operationType: 'updated images for'
        );
    }

    /**
     * 🖼️ Process images for individual item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function processItemImages(Model $item): void
    {
        switch ($this->imageData['operation_type']) {
            case 'upload_assign':
                $this->uploadAndAssignImages($item);
                break;

            case 'clear_images':
                $this->clearItemImages($item);
                break;

            case 'reorganize':
                $this->reorganizeItemImages($item);
                break;
        }
    }

    /**
     * 📤 Upload and assign images to item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function uploadAndAssignImages(Model $item): void
    {
        if (empty($this->imageFiles)) {
            return;
        }

        $imageUploadService = app(ImageUploadService::class);
        $folder = $this->targetType === 'products' ? 'products' : 'variants';

        foreach ($this->imageFiles as $index => $imageFile) {
            // Determine if this should be primary image
            $isPrimary = $this->imageData['assignment_type'] === 'primary' && $index === 0;

            if ($this->imageData['replace_existing'] && $isPrimary) {
                // Clear existing primary image
                $this->clearPrimaryImage($item);
            }

            // Upload image using our service
            $image = $imageUploadService->upload($imageFile, [
                'folder' => $folder,
                'title' => $item->name ?? 'Bulk Upload Image',
                'tags' => ['bulk-upload']
            ]);

            // Attach image to item
            $image->attachTo($item, [
                'is_primary' => $isPrimary,
                'sort_order' => $index
            ]);
        }
    }

    /**
     * 🗑️ Clear all images for item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function clearItemImages(Model $item): void
    {
        $imageUploadService = app(ImageUploadService::class);
        
        if ($this->targetType === 'products') {
            // Clear all images attached to this product
            $images = $item->images()->get();
            foreach ($images as $image) {
                $image->detachFrom($item);
                
                // If image is not attached to anything else, delete it
                if (!$image->isAttached()) {
                    $imageUploadService->deleteImage($image);
                }
            }
        } else {
            // Clear all images attached to this variant
            $images = $item->images()->get();
            foreach ($images as $image) {
                $image->detachFrom($item);
                
                // If image is not attached to anything else, delete it
                if (!$image->isAttached()) {
                    $imageUploadService->deleteImage($image);
                }
            }
        }
    }

    /**
     * 🔄 Reorganize existing images (future enhancement)
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function reorganizeItemImages(Model $item): void
    {
        // Future implementation for image reorganization
        // Could include reordering, setting new primary, etc.
        // For now, we can shuffle the sort orders
        $images = $item->images()->orderBy('sort_order')->get();
        foreach ($images as $index => $image) {
            $item->images()->updateExistingPivot($image->id, ['sort_order' => $index]);
        }
    }

    /**
     * 🧹 Clear primary image for item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function clearPrimaryImage(Model $item): void
    {
        // Remove primary flag from all current images
        $item->images()->updateExistingPivot(
            $item->images()->pluck('image_id')->toArray(),
            ['is_primary' => false]
        );
    }

    /**
     * 👁️ Handle file upload and preview generation
     */
    public function updatedImageFiles(): void
    {
        $this->validate([
            'imageFiles.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        // Generate previews for uploaded files
        $this->imagePreviews = [];

        if (is_array($this->imageFiles)) {
            foreach ($this->imageFiles as $index => $file) {
                // Create temporary URL for preview
                try {
                    $this->imagePreviews[(string) $index] = method_exists($file, 'temporaryUrl')
                        ? $file->temporaryUrl()
                        : 'No preview available';
                } catch (\Exception $e) {
                    $this->imagePreviews[(string) $index] = 'Preview error';
                }
            }
        }
    }

    /**
     * 🗑️ Remove uploaded file
     */
    public function removeUploadedFile(int $index): void
    {
        if (is_array($this->imageFiles) && isset($this->imageFiles[$index])) {
            unset($this->imageFiles[$index]);
            unset($this->imagePreviews[(string) $index]);

            // Reindex arrays
            $this->imageFiles = array_values($this->imageFiles);

            // Reindex imagePreviews array properly
            $reindexedPreviews = [];
            $index = 0;
            foreach ($this->imagePreviews as $preview) {
                $reindexedPreviews[(string) $index] = $preview;
                $index++;
            }
            $this->imagePreviews = $reindexedPreviews;
        }
    }

    /**
     * 📊 Get count of uploaded files
     */
    public function getUploadedFilesCountProperty(): int
    {
        return is_array($this->imageFiles) ? count($this->imageFiles) : 0;
    }

    /**
     * 📈 Get estimated storage size
     */
    public function getEstimatedStorageSizeProperty(): string
    {
        if (! is_array($this->imageFiles) || empty($this->imageFiles)) {
            return '0 MB';
        }

        $totalSize = 0;
        foreach ($this->imageFiles as $file) {
            $totalSize += $file->getSize();
        }

        // Convert to MB
        $sizeInMB = $totalSize / (1024 * 1024);

        return number_format($sizeInMB, 2).' MB';
    }

    /**
     * 🎨 Render the bulk image operation component
     */
    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.bulk-operations.bulk-image-operation');
    }
}
