<?php

namespace App\Services\Shopify\API\Client;

use Exception;
use Illuminate\Support\Facades\Log;
use PHPShopify\ShopifySDK;

/**
 * Shopify GraphQL API client
 *
 * Handles all GraphQL operations with the Shopify Admin API.
 * Provides methods for common queries and mutations.
 */
class ShopifyGraphQLClient
{
    protected ShopifySDK $sdk;

    protected array $config;

    public function __construct(ShopifySDK $sdk, array $config)
    {
        $this->sdk = $sdk;
        $this->config = $config;
    }

    /**
     * Execute a GraphQL query
     */
    public function query(string $query, array $variables = []): array
    {
        try {
            $data = empty($variables)
                ? $query
                : ['query' => $query, 'variables' => $variables];

            $response = $this->sdk->GraphQL->post($data);

            if (isset($response['errors'])) {
                Log::warning('GraphQL query returned errors', [
                    'errors' => $response['errors'],
                ]);
            }

            return [
                'success' => ! isset($response['errors']),
                'data' => $response['data'] ?? [],
                'errors' => $response['errors'] ?? null,
            ];
        } catch (Exception $e) {
            Log::error('GraphQL query failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get taxonomy categories
     */
    public function getTaxonomyCategories(int $first = 50, ?string $cursor = null): array
    {
        $afterClause = $cursor ? ", after: \"$cursor\"" : '';

        $query = <<<GRAPHQL
        query {
            taxonomy {
                categories(first: $first$afterClause) {
                    edges {
                        cursor
                        node {
                            id
                            name
                            fullName
                            level
                            isLeaf
                            parentId
                            childrenIds
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        }
        GRAPHQL;

        return $this->query($query);
    }

    /**
     * Get all taxonomy categories with pagination
     */
    public function getAllTaxonomyCategories(int $batchSize = 250): array
    {
        $allCategories = [];
        $hasNextPage = true;
        $cursor = null;
        $requestCount = 0;
        $maxRequests = 50;

        Log::info('Fetching all taxonomy categories');

        while ($hasNextPage && $requestCount < $maxRequests) {
            $requestCount++;

            $result = $this->getTaxonomyCategories($batchSize, $cursor);

            if (! $result['success']) {
                return $result;
            }

            $categoriesData = $result['data']['taxonomy']['categories'] ?? [];
            $edges = $categoriesData['edges'] ?? [];

            foreach ($edges as $edge) {
                if (isset($edge['node'])) {
                    $allCategories[] = $edge['node'];
                }
            }

            $pageInfo = $categoriesData['pageInfo'] ?? [];
            $hasNextPage = $pageInfo['hasNextPage'] ?? false;
            $cursor = $pageInfo['endCursor'] ?? null;

            if ($hasNextPage && ! $cursor) {
                break;
            }

            // Small delay between requests
            usleep(250000);
        }

        Log::info('Taxonomy categories fetched', [
            'total_categories' => count($allCategories),
            'requests_made' => $requestCount,
        ]);

        return [
            'success' => true,
            'data' => [
                'taxonomy' => [
                    'categories' => [
                        'edges' => array_map(fn ($cat) => ['node' => $cat], $allCategories),
                    ],
                ],
            ],
            'total_categories' => count($allCategories),
            'requests_made' => $requestCount,
        ];
    }

    /**
     * Search taxonomy categories
     */
    public function searchTaxonomyCategories(string $searchQuery): array
    {
        $query = <<<GRAPHQL
        query {
            taxonomy {
                categories(first: 100, query: "$searchQuery") {
                    edges {
                        node {
                            id
                            name
                            fullName
                            level
                            isLeaf
                            parentId
                        }
                    }
                }
            }
        }
        GRAPHQL;

        return $this->query($query);
    }

    /**
     * Create a product with GraphQL
     */
    public function createProduct(array $input): array
    {
        $query = <<<'GRAPHQL'
        mutation CreateProduct($input: ProductInput!) {
            productCreate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                    category {
                        id
                        name
                        fullName
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->query($query, ['input' => $input]);

        if ($result['success']) {
            $userErrors = $result['data']['productCreate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                Log::warning('Product creation had user errors', [
                    'errors' => $userErrors,
                ]);

                return [
                    'success' => false,
                    'errors' => $userErrors,
                ];
            }
        }

        return $result;
    }

    /**
     * Update a product with GraphQL
     */
    public function updateProduct(string $productGid, array $input): array
    {
        $input['id'] = $productGid;

        $query = <<<'GRAPHQL'
        mutation UpdateProduct($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    title
                    handle
                    status
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        $result = $this->query($query, ['input' => $input]);

        if ($result['success']) {
            $userErrors = $result['data']['productUpdate']['userErrors'] ?? [];
            if (! empty($userErrors)) {
                Log::warning('Product update had user errors', [
                    'errors' => $userErrors,
                ]);

                return [
                    'success' => false,
                    'errors' => $userErrors,
                ];
            }
        }

        return $result;
    }

    /**
     * Update product category
     */
    public function updateProductCategory(string $productGid, string $categoryId): array
    {
        $query = <<<GRAPHQL
        mutation {
            productUpdate(input: {
                id: "$productGid"
                category: "$categoryId"
            }) {
                product {
                    id
                    title
                    category {
                        id
                        name
                        fullName
                    }
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        return $this->query($query);
    }

    /**
     * Get specific categories by IDs
     */
    public function getCategoriesByIds(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return ['success' => true, 'data' => ['categories' => []]];
        }

        $idsString = implode('", "', $categoryIds);

        $query = <<<GRAPHQL
        query {
            nodes(ids: ["$idsString"]) {
                ... on TaxonomyCategory {
                    id
                    name
                    fullName
                    level
                    isLeaf
                    parentId
                    childrenIds
                }
            }
        }
        GRAPHQL;

        $result = $this->query($query);

        if ($result['success']) {
            $categories = [];
            foreach ($result['data']['nodes'] ?? [] as $node) {
                if ($node && isset($node['id'])) {
                    $categories[] = $node;
                }
            }

            return [
                'success' => true,
                'data' => ['categories' => $categories],
                'total_categories' => count($categories),
            ];
        }

        return $result;
    }

    /**
     * Bulk operations query
     */
    public function bulkOperation(string $mutation): array
    {
        $query = <<<GRAPHQL
        mutation {
            bulkOperationRunMutation(
                mutation: "$mutation"
            ) {
                bulkOperation {
                    id
                    status
                    createdAt
                    url
                }
                userErrors {
                    field
                    message
                }
            }
        }
        GRAPHQL;

        return $this->query($query);
    }
}
