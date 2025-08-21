<?php

namespace App\Console\Commands;

use App\Services\Shopify\API\AttributesApi;
use App\Services\Shopify\API\CategoryApi;
use App\Services\Shopify\API\ValueListsApi;
use Illuminate\Console\Command;

class TestShopifyTaxonomyApis extends Command
{
    protected $signature = 'shopify:test-taxonomy {shop_domain}';

    protected $description = 'Test Shopify taxonomy APIs - categories, attributes, and values';

    public function handle()
    {
        $shopDomain = $this->argument('shop_domain');

        $this->info("🌟 Testing Shopify Taxonomy APIs for: {$shopDomain}");
        $this->newLine();

        try {
            // Initialize APIs
            $categoryApi = new CategoryApi($shopDomain);
            $attributesApi = new AttributesApi($shopDomain);
            $valueListsApi = new ValueListsApi($shopDomain);

            // Test connection first
            $this->testConnection($categoryApi);

            // Step 1: Get Window Treatments Category
            $this->getWindowTreatmentsCategory($categoryApi);

            // Step 2: Get Metafield Definitions (Attributes)
            $this->getAttributes($attributesApi);

            // Step 3: Get Taxonomy Values
            $this->getTaxonomyValues($valueListsApi);

            $this->newLine();
            $this->info('✅ All taxonomy API tests completed successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Error testing Shopify taxonomy APIs: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testConnection(CategoryApi $categoryApi): void
    {
        $this->info('🔍 Testing API connection...');

        $connection = $categoryApi->testConnection();

        if ($connection['success']) {
            $this->info('✅ API connection successful');
            $this->line("   Shop: {$connection['shop_data']['name']}");
            $this->line("   Domain: {$connection['shop_data']['domain']}");
            $this->line("   Plan: {$connection['shop_data']['plan_name']}");
        } else {
            $this->error('❌ API connection failed: '.$connection['error']);
            throw new \Exception('Cannot proceed without valid API connection');
        }

        $this->newLine();
    }

    private function getWindowTreatmentsCategory(CategoryApi $categoryApi): void
    {
        $this->info('🏷️ Step 1: Getting Window Treatments Category...');

        // First, search for window treatments
        $searchResult = $categoryApi->searchCategories('Window Treatments');

        if ($searchResult['success'] && ! empty($searchResult['categories'])) {
            $this->info('✅ Found Window Treatments categories:');

            foreach ($searchResult['categories'] as $category) {
                $this->line("   • {$category['full_name']} (Level {$category['level']})");
                $this->line("     ID: {$category['id']}");
                $this->line('     Leaf: '.($category['is_leaf'] ? 'Yes' : 'No'));
                $this->line('     Attributes: '.count($category['attributes']));

                // If this category has attributes, show them
                if (! empty($category['attributes'])) {
                    foreach ($category['attributes'] as $attr) {
                        $this->line("       - {$attr['name']} ({$attr['handle']}) - Type: {$attr['type']}");
                    }
                }
                $this->newLine();
            }

            // Get detailed hierarchy for the first category
            $firstCategory = $searchResult['categories'][0];
            $this->info("📊 Getting hierarchy for: {$firstCategory['name']}");

            $hierarchy = $categoryApi->getCategoryHierarchy($firstCategory['id']);

            if ($hierarchy['success']) {
                $this->line('   Children: '.count($hierarchy['children']));
                $this->line('   Siblings: '.count($hierarchy['siblings']));
                $this->line('   Descendants: '.count($hierarchy['descendants']));

                if (! empty($hierarchy['children'])) {
                    $this->line('   Child categories:');
                    foreach ($hierarchy['children'] as $child) {
                        $this->line("     - {$child['name']}");
                    }
                }
            }

        } else {
            $this->warn('⚠️ No Window Treatments categories found');

            // Get all categories to see what's available
            $this->info('📋 Getting all available categories...');
            $allCategories = $categoryApi->getCategories(['first' => 20]);

            if ($allCategories['success']) {
                $this->line('Available categories:');
                foreach ($allCategories['categories'] as $category) {
                    $this->line("   • {$category['full_name']}");
                }
            }
        }

        // Get category statistics
        $this->info('📊 Getting category statistics...');
        $stats = $categoryApi->getCategoryStatistics();

        if ($stats['success']) {
            $statistics = $stats['statistics'];
            $this->line("   Total Categories: {$statistics['total_categories']}");
            $this->line("   Root Categories: {$statistics['root_categories']}");
            $this->line("   Leaf Categories: {$statistics['leaf_categories']}");
            $this->line('   Level Distribution: '.json_encode($statistics['levels']));
        }

        $this->newLine();
    }

    private function getAttributes(AttributesApi $attributesApi): void
    {
        $this->info('🏷️ Step 2: Getting Metafield Definitions (Attributes)...');

        // Get product-level attributes
        $productAttrs = $attributesApi->getMetafieldDefinitions('PRODUCT');

        if ($productAttrs['success']) {
            $this->info('✅ Product-level metafield definitions:');

            if (! empty($productAttrs['definitions'])) {
                foreach ($productAttrs['definitions'] as $definition) {
                    $this->line("   • {$definition['name']} ({$definition['namespace']}.{$definition['key']})");
                    $this->line("     Type: {$definition['type']['name']} | Category: {$definition['type']['category']}");
                    $this->line("     Used in: {$definition['metafields_count']} products");
                    $this->line('     Collection condition: '.($definition['use_as_collection_condition'] ? 'Yes' : 'No'));

                    if (! empty($definition['validations'])) {
                        $this->line('     Validations: '.count($definition['validations']));
                    }
                    $this->newLine();
                }
            } else {
                $this->line('   No custom product metafield definitions found');
            }
        }

        // Get variant-level attributes
        $variantAttrs = $attributesApi->getMetafieldDefinitions('PRODUCTVARIANT');

        if ($variantAttrs['success']) {
            $this->info('✅ Variant-level metafield definitions:');

            if (! empty($variantAttrs['definitions'])) {
                foreach ($variantAttrs['definitions'] as $definition) {
                    $this->line("   • {$definition['name']} ({$definition['namespace']}.{$definition['key']})");
                    $this->line("     Type: {$definition['type']['name']}");
                    $this->line("     Used in: {$definition['metafields_count']} variants");
                }
            } else {
                $this->line('   No custom variant metafield definitions found');
            }
        }

        // Get available metafield types
        $this->info('📋 Getting available metafield types...');
        $types = $attributesApi->getMetafieldTypes();

        if ($types['success']) {
            $grouped = $types['grouped_by_category'];

            foreach ($grouped as $category => $categoryTypes) {
                $this->line("   {$category} ({".count($categoryTypes).' types):');
                foreach (array_slice($categoryTypes, 0, 3) as $type) { // Show first 3 of each category
                    $this->line("     - {$type['name']}");
                }
                if (count($categoryTypes) > 3) {
                    $this->line('     ... and '.(count($categoryTypes) - 3).' more');
                }
            }
        }

        // Get attribute statistics
        $this->info('📊 Getting attribute statistics...');
        $attrStats = $attributesApi->getAttributeStatistics();

        if ($attrStats['success']) {
            $statistics = $attrStats['statistics'];
            $this->line("   Total Definitions: {$statistics['total_definitions']}");
            $this->line('   By Owner Type: '.json_encode($statistics['by_owner_type']));
            $this->line('   By Type Category: '.json_encode($statistics['by_type_category']));

            if (! empty($statistics['most_used'])) {
                $this->line('   Most Used Attributes:');
                foreach (array_slice($statistics['most_used'], 0, 3) as $attr) {
                    $this->line("     - {$attr['name']}: {$attr['usage_count']} uses");
                }
            }
        }

        $this->newLine();
    }

    private function getTaxonomyValues(ValueListsApi $valueListsApi): void
    {
        $this->info('📋 Step 3: Getting Taxonomy Values...');

        // Common attributes to look for
        $attributesToCheck = ['color', 'material', 'size', 'style', 'pattern'];

        foreach ($attributesToCheck as $attributeHandle) {
            $this->line("🔍 Checking taxonomy values for: {$attributeHandle}");

            $values = $valueListsApi->getTaxonomyValues($attributeHandle);

            if ($values['success'] && ! empty($values['values'])) {
                $this->info("✅ Found values for {$attributeHandle}:");

                foreach ($values['values'] as $valueData) {
                    $this->line("   Category: {$valueData['category_name']}");
                    $attribute = $valueData['attribute'];
                    $this->line("   Attribute: {$attribute['name']} ({$attribute['type']})");

                    if ($attribute['type'] === 'choice_list' && ! empty($attribute['choices'])) {
                        $this->line('   Choices: '.implode(', ', array_column($attribute['choices'], 'label')));
                    } elseif ($attribute['type'] === 'measurement' && ! empty($attribute['units'])) {
                        $this->line('   Units: '.implode(', ', $attribute['units']));
                    }

                    $this->newLine();
                }
            } else {
                $this->line("   No taxonomy values found for {$attributeHandle}");
            }
        }

        // Get value list statistics
        $this->info('📊 Getting value list statistics...');
        $valueStats = $valueListsApi->getValueListStatistics();

        if ($valueStats['success']) {
            $statistics = $valueStats['statistics'];
            $this->line("   Metaobject Definitions: {$statistics['metaobject_definitions']}");
            $this->line("   Total Metaobjects: {$statistics['total_metaobjects']}");
            $this->line("   Choice List Definitions: {$statistics['choice_list_definitions']}");
            $this->line("   Taxonomy Attributes: {$statistics['taxonomy_attributes']}");

            if (! empty($statistics['value_list_types'])) {
                $this->line('   Value List Types:');
                foreach (array_slice($statistics['value_list_types'], 0, 3) as $type) {
                    $this->line("     - {$type['name']}: {$type['metaobjects_count']} objects");
                }
            }
        }

        $this->newLine();
    }
}
