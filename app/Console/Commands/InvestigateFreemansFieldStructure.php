<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

/**
 * 🔍 INVESTIGATE FREEMANS FIELD STRUCTURE
 *
 * Deep investigation of Freemans field structure for variant grouping
 */
class InvestigateFreemansFieldStructure extends Command
{
    protected $signature = 'investigate:freemans-fields';

    protected $description = 'Investigate Freemans field structure to understand variant grouping';

    public function handle(): int
    {
        $this->info('🔍 Investigating Freemans Field Structure for Variant Grouping');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $config = [
            'base_url' => config('services.mirakl_operators.freemans.base_url'),
            'api_key' => config('services.mirakl_operators.freemans.api_key'),
            'store_id' => config('services.mirakl_operators.freemans.store_id'),
        ];

        if (! $config['base_url'] || ! $config['api_key'] || ! $config['store_id']) {
            $this->error('❌ Freemans configuration incomplete');

            return 1;
        }

        $client = new Client([
            'base_uri' => $config['base_url'],
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $config['api_key'],
            ],
        ]);

        // Step 1: Get product attributes for H02 category
        $this->info('📋 STEP 1: Getting product attributes for H02 category');
        $this->getProductAttributes($client, $config);

        // Step 2: Get existing products to see field structure
        $this->info('📦 STEP 2: Getting existing products to understand structure');
        $this->getExistingProducts($client, $config);

        // Step 3: Get value lists for LIST fields
        $this->info('📊 STEP 3: Getting value lists for LIST fields');
        $this->getValueLists($client, $config);

        // Step 4: Check offers structure
        $this->info('🏪 STEP 4: Checking offers structure');
        $this->getOffersStructure($client, $config);

        return 0;
    }

    protected function getProductAttributes(Client $client, array $config): void
    {
        try {
            $response = $client->request('GET', '/api/products/attributes', [
                'query' => [
                    'hierarchy' => 'H02',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $attributes = $data['attributes'] ?? [];

            $this->info('   ✅ Found {'.count($attributes).'} attributes for H02 category');

            // Group by required/optional
            $required = collect($attributes)->filter(fn ($attr) => $attr['required'])->values();
            $optional = collect($attributes)->filter(fn ($attr) => ! $attr['required'])->values();

            $this->info("   📋 Required fields: {$required->count()}");
            $this->info("   📋 Optional fields: {$optional->count()}");

            // Look for parent/variant relationship fields
            $this->newLine();
            $this->info('🔍 Looking for parent/variant relationship fields:');

            $parentFields = collect($attributes)->filter(function ($attr) {
                $code = strtolower($attr['code']);

                return str_contains($code, 'parent') ||
                       str_contains($code, 'group') ||
                       str_contains($code, 'reference') ||
                       str_contains($code, 'variant');
            });

            foreach ($parentFields as $field) {
                $this->info("   🏷️  {$field['code']} ({$field['type']}) - ".($field['required'] ? 'REQUIRED' : 'OPTIONAL'));
                if (isset($field['description'])) {
                    $this->info("      📝 {$field['description']}");
                }
            }

            // Show all required fields
            $this->newLine();
            $this->info('📋 ALL REQUIRED FIELDS:');
            foreach ($required as $field) {
                $this->info("   ✅ {$field['code']} ({$field['type']})");
            }

            // Look for color/size fields
            $this->newLine();
            $this->info('🎨 Looking for color/size variant fields:');

            $variantFields = collect($attributes)->filter(function ($attr) {
                $code = strtolower($attr['code']);

                return str_contains($code, 'color') ||
                       str_contains($code, 'colour') ||
                       str_contains($code, 'size') ||
                       str_contains($code, 'fgh');
            });

            foreach ($variantFields as $field) {
                $this->info("   🎨 {$field['code']} ({$field['type']}) - ".($field['required'] ? 'REQUIRED' : 'OPTIONAL'));
                if (isset($field['description'])) {
                    $this->info("      📝 {$field['description']}");
                }
            }

        } catch (GuzzleException $e) {
            $this->error("   ❌ Failed to get product attributes: {$e->getMessage()}");
        }
        $this->newLine();
    }

    protected function getExistingProducts(Client $client, array $config): void
    {
        try {
            $response = $client->request('GET', '/api/products', [
                'query' => [
                    'shop' => $config['store_id'],
                    'limit' => 10,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $products = $data['products'] ?? [];

            $this->info('   ✅ Found {'.count($products).'} existing products');

            if (! empty($products)) {
                $this->info('🔍 Analyzing first product structure:');
                $firstProduct = $products[0];

                foreach ($firstProduct as $key => $value) {
                    if (is_array($value)) {
                        $this->info("   📋 {$key}: [array with ".count($value).' items]');
                    } else {
                        $this->info("   📋 {$key}: ".(strlen($value) > 100 ? substr($value, 0, 100).'...' : $value));
                    }
                }

                // Look for parent/grouping patterns
                $this->newLine();
                $this->info('🔍 Looking for parent/grouping patterns in existing products:');

                foreach ($products as $i => $product) {
                    $parentFields = [];
                    foreach ($product as $key => $value) {
                        if (str_contains(strtolower($key), 'parent') ||
                            str_contains(strtolower($key), 'group') ||
                            str_contains(strtolower($key), 'reference') ||
                            str_contains(strtolower($key), 'variant')) {
                            $parentFields[$key] = $value;
                        }
                    }

                    if (! empty($parentFields)) {
                        $this->info("   Product {$i}: ".json_encode($parentFields));
                    }
                }
            }

        } catch (GuzzleException $e) {
            $this->error("   ❌ Failed to get existing products: {$e->getMessage()}");
        }
        $this->newLine();
    }

    protected function getValueLists(Client $client, array $config): void
    {
        try {
            $response = $client->request('GET', '/api/values_lists');
            $data = json_decode($response->getBody()->getContents(), true);
            $valueLists = $data['values_lists'] ?? [];

            $this->info('   ✅ Found {'.count($valueLists).'} value lists');

            // Look for color and size value lists
            foreach ($valueLists as $valueList) {
                $code = strtolower($valueList['code']);
                if (str_contains($code, 'color') ||
                    str_contains($code, 'colour') ||
                    str_contains($code, 'size') ||
                    str_contains($code, 'fgh')) {

                    $valuesCount = count($valueList['values'] ?? []);
                    $this->info("   🎨 {$valueList['code']}: {$valuesCount} values");

                    // Show first few values
                    if (! empty($valueList['values'])) {
                        $firstFew = array_slice($valueList['values'], 0, 5);
                        foreach ($firstFew as $value) {
                            $this->info("      • {$value['code']} - {$value['label']}");
                        }
                        if (count($valueList['values']) > 5) {
                            $this->info('      ... and '.(count($valueList['values']) - 5).' more');
                        }
                    }
                }
            }

        } catch (GuzzleException $e) {
            $this->error("   ❌ Failed to get value lists: {$e->getMessage()}");
        }
        $this->newLine();
    }

    protected function getOffersStructure(Client $client, array $config): void
    {
        try {
            $response = $client->request('GET', '/api/offers', [
                'query' => [
                    'shop' => $config['store_id'],
                    'limit' => 5,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $offers = $data['offers'] ?? [];

            $this->info('   ✅ Found {'.count($offers).'} existing offers');

            if (! empty($offers)) {
                $this->info('🔍 Analyzing first offer structure:');
                $firstOffer = $offers[0];

                foreach ($firstOffer as $key => $value) {
                    if (is_array($value)) {
                        $this->info("   📋 {$key}: [array with ".count($value).' items]');
                        if ($key === 'additional_fields') {
                            foreach ($value as $field) {
                                $this->info("      • {$field['code']}: {$field['value']}");
                            }
                        }
                    } else {
                        $this->info("   📋 {$key}: ".(strlen($value) > 100 ? substr($value, 0, 100).'...' : $value));
                    }
                }
            }

        } catch (GuzzleException $e) {
            $this->error("   ❌ Failed to get offers: {$e->getMessage()}");
        }
        $this->newLine();
    }
}
