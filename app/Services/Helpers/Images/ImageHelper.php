<?php

namespace App\Services\Helpers\Images;

use App\Models\Image;
use App\Models\Product;

/**
 * ImageHelper
 *
 * Utilities for image selection/attachment use-cases.
 */
class ImageHelper
{
    /**
     * Attach primary image to product if missing.
     */
    public function ensurePrimary(Product $product, Image $image): void
    {
        if (! $product->primaryImage()) {
            $product->images()->attach($image->id, ['is_primary' => true, 'sort_order' => 0]);
        }
    }
}

