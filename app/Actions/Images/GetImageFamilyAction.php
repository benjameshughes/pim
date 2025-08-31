<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\Image;

class GetImageFamilyAction extends BaseAction
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 👪 GET IMAGE FAMILY (ORIGINAL + VARIANTS)
     *
     * Returns the original image and all its variants in one collection
     */
    protected function performAction(...$params): array
    {
        $image = $params[0] ?? null;

        if (! $image instanceof Image) {
            throw new \InvalidArgumentException('First parameter must be an Image instance');
        }

        // If this is a variant, get the original
        $originalImage = $image->isVariant()
            ? Image::find($image->getOriginalImageId()) ?? $image
            : $image;

        // Get all variants using DAM system
        $variants = Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$originalImage->id}")
            ->orderByRaw("
                CASE 
                WHEN JSON_CONTAINS(tags, '\"thumb\"') THEN 1
                WHEN JSON_CONTAINS(tags, '\"small\"') THEN 2
                WHEN JSON_CONTAINS(tags, '\"medium\"') THEN 3
                WHEN JSON_CONTAINS(tags, '\"large\"') THEN 4
                ELSE 5
                END
            ")
            ->get();

        // Build family structure
        $family = collect([$originalImage])->merge($variants);

        return $this->success('Image family retrieved successfully', [
            'original' => $originalImage,
            'variants' => $variants,
            'family' => $family,
            'active_image' => $image,
            'total_images' => $family->count(),
            'context' => $image->isVariant() ? 'variant' : 'original',
        ]);
    }
}
