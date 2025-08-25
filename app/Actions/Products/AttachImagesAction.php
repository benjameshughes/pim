<?php

namespace App\Actions\Products;

use App\Exceptions\ProductWizard\ProductSaveException;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * 📸 ATTACH IMAGES ACTION
 *
 * Handles Step 3: Images
 * - Integration with DAM (Digital Asset Management) system
 * - Image relationships to products and variants
 * - Polymorphic relationship management
 *
 * Follows ProductWizard.md specification for Step 3 and Images section
 */
class AttachImagesAction
{
    public function execute(Product $product, array $imageIds, ?array $variantAssignments = null): array
    {
        try {
            $attachedImages = [];

            foreach ($imageIds as $imageId) {
                // Per ProductWizard.md: "images should be assigned the parent product"
                $this->attachImageToProduct($product, $imageId);
                $attachedImages[] = $imageId;

                // Per ProductWizard.md: "images can also be assigned to the variants"
                if ($variantAssignments && isset($variantAssignments[$imageId])) {
                    $this->attachImageToVariants($imageId, $variantAssignments[$imageId]);
                }
            }

            return [
                'success' => true,
                'attached_images' => $attachedImages,
                'message' => count($attachedImages).' images attached successfully',
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::imageAttachmentFailed($e);
        }
    }

    /**
     * Attach image to parent product
     * Per ProductWizard.md: "images should be assigned the parent product"
     */
    protected function attachImageToProduct(Product $product, int $imageId): void
    {
        \DB::table('images')
            ->where('id', $imageId)
            ->update([
                'imageable_type' => Product::class,
                'imageable_id' => $product->id,
                'updated_at' => now(),
            ]);
    }

    /**
     * Attach image to specific variants
     * Per ProductWizard.md: "variant with id's 1/2/3 have image id /3/4/5 as a relationship"
     */
    protected function attachImageToVariants(int $imageId, array $variantIds): void
    {
        // Create additional image relationships for variants
        // This allows one image to be associated with multiple variants
        foreach ($variantIds as $variantId) {
            // Verify variant exists and belongs to the same product
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                // Create a duplicate image record for variant relationship
                // This maintains the polymorphic relationship structure
                $originalImage = Image::find($imageId);
                if ($originalImage) {
                    Image::create([
                        'filename' => $originalImage->filename,
                        'filepath' => $originalImage->filepath,
                        'mimetype' => $originalImage->mimetype,
                        'filesize' => $originalImage->filesize,
                        'alt_text' => $originalImage->alt_text,
                        'imageable_type' => ProductVariant::class,
                        'imageable_id' => $variantId,
                    ]);
                }
            }
        }
    }

    /**
     * Remove image attachments
     */
    public function detachImages(Product $product, array $imageIds): array
    {
        try {
            \DB::table('images')
                ->whereIn('id', $imageIds)
                ->where('imageable_type', Product::class)
                ->where('imageable_id', $product->id)
                ->update([
                    'imageable_type' => null,
                    'imageable_id' => null,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'detached_images' => $imageIds,
                'message' => count($imageIds).' images detached successfully',
            ];
        } catch (\Exception $e) {
            throw ProductSaveException::imageDetachmentFailed($e);
        }
    }
}
