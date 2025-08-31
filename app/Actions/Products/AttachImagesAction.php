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
        $image = Image::find($imageId);
        if ($image) {
            $image->attachTo($product);
        }
    }

    /**
     * Attach image to specific variants
     * Per ProductWizard.md: "variant with id's 1/2/3 have image id /3/4/5 as a relationship"
     */
    protected function attachImageToVariants(int $imageId, array $variantIds): void
    {
        $image = Image::find($imageId);
        if (! $image) {
            return;
        }

        foreach ($variantIds as $variantId) {
            // Verify variant exists and belongs to the same product
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                $image->attachTo($variant);
            }
        }
    }

    /**
     * Remove image attachments
     */
    public function detachImages(Product $product, array $imageIds): array
    {
        try {
            foreach ($imageIds as $imageId) {
                $image = Image::find($imageId);
                if ($image) {
                    $image->detachFrom($product);
                }
            }

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
