<?php

namespace App\Services;

use App\Enums\ImageProcessingStatus;
use App\Jobs\GenerateImageVariantsJob;
use App\Jobs\ProcessImageJob;
use App\Models\Image;
use App\Exceptions\ImageReprocessException;
use App\Services\ImageProcessingTracker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 🖼️ SIMPLE IMAGE UPLOAD SERVICE - R2 STORAGE
 *
 * Clean, focused image upload service for R2 cloud storage
 * Handles upload with basic metadata and folder organization
 */
class ImageUploadService
{
    protected string $disk = 'images'; // R2 disk

    protected int $maxFileSize = 10 * 1024 * 1024; // 10MB

    /** @var string[] */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * 📤 UPLOAD SINGLE IMAGE
     *
     * Upload with optional background processing
     */
    public function upload(UploadedFile $file, array $metadata = [], bool $async = false, bool $generateVariants = false): Image
    {
        $this->validateFile($file);

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$extension;

        // Store to R2
        $path = Storage::disk($this->disk)->putFileAs('', $file, $filename);
        $url = Storage::disk($this->disk)->url($path);

        if ($async) {
            // Create minimal image record for async processing
            $image = Image::create([
                'filename' => $filename,
                'url' => $url,
                'size' => $file->getSize(),
                'width' => 0, // Will be filled by job
                'height' => 0, // Will be filled by job
                'mime_type' => $file->getMimeType(),
                'is_primary' => false,
                'sort_order' => 0,
                // Apply metadata
                'title' => $metadata['title'] ?? null,
                'alt_text' => $metadata['alt_text'] ?? null,
                'description' => $metadata['description'] ?? null,
                'folder' => $metadata['folder'] ?? null,
                'tags' => $metadata['tags'] ?? [],
            ]);

            // Mark as pending and dispatch processing job
            app(ImageProcessingTracker::class)->setStatus($image, ImageProcessingStatus::PENDING);
            ProcessImageJob::dispatch($image);

            // Optionally generate variants
            if ($generateVariants) {
                GenerateImageVariantsJob::dispatch($image)->delay(now()->addSeconds(30));
            }

        } else {
            // Synchronous processing (existing behavior)
            $dimensions = $this->getImageDimensions($file);

            $image = Image::create([
                'filename' => $filename,
                'url' => $url,
                'size' => $file->getSize(),
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'mime_type' => $file->getMimeType(),
                'is_primary' => false,
                'sort_order' => 0,
                // Apply metadata
                'title' => $metadata['title'] ?? null,
                'alt_text' => $metadata['alt_text'] ?? null,
                'description' => $metadata['description'] ?? null,
                'folder' => $metadata['folder'] ?? null,
                'tags' => $metadata['tags'] ?? [],
            ]);
        }

        return $image;
    }

    /**
     * 📤 UPLOAD MULTIPLE IMAGES
     *
     * @param  UploadedFile[]  $files
     * @return Image[]
     */
    public function uploadMultiple(array $files, array $metadata = []): array
    {
        $images = [];

        foreach ($files as $file) {
            $images[] = $this->upload($file, $metadata);
        }

        return $images;
    }

    /**
     * 🗑️ DELETE IMAGE
     *
     * Remove from R2 and database
     */
    public function deleteImage(Image $image): bool
    {
        // Delete from R2
        if ($image->filename) {
            Storage::disk($this->disk)->delete($image->filename);
        }

        // Delete from database
        return $image->delete();
    }

    /**
     * 🗑️ DELETE IMAGE (Legacy method name for backward compatibility)
     *
     * @deprecated Use deleteImage() instead
     */
    public function delete(Image $image): bool
    {
        return $this->deleteImage($image);
    }

    /**
     * 🔄 REPROCESS IMAGE METADATA
     *
     * Fetch and update image dimensions and metadata from R2 storage
     */
    public function reprocessImage(Image $image): Image
    {
        if (!$image->filename || !$image->url) {
            throw ImageReprocessException::invalidImage();
        }

        // Download image content temporarily
        $imageContent = Storage::disk($this->disk)->get($image->filename);
        
        if (!$imageContent) {
            throw ImageReprocessException::storageRetrievalFailed();
        }

        // Create temporary file for processing
        $tempFile = tmpfile();
        fwrite($tempFile, $imageContent);
        $tempPath = stream_get_meta_data($tempFile)['uri'];

        // Extract image dimensions and info
        $imageInfo = getimagesize($tempPath);
        
        if (!$imageInfo) {
            fclose($tempFile);
            throw ImageReprocessException::dimensionExtractionFailed();
        }

        $updates = [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
        ];
        
        // Get file size from storage
        $size = Storage::disk($this->disk)->size($image->filename);
        if ($size) {
            $updates['size'] = $size;
        }

        // Clean up temp file
        fclose($tempFile);

        // Update image record
        $image->update($updates);
        $image->refresh();

        return $image;
    }

    /**
     * 📏 GET IMAGE DIMENSIONS
     */
    protected function getImageDimensions(UploadedFile $file): array
    {
        try {
            $imageInfo = getimagesize($file->getPathname());
            
            return [
                'width' => $imageInfo[0] ?? 0,
                'height' => $imageInfo[1] ?? 0,
            ];
        } catch (\Exception $e) {
            // Fallback if dimensions can't be read
            return [
                'width' => 0,
                'height' => 0,
            ];
        }
    }

    /**
     * ✅ VALIDATE FILE
     */
    protected function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File too large. Maximum size is 10MB.');
        }

        if (! in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('Invalid file type. Only images are allowed.');
        }

        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Invalid file upload.');
        }
    }
}
