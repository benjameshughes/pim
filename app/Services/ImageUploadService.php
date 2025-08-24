<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 🔥✨ IMAGE UPLOAD SERVICE - R2 CLOUD STORAGE ✨🔥
 *
 * Professional image upload service using UUID filenames and R2 storage
 * Supports both Product and ProductVariant image attachments
 */
class ImageUploadService
{
    protected string $disk = 'images'; // Use the configured R2 images disk

    protected int $maxFileSize = 10 * 1024 * 1024; // 10MB

    /** @var string[] */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** @var string[] */
    protected array $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * 🖼️ UPLOAD IMAGES TO PRODUCT
     * @param UploadedFile[] $files
     */
    public function uploadToProduct(Product $product, array $files): Collection
    {
        // Validate all files first
        foreach ($files as $file) {
            $this->validateFile($file);
        }
        
        $images = new Collection;

        foreach ($files as $file) {
            $image = $this->processUpload($file, $product);
            $images->push($image);
        }

        return $images;
    }

    /**
     * 🎨 UPLOAD IMAGES TO VARIANT
     * @param UploadedFile[] $files
     */
    public function uploadToVariant(ProductVariant $variant, array $files): Collection
    {
        // Validate all files first
        foreach ($files as $file) {
            $this->validateFile($file);
        }
        
        $images = new Collection;

        foreach ($files as $file) {
            $image = $this->processUpload($file, $variant);
            $images->push($image);
        }

        return $images;
    }

    /**
     * 📚 UPLOAD STANDALONE IMAGES (DAM)
     * 
     * Upload images without attaching to any model - for DAM library
     * @param UploadedFile[] $files
     * @param array<string, mixed> $metadata
     */
    public function uploadStandalone(array $files, array $metadata = []): Collection
    {
        // Validate all files first
        foreach ($files as $file) {
            $this->validateFile($file);
        }
        
        $images = new Collection;

        foreach ($files as $file) {
            $image = $this->processStandaloneUpload($file, $metadata);
            $images->push($image);
        }

        return $images;
    }

    /**
     * 📤 PROCESS STANDALONE FILE UPLOAD
     * @param array<string, mixed> $metadata
     */
    protected function processStandaloneUpload(UploadedFile $file, array $metadata = []): Image
    {
        // Generate UUID filename
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";

        // Store file to R2
        $path = Storage::disk($this->disk)->putFileAs('', $file, $filename, 'public');
        $url = $path ? Storage::disk($this->disk)->url($path) : '';

        // Create standalone Image record (not attached to any model)
        $image = Image::create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => $url,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_primary' => false, // Standalone images are not primary
            'sort_order' => 0,
            // DAM metadata
            'title' => $metadata['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'alt_text' => $metadata['alt_text'] ?? null,
            'description' => $metadata['description'] ?? null,
            'folder' => $metadata['folder'] ?? 'uncategorized',
            'tags' => $this->processTags($metadata['tags'] ?? []),
            'created_by_user_id' => auth()->id(),
        ]);

        return $image;
    }

    /**
     * 📤 PROCESS SINGLE FILE UPLOAD
     */
    protected function processUpload(UploadedFile $file, Model $model): Image
    {
        // Generate UUID filename
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        $filename = "{$uuid}.{$extension}";

        // Store file to R2
        $path = Storage::disk($this->disk)->putFileAs('', $file, $filename, 'public');
        $url = $path ? Storage::disk($this->disk)->url($path) : '';

        // Simple file size check only
        $fileSize = $file->getSize();

        // Determine sort order (next in sequence)
        $sortOrder = $model->images()->count();

        // Create Image record with DAM metadata
        $image = $model->images()->create([
            'filename' => $file->getClientOriginalName(),
            'path' => $path,
            'url' => $url,
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'is_primary' => $model->images()->count() === 0, // First image is primary
            'sort_order' => $sortOrder,
            // DAM metadata
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), // Default title from filename
            'folder' => $this->determineFolderForModel($model),
            'created_by_user_id' => auth()->id(),
        ]);

        return $image;
    }

    /**
     * 📁 DETERMINE FOLDER FOR MODEL
     * 
     * Auto-categorize images based on the model they're attached to
     */
    protected function determineFolderForModel(Model $model): string
    {
        return match (get_class($model)) {
            \App\Models\Product::class => 'products',
            \App\Models\ProductVariant::class => 'variants',
            default => 'general',
        };
    }

    /**
     * 🔍 SIMPLE FILE VALIDATION
     */
    protected function validateFile(UploadedFile $file): bool
    {
        // Basic validation - let Laravel handle the rest
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File too large');
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $this->allowedExtensions)) {
            throw new \InvalidArgumentException('Invalid file type');
        }

        return true;
    }

    /**
     * ⭐ SET PRIMARY IMAGE
     */
    public function setPrimaryImage(Model $model, int $imageId): bool
    {
        // Remove primary flag from all images
        $model->images()->update(['is_primary' => false]);

        // Set new primary image
        $image = $model->images()->find($imageId);
        if ($image) {
            $image->update(['is_primary' => true]);

            return true;
        }

        return false;
    }

    /**
     * 🗑️ DELETE IMAGE
     */
    public function deleteImage(Image $image): bool
    {
        // Delete file from R2 only if path exists
        if ($image->path) {
            Storage::disk($this->disk)->delete($image->path);
        }

        // Delete database record
        $image->delete();

        return true;
    }

    /**
     * 🏷️ PROCESS TAGS
     * Convert string or array tags to proper array format
     * @param mixed $tags
     * @return string[]
     */
    protected function processTags($tags): array
    {
        if (is_string($tags)) {
            return array_filter(
                array_map('trim', explode(',', $tags)), 
                fn($tag) => !empty($tag)
            );
        }
        
        if (is_array($tags)) {
            return array_filter($tags, fn($tag) => !empty($tag));
        }
        
        return [];
    }
}
