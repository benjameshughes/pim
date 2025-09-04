<?php

namespace App\Services\Marketplace\Shopify;

use App\Models\SyncAccount;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

/**
 * 🛍️ SHOPIFY GRAPHQL CLIENT WRAPPER
 *
 * Simple wrapper for Shopify GraphQL API using Laravel HTTP client.
 * Handles authentication, rate limiting, and provides a clean interface.
 */
class ShopifyGraphQLClient
{
    protected string $shopDomain;

    protected string $accessToken;

    protected string $apiVersion;

    public function __construct(SyncAccount $syncAccount)
    {
        $credentials = $syncAccount->credentials;

        if (empty($credentials['shop_domain']) || empty($credentials['access_token'])) {
            throw new \InvalidArgumentException('Missing required Shopify credentials: shop_domain and access_token');
        }

        $this->shopDomain = $credentials['shop_domain'];
        $this->accessToken = $credentials['access_token'];
        $this->apiVersion = $credentials['api_version'] ?? '2024-07';
    }

    /**
     * Execute a GraphQL query using Laravel HTTP client
     */
    public function query(string $query, array $variables = []): array
    {
        $payload = ['query' => $query];

        // Only include variables if they're not empty
        if (! empty($variables)) {
            $payload['variables'] = $variables;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->getGraphQLEndpoint(), $payload);

        if (! $response->successful()) {
            throw new \Exception("Shopify API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();

        if (isset($body['errors'])) {
            \Illuminate\Support\Facades\Log::error('🚨 Shopify GraphQL Error', [
                'errors' => $body['errors'],
                'query_preview' => substr($query, 0, 200).'...'
            ]);
            throw new \Exception('GraphQL errors: '.json_encode($body['errors']));
        }

        return $body['data'] ?? [];
    }

    /**
     * Get the GraphQL endpoint URL
     */
    protected function getGraphQLEndpoint(): string
    {
        return "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/graphql.json";
    }

    /**
     * Execute a GraphQL mutation
     */
    public function mutate(string $mutation, array $variables = []): array
    {
        return $this->query($mutation, $variables);
    }

    /**
     * Test connection with a simple shop query
     */
    public function testConnection(): array
    {
        return $this->query('query { shop { id name email myshopifyDomain } }');
    }

    /**
     * STEP 1: Create product with options only (simple, focused)
     */
    public function createProductWithOptions(array $productInput): array
    {
        $mutation = '
            mutation productCreate($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
                        id
                        title
                        handle
                        status
                        options {
                            id
                            name
                            position
                            optionValues {
                                id
                                name
                            }
                        }
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        return $this->mutate($mutation, ['input' => $productInput]);
    }

    /**
     * STEP 2: Create variants for existing product (simple, focused)
     */
    public function createVariantsBulk(string $productId, array $variants): array
    {
        $mutation = '
            mutation productVariantsBulkCreate($productId: ID!, $variants: [ProductVariantsBulkInput!]!, $strategy: ProductVariantsBulkCreateStrategy!) {
                productVariantsBulkCreate(productId: $productId, variants: $variants, strategy: $strategy) {
                    productVariants {
                        id
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

        return $this->mutate($mutation, [
            'productId' => $productId,
            'variants' => $variants,
            'strategy' => 'REMOVE_STANDALONE_VARIANT'
        ]);
    }

    /**
     * Legacy method - simplified fallback to Step 1 approach
     */
    public function createProduct(array $productInput): array
    {
        // Simple fallback - just create product with options
        return $this->createProductWithOptions($productInput);
    }


    /**
     * Update a product using productUpdate mutation
     */
    public function updateProduct(string $productId, array $productInput): array
    {
        $mutation = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        handle
                        descriptionHtml
                        vendor
                        productType
                        status
                        updatedAt
                        category {
                            id
                            name
                        }
                        metafields(first: 10) {
                            edges {
                                node {
                                    id
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
        ';

        $productInput['id'] = $productId;

        return $this->mutate($mutation, ['input' => $productInput]);
    }

    /**
     * Get products using GraphQL query
     */
    public function getProducts(int $limit = 50, ?string $after = null): array
    {
        $query = '
            query getProducts($first: Int!, $after: String) {
                products(first: $first, after: $after) {
                    edges {
                        node {
                            id
                            title
                            handle
                            status
                            createdAt
                            updatedAt
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        title
                                        sku
                                        price
                                        inventoryQuantity
                                    }
                                }
                            }
                        }
                        cursor
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';

        $variables = ['first' => $limit];
        if ($after) {
            $variables['after'] = $after;
        }

        return $this->query($query, $variables);
    }

    /**
     * Bulk create products (for multiple color variants)
     */
    public function bulkCreateProducts(array $productInputs): array
    {
        $results = [];

        foreach ($productInputs as $productInput) {
            $result = $this->createProduct($productInput);
            $results[] = [
                'success' => empty($result['productCreate']['userErrors']),
                'product' => $result['productCreate']['product'] ?? null,
                'errors' => $result['productCreate']['userErrors'] ?? [],
                'input' => $productInput,
            ];

            // Rate limiting: Shopify allows 40 requests per minute
            usleep(1500000); // 1.5 second delay between requests
        }

        return $results;
    }

    /**
     * Update product title using GraphQL
     */
    public function updateProductTitle(string $productId, string $title): array
    {
        $mutation = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        handle
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = [
            'id' => $productId,
            'title' => $title,
        ];

        return $this->mutate($mutation, ['input' => $input]);
    }

    /**
     * Update product images using GraphQL
     */
    public function updateProductImages(string $productId, array $images): array
    {
        // Note: Images in Shopify need to be handled via separate productImageUpdate mutations
        // This is a placeholder for the concept
        $mutation = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        updatedAt
                        images(first: 10) {
                            edges {
                                node {
                                    id
                                    src
                                    altText
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
        ';

        $input = [
            'id' => $productId,
            // Images need to be handled differently in Shopify GraphQL
        ];

        return $this->mutate($mutation, ['input' => $input]);
    }

    /**
     * Search for products by title/handle to find existing products
     */
    public function searchProducts(string $query): array
    {
        $queryString = '
            query searchProducts($query: String!) {
                products(first: 50, query: $query) {
                    edges {
                        node {
                            id
                            title
                            handle
                            status
                            createdAt
                            updatedAt
                            metafields(namespace: "custom") {
                                edges {
                                    node {
                                        key
                                        value
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ';

        return $this->query($queryString, ['query' => $query]);
    }

    /**
     * Update variants for a product (for pricing fixes)
     */
    public function updateProductVariants(string $productId, array $variants): array
    {
        $mutation = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        sku
                        barcode
                        price
                        compareAtPrice
                        inventoryQuantity
                        updatedAt
                        title
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        // Transform variants to Shopify bulk update format with IDs
        $bulkVariants = [];
        foreach ($variants as $variant) {
            $bulkVariant = [
                'id' => $variant['id'], // CRITICAL: Shopify needs the variant ID
                'price' => $variant['price'], // The price to update
            ];

            // Add compareAtPrice if provided (supported field)
            if (isset($variant['compareAtPrice'])) {
                $bulkVariant['compareAtPrice'] = $variant['compareAtPrice'];
            }

            // Add metafields if provided (supported field)
            if (isset($variant['metafields'])) {
                $bulkVariant['metafields'] = $variant['metafields'];
            }

            // NOTE: SKU, barcode, and inventoryQuantity are NOT supported in ProductVariantsBulkInput
            // These need to be handled via separate API calls or different mutations

            $bulkVariants[] = $bulkVariant;
        }

        $result = $this->mutate($mutation, [
            'productId' => $productId,
            'variants' => $bulkVariants,
        ]);

        // Handle unsupported fields (SKU, barcode, inventoryQuantity) via separate calls
        $this->updateUnsupportedVariantFields($productId, $variants, $result);

        return $result;
    }

    /**
     * Update fields not supported by ProductVariantsBulkInput
     * Uses REST API for SKU updates since GraphQL doesn't support it
     */
    protected function updateUnsupportedVariantFields(string $productId, array $variants, array $bulkResult): void
    {
        $updatedVariants = $bulkResult['productVariantsBulkUpdate']['productVariants'] ?? [];

        foreach ($variants as $index => $requestedVariant) {
            $updatedVariant = $updatedVariants[$index] ?? null;
            if (! $updatedVariant) {
                continue;
            }

            $variantId = $updatedVariant['id'];
            $needsUpdate = false;
            $updateData = [];

            // Check if SKU needs updating
            if (isset($requestedVariant['sku']) && $requestedVariant['sku'] !== ($updatedVariant['sku'] ?? '')) {
                $updateData['sku'] = $requestedVariant['sku'];
                $needsUpdate = true;
            }

            // Check if barcode needs updating
            if (isset($requestedVariant['barcode']) && $requestedVariant['barcode'] !== ($updatedVariant['barcode'] ?? '')) {
                $updateData['barcode'] = $requestedVariant['barcode'];
                $needsUpdate = true;
            }

            // Update via REST API if needed
            if ($needsUpdate) {
                $this->updateVariantViaRest($variantId, $updateData);
            }
        }
    }

    /**
     * Update variant via REST API (for SKU, barcode, etc.) - Direct method for variant details
     */
    protected function updateSingleVariantViaRest(string $variantId, array $updateData): void
    {
        try {
            // Extract numeric ID from GraphQL ID (gid://shopify/ProductVariant/123 -> 123)
            $numericId = basename($variantId);

            $url = "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/variants/{$numericId}.json";

            $data = [
                'variant' => $updateData,
            ];

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->put($url, $data);

            if (!$response->successful()) {
                \Illuminate\Support\Facades\Log::error("❌ REST API variant update failed", [
                    'variant_id' => $variantId,
                    'numeric_id' => $numericId,
                    'status' => $response->status(),
                    'error' => $response->body(),
                    'update_data' => $updateData
                ]);
                throw new \Exception("REST API update failed: {$response->status()} - {$response->body()}");
            } else {
                \Illuminate\Support\Facades\Log::info("✅ Updated variant via REST API", [
                    'variant_id' => $variantId,
                    'numeric_id' => $numericId,
                    'updated_fields' => array_keys($updateData)
                ]);
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("❌ Exception updating variant via REST", [
                'variant_id' => $variantId,
                'error' => $e->getMessage(),
                'update_data' => $updateData
            ]);
            throw $e;
        }
    }

    /**
     * Update variant via REST API (for SKU, barcode, etc.) - Legacy method for compatibility
     */
    protected function updateVariantViaRest(string $variantId, array $updateData): void
    {
        $this->updateSingleVariantViaRest($variantId, $updateData);
    }

    /**
     * 🎯 KISS - Update a single variant using bulk mutation
     */
    public function updateSingleVariant(string $variantId, array $updates): array
    {
        // Extract product ID from variant ID (gid://shopify/ProductVariant/123 -> we need product ID)
        // We'll need to get this from the calling code or make a separate query

        // For now, let's use the bulk update with the variant ID and updates
        $variantData = array_merge(['id' => $variantId], $updates);

        // We need the product ID for productVariantsBulkUpdate
        // Let's get it by querying the variant first
        $variantQuery = '
            query getVariant($id: ID!) {
                productVariant(id: $id) {
                    id
                    product {
                        id
                    }
                }
            }
        ';

        $variantResult = $this->query($variantQuery, ['id' => $variantId]);
        $productId = $variantResult['productVariant']['product']['id'] ?? null;

        if (! $productId) {
            return ['error' => 'Could not find product ID for variant'];
        }

        // Now use bulk update with one variant
        return $this->updateProductVariants($productId, [$variantData]);
    }

    /**
     * 🎯 KISS - Get a single product with variants (for updates)
     */
    public function getProduct(string $productId): array
    {
        $query = '
            query getProduct($id: ID!) {
                product(id: $id) {
                    id
                    title
                    handle
                    status
                    variants(first: 100) {
                        edges {
                            node {
                                id
                                sku
                                price
                                inventoryQuantity
                                updatedAt
                            }
                        }
                    }
                }
            }
        ';

        return $this->query($query, ['id' => $productId]);
    }

    /**
     * 🗑️ Delete a product from Shopify
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

        return $this->mutate($mutation, [
            'input' => [
                'id' => $productId,
            ],
        ]);
    }

    /**
     * 🔍 Search for products by SKUs to link existing Shopify products
     */
    public function searchProductsBySku(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        // Build SKU search query - Shopify supports searching by variant SKU
        $skuQueries = array_map(fn ($sku) => "sku:{$sku}", $skus);
        $searchQuery = implode(' OR ', $skuQueries);

        $query = '
            query searchProducts($query: String!, $first: Int!) {
                products(query: $query, first: $first) {
                    edges {
                        node {
                            id
                            title
                            handle
                            status
                            createdAt
                            updatedAt
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        sku
                                        price
                                        inventoryQuantity
                                        title
                                        selectedOptions {
                                            name
                                            value
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        ';

        $variables = [
            'query' => $searchQuery,
            'first' => 50, // Should be enough for most scenarios
        ];

        $result = $this->query($query, $variables);

        // Transform GraphQL response to simpler format
        $products = [];
        $productEdges = $result['products']['edges'] ?? [];

        foreach ($productEdges as $edge) {
            $product = $edge['node'];
            $variants = [];

            foreach ($product['variants']['edges'] ?? [] as $variantEdge) {
                $variant = $variantEdge['node'];
                $variants[] = [
                    'id' => $variant['id'],
                    'sku' => $variant['sku'],
                    'price' => $variant['price'],
                    'title' => $variant['title'],
                    'inventoryQuantity' => $variant['inventoryQuantity'],
                    'selectedOptions' => $variant['selectedOptions'],
                ];
            }

            $products[] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'handle' => $product['handle'],
                'status' => $product['status'],
                'createdAt' => $product['createdAt'],
                'updatedAt' => $product['updatedAt'],
                'variants' => $variants,
            ];
        }

        return $products;
    }
}
