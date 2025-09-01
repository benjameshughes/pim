<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\Image;
use App\Services\ImageUploadService;

class ReprocessImageAction extends BaseAction
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {
        parent::__construct();
    }

    /**
     * 🔄 REPROCESS IMAGE METADATA
     *
     * Refresh image dimensions and metadata from R2 storage
     */
    protected function performAction(...$params): array
    {
        $image = $params[0] ?? null;

        if (! $image instanceof Image) {
            throw new \InvalidArgumentException('First parameter must be an Image instance');
        }

        // Store original dimensions for comparison
        $originalDimensions = [
            'width' => $image->width,
            'height' => $image->height,
            'size' => $image->size,
        ];

        // Reprocess the image
        $refreshedImage = $this->imageUploadService->reprocessImage($image);

        // Log the activity
        Activity::log()
            ->by(auth()->id())
            ->processed($refreshedImage, [
                'original_dimensions' => $originalDimensions,
                'new_dimensions' => [
                    'width' => $refreshedImage->width,
                    'height' => $refreshedImage->height,
                    'size' => $refreshedImage->size,
                ],
                'dimensions_changed' => $originalDimensions['width'] !== $refreshedImage->width
                    || $originalDimensions['height'] !== $refreshedImage->height,
                'action_type' => 'metadata_refresh',
            ])
            ->description('Refreshed image metadata and dimensions');

        return $this->success('Image reprocessed successfully', [
            'image' => $refreshedImage,
            'dimensions_changed' => $originalDimensions['width'] !== $refreshedImage->width 
                || $originalDimensions['height'] !== $refreshedImage->height,
            'original_dimensions' => $originalDimensions,
            'new_dimensions' => [
                'width' => $refreshedImage->width,
                'height' => $refreshedImage->height,
                'size' => $refreshedImage->size,
            ],
        ]);
    }
}
