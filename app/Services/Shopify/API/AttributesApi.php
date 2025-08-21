<?php

namespace App\Services\Shopify\API;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 🏷️ SHOPIFY ATTRIBUTES API (PM11 EQUIVALENT)
 *
 * Handles Shopify metafield definitions and custom product attributes.
 * Equivalent to Mirakl's PM11 (Product Attributes) API functionality.
 */
class AttributesApi extends BaseShopifyApi
{
    /**
     * 📋 GET METAFIELD DEFINITIONS
     *
     * Retrieves metafield definitions (custom attributes) for a specific owner type
     *
     * @param  string  $ownerType  Owner type (PRODUCT, PRODUCTVARIANT, etc.)
     * @param  array<string, mixed>  $options  Query options
     * @return array<string, mixed>
     */
    public function getMetafieldDefinitions(string $ownerType = 'PRODUCT', array $options = []): array
    {
        $first = $options['first'] ?? 50;
        $after = isset($options['after']) ? ", after: \"{$options['after']}\"" : '';

        $graphqlQuery = <<<GRAPHQL
        query GetMetafieldDefinitions {
            metafieldDefinitions(ownerType: $ownerType, first: $first$after) {
                edges {
                    cursor
                    node {
                        id
                        namespace
                        key
                        name
                        description
                        type {
                            name
                            category
                            supportsDefinitionMigrations
                            supportedValidations {
                                name
                                type
                            }
                        }
                        ownerType
                        validations {
                            name
                            type
                            value
                        }
                        access {
                            admin
                            storefront
                        }
                        useAsCollectionCondition
                        pinnedPosition
                        metafieldsCount
                    }
                }
                pageInfo {
                    hasNextPage
                    endCursor
                }
            }
        }
        GRAPHQL;

        $result = $this->graphql($graphqlQuery);

        if ($result['success']) {
            $definitions = $result['data']['metafieldDefinitions']['edges'] ?? [];

            Log::info('Metafield definitions retrieved', [
                'shop_domain' => $this->shopDomain,
                'owner_type' => $ownerType,
                'count' => count($definitions),
            ]);

            return [
                'success' => true,
                'owner_type' => $ownerType,
                'definitions' => array_map(function ($edge) {
                    return $this->transformMetafieldDefinition($edge['node']);
                }, $definitions),
                'pagination' => $result['data']['metafieldDefinitions']['pageInfo'] ?? null,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to retrieve metafield definitions',
            'owner_type' => $ownerType,
            'definitions' => [],
        ];
    }

    /**
     * ✨ CREATE METAFIELD DEFINITION
     *
     * Creates a new metafield definition (custom attribute)
     *
     * @param  array<string, mixed>  $definition  Definition data
     * @return array<string, mixed>
     */
    public function createMetafieldDefinition(array $definition): array
    {
        $mutation = <<<'GRAPHQL'
        mutation CreateMetafieldDefinition($definition: MetafieldDefinitionInput!) {
            metafieldDefinitionCreate(definition: $definition) {
                createdDefinition {
                    id
                    namespace
                    key
                    name
                    description
                    type {
                        name
                        category
                    }
                    ownerType
                    validations {
                        name
                        type
                        value
                    }
                    access {
                        admin
                        storefront
                    }
                    useAsCollectionCondition
                }
                userErrors {
                    field
                    message
                    code
                }
            }
        }
        GRAPHQL;

        $variables = [
            'definition' => $this->buildMetafieldDefinitionInput($definition),
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['metafieldDefinitionCreate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metafield definition creation failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $createdDefinition = $result['data']['metafieldDefinitionCreate']['createdDefinition'];

            Log::info('Metafield definition created', [
                'shop_domain' => $this->shopDomain,
                'definition_id' => $createdDefinition['id'],
                'namespace' => $createdDefinition['namespace'],
                'key' => $createdDefinition['key'],
            ]);

            return [
                'success' => true,
                'definition' => $this->transformMetafieldDefinition($createdDefinition),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create metafield definition',
        ];
    }

    /**
     * 🔄 UPDATE METAFIELD DEFINITION
     *
     * Updates an existing metafield definition
     *
     * @param  string  $definitionId  Definition GraphQL ID
     * @param  array<string, mixed>  $definition  Updated definition data
     * @return array<string, mixed>
     */
    public function updateMetafieldDefinition(string $definitionId, array $definition): array
    {
        $mutation = <<<'GRAPHQL'
        mutation UpdateMetafieldDefinition($definition: MetafieldDefinitionInput!) {
            metafieldDefinitionUpdate(definition: $definition) {
                updatedDefinition {
                    id
                    namespace
                    key
                    name
                    description
                    type {
                        name
                        category
                    }
                    ownerType
                    validations {
                        name
                        type
                        value
                    }
                    access {
                        admin
                        storefront
                    }
                    useAsCollectionCondition
                }
                userErrors {
                    field
                    message
                    code
                }
            }
        }
        GRAPHQL;

        $definitionInput = $this->buildMetafieldDefinitionInput($definition);
        $definitionInput['id'] = $definitionId;

        $variables = [
            'definition' => $definitionInput,
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['metafieldDefinitionUpdate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metafield definition update failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $updatedDefinition = $result['data']['metafieldDefinitionUpdate']['updatedDefinition'];

            Log::info('Metafield definition updated', [
                'shop_domain' => $this->shopDomain,
                'definition_id' => $definitionId,
            ]);

            return [
                'success' => true,
                'definition' => $this->transformMetafieldDefinition($updatedDefinition),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update metafield definition',
        ];
    }

    /**
     * 🗑️ DELETE METAFIELD DEFINITION
     *
     * Deletes a metafield definition
     *
     * @param  string  $definitionId  Definition GraphQL ID
     * @return array<string, mixed>
     */
    public function deleteMetafieldDefinition(string $definitionId): array
    {
        $mutation = <<<'GRAPHQL'
        mutation DeleteMetafieldDefinition($id: ID!) {
            metafieldDefinitionDelete(id: $id) {
                deletedDefinitionId
                userErrors {
                    field
                    message
                    code
                }
            }
        }
        GRAPHQL;

        $variables = [
            'id' => $definitionId,
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['metafieldDefinitionDelete']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metafield definition deletion failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            Log::info('Metafield definition deleted', [
                'shop_domain' => $this->shopDomain,
                'definition_id' => $definitionId,
            ]);

            return [
                'success' => true,
                'deleted_definition_id' => $result['data']['metafieldDefinitionDelete']['deletedDefinitionId'],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to delete metafield definition',
        ];
    }

    /**
     * 📊 GET METAFIELD TYPES
     *
     * Retrieves all available metafield definition types and their validations
     *
     * @return array<string, mixed>
     */
    public function getMetafieldTypes(): array
    {
        $cacheKey = "shopify_metafield_types_{$this->shopDomain}";

        return Cache::remember($cacheKey, 3600, function () {
            $graphqlQuery = <<<'GRAPHQL'
            query GetMetafieldTypes {
                metafieldDefinitionTypes {
                    category
                    name
                    supportsDefinitionMigrations
                    supportedValidations {
                        name
                        type
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($graphqlQuery);

            if ($result['success']) {
                $types = $result['data']['metafieldDefinitionTypes'] ?? [];

                return [
                    'success' => true,
                    'types' => array_map(function ($type) {
                        return [
                            'category' => $type['category'],
                            'name' => $type['name'],
                            'supports_definition_migrations' => $type['supportsDefinitionMigrations'],
                            'supported_validations' => $type['supportedValidations'],
                        ];
                    }, $types),
                    'grouped_by_category' => $this->groupTypesByCategory($types),
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to retrieve metafield types',
                'types' => [],
            ];
        });
    }

    /**
     * 🔗 LINK ATTRIBUTE TO PRODUCT OPTION
     *
     * Links a metafield to a product option for structured data
     *
     * @param  string  $productId  Product GraphQL ID
     * @param  string  $optionName  Option name (e.g., "Color", "Size")
     * @param  string  $metafieldNamespace  Metafield namespace
     * @param  string  $metafieldKey  Metafield key
     * @param  array<string>  $values  Optional array of metaobject IDs for values
     * @return array<string, mixed>
     */
    public function linkAttributeToOption(
        string $productId,
        string $optionName,
        string $metafieldNamespace,
        string $metafieldKey,
        array $values = []
    ): array {
        $mutation = <<<'GRAPHQL'
        mutation LinkAttributeToOption($productId: ID!, $options: [OptionCreateInput!]!) {
            productOptionsCreate(productId: $productId, options: $options) {
                userErrors {
                    field
                    message
                    code
                }
                product {
                    options {
                        name
                        linkedMetafield {
                            namespace
                            key
                        }
                        optionValues {
                            name
                            linkedMetafieldValue
                        }
                    }
                }
            }
        }
        GRAPHQL;

        $optionInput = [
            'name' => $optionName,
            'linkedMetafield' => [
                'namespace' => $metafieldNamespace,
                'key' => $metafieldKey,
            ],
        ];

        if (! empty($values)) {
            $optionInput['linkedMetafield']['values'] = $values;
        }

        $variables = [
            'productId' => $productId,
            'options' => [$optionInput],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['productOptionsCreate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Attribute linking failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $product = $result['data']['productOptionsCreate']['product'];

            Log::info('Attribute linked to product option', [
                'shop_domain' => $this->shopDomain,
                'product_id' => $productId,
                'option_name' => $optionName,
                'metafield_key' => "{$metafieldNamespace}.{$metafieldKey}",
            ]);

            return [
                'success' => true,
                'product_id' => $productId,
                'option_name' => $optionName,
                'metafield' => [
                    'namespace' => $metafieldNamespace,
                    'key' => $metafieldKey,
                ],
                'product_options' => $product['options'],
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to link attribute to option',
        ];
    }

    /**
     * 📝 SET PRODUCT METAFIELD
     *
     * Sets a metafield value on a product
     *
     * @param  string  $productId  Product GraphQL ID
     * @param  string  $namespace  Metafield namespace
     * @param  string  $key  Metafield key
     * @param  mixed  $value  Metafield value
     * @param  string  $type  Metafield type
     * @return array<string, mixed>
     */
    public function setProductMetafield(
        string $productId,
        string $namespace,
        string $key,
        $value,
        string $type = 'single_line_text_field'
    ): array {
        $mutation = <<<GRAPHQL
        mutation SetProductMetafield(\$input: ProductInput!) {
            productUpdate(input: \$input) {
                product {
                    id
                    metafield(namespace: "$namespace", key: "$key") {
                        namespace
                        key
                        value
                        type
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
                'metafields' => [
                    [
                        'namespace' => $namespace,
                        'key' => $key,
                        'value' => is_string($value) ? $value : json_encode($value),
                        'type' => $type,
                    ],
                ],
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['productUpdate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metafield update failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $metafield = $result['data']['productUpdate']['product']['metafield'];

            return [
                'success' => true,
                'product_id' => $productId,
                'metafield' => $metafield,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to set metafield',
        ];
    }

    /**
     * 📊 GET ATTRIBUTE STATISTICS
     *
     * Get statistics about metafield definitions and their usage
     *
     * @return array<string, mixed>
     */
    public function getAttributeStatistics(): array
    {
        $cacheKey = "shopify_attribute_stats_{$this->shopDomain}";

        return Cache::remember($cacheKey, 600, function () {
            $ownerTypes = ['PRODUCT', 'PRODUCTVARIANT', 'COLLECTION', 'ORDER'];
            $stats = [
                'total_definitions' => 0,
                'by_owner_type' => [],
                'by_type_category' => [],
                'most_used' => [],
            ];

            foreach ($ownerTypes as $ownerType) {
                $definitions = $this->getMetafieldDefinitions($ownerType);

                if ($definitions['success']) {
                    $count = count($definitions['definitions']);
                    $stats['total_definitions'] += $count;
                    $stats['by_owner_type'][$ownerType] = $count;

                    foreach ($definitions['definitions'] as $definition) {
                        $category = $definition['type']['category'] ?? 'unknown';
                        if (! isset($stats['by_type_category'][$category])) {
                            $stats['by_type_category'][$category] = 0;
                        }
                        $stats['by_type_category'][$category]++;

                        if ($definition['metafields_count'] > 0) {
                            $stats['most_used'][] = [
                                'namespace' => $definition['namespace'],
                                'key' => $definition['key'],
                                'name' => $definition['name'],
                                'usage_count' => $definition['metafields_count'],
                                'owner_type' => $ownerType,
                            ];
                        }
                    }
                }
            }

            // Sort most used by usage count
            usort($stats['most_used'], function ($a, $b) {
                return $b['usage_count'] <=> $a['usage_count'];
            });

            $stats['most_used'] = array_slice($stats['most_used'], 0, 10);

            return [
                'success' => true,
                'statistics' => $stats,
            ];
        });
    }

    /**
     * 🏗️ BUILD METAFIELD DEFINITION INPUT
     *
     * Builds metafield definition input for GraphQL mutations
     *
     * @param  array<string, mixed>  $definition  Definition data
     * @return array<string, mixed>
     */
    protected function buildMetafieldDefinitionInput(array $definition): array
    {
        $input = [
            'namespace' => $definition['namespace'],
            'key' => $definition['key'],
            'name' => $definition['name'],
            'type' => $definition['type'],
            'ownerType' => $definition['owner_type'] ?? 'PRODUCT',
        ];

        if (isset($definition['description'])) {
            $input['description'] = $definition['description'];
        }

        if (isset($definition['validations'])) {
            $input['validations'] = $definition['validations'];
        }

        if (isset($definition['access'])) {
            $input['access'] = $definition['access'];
        }

        if (isset($definition['use_as_collection_condition'])) {
            $input['useAsCollectionCondition'] = $definition['use_as_collection_condition'];
        }

        return $input;
    }

    /**
     * ✨ TRANSFORM METAFIELD DEFINITION
     *
     * Transforms a raw metafield definition into a structured format
     *
     * @param  array<string, mixed>  $definition  Raw definition data
     * @return array<string, mixed>
     */
    protected function transformMetafieldDefinition(array $definition): array
    {
        return [
            'id' => $definition['id'],
            'namespace' => $definition['namespace'],
            'key' => $definition['key'],
            'name' => $definition['name'],
            'description' => $definition['description'] ?? '',
            'type' => $definition['type'],
            'owner_type' => $definition['ownerType'],
            'validations' => $definition['validations'] ?? [],
            'access' => $definition['access'] ?? null,
            'use_as_collection_condition' => $definition['useAsCollectionCondition'] ?? false,
            'pinned_position' => $definition['pinnedPosition'] ?? null,
            'metafields_count' => $definition['metafieldsCount'] ?? 0,
        ];
    }

    /**
     * 📂 GROUP TYPES BY CATEGORY
     *
     * Groups metafield types by their category
     *
     * @param  array<array<string, mixed>>  $types  Metafield types
     * @return array<string, array<array<string, mixed>>>
     */
    protected function groupTypesByCategory(array $types): array
    {
        $grouped = [];

        foreach ($types as $type) {
            $category = $type['category'];
            if (! isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $type;
        }

        return $grouped;
    }
}
