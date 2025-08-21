<?php

namespace App\Services\Shopify\API;

use App\Models\Product;
use App\Services\Shopify\ShopifyColorSeparationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🛍️ SHOPIFY PRODUCTS API
 *
 * Handles product and variant management with Shopify API.
 * Integrates color-based product separation for window treatments.
 */
class ProductsApi extends BaseShopifyApi
{
    protected ShopifyColorSeparationService $colorGroupingService;

    public function __construct(string $shopDomain)
    {
        parent::__construct($shopDomain);
        $this->colorGroupingService = new ShopifyColorSeparationService;
    }

    /**
     * 🎨 CREATE PRODUCTS WITH COLOR SEPARATION
     *
     * Takes a PIM product and creates multiple Shopify products (one per color)
     *
     * @param  Product  $product  PIM product with variants
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    public function createColorSeparatedProducts(Product $product, array $options = []): array
    {
        try {
            $colorGroups = $this->colorGroupingService->groupVariantsByColor($product);

            if ($colorGroups->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'No variants found for product',
                    'product_id' => $product->id,
                ];
            }

            $createdProducts = [];
            $errors = [];

            foreach ($colorGroups as $colorGroup) {
                $result = $this->createSingleColorProduct($product, $colorGroup, $options);

                if ($result['success']) {
                    $createdProducts[] = $result['shopify_product'];
                } else {
                    $errors[] = [
                        'color' => $colorGroup['color'],
                        'error' => $result['error'],
                    ];
                }
            }

            Log::info('Color-separated products created', [
                'shop_domain' => $this->shopDomain,
                'pim_product_id' => $product->id,
                'colors_processed' => $colorGroups->count(),
                'products_created' => count($createdProducts),
                'errors' => count($errors),
            ]);

            return [
                'success' => ! empty($createdProducts),
                'pim_product_id' => $product->id,
                'shopify_products' => $createdProducts,
                'errors' => $errors,
                'summary' => [
                    'total_colors' => $colorGroups->count(),
                    'products_created' => count($createdProducts),
                    'errors_count' => count($errors),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create color-separated products', [
                'shop_domain' => $this->shopDomain,
                'pim_product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'product_id' => $product->id,
            ];
        }
    }

    /**
     * 🎯 CREATE SINGLE COLOR PRODUCT
     *
     * Creates one Shopify product for a specific color group
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    protected function createSingleColorProduct(Product $product, array $colorGroup, array $options = []): array
    {
        $productInput = $this->buildProductInput($product, $colorGroup, $options);

        $mutation = <<<'GRAPHQL'
        mutation CreateProduct($input: ProductInput!) {
            productCreate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                    totalInventory
                    variants(first: 50) {
                        edges {
                            node {
                                id
                                title
                                sku
                                price
                                inventoryQuantity
                                barcode
                            }
                        }
                    }
                    metafields(first: 10) {
                        edges {
                            node {
                                namespace
                                key
                                value
                            }
                        }
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['input' => $productInput]);

        if ($result['success']) {
            $userErrors = $result['data']['productCreate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Product creation failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $shopifyProduct = $result['data']['productCreate']['product'];

            return [
                'success' => true,
                'shopify_product' => [
                    'id' => $shopifyProduct['id'],
                    'title' => $shopifyProduct['title'],
                    'handle' => $shopifyProduct['handle'],
                    'status' => $shopifyProduct['status'],
                    'color' => $colorGroup['color'],
                    'total_inventory' => $shopifyProduct['totalInventory'],
                    'variant_count' => count($shopifyProduct['variants']['edges']),
                    'variants' => $this->extractVariants($shopifyProduct['variants']['edges']),
                ],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error creating product',
        ];
    }

    /**
     * 🏗️ BUILD PRODUCT INPUT
     *
     * Builds Shopify ProductInput from PIM product and color group
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    protected function buildProductInput(Product $product, array $colorGroup, array $options = []): array
    {
        $variants = $colorGroup['variants'];
        $primaryVariant = $colorGroup['primary_variant'];

        // Build product input
        $input = [
            'title' => $colorGroup['shopify_product_title'],
            'handle' => $colorGroup['shopify_product_handle'],
            'descriptionHtml' => $this->buildProductDescription($product, $colorGroup),
            'productType' => $options['product_type'] ?? 'Window Treatments',
            'vendor' => $product->brand ?? 'Unknown',
            'tags' => $this->buildProductTags($product, $colorGroup),
            'status' => $options['status'] ?? 'DRAFT',
            'requiresSellingPlan' => false,
            'giftCard' => false,
            'collectionsToJoin' => $options['collections'] ?? [],
        ];

        // Add product options (Size)
        $input['options'] = [
            [
                'name' => 'Size',
                'values' => $colorGroup['size_options'],
            ],
        ];

        // Add variants
        $input['variants'] = $variants->map(function ($variant) {
            return $this->buildVariantInput($variant);
        })->toArray();

        // Add metafields
        $input['metafields'] = $this->buildProductMetafields($product, $colorGroup);

        // Add images if available
        if ($product->image_url) {
            $input['images'] = [
                [
                    'src' => $product->image_url,
                    'altText' => $colorGroup['shopify_product_title'],
                ],
            ];
        }

        // Add SEO fields
        $input['seo'] = [
            'title' => $colorGroup['shopify_product_title'],
            'description' => $this->buildSeoDescription($product, $colorGroup),
        ];

        return $input;
    }

    /**
     * 🎨 BUILD VARIANT INPUT
     *
     * Builds Shopify variant input from PIM variant data
     *
     * @param  array<string, mixed>  $variantData  Transformed variant data
     * @return array<string, mixed>
     */
    protected function buildVariantInput(array $variantData): array
    {
        $input = [
            'sku' => $variantData['sku'],
            'price' => $variantData['price'],
            'inventoryQuantity' => $variantData['inventory_quantity'],
            'weight' => $variantData['weight'],
            'weightUnit' => strtoupper($variantData['weight_unit']),
            'requiresShipping' => $variantData['requires_shipping'],
            'taxable' => $variantData['taxable'],
            'inventoryPolicy' => 'DENY', // Don't allow overselling
            'fulfillmentService' => 'manual',
        ];

        // Add barcode if available
        if (! empty($variantData['barcode'])) {
            $input['barcode'] = $variantData['barcode'];
        }

        // Add compare at price if available
        if (! empty($variantData['compare_at_price'])) {
            $input['compareAtPrice'] = $variantData['compare_at_price'];
        }

        // Add option values (Size)
        $input['optionValues'] = [
            [
                'name' => 'Size',
                'optionName' => 'Size',
            ],
        ];

        // Add metafields
        if (! empty($variantData['metafields'])) {
            $input['metafields'] = array_map(function ($metafield) {
                return [
                    'namespace' => $metafield['namespace'],
                    'key' => $metafield['key'],
                    'value' => $metafield['value'],
                    'type' => $metafield['type'],
                ];
            }, $variantData['metafields']);
        }

        return $input;
    }

    /**
     * 📝 BUILD PRODUCT DESCRIPTION
     *
     * Creates rich HTML description for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     */
    protected function buildProductDescription(Product $product, array $colorGroup): string
    {
        $description = "<h3>{$colorGroup['shopify_product_title']}</h3>";

        if ($product->description) {
            $description .= "<p>{$product->description}</p>";
        }

        // Add color information
        $description .= "<p><strong>Color:</strong> {$colorGroup['color']}</p>";

        // Add size information
        if (! empty($colorGroup['size_options'])) {
            $sizeList = implode(', ', $colorGroup['size_options']);
            $description .= "<p><strong>Available Sizes:</strong> {$sizeList}</p>";
        }

        // Add price range
        $description .= "<p><strong>Price Range:</strong> {$colorGroup['price_range']['formatted']}</p>";

        // Add features if available
        if ($product->features) {
            $description .= '<h4>Features:</h4>';
            $description .= '<ul>';
            foreach (explode(',', $product->features) as $feature) {
                $description .= '<li>'.trim($feature).'</li>';
            }
            $description .= '</ul>';
        }

        return $description;
    }

    /**
     * 🏷️ BUILD PRODUCT TAGS
     *
     * Creates tags for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @return array<string>
     */
    protected function buildProductTags(Product $product, array $colorGroup): array
    {
        $tags = [];

        // Add color tag
        $tags[] = "Color: {$colorGroup['color']}";

        // Add brand tag (only if brand is not empty)
        if ($product->brand && trim($product->brand) !== '') {
            $tags[] = "Brand: {$product->brand}";
        }

        // Add product type
        $tags[] = 'Window Treatments';
        $tags[] = 'Blinds';

        // Add PIM reference
        $tags[] = "PIM: {$product->id}";

        return $tags;
    }

    /**
     * 📋 BUILD PRODUCT METAFIELDS
     *
     * Creates metafields for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     * @return array<array<string, mixed>>
     */
    protected function buildProductMetafields(Product $product, array $colorGroup): array
    {
        $metafields = [];

        // PIM reference
        $metafields[] = [
            'namespace' => 'pim',
            'key' => 'product_id',
            'value' => (string) $product->id,
            'type' => 'single_line_text_field',
        ];

        // Color information
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'color',
            'value' => $colorGroup['color'],
            'type' => 'single_line_text_field',
        ];

        // Product statistics
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'variant_count',
            'value' => (string) $colorGroup['variants']->count(),
            'type' => 'number_integer',
        ];

        // Price range
        $metafields[] = [
            'namespace' => 'product',
            'key' => 'price_range',
            'value' => json_encode($colorGroup['price_range']),
            'type' => 'json',
        ];

        return $metafields;
    }

    /**
     * 🔍 BUILD SEO DESCRIPTION
     *
     * Creates SEO description for Shopify product
     *
     * @param  Product  $product  Original PIM product
     * @param  array<string, mixed>  $colorGroup  Color group data
     */
    protected function buildSeoDescription(Product $product, array $colorGroup): string
    {
        $description = "{$colorGroup['shopify_product_title']} - High-quality window treatments";

        if (! empty($colorGroup['size_options'])) {
            $description .= ' available in sizes: '.implode(', ', $colorGroup['size_options']);
        }

        $description .= ". {$colorGroup['price_range']['formatted']}.";

        return substr($description, 0, 160); // SEO limit
    }

    /**
     * 📊 EXTRACT VARIANTS
     *
     * Extracts variant data from GraphQL response
     *
     * @param  array<array<string, mixed>>  $variantEdges  GraphQL variant edges
     * @return array<array<string, mixed>>
     */
    protected function extractVariants(array $variantEdges): array
    {
        return array_map(function ($edge) {
            $variant = $edge['node'];

            return [
                'id' => $variant['id'],
                'title' => $variant['title'],
                'sku' => $variant['sku'],
                'price' => $variant['price'],
                'inventory_quantity' => $variant['inventoryQuantity'],
                'barcode' => $variant['barcode'],
            ];
        }, $variantEdges);
    }

    /**
     * 🔄 UPDATE PRODUCT
     *
     * Update existing Shopify product
     *
     * @param  string  $productId  Shopify product GID
     * @param  array<string, mixed>  $input  Product update input
     * @return array<string, mixed>
     */
    public function updateProduct(string $productId, array $input): array
    {
        $input['id'] = $productId;

        $mutation = <<<'GRAPHQL'
        mutation UpdateProduct($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                    updatedAt
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, ['input' => $input]);

        if ($result['success']) {
            $userErrors = $result['data']['productUpdate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Product update failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }
        }

        return $result;
    }

    /**
     * 🗑️ DELETE PRODUCT
     *
     * Delete Shopify product
     *
     * @param  string  $productId  Shopify product GID
     * @return array<string, mixed>
     */
    public function deleteProduct(string $productId): array
    {
        $mutation = <<<'GRAPHQL'
        mutation DeleteProduct($input: ProductDeleteInput!) {
            productDelete(input: $input) {
                deletedProductId
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($mutation, [
            'input' => ['id' => $productId],
        ]);

        if ($result['success']) {
            $userErrors = $result['data']['productDelete']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Product deletion failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }
        }

        return $result;
    }

    /**
     * 📋 GET PRODUCTS
     *
     * Get products from Shopify with pagination
     *
     * @param  array<string, mixed>  $options  Query options
     * @return array<string, mixed>
     */
    public function getProducts(array $options = []): array
    {
        $first = $options['first'] ?? 50;
        $after = isset($options['after']) ? ", after: \"{$options['after']}\"" : '';
        $query = isset($options['query']) ? ", query: \"{$options['query']}\"" : '';

        $graphqlQuery = <<<GRAPHQL
        query GetProducts {
            products(first: $first$after$query) {
                edges {
                    cursor
                    node {
                        id
                        title
                        handle
                        status
                        totalInventory
                        createdAt
                        updatedAt
                        variants(first: 10) {
                            edges {
                                node {
                                    id
                                    sku
                                    price
                                    inventoryQuantity
                                }
                            }
                        }
                        metafields(first: 5, namespace: "pim") {
                            edges {
                                node {
                                    namespace
                                    key
                                    value
                                }
                            }
                        }
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GRAPHQL;

        return $this->graphql($graphqlQuery);
    }

    /**
     * 📊 GET PRODUCT STATISTICS
     *
     * Get statistics about products in the shop
     *
     * @return array<string, mixed>
     */
    public function getProductStatistics(): array
    {
        $cacheKey = "shopify_product_stats_{$this->shopDomain}";

        return Cache::remember($cacheKey, 300, function () {
            $query = <<<'GRAPHQL'
            query GetProductStats {
                products(first: 1) {
                    edges {
                        node {
                            id
                        }
                    }
                    pageInfo {
                        hasNextPage
                    }
                }
                shop {
                    name
                    plan {
                        displayName
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($query);

            if ($result['success']) {
                // Note: This is a simplified version - you'd need additional queries for full stats
                return [
                    'success' => true,
                    'shop_name' => $result['data']['shop']['name'] ?? '',
                    'plan' => $result['data']['shop']['plan']['displayName'] ?? '',
                    // Add more statistics as needed
                ];
            }

            return $result;
        });
    }
}
