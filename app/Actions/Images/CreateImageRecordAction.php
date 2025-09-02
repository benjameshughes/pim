<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\Image;

/**
 * 💾 CREATE IMAGE RECORD ACTION
 *
 * Single responsibility: Create database record for uploaded image
 * Takes storage info and metadata, creates Image model
 */
class CreateImageRecordAction extends BaseAction
{
    protected function performAction(...$params): array
    {
        $storageData = $params[0] ?? [];
        $originalFilename = $params[1] ?? null;
        $mimeType = $params[2] ?? null;
        $metadata = $params[3] ?? [];

        // Validate required storage data
        if (empty($storageData['filename']) || empty($storageData['url'])) {
            throw new \InvalidArgumentException('Storage data must include filename and url');
        }

        if (!$originalFilename) {
            throw new \InvalidArgumentException('Original filename is required');
        }

        if (!$mimeType) {
            throw new \InvalidArgumentException('MIME type is required');
        }

        // Process tags to ensure array format
        $tags = $this->processTags($metadata['tags'] ?? []);
        
        // Add 'original' tag to all uploaded images
        $tags[] = 'original';

        // Create image record
        try {
            $image = Image::create([
                'filename' => $storageData['filename'],
                'original_filename' => $originalFilename,
                'url' => $storageData['url'],
                'size' => $storageData['size'] ?? 0,
                'width' => 0, // Will be filled by ExtractImageMetadataAction
                'height' => 0, // Will be filled by ExtractImageMetadataAction
                'mime_type' => $mimeType,
                'is_primary' => false,
                'sort_order' => $this->getNextSortOrder(),
                // Apply metadata
                'title' => $metadata['title'] ?? null,
                'alt_text' => $metadata['alt_text'] ?? null,
                'description' => $metadata['description'] ?? null,
                'folder' => $metadata['folder'] ?? null,
                'tags' => $tags,
            ]);

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create image record: ' . $e->getMessage());
        }

        return $this->success('Image record created successfully', [
            'image' => $image,
            'image_id' => $image->id,
            'uuid' => $image->uuid,
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

    /**
     * Get next sort order for image
     */
    protected function getNextSortOrder(): int
    {
        $maxSortOrder = Image::max('sort_order') ?? 0;
        return $maxSortOrder + 1;
    }
}