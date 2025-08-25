<?php

namespace App\Services;

use App\Models\Image;
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
     * Simple upload with metadata support
     */
    public function upload(UploadedFile $file, array $metadata = []): Image
    {
        $this->validateFile($file);

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$extension;

        // Store to R2
        $path = Storage::disk($this->disk)->putFileAs('', $file, $filename);
        $url = Storage::disk($this->disk)->url($path);

        // Create image record
        return Image::create([
            'filename' => $filename,
            'url' => $url,
            'size' => $file->getSize(),
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
    public function delete(Image $image): bool
    {
        // Delete from R2
        if ($image->filename) {
            Storage::disk($this->disk)->delete($image->filename);
        }

        // Delete from database
        return $image->delete();
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
