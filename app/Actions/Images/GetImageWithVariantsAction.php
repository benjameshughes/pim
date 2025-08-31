<?php

namespace App\Actions\Images;

use App\Actions\Base\BaseAction;
use App\Models\Image;

/**
 * 🖼️ GET IMAGE WITH VARIANTS ACTION
 *
 * Retrieves an image with all its variants for gallery display
 * Handles both original images and variants, always returns the full family
 */
class GetImageWithVariantsAction extends BaseAction
{
    protected function performAction(...$params): array
    {
        $image = $params[0] ?? null;
        
        if (!$image instanceof Image) {
            throw new \InvalidArgumentException('First parameter must be an Image instance');
        }

        // Get the original image if this is a variant
        $originalImage = $image->isVariant() 
            ? Image::find($image->getOriginalImageId()) ?? $image
            : $image;

        // Get all variants of the original
        $variants = Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$originalImage->id}")
            ->orderBy('created_at', 'asc')
            ->get();

        // Determine which image is currently being viewed
        $currentImage = $image->isVariant() ? $image : $originalImage;
        
        // Create variant collection including original
        $allVariants = collect([$originalImage])
            ->merge($variants)
            ->keyBy('id');

        return $this->success('Image gallery data retrieved successfully', [
            'original_image' => $originalImage,
            'current_image' => $currentImage,
            'variants' => $variants,
            'all_variants' => $allVariants,
            'variant_count' => $variants->count(),
            'current_variant_type' => $currentImage->getVariantType(),
            'is_viewing_variant' => $image->isVariant(),
        ]);
    }
}