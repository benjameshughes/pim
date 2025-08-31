<?php

namespace App\Actions\Products;

use App\Actions\Base\BaseAction;
use App\Models\Product;
use App\Traits\WithActivityLogs;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Delete Product Action
 *
 * Handles safe deletion of products with cascading cleanup.
 * Manages related data removal and maintains referential integrity.
 */
class DeleteProductAction extends BaseAction
{
    use WithActivityLogs;

    /**
     * Validate parameters before execution
     */
    protected function validate(array $params): bool
    {
        if (count($params) < 1) {
            throw new InvalidArgumentException('Product instance is required as first parameter');
        }

        if (! $params[0] instanceof Product) {
            throw new InvalidArgumentException('First parameter must be a Product instance');
        }

        return true;
    }

    /**
     * Perform the product deletion action
     */
    protected function performAction(...$params): array
    {
        // Authorize deleting products
        $this->authorizeWithRole('delete-products', 'admin');

        $product = $params[0];
        $forceDelete = $params[1] ?? false;

        // Log the deletion before it happens (capture product data)
        $this->logDeleted($product, sprintf(
            'Product deleted with %d variants and %d images',
            $product->variants()->count(),
            count($product->images ?? [])
        ));

        // Handle pre-deletion cleanup
        $this->handlePreDeletion($product);

        // Delete related data first to maintain referential integrity
        $this->deleteRelatedData($product);

        // Delete the product itself
        $success = $forceDelete ? $product->forceDelete() : $product->delete();

        if ($success) {
            return $this->success(
                'Product deleted successfully',
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'force_delete' => $forceDelete,
                ]
            );
        } else {
            return $this->failure(
                'Failed to delete product',
                [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                ]
            );
        }
    }

    /**
     * Handle pre-deletion validation and preparation
     *
     * @param  Product  $product  Product to be deleted
     *
     * @throws InvalidArgumentException If product cannot be deleted
     */
    protected function handlePreDeletion(Product $product): void
    {
        // Check if product has variants that need special handling
        $variantCount = $product->variants()->count();
        if ($variantCount > 0) {
            // You might want to prevent deletion or handle variants differently
            // For now, we'll cascade delete them, but this could be configurable
        }

        // Additional business logic checks can be added here
        // For example, checking if product is part of active orders, etc.
    }

    /**
     * Delete related data in proper order
     *
     * @param  Product  $product  Product instance
     */
    protected function deleteRelatedData(Product $product): void
    {
        // Delete variants first (they depend on product)
        $this->deleteVariants($product);

        // Delete product images
        $this->deleteImages($product);

        // Delete product attributes
        $product->attributes()->delete();

        // Delete product metadata
        $product->metadata()->delete();

        // Detach categories (many-to-many relationship)
        $product->categories()->detach();

        // Handle any other related data
        $this->deleteAdditionalRelatedData($product);
    }

    /**
     * Delete product variants and their related data
     *
     * @param  Product  $product  Product instance
     */
    protected function deleteVariants(Product $product): void
    {
        foreach ($product->variants as $variant) {
            // Delete variant-specific data
            $variant->barcodes()->delete();
            $variant->pricing()->delete();
            $variant->attributes()->delete();
            $variant->marketplaceVariants()->delete();
            $variant->marketplaceBarcodes()->delete();

            // Delete variant images
            $this->deleteVariantImages($variant);

            // Set deletion reason for archiving (if implemented)
            $variant->deletion_reason = 'parent_product_deleted';
            $variant->deletion_notes = "Deleted due to parent product '{$product->name}' deletion";

            // Delete the variant
            $variant->delete();
        }
    }

    /**
     * Delete product images from storage
     *
     * @param  Product  $product  Product instance
     */
    protected function deleteImages(Product $product): void
    {
        // Delete images stored in the images JSON field
        if ($product->images && is_array($product->images)) {
            foreach ($product->images as $imagePath) {
                if (Storage::exists($imagePath)) {
                    Storage::delete($imagePath);
                }
            }
        }

        // Delete product images from relationship
        foreach ($product->productImages as $productImage) {
            if (Storage::exists($productImage->image_path)) {
                Storage::delete($productImage->image_path);
            }
            $productImage->delete();
        }

        // Delete all related images (including variant images)
        foreach ($product->allImages as $image) {
            if (Storage::exists($image->image_path)) {
                Storage::delete($image->image_path);
            }
            $image->delete();
        }
    }

    /**
     * Delete variant images from storage
     *
     * @param  \App\Models\ProductVariant  $variant  Variant instance
     */
    protected function deleteVariantImages($variant): void
    {
        // Delete images stored in the images JSON field
        if ($variant->images && is_array($variant->images)) {
            foreach ($variant->images as $imagePath) {
                if (Storage::exists($imagePath)) {
                    Storage::delete($imagePath);
                }
            }
        }

        // Delete variant images from relationship
        foreach ($variant->variantImages as $variantImage) {
            if (Storage::exists($variantImage->image_path)) {
                Storage::delete($variantImage->image_path);
            }
            $variantImage->delete();
        }

        // Delete media library images if using Spatie Media Library
        if (method_exists($variant, 'clearMediaCollection')) {
            $variant->clearMediaCollection('images');
        }
    }

    /**
     * Delete additional related data
     *
     * @param  Product  $product  Product instance
     */
    protected function deleteAdditionalRelatedData(Product $product): void
    {
        // Handle Shopify sync records
        if (class_exists('App\Models\ShopifyProductSync')) {
            $product->shopifySyncs()->delete();
        }

        // Handle any other marketplace sync records
        // This can be extended as needed

        // Handle any cached data
        $this->clearProductCache($product);
    }

    /**
     * Clear any cached data related to the product
     *
     * @param  Product  $product  Product instance
     */
    protected function clearProductCache(Product $product): void
    {
        // Clear common cache keys that might be related to this product
        $cacheKeys = [
            "product.{$product->id}",
            "product.slug.{$product->slug}",
            "product.{$product->parent_sku}",
        ];

        foreach ($cacheKeys as $key) {
            \Cache::forget($key);
        }

        // Clear any tags-based cache if implemented
        if (method_exists(\Cache::class, 'tags')) {
            \Cache::tags(['products', "product.{$product->id}"])->flush();
        }
    }
}
