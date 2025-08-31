<?php

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductVariant;

describe('Product Model Attribute System Integration', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::factory()->create([
            'product_id' => $this->product->id,
            'sku' => 'TEST123-RED',
            'title' => 'Test Product - Red',
            'status' => 'active',
        ]);
    });

    test('product model excludes brand from fillable after refactor', function () {
        $fillable = (new Product)->getFillable();

        expect($fillable)->not()->toContain('brand');
    });

    test('product uses attributes system for brand instead of direct field', function () {
        // Verify no brand column in database
        $tableColumns = \Schema::getColumnListing('products');
        expect($tableColumns)->not()->toContain('brand');

        // Test that brand should use attributes system
        expect($this->product->getFillable())->not()->toContain('brand');
    });

    test('product can set attributes via attributes system', function () {
        // Mock attribute definition for brand
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $result = $this->product->setAttributeValue('brand', 'Test Brand');

        expect($result)->toBeInstanceOf(ProductAttribute::class);
        expect($this->product->getSmartAttributeValue('brand'))->toBe('Test Brand');
    });

    test('product smart brand value fallback works', function () {
        // Test without any brand value
        expect($this->product->getSmartBrandValue())->toBeNull();

        // Set brand via attributes and test fallback
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $this->product->setAttributeValue('brand', 'Attribute Brand');

        expect($this->product->fresh()->getSmartBrandValue())->toBe('Attribute Brand');
    });

    test('product can sync multiple attributes efficiently', function () {
        // Create attribute definitions
        $attributeKeys = ['brand', 'category', 'material'];
        foreach ($attributeKeys as $key) {
            if (! AttributeDefinition::where('key', $key)->exists()) {
                AttributeDefinition::create([
                    'key' => $key,
                    'name' => ucfirst($key),
                    'type' => 'string',
                    'group' => 'general',
                ]);
            }
        }

        $attributes = [
            'brand' => 'Test Brand',
            'category' => 'Electronics',
            'material' => 'Plastic',
        ];

        $results = $this->product->syncAttributes($attributes, ['source' => 'test']);

        expect($results['created'])->toHaveCount(3)
            ->toContain('brand', 'category', 'material')
            ->and($results['errors'])->toBeEmpty();
    });

    test('product can get typed attributes array', function () {
        // Set some attributes
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $this->product->setAttributeValue('brand', 'Test Brand');

        $attributes = $this->product->fresh()->getTypedAttributesArray();

        expect($attributes)->toBeArray()
            ->toHaveKey('brand')
            ->and($attributes['brand'])->toBe('Test Brand');
    });

    test('product validates all attributes correctly', function () {
        // Create valid attribute
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $this->product->setAttributeValue('brand', 'Valid Brand');

        $validationResults = $this->product->validateAllAttributes();

        expect($validationResults)->toHaveKey('valid')
            ->and($validationResults['valid'])->toBeTrue()
            ->and($validationResults['validated_count'])->toBeGreaterThan(0);
    });

    test('product can bulk update attributes', function () {
        // Create attribute definitions
        $attributeKeys = ['brand', 'warranty', 'origin_country'];
        foreach ($attributeKeys as $key) {
            if (! AttributeDefinition::where('key', $key)->exists()) {
                AttributeDefinition::create([
                    'key' => $key,
                    'name' => ucfirst(str_replace('_', ' ', $key)),
                    'type' => 'string',
                    'group' => 'general',
                ]);
            }
        }

        $updates = [
            'brand' => 'Updated Brand',
            'warranty' => '2 years',
            'origin_country' => 'USA',
        ];

        $results = $this->product->bulkUpdateAttributes($updates);

        expect($results['success'])->toHaveCount(3)
            ->and($results['errors'])->toBeEmpty()
            ->and($results['total'])->toBe(3);
    });

    test('product attributes statistics work correctly', function () {
        // Add some attributes
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $this->product->setAttributeValue('brand', 'Test Brand');

        $stats = $this->product->fresh()->getAttributesStatistics();

        expect($stats)->toHaveKey('total_attributes')
            ->and($stats['total_attributes'])->toBeGreaterThan(0)
            ->and($stats)->toHaveKey('valid_attributes')
            ->and($stats)->toHaveKey('completion_percentage');
    });

    test('product can search attributes', function () {
        // Create and set attribute
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        $this->product->setAttributeValue('brand', 'Searchable Brand');

        $searchResults = $this->product->fresh()->searchAttributes('Searchable');

        expect($searchResults)->not()->toBeEmpty();
    });

    test('variant inherits product attributes correctly', function () {
        // Create product-level attribute
        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
                'is_inheritable' => true,
            ]);
        }

        $this->product->setAttributeValue('brand', 'Product Brand');

        // Test variant inheritance (if InheritsAttributesTrait is working)
        if (method_exists($this->variant, 'getEffectiveAttributeValue')) {
            $effectiveValue = $this->variant->getEffectiveAttributeValue('brand');
            expect($effectiveValue)->toBe('Product Brand');
        }
    });

    test('product can get attributes by group', function () {
        // Create attributes in different groups
        $attributeDefinitions = [
            ['key' => 'brand', 'name' => 'Brand', 'group' => 'general'],
            ['key' => 'meta_title', 'name' => 'Meta Title', 'group' => 'seo'],
        ];

        foreach ($attributeDefinitions as $definition) {
            if (! AttributeDefinition::where('key', $definition['key'])->exists()) {
                AttributeDefinition::create(array_merge($definition, [
                    'type' => 'string',
                ]));
            }
        }

        $this->product->setAttributeValue('brand', 'Test Brand');
        $this->product->setAttributeValue('meta_title', 'Test Meta');

        $groupedAttributes = $this->product->fresh()->getAttributesByGroup();

        expect($groupedAttributes)->toHaveKey('general')
            ->and($groupedAttributes)->toHaveKey('seo');
    });

    test('product fillable excludes deprecated fields', function () {
        $fillable = (new Product)->getFillable();

        // These fields should use the attribute system instead
        $deprecatedFields = ['brand'];

        foreach ($deprecatedFields as $field) {
            expect($fillable)->not()->toContain($field);
        }

        // Core fields should still be fillable
        $coreFields = ['name', 'parent_sku', 'description', 'status'];

        foreach ($coreFields as $field) {
            expect($fillable)->toContain($field);
        }
    });

    test('product collection statistics include attribute data', function () {
        // Create a few products with different attribute completions
        $products = Product::factory(3)->create();

        if (! AttributeDefinition::where('key', 'brand')->exists()) {
            AttributeDefinition::create([
                'key' => 'brand',
                'name' => 'Brand',
                'type' => 'string',
                'group' => 'general',
            ]);
        }

        // Give first product attributes
        $products[0]->setAttributeValue('brand', 'Brand A');

        $stats = Product::getCollectionStatistics();

        expect($stats)->toHaveKey('total_products')
            ->and($stats['total_products'])->toBeGreaterThan(0)
            ->and($stats)->toHaveKey('completion_stats');
    });
});
