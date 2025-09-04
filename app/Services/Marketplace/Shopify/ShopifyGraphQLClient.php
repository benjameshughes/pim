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
     * Update variant prices (simplified for common use case)
     */
    public function updateVariantPrices(string $productId, array $variants): array
    {
        $mutation = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        price
                        compareAtPrice
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        // Clean variant data with only supported fields
        $bulkVariants = [];
        foreach ($variants as $variant) {
            $bulkVariant = [
                'id' => $variant['id'],
                'price' => $variant['price'],
            ];

            if (isset($variant['compareAtPrice'])) {
                $bulkVariant['compareAtPrice'] = $variant['compareAtPrice'];
            }

            $bulkVariants[] = $bulkVariant;
        }

        return $this->mutate($mutation, [
            'productId' => $productId,
            'variants' => $bulkVariants,
        ]);
    }


    /**
     * Update variant SKU/barcode via REST API (GraphQL doesn't support these fields)
     */
    public function updateVariantDetails(string $variantId, array $updateData): array
    {
        try {
            $numericId = basename($variantId);
            $url = "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/variants/{$numericId}.json";

            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->put($url, ['variant' => $updateData]);

            if (!$response->successful()) {
                throw new \Exception("REST API update failed: {$response->status()}");
            }

            return $response->json();

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('❌ Variant REST update failed', [
                'variant_id' => $variantId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Batch update variant SKUs using REST API (GraphQL productVariantUpdate is deprecated)
     */
    public function batchUpdateVariantSKUs(array $variantSKUMappings): array
    {
        if (empty($variantSKUMappings)) {
            return ['success' => true, 'updated' => []];
        }

        \Illuminate\Support\Facades\Log::info('🔧 Batch updating variant SKUs via REST API', [
            'variant_count' => count($variantSKUMappings)
        ]);

        $successful = [];
        $failed = [];

        // Update each variant via REST API (GraphQL productVariantUpdate is deprecated)
        foreach ($variantSKUMappings as $index => $mapping) {
            try {
                $result = $this->updateVariantDetails($mapping['variantId'], [
                    'sku' => $mapping['sku']
                ]);
                
                $successful[] = [
                    'id' => $mapping['variantId'],
                    'sku' => $mapping['sku']
                ];
                
            } catch (\Exception $e) {
                $failed[] = [
                    'variantId' => $mapping['variantId'],
                    'sku' => $mapping['sku'],
                    'error' => $e->getMessage()
                ];
            }
        }

        \Illuminate\Support\Facades\Log::info('✅ SKU batch update completed', [
            'successful_count' => count($successful),
            'failed_count' => count($failed)
        ]);

        return [
            'success' => empty($failed),
            'successful' => $successful,
            'failed' => $failed,
            'total_processed' => count($variantSKUMappings)
        ];
    }



    /**
     * Get a single product with variants
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
     * Delete a product from Shopify
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
            'input' => ['id' => $productId]
        ]);
    }

    /**
     * Search for products by SKUs
     */
    public function searchProductsBySku(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

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
                            variants(first: 100) {
                                edges {
                                    node {
                                        id
                                        sku
                                        price
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

        $result = $this->query($query, ['query' => $searchQuery, 'first' => 50]);

        // Transform to simpler format
        $products = [];
        foreach ($result['products']['edges'] ?? [] as $edge) {
            $product = $edge['node'];
            $variants = [];

            foreach ($product['variants']['edges'] ?? [] as $variantEdge) {
                $variant = $variantEdge['node'];
                $variants[] = [
                    'id' => $variant['id'],
                    'sku' => $variant['sku'],
                    'price' => $variant['price'],
                    'selectedOptions' => $variant['selectedOptions'],
                ];
            }

            $products[] = [
                'id' => $product['id'],
                'title' => $product['title'],
                'handle' => $product['handle'],
                'status' => $product['status'],
                'variants' => $variants,
            ];
        }

        return $products;
    }

    /**
     * Update single variant (for pricing/inventory updates)
     */
    public function updateSingleVariant(string $variantId, array $variantData): array
    {
        $mutation = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        price
                        compareAtPrice
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        // Extract product ID from variant ID (gid://shopify/Product/123/ProductVariant/456)
        $productId = 'gid://shopify/Product/' . explode('/', $variantId)[4];

        $bulkVariant = [
            'id' => $variantId,
        ];

        if (isset($variantData['price'])) {
            $bulkVariant['price'] = $variantData['price'];
        }

        if (isset($variantData['compareAtPrice'])) {
            $bulkVariant['compareAtPrice'] = $variantData['compareAtPrice'];
        }

        return $this->mutate($mutation, [
            'productId' => $productId,
            'variants' => [$bulkVariant],
        ]);
    }

    /**
     * Update product title via productUpdate
     */
    public function updateProductTitle(string $productId, string $title): array
    {
        $mutation = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        updatedAt
                    }
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
                'title' => $title,
            ]
        ]);
    }

    /**
     * Update product content (title, description, vendor, etc.)
     */
    public function updateProductContent(string $productId, array $contentData): array
    {
        $mutation = '
            mutation productUpdate($input: ProductInput!) {
                productUpdate(input: $input) {
                    product {
                        id
                        title
                        descriptionHtml
                        vendor
                        productType
                        status
                        updatedAt
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        ';

        $input = ['id' => $productId];

        // Add fields that are being updated
        foreach (['title', 'descriptionHtml', 'vendor', 'productType', 'status', 'metafields'] as $field) {
            if (isset($contentData[$field])) {
                $input[$field] = $contentData[$field];
            }
        }

        return $this->mutate($mutation, ['input' => $input]);
    }

    /**
     * Bulk update variants for a product (prices, compareAtPrice, etc.)
     */
    public function bulkUpdateVariants(string $productId, array $variants): array
    {
        $mutation = '
            mutation productVariantsBulkUpdate($productId: ID!, $variants: [ProductVariantsBulkInput!]!) {
                productVariantsBulkUpdate(productId: $productId, variants: $variants) {
                    productVariants {
                        id
                        price
                        compareAtPrice
                        updatedAt
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
        ]);
    }

    /**
     * Update product media/images using modern productUpdate mutation
     */
    public function updateProductMedia(string $productId, array $images): array
    {
        if (empty($images)) {
            return [
                'success' => true,
                'message' => 'No images to update',
                'data' => []
            ];
        }

        try {
            $mutation = '
                mutation productUpdate($product: ProductUpdateInput!, $media: [CreateMediaInput!]!) {
                    productUpdate(product: $product, media: $media) {
                        product {
                            id
                            media(first: 20) {
                                nodes {
                                    id
                                    alt
                                    mediaContentType
                                    status
                                    ... on MediaImage {
                                        image {
                                            url
                                            altText
                                        }
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

            // Convert image URLs to Shopify media input format
            $mediaInputs = [];
            foreach ($images as $image) {
                $mediaInputs[] = [
                    'originalSource' => $image['src'],
                    'alt' => $image['altText'] ?? '',
                    'mediaContentType' => 'IMAGE'
                ];
            }

            $result = $this->mutate($mutation, [
                'product' => ['id' => $productId],
                'media' => $mediaInputs
            ]);

            $userErrors = $result['productUpdate']['userErrors'] ?? [];

            if (!empty($userErrors)) {
                \Illuminate\Support\Facades\Log::error('Shopify media update errors', [
                    'product_id' => $productId,
                    'errors' => $userErrors
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Image update failed: ' . json_encode($userErrors),
                    'data' => $userErrors
                ];
            }

            $updatedMedia = $result['productUpdate']['product']['media']['nodes'] ?? [];
            
            \Illuminate\Support\Facades\Log::info('✅ Successfully updated product images', [
                'product_id' => $productId,
                'media_count' => count($updatedMedia),
                'images_added' => count($images)
            ]);

            return [
                'success' => true,
                'message' => 'Successfully added ' . count($images) . ' images to product',
                'data' => $updatedMedia
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Exception during Shopify media update', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Image update exception: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }
}
