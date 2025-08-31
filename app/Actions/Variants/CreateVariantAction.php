<?php

namespace App\Actions\Variants;

use App\Actions\Base\BaseAction;
use App\Facades\Activity;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Create Product Variant Action
 *
 * Handles the creation of new product variants with validation and transaction safety.
 * Ensures data integrity and follows Laravel best practices.
 */
class CreateVariantAction extends BaseAction
{
    /**
     * Perform variant creation action
     *
     * @param  array  $data  Variant data
     * @return array Action result with created variant
     *
     * @throws InvalidArgumentException If required data is missing
     */
    protected function performAction(...$params): array
    {
        // Authorize creating variants
        $this->authorizeWithRole('create-variants', 'admin');

        $data = $params[0] ?? [];

        $this->validateVariantData($data);

        $variant = DB::transaction(function () use ($data) {
            // Create the variant
            $variant = ProductVariant::create($data);

            // Handle any additional post-creation logic
            $this->handlePostCreation($variant, $data);

            return $variant->fresh();
        });

        // 📝 Log variant creation with gorgeous detail
        $userName = auth()->user()?->name ?? 'System';
        $productName = $variant->product?->name ?? 'Unknown Product';

        Activity::log()
            ->by(auth()->id())
            ->created($variant)
            ->description("{$productName} variant {$variant->sku} created by {$userName} ({$variant->title})")
            ->with([
                'variant_sku' => $variant->sku,
                'variant_title' => $variant->title,
                'product_id' => $variant->product_id,
                'product_name' => $productName,
                'user_name' => $userName,
                'variant_data' => $data,
            ])
            ->save();

        // Return standardized array format while maintaining access to the variant
        return [
            'success' => true,
            'variant' => $variant,
            'product' => $variant, // For backward compatibility with builder expectations
            'message' => "Variant '{$variant->sku}' created successfully",
            'variant_id' => $variant->id,
        ];
    }

    /**
     * Validate variant data before creation
     *
     * @param  array  $data  Variant data to validate
     *
     * @throws InvalidArgumentException If validation fails
     */
    protected function validateVariantData(array $data): void
    {
        if (empty($data['product_id'])) {
            throw new InvalidArgumentException('Product ID is required');
        }

        if (empty($data['sku'])) {
            throw new InvalidArgumentException('Variant SKU is required');
        }

        // Validate product exists
        if (! Product::find($data['product_id'])) {
            throw new InvalidArgumentException('Product not found');
        }

        // Validate SKU uniqueness
        if (ProductVariant::where('sku', $data['sku'])->exists()) {
            throw new InvalidArgumentException('Variant with this SKU already exists');
        }

        // Validate status if provided
        if (isset($data['status']) && ! in_array($data['status'], ['draft', 'active', 'inactive', 'archived'])) {
            throw new InvalidArgumentException('Invalid variant status');
        }
    }

    /**
     * Handle post-creation logic
     *
     * @param  ProductVariant  $variant  Created variant
     * @param  array  $data  Original creation data
     */
    protected function handlePostCreation(ProductVariant $variant, array $data): void
    {
        // Handle initial attributes if provided
        if (! empty($data['attributes'])) {
            $this->setAttributes($variant, $data['attributes']);
        }

        // Handle initial pricing if provided
        if (! empty($data['pricing'])) {
            $this->setPricing($variant, $data['pricing']);
        }

        // Handle initial barcodes if provided
        if (! empty($data['barcodes'])) {
            $this->setBarcodes($variant, $data['barcodes']);
        }
    }

    /**
     * Set variant attributes
     *
     * @param  ProductVariant  $variant  Variant instance
     * @param  array  $attributes  Attribute data
     */
    protected function setAttributes(ProductVariant $variant, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $variant->setVariantAttributeValue($key, $value, 'string');
        }
    }

    /**
     * Set variant pricing
     *
     * @param  ProductVariant  $variant  Variant instance
     * @param  array  $pricing  Pricing data
     */
    protected function setPricing(ProductVariant $variant, array $pricing): void
    {
        $variant->pricing()->create([
            'retail_price' => $pricing['retail_price'] ?? null,
            'cost_price' => $pricing['cost_price'] ?? null,
            'sale_price' => $pricing['sale_price'] ?? null,
            'currency' => $pricing['currency'] ?? 'GBP',
        ]);
    }

    /**
     * Set variant barcodes
     *
     * @param  ProductVariant  $variant  Variant instance
     * @param  array  $barcodes  Barcode data
     */
    protected function setBarcodes(ProductVariant $variant, array $barcodes): void
    {
        foreach ($barcodes as $barcode) {
            $variant->barcodes()->create([
                'barcode' => $barcode['barcode'],
                'barcode_type' => $barcode['type'] ?? 'EAN13',
                'is_primary' => $barcode['is_primary'] ?? false,
            ]);
        }
    }
}
