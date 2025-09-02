<?php

namespace App\Actions\Images;

use App\Facades\Activity;
use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 🗑️ DELETE IMAGE ACTION
 *
 * Handles complete image deletion with proper cleanup, transaction safety,
 * and comprehensive error handling following Laravel best practices
 */
class DeleteImageAction
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    /**
     * Execute image deletion with transaction safety
     *
     * @throws \Exception When deletion fails
     */
    public function execute(Image $image): void
    {
        DB::beginTransaction();

        try {
            // Capture relationships before deletion for activity logging
            $attachedProducts = $image->products()->get();
            $attachedVariants = $image->variants()->get();
            $variantCount = $image->variants()->exists() ?
                $this->getVariantsOfOriginal($image)->count() : 0;

            // Log the deletion attempt
            Log::info('Attempting to delete image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
            ]);

            // Detach from all relationships first
            $this->detachFromAllRelationships($image);

            // If this is an original image, also delete its variants
            if ($image->isOriginal()) {
                $variants = $this->getVariantsOfOriginal($image);
                foreach ($variants as $variant) {
                    $this->imageUploadService->deleteImage($variant);
                    Log::debug('Deleted variant image', [
                        'variant_id' => $variant->id,
                        'variant_filename' => $variant->filename,
                        'original_id' => $image->id,
                    ]);
                }
                Log::info('Deleted ' . $variants->count() . ' variants for original image', [
                    'original_id' => $image->id,
                    'variants_deleted' => $variants->count(),
                ]);
            }

            // Use the existing ImageUploadService for file and database cleanup
            $this->imageUploadService->deleteImage($image);

            // Log successful deletion activity
            // TODO: Re-enable after fixing description() method issue
            // Activity::log()
            //     ->by(auth()->id())
            //     ->deleted($image)
            //     ->with([
            //         'attached_products_count' => $attachedProducts->count(),
            //         'attached_variants_count' => $attachedVariants->count(),
            //         'had_variants' => $variantCount > 0,
            //         'variants_count' => $variantCount,
            //         'file_size' => $image->size,
            //         'dimensions' => [
            //             'width' => $image->width,
            //             'height' => $image->height,
            //         ],
            //     ])
            //     ->description("Deleted image '{$image->display_title}' and cleaned up attachments");

            DB::commit();

            // Log successful deletion
            Log::info('Image deleted successfully', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'user_id' => auth()->id(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Log the error
            Log::error('Failed to delete image', [
                'image_id' => $image->id,
                'filename' => $image->filename,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            throw new \Exception(
                "Failed to delete image '{$image->display_title}': {$e->getMessage()}"
            );
        }
    }

    /**
     * Get variants of the original image using DAM system
     */
    protected function getVariantsOfOriginal(Image $image): \Illuminate\Database\Eloquent\Collection
    {
        // If this is already a variant, get its original first
        $originalId = $image->isVariant() ?
            $image->getOriginalImageId() ?? $image->id :
            $image->id;

        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$originalId}")
            ->get();
    }

    /**
     * Detach image from all relationships before deletion
     */
    protected function detachFromAllRelationships(Image $image): void
    {
        // Detach from products
        if ($image->products()->exists()) {
            $productCount = $image->products()->count();
            $image->products()->detach();
            Log::debug("Detached image from {$productCount} products", [
                'image_id' => $image->id,
            ]);
        }

        // Detach from variants
        if ($image->variants()->exists()) {
            $variantCount = $image->variants()->count();
            $image->variants()->detach();
            Log::debug("Detached image from {$variantCount} variants", [
                'image_id' => $image->id,
            ]);
        }
    }
}
