<?php

namespace App\Services\Shopify\Builders\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Log;

/**
 * Builder for Shopify product data
 *
 * Provides a fluent API for building Shopify product payloads
 * from local Product models with all necessary transformations.
 */
class ShopifyProductBuilder
{
    private ?Product $product = null;

    private array $data = [];

    private array $variants = [];

    private array $options = [];

    private array $metafields = [];

    private array $images = [];

    private ?string $categoryId = null;

    private array $customOptions = [];

    /**
     * Set the source product
     */
    public function fromProduct(Product $product): static
    {
        $this->product = $product;

        // Set basic product data
        $this->data = [
            'title' => $product->name,
            'body_html' => $product->description ?? '',
            'vendor' => $product->vendor ?? config('app.name'),
            'product_type' => $product->product_type ?? 'Default',
            'status' => $product->status === 'active' ? 'active' : 'draft',
        ];

        // Build variants from product
        if ($product->variants) {
            $this->buildVariantsFromProduct($product);
        }

        // Add tags
        if ($product->tags && $product->tags->count() > 0) {
            $this->data['tags'] = $product->tags->pluck('name')->implode(', ');
        }

        return $this;
    }

    /**
     * Set product title
     */
    public function title(string $title): static
    {
        $this->data['title'] = $title;

        return $this;
    }

    /**
     * Set product description
     */
    public function description(string $description): static
    {
        $this->data['body_html'] = $description;

        return $this;
    }

    /**
     * Set vendor
     */
    public function vendor(string $vendor): static
    {
        $this->data['vendor'] = $vendor;

        return $this;
    }

    /**
     * Set product type
     */
    public function productType(string $type): static
    {
        $this->data['product_type'] = $type;

        return $this;
    }

    /**
     * Set product status
     */
    public function status(string $status): static
    {
        $this->data['status'] = in_array($status, ['active', 'draft', 'archived'])
            ? $status
            : 'draft';

        return $this;
    }

    /**
     * Add a variant
     */
    public function variant(array $variantData): static
    {
        $this->variants[] = $variantData;

        return $this;
    }

    /**
     * Add multiple variants
     */
    public function variants(array $variants): static
    {
        $this->variants = array_merge($this->variants, $variants);

        return $this;
    }

    /**
     * Add product option (e.g., Color, Size)
     */
    public function option(string $name, array $values = []): static
    {
        $option = ['name' => $name];

        if (! empty($values)) {
            $option['values'] = $values;
        }

        $this->options[] = $option;

        return $this;
    }

    /**
     * Add metafield
     */
    public function metafield(string $namespace, string $key, $value, string $type = 'single_line_text_field'): static
    {
        $this->metafields[] = [
            'namespace' => $namespace,
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'type' => $type,
        ];

        return $this;
    }

    /**
     * Add custom metafields
     */
    public function customMetafields(array $metafields): static
    {
        foreach ($metafields as $metafield) {
            if (isset($metafield['namespace']) && isset($metafield['key']) && isset($metafield['value'])) {
                $this->metafield(
                    $metafield['namespace'],
                    $metafield['key'],
                    $metafield['value'],
                    $metafield['type'] ?? 'single_line_text_field'
                );
            }
        }

        return $this;
    }

    /**
     * Set taxonomy category
     */
    public function category(string $categoryId): static
    {
        $this->categoryId = $categoryId;

        return $this;
    }

    /**
     * Add image
     */
    public function image(string $src, ?string $alt = null, ?int $position = null): static
    {
        $image = ['src' => $src];

        if ($alt) {
            $image['alt'] = $alt;
        }

        if ($position) {
            $image['position'] = $position;
        }

        $this->images[] = $image;

        return $this;
    }

    /**
     * Add multiple images
     */
    public function images(array $images): static
    {
        $this->images = array_merge($this->images, $images);

        return $this;
    }

    /**
     * Set tags
     */
    public function tags(array|string $tags): static
    {
        if (is_array($tags)) {
            $this->data['tags'] = implode(', ', $tags);
        } else {
            $this->data['tags'] = $tags;
        }

        return $this;
    }

    /**
     * Set SEO title
     */
    public function seoTitle(string $title): static
    {
        $this->metafield('global', 'title_tag', $title, 'single_line_text_field');

        return $this;
    }

    /**
     * Set SEO description
     */
    public function seoDescription(string $description): static
    {
        $this->metafield('global', 'description_tag', $description, 'multi_line_text_field');

        return $this;
    }

    /**
     * Apply custom options
     */
    public function withOptions(array $options): static
    {
        $this->customOptions = array_merge($this->customOptions, $options);

        // Apply known options
        if (isset($options['status'])) {
            $this->status($options['status']);
        }

        if (isset($options['category'])) {
            $this->category($options['category']);
        }

        if (isset($options['metafields'])) {
            $this->customMetafields($options['metafields']);
        }

        return $this;
    }

    /**
     * Build variants from product model
     */
    private function buildVariantsFromProduct(Product $product): void
    {
        $hasColors = false;
        $hasSizes = false;

        foreach ($product->variants as $variant) {
            $variantData = [
                'sku' => $variant->sku,
                'price' => (string) ($variant->price ?? '0.00'),
                'inventory_quantity' => $variant->stock_level ?? 0,
            ];

            // Add barcode if available
            if ($variant->barcode) {
                $variantData['barcode'] = $variant->barcode->barcode_number;
            }

            // Add options
            if ($variant->color) {
                $variantData['option1'] = $variant->color;
                $hasColors = true;
            }

            if ($variant->size) {
                $variantData['option2'] = $variant->size;
                $hasSizes = true;
            }

            // Add weight if available
            if ($variant->weight) {
                $variantData['weight'] = $variant->weight;
                $variantData['weight_unit'] = 'kg';
            }

            $this->variants[] = $variantData;
        }

        // Auto-create options based on variants
        if ($hasColors) {
            $colors = $product->variants->pluck('color')->filter()->unique()->values()->toArray();
            $this->option('Color', $colors);
        }

        if ($hasSizes) {
            $sizes = $product->variants->pluck('size')->filter()->unique()->values()->toArray();
            $this->option('Size', $sizes);
        }
    }

    /**
     * Build the final product data
     */
    public function build(): array
    {
        $productData = $this->data;

        // Add variants
        if (! empty($this->variants)) {
            $productData['variants'] = $this->variants;
        }

        // Add options
        if (! empty($this->options)) {
            $productData['options'] = $this->options;
        }

        // Add images
        if (! empty($this->images)) {
            $productData['images'] = $this->images;
        }

        // Add metafields if any
        if (! empty($this->metafields)) {
            $productData['metafields'] = $this->metafields;
        }

        // Add category for GraphQL
        if ($this->categoryId) {
            $productData['category'] = $this->categoryId;
        }

        // Merge any additional custom options
        foreach ($this->customOptions as $key => $value) {
            if (! in_array($key, ['status', 'category', 'metafields'])) {
                $productData[$key] = $value;
            }
        }

        Log::debug('Built Shopify product data', [
            'title' => $productData['title'] ?? 'Unknown',
            'variants_count' => count($productData['variants'] ?? []),
            'has_metafields' => ! empty($this->metafields),
            'has_category' => ! empty($this->categoryId),
        ]);

        return $productData;
    }

    /**
     * Build and wrap in 'product' key for REST API
     */
    public function buildForRest(): array
    {
        return ['product' => $this->build()];
    }

    /**
     * Build for GraphQL mutation
     */
    public function buildForGraphQL(): array
    {
        $data = $this->build();

        // GraphQL uses different field names
        if (isset($data['body_html'])) {
            $data['descriptionHtml'] = $data['body_html'];
            unset($data['body_html']);
        }

        if (isset($data['product_type'])) {
            $data['productType'] = $data['product_type'];
            unset($data['product_type']);
        }

        // GraphQL options structure is different
        if (isset($data['options'])) {
            $data['productOptions'] = $data['options'];
            unset($data['options']);
        }

        return $data;
    }

    /**
     * Static factory method
     */
    public static function create(): static
    {
        return new static;
    }

    /**
     * Create builder from product
     */
    public static function forProduct(Product $product): static
    {
        return (new static)->fromProduct($product);
    }
}
