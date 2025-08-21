<?php

namespace App\Builders;

use App\Actions\Variants\CreateVariantAction;
use App\Actions\Variants\CreateVariantWithBarcodeAction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeAssignmentService;
use Illuminate\Support\Facades\App;

/**
 * 🏗️ VARIANT BUILDER - FLUENT API FOR VARIANT CREATION
 *
 * Elegant builder pattern for creating product variants with:
 * - Automatic barcode assignment from GS1 pool
 * - Pricing integration
 * - Dimension handling
 * - Smart routing between simple and complex actions
 */
class VariantBuilder
{
    protected Product $product;

    protected array $data = [];

    protected bool $needsBarcodeAssignment = false;

    protected ?BarcodeAssignmentService $barcodeService = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
        $this->data['product_id'] = $product->id;
        $this->barcodeService = App::make(BarcodeAssignmentService::class);
    }

    /**
     * Set the variant SKU
     */
    public function sku(string $sku): self
    {
        $this->data['sku'] = $sku;

        return $this;
    }

    /**
     * Set the variant status
     */
    public function status(string $status): self
    {
        $this->data['status'] = $status;

        return $this;
    }

    /**
     * Set the stock level
     */
    public function stockLevel(int $stockLevel): self
    {
        $this->data['stock_level'] = $stockLevel;

        return $this;
    }

    /**
     * Set the variant color
     */
    public function color(string $color): self
    {
        $this->data['color'] = $color;

        return $this;
    }

    /**
     * Set window dimensions (for window shades)
     */
    public function windowDimensions(?string $width = null, ?string $drop = null): self
    {
        if ($width) {
            $this->data['width'] = $width;
        }
        if ($drop) {
            $this->data['drop'] = $drop;
        }

        return $this;
    }

    /**
     * Set retail price
     */
    public function retailPrice(float $price): self
    {
        $this->data['pricing'] = $this->data['pricing'] ?? [];
        $this->data['pricing']['retail_price'] = $price;

        return $this;
    }

    /**
     * Set cost price
     */
    public function costPrice(float $price): self
    {
        $this->data['pricing'] = $this->data['pricing'] ?? [];
        $this->data['pricing']['cost_price'] = $price;

        return $this;
    }

    /**
     * Set VAT inclusive price
     */
    public function vatInclusivePrice(float $price, float $vatRate = 20): self
    {
        $this->data['pricing'] = $this->data['pricing'] ?? [];
        $this->data['pricing']['retail_price'] = $price;
        $this->data['pricing']['vat_rate'] = $vatRate;
        $this->data['pricing']['price_excluding_vat'] = $price / (1 + ($vatRate / 100));

        return $this;
    }

    /**
     * Set package dimensions
     */
    public function dimensions(float $length, float $width, float $height, ?float $weight = null): self
    {
        $this->data['package_length'] = $length;
        $this->data['package_width'] = $width;
        $this->data['package_height'] = $height;
        if ($weight) {
            $this->data['package_weight'] = $weight;
        }

        return $this;
    }

    /**
     * Add marketplace-specific pricing
     */
    public function addMarketplacePricing(string $marketplace, float $retailPrice, ?float $costPrice = null): self
    {
        $this->data['marketplace_pricing'] = $this->data['marketplace_pricing'] ?? [];
        $this->data['marketplace_pricing'][$marketplace] = [
            'retail_price' => $retailPrice,
            'cost_price' => $costPrice,
            'currency' => 'GBP',
            'vat_rate' => 20,
        ];

        return $this;
    }

    /**
     * Set a primary barcode manually
     */
    public function primaryBarcode(string $barcode, string $type = 'EAN13'): self
    {
        $this->data['barcodes'] = $this->data['barcodes'] ?? [];
        $this->data['barcodes'][] = [
            'barcode' => $barcode,
            'type' => $type,
            'is_primary' => true,
        ];
        $this->needsBarcodeAssignment = false; // Don't auto-assign if manually set

        return $this;
    }

    /**
     * 🏊‍♂️ AUTO-ASSIGN BARCODE FROM POOL
     *
     * This is the key method that integrates with the GS1 barcode pool
     */
    public function assignFromPool(string $type = 'EAN13'): self
    {
        $this->needsBarcodeAssignment = true;
        $this->data['barcode_type'] = $type;

        return $this;
    }

    /**
     * Add variant attributes
     */
    public function attributes(array $attributes): self
    {
        $this->data['attributes'] = array_merge($this->data['attributes'] ?? [], $attributes);

        return $this;
    }

    /**
     * 🚀 EXECUTE THE BUILDER
     *
     * Create the variant using the appropriate action based on complexity
     */
    public function execute(): ProductVariant
    {
        // If we need barcode assignment, use the complex action
        if ($this->needsBarcodeAssignment) {
            // Assign barcode from pool before creating variant
            $nextBarcode = $this->barcodeService->getNextAvailableBarcode($this->data['barcode_type']);

            if (! $nextBarcode) {
                throw new \App\Exceptions\BarcodePoolExhaustedException(
                    "No available {$this->data['barcode_type']} barcodes in the pool.",
                    $this->data['barcode_type']
                );
            }

            // Add the barcode to the data for the action
            $this->data['barcodes'] = $this->data['barcodes'] ?? [];
            $this->data['barcodes'][] = [
                'barcode' => $nextBarcode->barcode,
                'type' => $nextBarcode->barcode_type,
                'is_primary' => true,
            ];

            // Store the pool barcode ID for assignment tracking
            $this->data['barcode_pool_id'] = $nextBarcode->id;

            $action = new CreateVariantWithBarcodeAction;
        } else {
            // Use simple action for basic variants
            $action = new CreateVariantAction;
        }

        // Execute the action and return the variant
        $result = $action->execute($this->data);

        if (! $result['success']) {
            throw new \RuntimeException('Failed to create variant: '.$result['message']);
        }

        return $result['variant'];
    }

    /**
     * Get the build data (for debugging)
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Check if this variant needs barcode assignment
     */
    public function needsBarcodeAssignment(): bool
    {
        return $this->needsBarcodeAssignment;
    }
}
