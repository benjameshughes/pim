<?php

namespace App\Extractors;

use App\Models\Image;

class ImageExtractor
{
    public static function extract(Image $image): array
    {
        return [
            'id' => $image->id,
            'type' => 'Image',
            'name' => $image->display_title,
            'title' => $image->title,
            'filename' => $image->original_filename ?: $image->filename,
            'original_filename' => $image->original_filename,
            'uuid_filename' => $image->filename,
            'size' => $image->size,
            'mime_type' => $image->mime_type,
            'folder' => $image->folder,
            'width' => $image->width,
            'height' => $image->height,
            'variant_type' => $image->getVariantType(),
            'is_variant' => $image->isVariant(),
        ];
    }
}