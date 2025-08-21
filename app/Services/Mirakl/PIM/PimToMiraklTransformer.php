<?php

namespace App\Services\Mirakl\PIM;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * 🔄 PIM TO MIRAKL TRANSFORMER
 *
 * Pure data transformation class that converts PIM data to Mirakl format.
 * No API calls, no business logic - just clean data mapping.
 */
class PimToMiraklTransformer
{
    /**
     * 🏗️ TRANSFORM PRODUCTS FOR MIRAKL
     *
     * Converts PIM Product/ProductVariant data to Mirakl format
     *
     * @param  Collection<Product>  $products
     * @return array<string, mixed>
     */
    public function transformProducts(Collection $products, string $operator): array
    {
        return $products->map(function (Product $product) use ($operator) {
            return $this->transformSingleProduct($product, $operator);
        })->toArray();
    }

    /**
     * 🎯 TRANSFORM SINGLE PRODUCT
     *
     * @return array<string, mixed>
     */
    protected function transformSingleProduct(Product $product, string $operator): array
    {
        $baseData = $this->extractProductBase($product);

        // Transform variants for this product
        $variants = $product->variants->map(function (ProductVariant $variant) use ($operator, $baseData) {
            return $this->transformVariant($variant, $operator, $baseData);
        })->toArray();

        return [
            'product' => $baseData,
            'variants' => $variants,
            'operator' => $operator,
        ];
    }

    /**
     * 📋 EXTRACT PRODUCT BASE DATA
     *
     * @return array<string, mixed>
     */
    protected function extractProductBase(Product $product): array
    {
        return [
            'id' => $product->id,
            'parent_sku' => $product->parent_sku,
            'name' => $product->name,
            'description' => $product->description,
            'brand' => $product->brand,
            'status' => $product->status->value ?? 'active',
            'image_url' => $product->image_url,
            'category_id' => $product->category_id,
            'meta_description' => $product->meta_description,
            // Window treatment specific
            'length' => $product->length,
            'width' => $product->width,
            'depth' => $product->depth,
            'weight' => $product->weight,
            'retail_price' => $product->retail_price,
            'barcode' => $product->barcode,
        ];
    }

    /**
     * 🎨 TRANSFORM VARIANT TO MIRAKL FORMAT
     *
     * @param  array<string, mixed>  $productBase
     * @return array<string, mixed>
     */
    protected function transformVariant(ProductVariant $variant, string $operator, array $productBase): array
    {
        $variantData = [
            // Core variant data
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'external_sku' => $variant->external_sku,
            'title' => $variant->title,
            'price' => $variant->price,
            'stock_level' => $variant->stock_level,
            'status' => $variant->status ?? 'active',

            // Window treatment dimensions
            'color' => $variant->color,
            'width' => $variant->width,
            'drop' => $variant->drop,
            'max_drop' => $variant->max_drop,

            // Shipping dimensions
            'parcel_length' => $variant->parcel_length,
            'parcel_width' => $variant->parcel_width,
            'parcel_depth' => $variant->parcel_depth,
            'parcel_weight' => $variant->parcel_weight,

            // Parent product data
            'product_name' => $productBase['name'],
            'product_description' => $productBase['description'],
            'product_brand' => $productBase['brand'],
            'product_image_url' => $productBase['image_url'],
        ];

        // Add operator-specific transformations
        return $this->applyOperatorSpecificTransforms($variantData, $operator);
    }

    /**
     * ⚙️ APPLY OPERATOR-SPECIFIC TRANSFORMS
     *
     * Each operator may have specific requirements for data format
     *
     * @param  array<string, mixed>  $variantData
     * @return array<string, mixed>
     */
    protected function applyOperatorSpecificTransforms(array $variantData, string $operator): array
    {
        return match ($operator) {
            'freemans' => $this->transformForFreemans($variantData),
            'bq' => $this->transformForBQ($variantData),
            'debenhams' => $this->transformForDebenhams($variantData),
            default => $variantData,
        };
    }

    /**
     * 🏬 FREEMANS-SPECIFIC TRANSFORMS
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function transformForFreemans(array $data): array
    {
        // Add Freemans-specific field transformations
        $data['category_code'] = 'H02'; // Default Freemans category
        $data['logistic_class'] = 'DL';
        $data['leadtime_to_ship'] = 2;
        $data['state'] = '11'; // Default state

        // Transform title for Freemans format
        if ($data['color'] && $data['width'] && $data['drop']) {
            $data['freemans_title'] = "{$data['product_name']} - {$data['color']} - {$data['width']}cm x {$data['drop']}cm";
        }

        return $data;
    }

    /**
     * 🏪 B&Q-SPECIFIC TRANSFORMS
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function transformForBQ(array $data): array
    {
        // Add B&Q-specific field transformations
        $data['eco_compliance'] = true;
        $data['category_mapping'] = 'home-garden'; // Default B&Q category

        // B&Q may require different title format
        if ($data['color'] && $data['width']) {
            $data['bq_title'] = "{$data['product_name']} ({$data['color']}) - {$data['width']}cm";
        }

        return $data;
    }

    /**
     * 👗 DEBENHAMS-SPECIFIC TRANSFORMS
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function transformForDebenhams(array $data): array
    {
        // Add Debenhams-specific field transformations
        $data['performance_metrics'] = true;
        $data['category_default'] = 'home-curtains';

        // Debenhams may require color variations format
        if ($data['color']) {
            $data['color_variation'] = strtoupper($data['color']);
        }

        return $data;
    }

    /**
     * 🎨 GENERATE MIRAKL CSV ROW
     *
     * Convert transformed data to CSV row format
     *
     * @param  array<string, mixed>  $transformedData
     * @param  array<string, mixed>  $fieldMapping
     * @return array<string, mixed>
     */
    public function toCsvRow(array $transformedData, array $fieldMapping): array
    {
        $csvRow = [];

        foreach ($fieldMapping as $miraklField => $pimField) {
            $csvRow[$miraklField] = $transformedData[$pimField] ?? '';
        }

        return $csvRow;
    }

    /**
     * 📊 GET FIELD MAPPING FOR OPERATOR
     *
     * Returns PIM field → Mirakl field mapping
     *
     * @return array<string, string>
     */
    public function getFieldMapping(string $operator): array
    {
        return match ($operator) {
            'freemans' => $this->getFreemansFieldMapping(),
            'bq' => $this->getBQFieldMapping(),
            'debenhams' => $this->getDebenhamsFieldMapping(),
            default => $this->getDefaultFieldMapping(),
        };
    }

    /**
     * 🏬 FREEMANS FIELD MAPPING
     *
     * @return array<string, string>
     */
    protected function getFreemansFieldMapping(): array
    {
        return [
            'product-sku' => 'sku',
            'product-title' => 'freemans_title',
            'product-description' => 'product_description',
            'category-code' => 'category_code',
            'brand' => 'product_brand',
            'price' => 'price',
            'quantity' => 'stock_level',
            'logistic-class' => 'logistic_class',
            'leadtime-to-ship' => 'leadtime_to_ship',
            'state' => 'state',
            'color' => 'color',
            'width' => 'width',
            'drop' => 'drop',
        ];
    }

    /**
     * 🏪 B&Q FIELD MAPPING
     *
     * @return array<string, string>
     */
    protected function getBQFieldMapping(): array
    {
        return [
            'sku' => 'sku',
            'title' => 'bq_title',
            'description' => 'product_description',
            'category' => 'category_mapping',
            'brand' => 'product_brand',
            'price' => 'price',
            'stock' => 'stock_level',
            'eco_compliance' => 'eco_compliance',
            'color' => 'color',
            'width_cm' => 'width',
        ];
    }

    /**
     * 👗 DEBENHAMS FIELD MAPPING
     *
     * @return array<string, string>
     */
    protected function getDebenhamsFieldMapping(): array
    {
        return [
            'sku' => 'sku',
            'name' => 'title',
            'description' => 'product_description',
            'category' => 'category_default',
            'brand' => 'product_brand',
            'price' => 'price',
            'quantity' => 'stock_level',
            'color_variation' => 'color_variation',
            'width' => 'width',
            'length' => 'drop',
        ];
    }

    /**
     * 📋 DEFAULT FIELD MAPPING
     *
     * @return array<string, string>
     */
    protected function getDefaultFieldMapping(): array
    {
        return [
            'sku' => 'sku',
            'title' => 'title',
            'description' => 'product_description',
            'brand' => 'product_brand',
            'price' => 'price',
            'quantity' => 'stock_level',
            'color' => 'color',
            'width' => 'width',
            'drop' => 'drop',
        ];
    }
}
