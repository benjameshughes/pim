<?php

namespace App\Services\Shopify;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * 🚀 SHOPIFY GRAPHQL CLIENT
 *
 * Handles GraphQL operations for Shopify Admin API.
 * Supports product creation, updates, and bulk variant operations.
 * Built for 2025-07 API version with unlimited variants.
 */
class ShopifyGraphQLClient
{
    protected Client $httpClient;

    protected array $config;

    protected string $graphqlEndpoint;

    public function __construct()
    {
        $this->config = [
            'store_url' => config('services.shopify.store_url'),
            'access_token' => config('services.shopify.access_token'),
            'api_version' => config('services.shopify.api_version', '2025-07'),
        ];

        $this->graphqlEndpoint = "https://{$this->config['store_url']}/admin/api/{$this->config['api_version']}/graphql.json";

        $this->httpClient = new Client([
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Shopify-Access-Token' => $this->config['access_token'],
            ],
        ]);
    }

    /**
     * 🏭 CREATE PRODUCT WITH GRAPHQL
     *
     * Creates a product using GraphQL productCreate mutation
     *
     * @param  array  $productData  Product data for creation
     * @return array Result with success status and product data
     */
    public function createProduct(array $productData): array
    {
        Log::info('🚀 Creating product with GraphQL', [
            'title' => $productData['title'] ?? 'Unknown',
        ]);

        $mutation = '
            mutation productCreate($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
                        id
                        title
                        handle
                        status
                        createdAt
                        updatedAt
                        onlineStoreUrl
                        variants(first: 1) {
                            nodes {
                                id
                                title
                                sku
                                price
                            }
                        }
                        options {
                            id
                            name
                            position
                            values
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'input' => $this->buildProductInput($productData),
        ];

        $result = $this->executeQuery($mutation, $variables);

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data']['productCreate'] ?? null;

        if (! empty($data['userErrors'])) {
            return [
                'success' => false,
                'error' => 'GraphQL user errors: '.collect($data['userErrors'])->pluck('message')->implode(', '),
                'user_errors' => $data['userErrors'],
            ];
        }

        if (! isset($data['product'])) {
            return [
                'success' => false,
                'error' => 'Product creation failed - no product returned',
            ];
        }

        return [
            'success' => true,
            'product' => $data['product'],
            'message' => 'Product created successfully via GraphQL',
        ];
    }

    /**
     * 🔧 CREATE BULK VARIANTS
     *
     * Creates multiple variants for a product using productVariantsBulkCreate
     *
     * @param  string  $productId  Shopify product GID
     * @param  array  $variants  Array of variant data
     * @return array Result with success status and variant data
     */
    public function createBulkVariants(string $productId, array $variants): array
    {
        Log::info('📦 Creating bulk variants with GraphQL', [
            'product_id' => $productId,
            'variants_count' => count($variants),
        ]);

        $mutation = '
            mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkCreate(productId: $productId, variants: $variants) {
                    product {
                        id
                        title
                        totalVariants
                    }
                    productVariants {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                        selectedOptions {
                            name
                            value
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'productId' => $productId,
            'variants' => $this->buildVariantsInput($variants),
        ];

        $result = $this->executeQuery($mutation, $variables);

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data']['productVariantsBulkCreate'] ?? null;

        if (! empty($data['userErrors'])) {
            return [
                'success' => false,
                'error' => 'GraphQL user errors: '.collect($data['userErrors'])->pluck('message')->implode(', '),
                'user_errors' => $data['userErrors'],
            ];
        }

        return [
            'success' => true,
            'product' => $data['product'] ?? null,
            'variants' => $data['productVariants'] ?? [],
            'variants_created' => count($data['productVariants'] ?? []),
            'message' => 'Variants created successfully via GraphQL',
        ];
    }

    /**
     * 📋 GET PRODUCT
     *
     * Retrieves a product using GraphQL query
     *
     * @param  string  $productId  Shopify product GID or numeric ID
     * @return array Result with success status and product data
     */
    public function getProduct(string $productId): array
    {
        // Convert numeric ID to GID if needed
        if (is_numeric($productId)) {
            $productId = "gid://shopify/Product/{$productId}";
        }

        $query = '
            query getProduct($id: ID!) {
                product(id: $id) {
                    id
                    title
                    handle
                    status
                    totalVariants
                    createdAt
                    updatedAt
                    variants(first: 250) {
                        nodes {
                            id
                            title
                            sku
                            price
                            inventoryQuantity
                            selectedOptions {
                                name
                                value
                            }
                        }
                    }
                    options {
                        id
                        name
                        position
                        values
                    }
                }
            }
        ';

        $variables = ['id' => $productId];

        $result = $this->executeQuery($query, $variables);

        if (! $result['success']) {
            return $result;
        }

        $product = $result['data']['product'] ?? null;

        if (! $product) {
            return [
                'success' => false,
                'error' => 'Product not found',
            ];
        }

        return [
            'success' => true,
            'product' => $product,
        ];
    }

    /**
     * 🔥 DELETE PRODUCT
     *
     * Deletes a product using GraphQL mutation
     *
     * @param  string  $productId  Shopify product GID
     * @return array Result with success status
     */
    public function deleteProduct(string $productId): array
    {
        $mutation = '
            mutation productDelete($input: ProductDeleteInput!) {
                productDelete(input: $input) {
                    deletedProductId
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $variables = [
            'input' => [
                'id' => $productId,
            ],
        ];

        $result = $this->executeQuery($mutation, $variables);

        if (! $result['success']) {
            return $result;
        }

        $data = $result['data']['productDelete'] ?? null;

        if (! empty($data['userErrors'])) {
            return [
                'success' => false,
                'error' => 'GraphQL user errors: '.collect($data['userErrors'])->pluck('message')->implode(', '),
                'user_errors' => $data['userErrors'],
            ];
        }

        return [
            'success' => true,
            'deleted_product_id' => $data['deletedProductId'] ?? null,
            'message' => 'Product deleted successfully',
        ];
    }

    /**
     * 🔍 TEST GRAPHQL CONNECTION
     *
     * Tests GraphQL API connectivity
     */
    public function testConnection(): array
    {
        Log::info('🧪 Testing GraphQL connection');

        $query = '
            query {
                shop {
                    id
                    name
                    email
                    myshopifyDomain
                    plan {
                        displayName
                    }
                }
            }
        ';

        $result = $this->executeQuery($query);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'GraphQL connection test failed: '.($result['error'] ?? 'Unknown error'),
            ];
        }

        $shop = $result['data']['shop'] ?? null;

        if (! $shop) {
            return [
                'success' => false,
                'error' => 'GraphQL connection test failed: No shop data returned',
            ];
        }

        return [
            'success' => true,
            'message' => 'GraphQL connection successful',
            'shop_info' => [
                'id' => $shop['id'],
                'name' => $shop['name'],
                'domain' => $shop['myshopifyDomain'],
                'plan' => $shop['plan']['displayName'] ?? 'Unknown',
            ],
        ];
    }

    /**
     * 🚀 EXECUTE GRAPHQL QUERY
     *
     * Core method to execute GraphQL queries and mutations
     *
     * @param  string  $query  GraphQL query or mutation
     * @param  array  $variables  Query variables
     * @return array Result with success status and data
     */
    protected function executeQuery(string $query, array $variables = []): array
    {
        try {
            $payload = ['query' => $query];

            if (! empty($variables)) {
                $payload['variables'] = $variables;
            }

            $startTime = microtime(true);

            $response = $this->httpClient->post($this->graphqlEndpoint, [
                'json' => $payload,
            ]);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::debug('GraphQL query executed', [
                'duration_ms' => $duration,
                'status_code' => $response->getStatusCode(),
                'has_errors' => isset($data['errors']),
            ]);

            if (isset($data['errors'])) {
                $errors = collect($data['errors'])->pluck('message')->implode(', ');

                return [
                    'success' => false,
                    'error' => "GraphQL errors: {$errors}",
                    'graphql_errors' => $data['errors'],
                    'duration_ms' => $duration,
                ];
            }

            return [
                'success' => true,
                'data' => $data['data'] ?? null,
                'duration_ms' => $duration,
            ];

        } catch (\Exception $e) {
            Log::error('GraphQL query failed', [
                'error' => $e->getMessage(),
                'endpoint' => $this->graphqlEndpoint,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
            ];
        }
    }

    /**
     * Build product input for GraphQL mutation
     */
    protected function buildProductInput(array $productData): array
    {
        $input = [
            'title' => $productData['title'],
            'status' => strtoupper($productData['status'] ?? 'DRAFT'),
        ];

        // Add optional fields
        if (isset($productData['body_html'])) {
            $input['descriptionHtml'] = $productData['body_html'];
        }

        if (isset($productData['vendor'])) {
            $input['vendor'] = $productData['vendor'];
        }

        if (isset($productData['product_type'])) {
            $input['productType'] = $productData['product_type'];
        }

        if (isset($productData['tags']) && is_array($productData['tags'])) {
            $input['tags'] = $productData['tags'];
        }

        // Add product options
        if (isset($productData['options']) && is_array($productData['options'])) {
            $input['productOptions'] = array_map(function ($option) {
                return [
                    'name' => $option['name'],
                    'values' => array_map(function ($value) {
                        return ['name' => $value];
                    }, $option['values'] ?? []),
                ];
            }, $productData['options']);
        }

        return $input;
    }

    /**
     * Build variants input for GraphQL mutation
     */
    protected function buildVariantsInput(array $variants): array
    {
        return array_map(function ($variant) {
            $input = [
                'price' => (string) ($variant['price'] ?? '0.00'),
            ];

            if (isset($variant['sku'])) {
                $input['sku'] = $variant['sku'];
            }

            // Skip inventory setup for now to avoid location issues
            // if (isset($variant['inventory_quantity'])) {
            //     $input['inventoryQuantities'] = [
            //         [
            //             'availableQuantity' => (int) $variant['inventory_quantity'],
            //             'locationId' => $this->getDefaultLocationId(),
            //         ]
            //     ];
            // }

            // Add option values
            $optionValues = [];
            if (isset($variant['option1'])) {
                $optionValues[] = ['name' => $variant['option1']];
            }
            if (isset($variant['option2'])) {
                $optionValues[] = ['name' => $variant['option2']];
            }
            if (isset($variant['option3'])) {
                $optionValues[] = ['name' => $variant['option3']];
            }

            if (! empty($optionValues)) {
                $input['optionValues'] = $optionValues;
            }

            return $input;
        }, $variants);
    }

    /**
     * Get default location ID from Shopify
     */
    protected function getDefaultLocationId(): string
    {
        // Try to get from cache first
        $locationId = cache()->remember('shopify_default_location', 3600, function () {
            return $this->fetchDefaultLocationId();
        });

        return $locationId ?: 'gid://shopify/Location/1'; // Fallback
    }

    /**
     * Fetch default location ID from Shopify
     */
    protected function fetchDefaultLocationId(): ?string
    {
        $query = '
            query {
                locations(first: 1) {
                    nodes {
                        id
                        name
                        isActive
                    }
                }
            }
        ';

        $result = $this->executeQuery($query);

        if ($result['success'] &&
            isset($result['data']['locations']['nodes']) &&
            ! empty($result['data']['locations']['nodes'])) {

            return $result['data']['locations']['nodes'][0]['id'];
        }

        return null;
    }

    /**
     * Extract numeric ID from Shopify GID
     */
    public function extractNumericId(string $gid): ?int
    {
        if (preg_match('/\/(\d+)$/', $gid, $matches)) {
            return (int) $matches[1];
        }

        if (is_numeric($gid)) {
            return (int) $gid;
        }

        return null;
    }

    /**
     * Convert numeric ID to GID
     */
    public function buildGid(string $resource, int $id): string
    {
        return "gid://shopify/{$resource}/{$id}";
    }

    /**
     * 💰 UPDATE PRODUCT VARIANTS PRICING
     *
     * Bulk update pricing for product variants using GraphQL productVariantsBulkUpdate
     *
     * @param  array  $variantUpdates  Array of variant updates with GIDs and pricing
     * @return array Result with success status and updated variants
     */
    public function updateProductVariantsPricing(array $variantUpdates): array
    {
        Log::info('💰 Updating variants pricing with GraphQL', [
            'variants_count' => count($variantUpdates),
            'sample_update' => ! empty($variantUpdates) ? $variantUpdates[0] : null,
        ]);

        if (empty($variantUpdates)) {
            return [
                'success' => true,
                'message' => 'No variants to update',
                'updated_count' => 0,
            ];
        }

        // Build the variants input for bulk update
        $variantsInput = array_map(function ($update) {
            $input = [
                'id' => $update['id'], // Shopify variant GID
                'price' => (string) $update['price'], // Price as string
            ];

            // Add compareAtPrice if provided (for sale pricing)
            if (isset($update['compare_at_price']) && $update['compare_at_price']) {
                $input['compareAtPrice'] = (string) $update['compare_at_price'];
            }

            return $input;
        }, $variantUpdates);

        $mutation = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        price
                        compareAtPrice
                        sku
                        displayName
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        // Group variants by product ID (all variants should belong to same product for this method)
        $productId = $this->extractProductIdFromVariant($variantUpdates[0]['id']);

        $variables = [
            'productId' => $productId,
            'variants' => $variantsInput,
        ];

        $result = $this->executeQuery($mutation, $variables);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'GraphQL query failed: '.$result['error'],
                'updated_count' => 0,
            ];
        }

        $bulkUpdateData = $result['data']['productVariantsBulkUpdate'];

        // Check for user errors
        if (! empty($bulkUpdateData['userErrors'])) {
            $errors = array_map(fn ($error) => $error['message'], $bulkUpdateData['userErrors']);

            Log::error('❌ Shopify pricing update had user errors', [
                'errors' => $errors,
                'variants_attempted' => count($variantUpdates),
            ]);

            return [
                'success' => false,
                'error' => 'Shopify validation errors: '.implode(', ', $errors),
                'updated_count' => 0,
                'user_errors' => $bulkUpdateData['userErrors'],
            ];
        }

        $updatedVariants = $bulkUpdateData['productVariants'] ?? [];

        Log::info('✅ Successfully updated variant pricing', [
            'updated_count' => count($updatedVariants),
            'product_id' => $productId,
        ]);

        return [
            'success' => true,
            'message' => 'Successfully updated '.count($updatedVariants).' variant prices',
            'updated_count' => count($updatedVariants),
            'updated_variants' => $updatedVariants,
            'product_id' => $productId,
        ];
    }

    /**
     * 🔍 GET PRODUCT VARIANTS WITH PRICING
     *
     * Fetch product variants with their current pricing information
     *
     * @param  string  $productId  Shopify product GID
     * @return array Result with variants and pricing data
     */
    public function getProductVariantsWithPricing(string $productId): array
    {
        // Convert numeric ID to GID format if needed
        if (is_numeric($productId)) {
            $productId = $this->buildGid('Product', intval($productId));
        }

        $query = '
            query getProductVariants($productId: ID!) {
                product(id: $productId) {
                    id
                    title
                    variants(first: 100) {
                        nodes {
                            id
                            price
                            compareAtPrice
                            sku
                            displayName
                            inventoryQuantity
                            selectedOptions {
                                name
                                value
                            }
                        }
                    }
                }
            }
        ';

        $variables = ['productId' => $productId];
        $result = $this->executeQuery($query, $variables);

        if (! $result['success']) {
            return [
                'success' => false,
                'error' => 'Failed to fetch variants: '.$result['error'],
            ];
        }

        $product = $result['data']['product'] ?? null;
        if (! $product) {
            return [
                'success' => false,
                'error' => 'Product not found',
            ];
        }

        return [
            'success' => true,
            'product' => $product,
            'variants' => $product['variants']['nodes'] ?? [],
            'variants_count' => count($product['variants']['nodes'] ?? []),
        ];
    }

    /**
     * 🔧 EXTRACT PRODUCT ID FROM VARIANT GID
     *
     * Helper to extract product GID from variant GID
     * Note: This is a simple approach - in production you might want to query Shopify
     *
     * @param  string  $variantId  Shopify variant GID
     * @return string Product GID (best guess)
     */
    protected function extractProductIdFromVariant(string $variantId): string
    {
        // For bulk pricing updates, all variants should belong to the same product
        // In our color-based system, this will always be true per job

        // We'll need to query Shopify to get the actual product ID
        $query = '
            query getVariantProduct($variantId: ID!) {
                productVariant(id: $variantId) {
                    product {
                        id
                    }
                }
            }
        ';

        $result = $this->executeQuery($query, ['variantId' => $variantId]);

        if ($result['success'] && isset($result['data']['productVariant']['product']['id'])) {
            return $result['data']['productVariant']['product']['id'];
        }

        // Fallback: this shouldn't happen in normal operation
        Log::warning('⚠️ Could not determine product ID from variant', ['variant_id' => $variantId]);

        return '';
    }

    /**
     * Check if the client is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->config['store_url']) &&
               ! empty($this->config['access_token']) &&
               ! empty($this->config['api_version']);
    }
}
