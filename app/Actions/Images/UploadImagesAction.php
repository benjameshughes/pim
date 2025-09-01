<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Log;

/**
 * 📤 UPLOAD IMAGES ACTION
 *
 * Handles multiple image uploads with metadata using proper Action pattern
 * Integrates with ImageUploadService for consistent file handling
 */
class UploadImagesAction extends BaseAction
{
    public function __construct(
        protected ImageUploadService $uploadService
    ) {
        parent::__construct();
    }

    protected function performAction(...$params): array
    {
        $files = $params[0] ?? [];
        $metadata = $params[1] ?? [];

        if (empty($files)) {
            throw new \InvalidArgumentException('Files array is required');
        }

        // Validate files are uploaded files
        foreach ($files as $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                throw new \InvalidArgumentException('All files must be UploadedFile instances');
            }
        }

        // Process metadata - ensure tags are array format
        $processedMetadata = [
            'title' => $metadata['title'] ?? null,
            'alt_text' => $metadata['alt_text'] ?? null,
            'description' => $metadata['description'] ?? null,
            'folder' => $metadata['folder'] ?? null,
            'tags' => $this->processTags($metadata['tags'] ?? []),
        ];

        // Debug logging for production
        if (app()->environment('production')) {
            Log::info('Image Upload Action Debug', [
                'disk_config' => config('filesystems.disks.images'),
                'file_count' => count($files),
                'has_r2_key' => !empty(config('filesystems.disks.images.key')),
                'has_r2_secret' => !empty(config('filesystems.disks.images.secret')),
                'has_r2_bucket' => !empty(config('filesystems.disks.images.bucket')),
                'metadata' => $processedMetadata,
            ]);
        }

        // Upload using the service
        $uploadedImages = $this->uploadService->uploadMultiple($files, $processedMetadata);

        return $this->success('Images uploaded successfully', [
            'uploaded_images' => $uploadedImages,
            'upload_count' => count($uploadedImages),
            'metadata_applied' => $processedMetadata,
        ]);
    }

    /**
     * Process tags into proper array format
     */
    protected function processTags(mixed $tags): array
    {
        if (is_string($tags)) {
            return array_filter(array_map('trim', explode(',', $tags)));
        }

        if (is_array($tags)) {
            return array_filter($tags);
        }

        return [];
    }
}