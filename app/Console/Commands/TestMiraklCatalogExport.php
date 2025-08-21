<?php

namespace App\Console\Commands;

use App\Services\Mirakl\UniversalMiraklCsvGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * 🔍 TEST MIRAKL CATALOG EXPORT (H11/PM11/VL11 APIs)
 *
 * Test the enhanced Universal Mirakl CSV Generator with proper
 * H11 (Categories), PM11 (Attributes), and VL11 (Value Lists) API implementation
 */
class TestMiraklCatalogExport extends Command
{
    protected $signature = 'test:mirakl-catalog-export 
                            {operator : Operator to test (freemans, debenhams, bq)}
                            {--hierarchy= : Category hierarchy to filter by}
                            {--max-level= : Max tree depth to retrieve}
                            {--with-roles : Get only attributes with roles}
                            {--all-attributes : Get all operator attributes (not just sales channel)}
                            {--value-list= : Specific value list code to retrieve}
                            {--export-complete : Export complete catalog structure}
                            {--save-json : Save results to JSON files}';

    protected $description = 'Test enhanced Mirakl catalog export using H11/PM11/VL11 APIs';

    public function handle(): int
    {
        $operator = $this->argument('operator');
        $hierarchy = $this->option('hierarchy');
        $maxLevel = $this->option('max-level');
        $withRoles = $this->option('with-roles');
        $allAttributes = $this->option('all-attributes');
        $valueListCode = $this->option('value-list');
        $exportComplete = $this->option('export-complete');
        $saveJson = $this->option('save-json');

        $this->info("🔍 Testing Mirakl Catalog Export for {$operator}");
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        try {
            $generator = UniversalMiraklCsvGenerator::for($operator);

            if ($exportComplete) {
                return $this->exportCompleteCatalog($generator, $hierarchy, $saveJson);
            }

            return $this->exportIndividualAPIs($generator, $hierarchy, $maxLevel, $withRoles, $allAttributes, $valueListCode, $saveJson);

        } catch (\Exception $e) {
            $this->error("❌ Failed to test catalog export: {$e->getMessage()}");

            return 1;
        }
    }

    protected function exportCompleteCatalog(UniversalMiraklCsvGenerator $generator, ?string $hierarchy, bool $saveJson): int
    {
        $this->info('🔍 Exporting complete catalog structure...');

        $options = [];
        if ($this->option('max-level')) {
            $options['max_level'] = (int) $this->option('max-level');
        }
        if ($this->option('with-roles')) {
            $options['with_roles'] = true;
        }
        if ($this->option('all-attributes')) {
            $options['all_operator_attributes'] = true;
        }

        $catalog = $generator->exportCompleteCatalogStructure($hierarchy, $options);

        $this->displayCatalogSummary($catalog);

        if ($saveJson) {
            $this->saveCatalogToJson($catalog, $generator->operator);
        }

        return 0;
    }

    protected function exportIndividualAPIs(
        UniversalMiraklCsvGenerator $generator,
        ?string $hierarchy,
        ?string $maxLevel,
        bool $withRoles,
        bool $allAttributes,
        ?string $valueListCode,
        bool $saveJson
    ): int {
        $results = [];

        // Test H11 Categories API
        $this->info('🗂️ Testing H11 Categories API...');
        $categoryOptions = [];
        if ($maxLevel) {
            $categoryOptions['max_level'] = (int) $maxLevel;
        }

        $categories = $generator->exportCategories($hierarchy, $categoryOptions);
        $results['categories'] = $categories;

        $this->info('   ✅ Retrieved '.count($categories).' categories');
        if (! empty($categories)) {
            $this->line('   📋 Sample categories:');
            foreach (array_slice($categories, 0, 3) as $category) {
                $this->line("      • {$category['code']} - {$category['label']}");
            }
        }

        // Test PM11 Attributes API
        $this->info('📋 Testing PM11 Attributes API...');
        $attributeOptions = [];
        if ($maxLevel) {
            $attributeOptions['max_level'] = (int) $maxLevel;
        }
        if ($withRoles) {
            $attributeOptions['with_roles'] = true;
        }
        if ($allAttributes) {
            $attributeOptions['all_operator_attributes'] = true;
        }

        $attributes = $generator->exportAttributes($hierarchy, $attributeOptions);
        $results['attributes'] = $attributes;

        $this->info('   ✅ Retrieved '.count($attributes).' attributes');
        if (! empty($attributes)) {
            $required = collect($attributes)->where('required', true)->count();
            $optional = collect($attributes)->where('required', false)->count();
            $this->line("   📊 Required: {$required}, Optional: {$optional}");

            $this->line('   📋 Sample attributes:');
            foreach (array_slice($attributes, 0, 3) as $attribute) {
                $required = $attribute['required'] ? 'REQUIRED' : 'OPTIONAL';
                $this->line("      • {$attribute['code']} ({$attribute['type']}) - {$required}");
            }
        }

        // Test VL11 Value Lists API
        $this->info('📊 Testing VL11 Value Lists API...');
        $valueLists = $generator->exportValueLists($valueListCode);
        $results['value_lists'] = $valueLists;

        $this->info('   ✅ Retrieved '.count($valueLists).' value lists');
        if (! empty($valueLists)) {
            $this->line('   📋 Value lists:');
            foreach (array_slice($valueLists, 0, 5) as $code => $valueList) {
                $valuesCount = count($valueList['values'] ?? []);
                $this->line("      • {$code}: {$valuesCount} values");
            }
        }

        if ($saveJson) {
            $this->saveResultsToJson($results, $generator->operator);
        }

        return 0;
    }

    protected function displayCatalogSummary(array $catalog): void
    {
        $this->newLine();
        $this->info('📊 Catalog Export Summary:');
        $this->line("   Operator: {$catalog['operator']}");
        $this->line("   Export Time: {$catalog['export_timestamp']}");
        $this->line('   Categories: '.count($catalog['categories']));
        $this->line('   Attributes: '.count($catalog['attributes']));
        $this->line('   Value Lists: '.count($catalog['value_lists']));

        if (! empty($catalog['hierarchy'])) {
            $this->line("   Filtered by hierarchy: {$catalog['hierarchy']}");
        }

        // Show attribute breakdown
        if (! empty($catalog['attributes'])) {
            $attributes = collect($catalog['attributes']);
            $required = $attributes->where('required', true)->count();
            $optional = $attributes->where('required', false)->count();
            $this->line("   Required Attributes: {$required}");
            $this->line("   Optional Attributes: {$optional}");
        }
    }

    protected function saveCatalogToJson(array $catalog, string $operator): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $hierarchy = $catalog['hierarchy'] ? "_{$catalog['hierarchy']}" : '';
        $filename = "mirakl_catalog_{$operator}{$hierarchy}_{$timestamp}.json";
        $filepath = "exports/mirakl_catalogs/{$filename}";

        Storage::makeDirectory('exports/mirakl_catalogs');
        Storage::put($filepath, json_encode($catalog, JSON_PRETTY_PRINT));

        $this->info("💾 Complete catalog saved to: {$filepath}");
    }

    protected function saveResultsToJson(array $results, string $operator): void
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        foreach ($results as $type => $data) {
            if (! empty($data)) {
                $filename = "mirakl_{$type}_{$operator}_{$timestamp}.json";
                $filepath = "exports/mirakl_catalogs/{$filename}";

                Storage::makeDirectory('exports/mirakl_catalogs');
                Storage::put($filepath, json_encode($data, JSON_PRETTY_PRINT));

                $this->info("💾 {$type} data saved to: {$filepath}");
            }
        }
    }
}
