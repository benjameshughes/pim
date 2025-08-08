<?php

namespace App\Actions\Variants;

use App\Actions\Base\BaseAction;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Pricing;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use App\Traits\PerformanceMonitoring;
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
    use PerformanceMonitoring;

    /**
     * Execute variant creation with all integrations
     *
     * @param  mixed  ...$params  Variant data from VariantBuilder
     * @return ProductVariant Created variant with all relationships
     */
    public function execute(...$params): ProductVariant
    {
        $data = $params[0] ?? [];

        $this->startTimer('variant_creation_total');

        return DB::transaction(function () use ($data) {
            // Create the base variant
            $this->startTimer('variant_creation');
            $variantData = $this->extractVariantData($data);
            $variant = ProductVariant::create($variantData);
            $this->endTimer('variant_creation');

            // Handle barcode assignment if provided
            if (! empty($data['barcodes']) || ! empty($data['barcode_pool_id'])) {
                $this->startTimer('barcode_assignment');
                $this->handleBarcodeAssignment($variant, $data);
                $this->endTimer('barcode_assignment');
            }

            // Handle pricing if provided
            if (! empty($data['pricing']) || ! empty($data['marketplace_pricing'])) {
                $this->startTimer('pricing_creation');
                $this->handlePricing($variant, $data);
                $this->handleMarketplacePricing($variant, $data);
                $this->endTimer('pricing_creation');
            }

            // Handle variant images if provided
            if (! empty($data['variant_images'])) {
                $this->startTimer('image_processing');
                $this->handleVariantImages($variant, $data);
                $this->endTimer('image_processing');
            }

            // Handle attributes if provided
            if (! empty($data['attributes'])) {
                $this->startTimer('attribute_processing');
                $this->handleAttributes($variant, $data);
                $this->endTimer('attribute_processing');
            }

            // Optimized eager loading - load all relationships in one query
            $this->startTimer('relationship_loading');
            $result = $variant->fresh([
                'barcodes',
                'pricing',
                'attributes',
                'images',
                'product:id,name,sku',  // Only load needed product fields
            ]);
            $this->endTimer('relationship_loading');
            $this->endTimer('variant_creation_total');

            return $result;
        });
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
