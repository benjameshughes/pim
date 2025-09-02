<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\Image;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * 📐 EXTRACT IMAGE METADATA ACTION
 *
 * Single responsibility: Extract dimensions and metadata from stored image
 * Downloads image temporarily, extracts info, updates database record
 */
class ExtractImageMetadataAction extends BaseAction
{
    protected function performAction(...$params): array
    {
        $image = $params[0] ?? null;

        if (!$image instanceof Image) {
            throw new \InvalidArgumentException('Image model instance is required');
        }

        if (!$image->filename || !$image->url) {
            throw new \InvalidArgumentException('Image must have filename and url');
        }

        // Download image content temporarily
        $imageContent = Storage::disk('images')->get($image->filename);

        if (!$imageContent) {
            throw new \RuntimeException('Failed to retrieve image from storage');
        }

        // Create temporary file for processing
        $tempFile = tmpfile();
        if (!$tempFile) {
            throw new \RuntimeException('Failed to create temporary file');
        }

        try {
            fwrite($tempFile, $imageContent);
            $tempPath = stream_get_meta_data($tempFile)['uri'];

            // Use Intervention Image for better metadata extraction
            $manager = new ImageManager(new Driver());
            $interventionImage = $manager->read($tempPath);

            $updates = [
                'width' => $interventionImage->width(),
                'height' => $interventionImage->height(),
                'mime_type' => $interventionImage->origin()->mediaType(),
            ];

            // Get file size from storage
            $size = Storage::disk('images')->size($image->filename);
            if ($size) {
                $updates['size'] = $size;
            }

            // Update image record
            $image->update($updates);
            $image->refresh();

        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to extract image metadata: ' . $e->getMessage());
        } finally {
            // Clean up temp file
            fclose($tempFile);
        }

        return $this->success('Image metadata extracted successfully', [
            'image' => $image,
            'width' => $image->width,
            'height' => $image->height,
            'size' => $image->size,
            'mime_type' => $image->mime_type,
        ]);
    }
}