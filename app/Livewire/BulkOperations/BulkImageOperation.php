<?php

namespace App\Livewire\BulkOperations;

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

        foreach ($this->imageFiles as $index => $imageFile) {
            // Generate unique filename
            $filename = time().'_'.$index.'_'.$imageFile->getClientOriginalName();

            // Store in appropriate directory
            $directory = $this->targetType === 'products' ? 'products' : 'variants';
            $path = $imageFile->storeAs("images/{$directory}", $filename, 'public');

            // Determine if this should be primary image
            $isPrimary = $this->imageData['assignment_type'] === 'primary' && $index === 0;

            if ($this->imageData['replace_existing'] && $isPrimary) {
                // Clear existing primary image
                $this->clearPrimaryImage($item);
            }

            // Create image record based on target type
            $this->createImageRecord($item, $path, $isPrimary);
        }
    }

    /**
     * 🗑️ Clear all images for item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function clearItemImages(Model $item): void
    {
        if ($this->targetType === 'products' && method_exists($item, 'productImages')) {
            // Clear product images via relationship
            try {
                $images = $item->productImages()->get();
                foreach ($images as $image) {
                    if ($image->image_path) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    $image->delete();
                }
            } catch (\Exception $e) {
                // Silently continue if relationship doesn't exist
            }

            // Clear primary image field if property exists
            if ($item->isFillable('primary_image')) {
                $item->update(['primary_image' => null]);
            }
        } else {
            // Clear variant images
            if ($item->image_url) {
                $imagePath = str_replace('/storage/', '', $item->image_url);
                Storage::disk('public')->delete($imagePath);
            }

            $item->update(['image_url' => null]);
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
    }

    /**
     * 🧹 Clear primary image for item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function clearPrimaryImage(Model $item): void
    {
        if ($this->targetType === 'products' && $item->isFillable('primary_image')) {
            $primaryImage = $item->getAttribute('primary_image');
            if ($primaryImage) {
                Storage::disk('public')->delete($primaryImage);
                $item->update(['primary_image' => null]);
            }
        } else {
            if ($item->image_url) {
                $imagePath = str_replace('/storage/', '', $item->image_url);
                Storage::disk('public')->delete($imagePath);
                $item->update(['image_url' => null]);
            }
        }
    }

    /**
     * 📝 Create image record for item
     *
     * @param  \App\Models\Product|\App\Models\ProductVariant  $item
     */
    private function createImageRecord(Model $item, string $path, bool $isPrimary): void
    {
        $fullPath = '/storage/'.$path;

        if ($this->targetType === 'products') {
            // For products, we can have multiple images via ProductImage model
            // Also set primary_image field if this is primary
            if ($isPrimary && $item->isFillable('primary_image')) {
                $item->update(['primary_image' => $fullPath]);
            }

            // If ProductImage model exists, create record there too
            if (class_exists('App\\Models\\ProductImage') && method_exists($item, 'productImages')) {
                $item->productImages()->create([
                    'image_path' => $fullPath,
                    'alt_text' => $item->getAttribute('name') ?? 'Product Image',
                    'sort_order' => $isPrimary ? 0 : 999,
                    'is_primary' => $isPrimary,
                ]);
            }
        } else {
            // For variants, use the image_url field
            $item->update(['image_url' => $fullPath]);
        }
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
