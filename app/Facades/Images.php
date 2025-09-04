<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * 🎨✨ IMAGES FACADE - BEAUTIFUL FLUENT API ✨🎨
 *
 * The crown jewel! Beautiful facade for the three-tier image system:
 *
 * @method static \App\Services\ProductImageContext product(\App\Models\Product $product)
 * @method static \App\Services\VariantImageContext variant(\App\Models\ProductVariant $variant)  
 * @method static \App\Services\ColorImageContext color(\App\Models\Product $product, string $color)
 * @method static \App\Services\SmartImageContext smart(\App\Models\ProductVariant $variant)
 * @method static \App\Services\ProcessingImageContext process(\App\Models\Image $image)
 * @method static \App\Services\StorageImageContext storage()
 * @method static \App\Services\UploadImageContext upload(array $files)
 * @method static \App\Services\CreateImageContext create()
 * @method static \App\Services\ValidateImageContext validate($file)
 * @method static \App\Services\DeleteImageContext delete(\App\Models\Image $image)
 * @method static \App\Services\UpdateImageContext update(\App\Models\Image $image)
 * @method static \App\Actions\Images\Manager\AttachImageAction attachAction()
 * @method static \App\Actions\Images\Manager\DetachImageAction detachAction()
 * @method static \App\Actions\Images\Manager\SmartResolverAction smartAction()
 *
 * Usage Examples:
 * Images::product($product)->attach($imageId)->asPrimary();
 * Images::variant($variant)->detach(['1','2','3']);  
 * Images::color($product, 'Grey')->attach($images)->sorted();
 * Images::smart($variant)->display();
 * Images::storage()->upload($file)->withName('custom.jpg');
 * Images::process($image)->variants(['thumb', 'large']);
 */
class Images extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'image.manager';
    }
}