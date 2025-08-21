<?php

namespace App\Services\Shopify\API;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 📋 SHOPIFY VALUE LISTS API (VL11 EQUIVALENT)
 *
 * Handles Shopify taxonomy values, metaobjects, and choice lists.
 * Equivalent to Mirakl's VL11 (Value Lists) API functionality.
 */
class ValueListsApi extends BaseShopifyApi
{
    /**
     * 📋 GET TAXONOMY VALUES
     *
     * Retrieves taxonomy values for a specific attribute handle
     *
     * @param  string  $attributeHandle  Taxonomy attribute handle
     * @param  array<string, mixed>  $options  Query options
     * @return array<string, mixed>
     */
    public function getTaxonomyValues(string $attributeHandle, array $options = []): array
    {
        $cacheKey = "shopify_taxonomy_values_{$this->shopDomain}_{$attributeHandle}";

        return Cache::remember($cacheKey, 300, function () use ($attributeHandle) {
            // First, get categories that might contain the attribute
            $graphqlQuery = <<<'GRAPHQL'
            query GetTaxonomyValues {
                taxonomy {
                    categories(first: 250) {
                        edges {
                            node {
                                id
                                name
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
                                                options {
                                                    key
                                                    value
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

            $result = $this->graphql($graphqlQuery);

            if ($result['success']) {
                $categories = $result['data']['taxonomy']['categories']['edges'] ?? [];
                $values = [];

                foreach ($categories as $categoryEdge) {
                    $category = $categoryEdge['node'];
                    $attributes = $category['attributes']['edges'] ?? [];

                    foreach ($attributes as $attrEdge) {
                        $attribute = $attrEdge['node'];

                        if (($attribute['handle'] ?? '') === $attributeHandle) {
                            $values[] = [
                                'category_id' => $category['id'],
                                'category_name' => $category['name'],
                                'attribute' => $this->transformTaxonomyAttribute($attribute),
                            ];
                        }
                    }
                }

                Log::info('Taxonomy values retrieved', [
                    'shop_domain' => $this->shopDomain,
                    'attribute_handle' => $attributeHandle,
                    'values_count' => count($values),
                ]);

                return [
                    'success' => true,
                    'attribute_handle' => $attributeHandle,
                    'values' => $values,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to retrieve taxonomy values',
                'attribute_handle' => $attributeHandle,
                'values' => [],
            ];
        });
    }

    /**
     * ✨ CREATE METAOBJECT
     *
     * Creates a metaobject that can serve as a structured value list
     *
     * @param  string  $type  Metaobject type
     * @param  array<string, mixed>  $fields  Field data
     * @return array<string, mixed>
     */
    public function createMetaobject(string $type, array $fields): array
    {
        $mutation = <<<'GRAPHQL'
        mutation CreateMetaobject($metaobject: MetaobjectCreateInput!) {
            metaobjectCreate(metaobject: $metaobject) {
                metaobject {
                    id
                    type
                    handle
                    displayName
                    fields {
                        key
                        value
                        type
                    }
                    updatedAt
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
            'metaobject' => [
                'type' => $type,
                'fields' => $this->buildMetaobjectFields($fields),
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['metaobjectCreate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metaobject creation failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $metaobject = $result['data']['metaobjectCreate']['metaobject'];

            Log::info('Metaobject created', [
                'shop_domain' => $this->shopDomain,
                'metaobject_id' => $metaobject['id'],
                'type' => $type,
            ]);

            return [
                'success' => true,
                'metaobject' => $this->transformMetaobject($metaobject),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create metaobject',
        ];
    }

    /**
     * 🔄 UPDATE METAOBJECT
     *
     * Updates an existing metaobject
     *
     * @param  string  $metaobjectId  Metaobject GraphQL ID
     * @param  array<string, mixed>  $fields  Updated field data
     * @return array<string, mixed>
     */
    public function updateMetaobject(string $metaobjectId, array $fields): array
    {
        $mutation = <<<'GRAPHQL'
        mutation UpdateMetaobject($id: ID!, $metaobject: MetaobjectUpdateInput!) {
            metaobjectUpdate(id: $id, metaobject: $metaobject) {
                metaobject {
                    id
                    type
                    handle
                    displayName
                    fields {
                        key
                        value
                        type
                    }
                    updatedAt
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
            'id' => $metaobjectId,
            'metaobject' => [
                'fields' => $this->buildMetaobjectFields($fields),
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['metaobjectUpdate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Metaobject update failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $metaobject = $result['data']['metaobjectUpdate']['metaobject'];

            Log::info('Metaobject updated', [
                'shop_domain' => $this->shopDomain,
                'metaobject_id' => $metaobjectId,
            ]);

            return [
                'success' => true,
                'metaobject' => $this->transformMetaobject($metaobject),
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update metaobject',
        ];
    }

    /**
     * 📋 GET CHOICE LIST VALUES
     *
     * Retrieves predefined choice values for a metafield definition
     *
     * @param  string  $metafieldDefinitionId  Metafield definition GraphQL ID
     * @return array<string, mixed>
     */
    public function getChoiceListValues(string $metafieldDefinitionId): array
    {
        $cacheKey = "shopify_choice_list_{$this->shopDomain}_{$metafieldDefinitionId}";

        return Cache::remember($cacheKey, 300, function () use ($metafieldDefinitionId) {
            $graphqlQuery = <<<GRAPHQL
            query GetChoiceListValues {
                metafieldDefinition(id: "$metafieldDefinitionId") {
                    id
                    namespace
                    key
                    name
                    type {
                        name
                        category
                        supportedValidations {
                            name
                            type
                        }
                    }
                    validations {
                        name
                        type
                        value
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($graphqlQuery);

            if ($result['success']) {
                $definition = $result['data']['metafieldDefinition'] ?? null;

                if (! $definition) {
                    return [
                        'success' => false,
                        'error' => 'Metafield definition not found',
                        'definition_id' => $metafieldDefinitionId,
                        'values' => [],
                    ];
                }

                // Extract choice values from validations
                $choices = [];
                foreach ($definition['validations'] as $validation) {
                    if ($validation['name'] === 'choices' && isset($validation['value'])) {
                        $choiceData = json_decode($validation['value'], true);
                        if (is_array($choiceData)) {
                            $choices = $choiceData;
                        }
                    }
                }

                return [
                    'success' => true,
                    'definition_id' => $metafieldDefinitionId,
                    'definition' => [
                        'id' => $definition['id'],
                        'namespace' => $definition['namespace'],
                        'key' => $definition['key'],
                        'name' => $definition['name'],
                        'type' => $definition['type'],
                    ],
                    'values' => $choices,
                ];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Failed to retrieve choice list values',
                'definition_id' => $metafieldDefinitionId,
                'values' => [],
            ];
        });
    }

    /**
     * ✅ VALIDATE VALUE LIST
     *
     * Validates values against constraints for a metafield definition
     *
     * @param  array<mixed>  $values  Values to validate
     * @param  array<string, mixed>  $constraints  Validation constraints
     * @return array<string, mixed>
     */
    public function validateValueList(array $values, array $constraints): array
    {
        $errors = [];
        $warnings = [];

        // Check list size constraints
        if (isset($constraints['min']) && count($values) < $constraints['min']) {
            $errors[] = "Value list must contain at least {$constraints['min']} items";
        }

        if (isset($constraints['max']) && count($values) > $constraints['max']) {
            $errors[] = "Value list cannot contain more than {$constraints['max']} items";
        }

        // Check for duplicate values
        $uniqueValues = array_unique($values);
        if (count($uniqueValues) !== count($values)) {
            $warnings[] = 'Duplicate values detected in list';
        }

        // Check value format constraints
        if (isset($constraints['format'])) {
            foreach ($values as $index => $value) {
                if (! $this->validateValueFormat($value, $constraints['format'])) {
                    $errors[] = "Value at index {$index} does not match required format: {$constraints['format']}";
                }
            }
        }

        // Check allowed values
        if (isset($constraints['allowed_values'])) {
            foreach ($values as $index => $value) {
                if (! in_array($value, $constraints['allowed_values'])) {
                    $errors[] = "Value '{$value}' at index {$index} is not in allowed values list";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'values_count' => count($values),
            'unique_values_count' => count($uniqueValues),
        ];
    }

    /**
     * 🔗 CREATE VALUE LIST COLLECTION
     *
     * Creates a smart collection based on value list conditions
     *
     * @param  string  $collectionName  Collection name
     * @param  string  $metafieldDefinitionId  Metafield definition to use for rules
     * @param  array<string>  $values  Values to match
     * @param  array<string, mixed>  $options  Collection options
     * @return array<string, mixed>
     */
    public function createValueListCollection(
        string $collectionName,
        string $metafieldDefinitionId,
        array $values,
        array $options = []
    ): array {
        $rules = [];

        foreach ($values as $value) {
            $rules[] = [
                'column' => 'PRODUCT_METAFIELD_DEFINITION',
                'relation' => 'EQUALS',
                'condition' => $value,
                'conditionObjectId' => $metafieldDefinitionId,
            ];
        }

        $mutation = <<<'GRAPHQL'
        mutation CreateValueListCollection($input: CollectionInput!) {
            collectionCreate(input: $input) {
                collection {
                    id
                    title
                    handle
                    ruleSet {
                        appliedDisjunctively
                        rules {
                            column
                            relation
                            condition
                            conditionObject {
                                ... on CollectionRuleMetafieldCondition {
                                    metafieldDefinition {
                                        id
                                        name
                                        namespace
                                        key
                                    }
                                }
                            }
                        }
                    }
                    productsCount
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
                'title' => $collectionName,
                'handle' => $options['handle'] ?? str_replace(' ', '-', strtolower($collectionName)),
                'descriptionHtml' => $options['description'] ?? "Smart collection based on {$collectionName} values",
                'ruleSet' => [
                    'appliedDisjunctively' => $options['any_match'] ?? true, // OR vs AND
                    'rules' => $rules,
                ],
            ],
        ];

        $result = $this->graphql($mutation, $variables);

        if ($result['success']) {
            $userErrors = $result['data']['collectionCreate']['userErrors'] ?? [];

            if (! empty($userErrors)) {
                return [
                    'success' => false,
                    'error' => 'Collection creation failed: '.collect($userErrors)->pluck('message')->implode(', '),
                    'user_errors' => $userErrors,
                ];
            }

            $collection = $result['data']['collectionCreate']['collection'];

            Log::info('Value list collection created', [
                'shop_domain' => $this->shopDomain,
                'collection_id' => $collection['id'],
                'collection_name' => $collectionName,
                'values_count' => count($values),
            ]);

            return [
                'success' => true,
                'collection' => $collection,
                'metafield_definition_id' => $metafieldDefinitionId,
                'values' => $values,
            ];
        }

        return [
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create value list collection',
        ];
    }

    /**
     * 📊 GET VALUE LIST STATISTICS
     *
     * Get statistics about value lists and their usage
     *
     * @return array<string, mixed>
     */
    public function getValueListStatistics(): array
    {
        $cacheKey = "shopify_value_list_stats_{$this->shopDomain}";

        return Cache::remember($cacheKey, 600, function () {
            // Get metaobject types that could serve as value lists
            $metaobjectQuery = <<<'GRAPHQL'
            query GetMetaobjectDefinitions {
                metaobjectDefinitions(first: 50) {
                    edges {
                        node {
                            id
                            type
                            name
                            fieldDefinitions {
                                key
                                name
                                type {
                                    name
                                }
                            }
                            metaobjectsCount
                        }
                    }
                }
            }
            GRAPHQL;

            $result = $this->graphql($metaobjectQuery);

            $stats = [
                'metaobject_definitions' => 0,
                'total_metaobjects' => 0,
                'choice_list_definitions' => 0,
                'taxonomy_attributes' => 0,
                'value_list_types' => [],
            ];

            if ($result['success']) {
                $definitions = $result['data']['metaobjectDefinitions']['edges'] ?? [];

                foreach ($definitions as $edge) {
                    $definition = $edge['node'];
                    $stats['metaobject_definitions']++;
                    $stats['total_metaobjects'] += $definition['metaobjectsCount'];

                    $stats['value_list_types'][] = [
                        'type' => $definition['type'],
                        'name' => $definition['name'],
                        'fields_count' => count($definition['fieldDefinitions']),
                        'metaobjects_count' => $definition['metaobjectsCount'],
                    ];
                }
            }

            // Get taxonomy statistics (approximated)
            $taxonomyQuery = <<<'GRAPHQL'
            query GetTaxonomyStats {
                taxonomy {
                    categories(first: 50) {
                        edges {
                            node {
                                attributes {
                                    edges {
                                        node {
                                            ... on TaxonomyChoiceListAttribute {
                                                id
                                                choices {
                                                    value
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

            $taxonomyResult = $this->graphql($taxonomyQuery);

            if ($taxonomyResult['success']) {
                $categories = $taxonomyResult['data']['taxonomy']['categories']['edges'] ?? [];

                foreach ($categories as $categoryEdge) {
                    $attributes = $categoryEdge['node']['attributes']['edges'] ?? [];

                    foreach ($attributes as $attrEdge) {
                        if (isset($attrEdge['node']['choices'])) {
                            $stats['choice_list_definitions']++;
                            $stats['taxonomy_attributes']++;
                        }
                    }
                }
            }

            return [
                'success' => true,
                'statistics' => $stats,
            ];
        });
    }

    /**
     * 🏗️ BUILD METAOBJECT FIELDS
     *
     * Builds metaobject field input for GraphQL mutations
     *
     * @param  array<string, mixed>  $fields  Field data
     * @return array<array<string, mixed>>
     */
    protected function buildMetaobjectFields(array $fields): array
    {
        $metaobjectFields = [];

        foreach ($fields as $key => $value) {
            $metaobjectFields[] = [
                'key' => $key,
                'value' => is_string($value) ? $value : json_encode($value),
            ];
        }

        return $metaobjectFields;
    }

    /**
     * ✨ TRANSFORM METAOBJECT
     *
     * Transforms a raw metaobject into a structured format
     *
     * @param  array<string, mixed>  $metaobject  Raw metaobject data
     * @return array<string, mixed>
     */
    protected function transformMetaobject(array $metaobject): array
    {
        $fields = [];

        foreach ($metaobject['fields'] as $field) {
            $fields[$field['key']] = [
                'value' => $field['value'],
                'type' => $field['type'],
            ];
        }

        return [
            'id' => $metaobject['id'],
            'type' => $metaobject['type'],
            'handle' => $metaobject['handle'] ?? null,
            'display_name' => $metaobject['displayName'] ?? null,
            'fields' => $fields,
            'updated_at' => $metaobject['updatedAt'] ?? null,
        ];
    }

    /**
     * ✨ TRANSFORM TAXONOMY ATTRIBUTE
     *
     * Transforms a taxonomy attribute into a structured format
     *
     * @param  array<string, mixed>  $attribute  Raw attribute data
     * @return array<string, mixed>
     */
    protected function transformTaxonomyAttribute(array $attribute): array
    {
        $transformed = [
            'id' => $attribute['id'],
            'name' => $attribute['name'],
            'handle' => $attribute['handle'],
            'type' => 'attribute',
        ];

        // Handle choice list attributes
        if (isset($attribute['choices'])) {
            $transformed['type'] = 'choice_list';
            $transformed['choices'] = $attribute['choices'];
            $transformed['values'] = array_column($attribute['choices'], 'value');
        }

        // Handle measurement attributes
        if (isset($attribute['units'])) {
            $transformed['type'] = 'measurement';
            $transformed['units'] = $attribute['units'];

            if (isset($attribute['options'])) {
                $transformed['options'] = $attribute['options'];
            }
        }

        return $transformed;
    }

    /**
     * ✅ VALIDATE VALUE FORMAT
     *
     * Validates a single value against a format constraint
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $format  Format constraint
     */
    protected function validateValueFormat($value, string $format): bool
    {
        switch ($format) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'number':
                return is_numeric($value);

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'json':
                if (! is_string($value)) {
                    return false;
                }
                json_decode($value);

                return json_last_error() === JSON_ERROR_NONE;

            case 'hex_color':
                return preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;

            default:
                return true; // Unknown format, assume valid
        }
    }
}
