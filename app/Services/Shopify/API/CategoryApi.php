<?php

namespace App\Services\Shopify\API;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ SHOPIFY CATEGORY API (H11 EQUIVALENT)
 *
 * Handles Shopify taxonomy categories and product categorization.
 * Equivalent to Mirakl's H11 (Category) API functionality.
 */
class CategoryApi extends BaseShopifyApi
{
    /**
     * 📋 GET TAXONOMY CATEGORIES
     *
     * Retrieves taxonomy categories with optional filtering and hierarchy navigation
     *
     * @param  array<string, mixed>  $options  Query options
     * @return array<string, mixed>
     */
    public function getCategories(array $options = []): array
    {
        $query = $this->buildCategoriesQuery($options);

        $graphqlQuery = <<<GRAPHQL
        query GetTaxonomyCategories {
            taxonomy {
                categories($query) {
                    edges {
                        cursor
                        node {
                            id
                            name
                            fullName
                            level
                            isLeaf
                            isRoot
                            isArchived
                            ancestorIds
                            childrenIds
                            parentId
                            attributes {
                                edges {
                                    node {
                                        ... on TaxonomyAttribute {
                                            id
                                            name
                                            handle
                                        }
                                        ... on TaxonomyChoiceListAttribute {
                                            id
                                            name
                                            handle
                                            choices {
                                                value
                                                label
                                            }
                                        }
                                        ... on TaxonomyMeasurementAttribute {
                                            id
                                            name
                                            handle
                                            units
                                        }
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
        }
        GRAPHQL;

        $result = $this->graphql($graphqlQuery);

        if ($result['success']) {
            $categories = $result['data']['taxonomy']['categories']['edges'] ?? [];

            return [
                'success' => true,
                'categories' => array_map(function ($edge) {
                    return $this->transformCategory($edge['node']);
                }, $categories),
                'pagination' => $result['data']['taxonomy']['categories']['pageInfo'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to retrieve categories',
            'categories' => [],
        ];
    }

    /**
     * 🔍 GET CATEGORY BY ID
     *
     * Retrieves a specific category by its taxonomy ID
     *
     * @param  string  $categoryId  Taxonomy category ID
     * @return array<string, mixed>
     */
    public function getCategoryById(string $categoryId): array
    {
        $cacheKey = "shopify_category_{$this->shopDomain}_{$categoryId}";

        return Cache::remember($cacheKey, 300, function () use ($categoryId) {
            $graphqlQuery = <<<GRAPHQL
            query GetTaxonomyCategory {
                taxonomy {
                    categories(children_of: "$categoryId", first: 1) {
                        edges {
                            node {
                                id
                                name
                                fullName
                                level
                                isLeaf
                                isRoot
                                isArchived
                                ancestorIds
                                childrenIds
                                parentId
                                attributes {
                                    edges {
                                        node {
                                            ... on TaxonomyAttribute {
                                                id
                                                name
                                                handle
                                            }
                                            ... on TaxonomyChoiceListAttribute {
                                                id
                                                name
                                                handle
                                                choices {
                                                    value
                                                    label
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            // Also get the specific category if it exists
            $directQuery = <<<GRAPHQL
            query GetSpecificCategory {
                taxonomy {
                    categories(search: "$categoryId") {
                        edges {
                            node {
                                id
                                name
                                fullName
                                level
                                isLeaf
                                isRoot
                                isArchived
                                ancestorIds
                                childrenIds
                                parentId
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($directQuery);

            if ($result['success']) {
                $categories = $result['data']['taxonomy']['categories']['edges'] ?? [];

                if (! empty($categories)) {
                    return [
                        'success' => true,
                        'category' => $this->transformCategory($categories[0]['node']),
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Category not found',
                'category' => null,
            ];
        });
    }

    /**
     * 🔎 SEARCH CATEGORIES
     *
     * Search taxonomy categories by query string
     *
     * @param  string  $query  Search query
     * @param  array<string, mixed>  $options  Additional options
     * @return array<string, mixed>
     */
    public function searchCategories(string $query, array $options = []): array
    {
        $first = $options['first'] ?? 50;

        $graphqlQuery = <<<GRAPHQL
        query SearchTaxonomyCategories {
            taxonomy {
                categories(search: "$query", first: $first) {
                    edges {
                        node {
                            id
                            name
                            fullName
                            level
                            isLeaf
                            isRoot
                            isArchived
                            ancestorIds
                            childrenIds
                            parentId
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

        $result = $this->graphql($graphqlQuery);

        if ($result['success']) {
            $categories = $result['data']['taxonomy']['categories']['edges'] ?? [];

            Log::info('Category search completed', [
                'shop_domain' => $this->shopDomain,
                'query' => $query,
                'results_count' => count($categories),
            ]);

            return [
                'success' => true,
                'categories' => array_map(function ($edge) {
                    return $this->transformCategory($edge['node']);
                }, $categories),
                'query' => $query,
                'pagination' => $result['data']['taxonomy']['categories']['pageInfo'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Search failed',
            'categories' => [],
            'query' => $query,
        ];
    }

    /**
     * 🌳 GET CATEGORY HIERARCHY
     *
     * Retrieves the full hierarchy for a category (ancestors and descendants)
     *
     * @param  string  $categoryId  Category ID
     * @return array<string, mixed>
     */
    public function getCategoryHierarchy(string $categoryId): array
    {
        try {
            // Get the category and its children
            $childrenQuery = <<<GRAPHQL
            query GetCategoryChildren {
                taxonomy {
                    categories(children_of: "$categoryId") {
                        edges {
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
                    }
                }
            }
            GRAPHQL;

            // Get siblings
            $siblingsQuery = <<<GRAPHQL
            query GetCategorySiblings {
                taxonomy {
                    categories(siblings_of: "$categoryId") {
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

            // Get descendants
            $descendantsQuery = <<<GRAPHQL
            query GetCategoryDescendants {
                taxonomy {
                    categories(decendents_of: "$categoryId") {
                        edges {
                            node {
                                id
                                name
                                fullName
                                level
                                isLeaf
                                parentId
                                ancestorIds
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $childrenResult = $this->graphql($childrenQuery);
            $siblingsResult = $this->graphql($siblingsQuery);
            $descendantsResult = $this->graphql($descendantsQuery);

            $hierarchy = [
                'success' => true,
                'category_id' => $categoryId,
                'children' => [],
                'siblings' => [],
                'descendants' => [],
            ];

            if ($childrenResult['success']) {
                $hierarchy['children'] = array_map(function ($edge) {
                    return $this->transformCategory($edge['node']);
                }, $childrenResult['data']['taxonomy']['categories']['edges'] ?? []);
            }

            if ($siblingsResult['success']) {
                $hierarchy['siblings'] = array_map(function ($edge) {
                    return $this->transformCategory($edge['node']);
                }, $siblingsResult['data']['taxonomy']['categories']['edges'] ?? []);
            }

            if ($descendantsResult['success']) {
                $hierarchy['descendants'] = array_map(function ($edge) {
                    return $this->transformCategory($edge['node']);
                }, $descendantsResult['data']['taxonomy']['categories']['edges'] ?? []);
            }

            return $hierarchy;

        } catch (\Exception $e) {
            Log::error('Failed to get category hierarchy', [
                'shop_domain' => $this->shopDomain,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'category_id' => $categoryId,
            ];
        }
    }

    /**
     * 🏷️ ASSIGN PRODUCT TO CATEGORY
     *
     * Assigns a product to a taxonomy category
     *
     * @param  string  $productId  Product GraphQL ID
     * @param  string  $categoryId  Category ID
     * @return array<string, mixed>
     */
    public function assignProductToCategory(string $productId, string $categoryId): array
    {
        $mutation = <<<'GRAPHQL'
        mutation AssignProductCategory($input: ProductInput!) {
            productUpdate(input: $input) {
                product {
                    id
                    productCategory {
                        productTaxonomyNode {
                            id
                            name
                            fullName
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

        $variables = [
            'input' => [
                'id' => $productId,
                'category' => $categoryId,
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['productUpdate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Product category assignment failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $product = $result['data']['productUpdate']['product'];

            Log::info('Product assigned to category', [
                'shop_domain' => $this->shopDomain,
                'product_id' => $productId,
                'category_id' => $categoryId,
            ]);

            return [
                'success' => true,
                'product_id' => $productId,
                'category_id' => $categoryId,
                'product' => $product,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Category assignment failed',
            'product_id' => $productId,
            'category_id' => $categoryId,
        ];
    }

    /**
     * 📊 GET CATEGORY STATISTICS
     *
     * Get statistics about categories and their usage
     *
     * @return array<string, mixed>
     */
    public function getCategoryStatistics(): array
    {
        $cacheKey = "shopify_category_stats_{$this->shopDomain}";

        return Cache::remember($cacheKey, 600, function () {
            // Get top-level categories
            $topLevelQuery = <<<'GRAPHQL'
            query GetTopLevelCategories {
                taxonomy {
                    categories(first: 100) {
                        edges {
                            node {
                                id
                                name
                                level
                                isRoot
                                isLeaf
                                childrenIds
                            }
                        }
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($topLevelQuery);

            if ($result['success']) {
                $categories = $result['data']['taxonomy']['categories']['edges'] ?? [];

                $stats = [
                    'total_categories' => count($categories),
                    'root_categories' => 0,
                    'leaf_categories' => 0,
                    'levels' => [],
                    'category_breakdown' => [],
                ];

                foreach ($categories as $edge) {
                    $category = $edge['node'];

                    if ($category['isRoot']) {
                        $stats['root_categories']++;
                    }

                    if ($category['isLeaf']) {
                        $stats['leaf_categories']++;
                    }

                    $level = $category['level'];
                    if (! isset($stats['levels'][$level])) {
                        $stats['levels'][$level] = 0;
                    }
                    $stats['levels'][$level]++;

                    $stats['category_breakdown'][] = [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'level' => $level,
                        'children_count' => count($category['childrenIds'] ?? []),
                        'is_root' => $category['isRoot'],
                        'is_leaf' => $category['isLeaf'],
                    ];
                }

                return [
                    'success' => true,
                    'statistics' => $stats,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve category statistics',
            ];
        });
    }

    /**
     * 🔧 BUILD CATEGORIES QUERY
     *
     * Builds the query string for category requests
     *
     * @param  array<string, mixed>  $options  Query options
     */
    protected function buildCategoriesQuery(array $options): string
    {
        $queryParts = [];

        if (isset($options['search'])) {
            $queryParts[] = "search: \"{$options['search']}\"";
        }

        if (isset($options['children_of'])) {
            $queryParts[] = "children_of: \"{$options['children_of']}\"";
        }

        if (isset($options['siblings_of'])) {
            $queryParts[] = "siblings_of: \"{$options['siblings_of']}\"";
        }

        if (isset($options['descendants_of'])) {
            $queryParts[] = "decendents_of: \"{$options['descendants_of']}\"";
        }

        $first = $options['first'] ?? 50;
        $queryParts[] = "first: $first";

        if (isset($options['after'])) {
            $queryParts[] = "after: \"{$options['after']}\"";
        }

        return implode(', ', $queryParts);
    }

    /**
     * ✨ TRANSFORM CATEGORY
     *
     * Transforms a raw category node into a structured format
     *
     * @param  array<string, mixed>  $category  Raw category data
     * @return array<string, mixed>
     */
    protected function transformCategory(array $category): array
    {
        return [
            'id' => $category['id'],
            'name' => $category['name'],
            'full_name' => $category['fullName'] ?? '',
            'level' => $category['level'] ?? 0,
            'is_leaf' => $category['isLeaf'] ?? false,
            'is_root' => $category['isRoot'] ?? false,
            'is_archived' => $category['isArchived'] ?? false,
            'parent_id' => $category['parentId'] ?? null,
            'ancestor_ids' => $category['ancestorIds'] ?? [],
            'children_ids' => $category['childrenIds'] ?? [],
            'attributes' => $this->transformCategoryAttributes($category['attributes'] ?? []),
        ];
    }

    /**
     * 🎯 TRANSFORM CATEGORY ATTRIBUTES
     *
     * Transforms category attributes from GraphQL response
     *
     * @param  array<string, mixed>  $attributesConnection  Attributes connection
     * @return array<array<string, mixed>>
     */
    protected function transformCategoryAttributes(array $attributesConnection): array
    {
        if (! isset($attributesConnection['edges'])) {
            return [];
        }

        return array_map(function ($edge) {
            $attribute = $edge['node'];

            $transformed = [
                'id' => $attribute['id'],
                'name' => $attribute['name'],
                'handle' => $attribute['handle'],
                'type' => 'attribute',
            ];

            // Handle different attribute types
            if (isset($attribute['choices'])) {
                $transformed['type'] = 'choice_list';
                $transformed['choices'] = $attribute['choices'];
            }

            if (isset($attribute['units'])) {
                $transformed['type'] = 'measurement';
                $transformed['units'] = $attribute['units'];
            }

            return $transformed;
        }, $attributesConnection['edges']);
    }
}
