<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
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
    public function execute(Image $image): Image
    {
        return $this->executeWithBaseHandling(function () use ($image) {
            $refreshedImage = $this->imageUploadService->reprocessImage($image);
            
            return $refreshedImage;
        }, ['image_id' => $image->id, 'filename' => $image->filename]);
    }
}