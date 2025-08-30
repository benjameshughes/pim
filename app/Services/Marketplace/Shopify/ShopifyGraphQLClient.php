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
        if (!empty($variables)) {
            $payload['variables'] = $variables;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->getGraphQLEndpoint(), $payload);

        if (!$response->successful()) {
            throw new \Exception("Shopify API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();
        
        if (isset($body['errors'])) {
            throw new \Exception('GraphQL errors: ' . json_encode($body['errors']));
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
     * Create a product using productCreate mutation
     */
    public function createProduct(array $productInput): array
    {
        $mutation = '
            mutation productCreate($input: ProductInput!) {
                productCreate(input: $input) {
                    product {
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

        return $this->mutate($mutation, ['input' => $productInput]);
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
                        updatedAt
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
    public function getProducts(int $limit = 50, string $after = null): array
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
}