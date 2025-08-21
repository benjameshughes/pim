<?php

namespace App\Actions\Variants;

use App\Actions\Base\BaseAction;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Pricing;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Create Variant With Full Integration Action
 *
 * Handles comprehensive variant creation including:
 * - Barcode pool integration
 * - Pricing across marketplaces
 * - Image processing and storage
 * - Attribute system integration
 */
class CreateVariantWithBarcodeAction extends BaseAction
{
    /**
     * Perform variant creation with all integrations
     *
     * @param  mixed  ...$params  Variant data from VariantBuilder
     * @return array Action result with created variant
     */
    protected function performAction(...$params): array
    {
        $data = $params[0] ?? [];

        $variant = DB::transaction(function () use ($data) {
            // Create the base variant
            $variantData = $this->extractVariantData($data);
            $variant = ProductVariant::create($variantData);

            // Handle barcode assignment if provided
            if (! empty($data['barcodes']) || ! empty($data['barcode_pool_id'])) {
                $this->handleBarcodeAssignment($variant, $data);
            }

            // Handle pricing if provided
            if (! empty($data['pricing']) || ! empty($data['marketplace_pricing'])) {
                $this->handlePricing($variant, $data);
                $this->handleMarketplacePricing($variant, $data);
            }

            // Handle variant images if provided
            if (! empty($data['variant_images'])) {
                $this->handleVariantImages($variant, $data);
            }

            // Handle attributes if provided
            if (! empty($data['attributes'])) {
                $this->handleAttributes($variant, $data);
            }

            // Optimized eager loading - load all relationships in one query
            $result = $variant->fresh([
                'barcodes',
                'pricing',
                'product:id,name,parent_sku',  // Only load needed product fields
            ]);

            return $result;
        });

        // Return standardized array format for BaseAction compatibility
        return $this->success("Variant '{$variant->sku}' created successfully", [
            'variant' => $variant,
            'product' => $variant, // For backward compatibility with builder expectations
            'variant_id' => $variant->id,
        ]);
    }

    /**
     * Extract core variant data for model creation
     *
     * @param  array  $data  Full data array
     * @return array Core variant data
     */
    protected function extractVariantData(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'product_id',
            'sku',
            'title',
            'color',
            'width',
            'drop',
            'price',
            'status',
            'stock_level',
            'images', // Legacy images field
            'package_length',
            'package_width',
            'package_height',
            'package_weight',
        ]));
    }

    /**
     * Handle barcode assignment from pool
     */
    protected function handleBarcodeAssignment(ProductVariant $variant, array $data): void
    {
        $barcodes = $data['barcodes'] ?? [];

        foreach ($barcodes as $barcodeData) {
            // Create barcode record
            Barcode::create([
                'product_variant_id' => $variant->id,
                'barcode' => $barcodeData['barcode'],
                'barcode_type' => $barcodeData['type'] ?? 'EAN13',
                'is_primary' => $barcodeData['is_primary'] ?? false,
            ]);

            // Update barcode pool if this came from pool assignment
            if (isset($data['barcode_pool_id'])) {
                BarcodePool::where('id', $data['barcode_pool_id'])
                    ->update([
                        'status' => 'assigned',
                        'assigned_to_variant_id' => $variant->id,
                        'assigned_at' => now(),
                        'date_first_used' => now(),
                    ]);
            }
        }
    }

    /**
     * Handle basic pricing creation
     */
    protected function handlePricing(ProductVariant $variant, array $data): void
    {
        $pricing = $data['pricing'] ?? null;

        if (! $pricing) {
            return;
        }

        Pricing::create([
            'product_variant_id' => $variant->id,
            'marketplace' => 'default',
            'retail_price' => $pricing['retail_price'] ?? null,
            'cost_price' => $pricing['cost_price'] ?? null,
            'currency' => $pricing['currency'] ?? 'GBP',
            'vat_percentage' => $pricing['vat_rate'] ?? 0,  // Default to 0 instead of null
            'vat_amount' => $pricing['vat_amount'] ?? 0,   // Default to 0 instead of null
            'vat_inclusive' => isset($pricing['price_excluding_vat']),  // If we have price_excluding_vat, it means we're VAT inclusive
        ]);
    }

    /**
     * Handle marketplace-specific pricing
     */
    protected function handleMarketplacePricing(ProductVariant $variant, array $data): void
    {
        $marketplacePricing = $data['marketplace_pricing'] ?? [];

        foreach ($marketplacePricing as $marketplace => $pricingData) {
            Pricing::create([
                'product_variant_id' => $variant->id,
                'marketplace' => $marketplace,
                'retail_price' => $pricingData['retail_price'],
                'cost_price' => $pricingData['cost_price'] ?? null,
                'currency' => $pricingData['currency'] ?? 'GBP',
                'vat_percentage' => $pricingData['vat_rate'] ?? 20,
                'vat_inclusive' => true,  // Marketplace pricing is typically VAT inclusive
            ]);
        }
    }

    /**
     * Handle variant image processing and storage
     */
    protected function handleVariantImages(ProductVariant $variant, array $data): void
    {
        $variantImages = $data['variant_images'] ?? [];
        $sortOrder = 1;

        foreach ($variantImages as $imageType => $images) {
            if ($imageType === 'gallery' && is_array($images)) {
                // Handle multiple gallery images
                foreach ($images as $image) {
                    $this->storeVariantImage($variant, $image, 'gallery', $sortOrder++);
                }
            } else {
                // Handle single images (main, swatch)
                $this->storeVariantImage($variant, $images, $imageType, $sortOrder++);
            }
        }
    }

    /**
     * Store individual variant image
     */
    protected function storeVariantImage(ProductVariant $variant, UploadedFile|string $image, string $imageType, int $sortOrder): void
    {
        if ($image instanceof UploadedFile) {
            $path = $image->store('variant-images', 'public');
        } else {
            $path = $image; // Assume it's already a stored path
        }

        ProductImage::create([
            'product_id' => null, // Variant-specific image
            'variant_id' => $variant->id,
            'image_path' => $path,
            'image_type' => $imageType,
            'alt_text' => $variant->product->name ?? 'Variant Image',
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Handle variant attributes using the attribute system
     */
    protected function handleAttributes(ProductVariant $variant, array $data): void
    {
        $attributes = $data['attributes'] ?? [];

        foreach ($attributes as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                // Complex attribute with metadata
                VariantAttribute::setValue(
                    $variant->id,
                    $key,
                    $value['value'],
                    $value['data_type'] ?? 'string',
                    $value['category'] ?? null
                );
            } else {
                // Simple key-value attribute
                VariantAttribute::setValue($variant->id, $key, $value, 'string');
            }
        }
    }
}
