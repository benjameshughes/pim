<?php

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\Attributes\AttributeBuilder;
use App\Services\Attributes\Exceptions\AttributeValidationException;
use App\Services\Attributes\Facades\Attributes;

describe('AttributeBuilder Traits Individual Tests', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active',
        ]);

        // Create test attribute definitions
        $this->brandDef = AttributeDefinition::factory()->create([
            'key' => 'brand',
            'name' => 'Brand',
            'data_type' => 'string',
            'group' => 'general',
            'is_active' => true,
        ]);

        $this->colorDef = AttributeDefinition::factory()->create([
            'key' => 'color',
            'name' => 'Color',
            'data_type' => 'string',
            'group' => 'appearance',
            'is_active' => true,
        ]);

        $this->sizeDef = AttributeDefinition::factory()->create([
            'key' => 'size',
            'name' => 'Size',
            'data_type' => 'string',
            'group' => 'dimensions',
            'is_active' => true,
        ]);

        $this->materialDef = AttributeDefinition::factory()->create([
            'key' => 'material',
            'name' => 'Material',
            'data_type' => 'string',
            'group' => 'general',
            'is_active' => true,
        ]);
    });

    describe('TargetsAttributes Trait', function () {
        test('key method sets single target key', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->key('brand');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Test the targeting by setting a value
            $result->value('Nike');
            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Nike');
        });

        test('keys method sets multiple target keys', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys(['brand', 'color']);

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Test bulk operation
            $result->value(['Nike', 'Red']);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
        });

        test('keys method removes duplicates and reorders', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys(['brand', 'color', 'brand', 'size']);

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Test that duplicates are handled (only one 'brand' should be set)
            $result->value(['Nike', 'Red', 'Large']);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
            expect($product->getTypedAttributeValue('size'))->toBe('Large');
        });

        test('group method targets attributes by definition group', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->group('general');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Test that both 'brand' and 'material' (both in 'general' group) are targeted
            $result->value('Same Value');
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Same Value');
            expect($product->getTypedAttributeValue('material'))->toBe('Same Value');
        });

        test('group method works with empty groups', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->group('nonexistent_group');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Should not set any values
            $result->value('Test Value');
            
            // All values should remain null
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('color'))->toBeNull();
        });

        test('subsequent key calls override previous targets', function () {
            $builder = new AttributeBuilder($this->product);
            
            $builder->keys(['brand', 'color'])
                ->key('size')  // Should override previous keys
                ->value('Large');

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('color'))->toBeNull();
            expect($product->getTypedAttributeValue('size'))->toBe('Large');
        });
    });

    describe('ReadsAttributes Trait', function () {
        test('get method returns typed attribute value', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            
            $builder = new AttributeBuilder($this->product);
            $value = $builder->get('brand');

            expect($value)->toBe('Nike');
        });

        test('get method returns null for non-existent attributes', function () {
            $builder = new AttributeBuilder($this->product);
            $value = $builder->get('nonexistent');

            expect($value)->toBeNull();
        });

        test('get method works with models without attribute support', function () {
            $unsupportedModel = new stdClass();
            $builder = new AttributeBuilder($unsupportedModel);
            $value = $builder->get('brand');

            expect($value)->toBeNull();
        });

        test('all method returns all typed attributes as array', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            
            $builder = new AttributeBuilder($this->product);
            $attributes = $builder->all();

            expect($attributes)->toBeArray()
                ->toHaveKey('brand', 'Nike')
                ->toHaveKey('color', 'Red');
        });

        test('all method returns empty array for models without attribute support', function () {
            $unsupportedModel = new stdClass();
            $builder = new AttributeBuilder($unsupportedModel);
            $attributes = $builder->all();

            expect($attributes)->toBe([]);
        });

        test('byGroup method returns attributes grouped by definition group', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('material', 'Cotton');
            $this->product->setTypedAttributeValue('color', 'Red');
            
            $builder = new AttributeBuilder($this->product);
            $grouped = $builder->byGroup();

            expect($grouped)->toBeArray()
                ->toHaveKey('general')
                ->toHaveKey('appearance');
            
            // Verify structure contains expected attributes in groups
            expect($grouped['general'])->not()->toBeEmpty();
            expect($grouped['appearance'])->not()->toBeEmpty();
        });

        test('byGroup method returns empty array for models without attribute support', function () {
            $unsupportedModel = new stdClass();
            $builder = new AttributeBuilder($unsupportedModel);
            $grouped = $builder->byGroup();

            expect($grouped)->toBe([]);
        });
    });

    describe('WritesAttributes Trait', function () {
        test('value method with single target key sets attribute', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->key('brand')->value('Nike');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Nike');
        });

        test('value method with multiple target keys sets bulk attributes', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys(['brand', 'color'])->value(['Nike', 'Red']);

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
        });

        test('value method with multiple keys and single value fills all', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys(['brand', 'color'])->value('Same Value');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Same Value');
            expect($product->getTypedAttributeValue('color'))->toBe('Same Value');
        });

        test('value method handles empty target keys gracefully', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys([])->value('Test Value');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            // Should not set any attributes
            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBeNull();
        });

        test('unset method with key parameter unsets specific attribute', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            
            $builder = new AttributeBuilder($this->product);
            $result = $builder->unset('brand');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('color'))->toBe('Red');  // Should remain
        });

        test('unset method without key parameter uses target keys', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            $this->product->setTypedAttributeValue('size', 'Large');
            
            $builder = new AttributeBuilder($this->product);
            $result = $builder->keys(['brand', 'color'])->unset();

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('color'))->toBeNull();
            expect($product->getTypedAttributeValue('size'))->toBe('Large');  // Should remain
        });

        test('writeOptions method includes source when set', function () {
            $builder = new AttributeBuilder($this->product);
            $builder->source('test_source');
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('writeOptions');
            $method->setAccessible(true);
            $options = $method->invoke($builder);

            expect($options)->toHaveKey('source', 'test_source');
        });

        test('writeOptions method defaults to manual source', function () {
            $builder = new AttributeBuilder($this->product);
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('writeOptions');
            $method->setAccessible(true);
            $options = $method->invoke($builder);

            expect($options)->toHaveKey('source', 'manual');
        });
    });

    describe('ConfiguresLogging Trait', function () {
        test('source method sets source string', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->source('api_import');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Test that source is used in operations
            $builder->key('brand')->value('Nike');
            
            $productAttr = $this->product->fresh()->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();

            expect($productAttr->source)->toBe('api_import');
        });

        test('log method enables activity logging with defaults', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->log();

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Verify logging is enabled by checking internal state
            $reflection = new ReflectionClass($builder);
            $property = $reflection->getProperty('shouldLog');
            $property->setAccessible(true);
            
            expect($property->getValue($builder))->toBeTrue();
        });

        test('log method accepts custom data', function () {
            $customData = ['custom_field' => 'custom_value'];
            
            $builder = new AttributeBuilder($this->product);
            $result = $builder->log($customData);

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Verify custom data is stored
            $reflection = new ReflectionClass($builder);
            $property = $reflection->getProperty('logData');
            $property->setAccessible(true);
            $logData = $property->getValue($builder);
            
            expect($logData)->toHaveKey('custom_field', 'custom_value');
        });

        test('log method accepts custom description', function () {
            $builder = new AttributeBuilder($this->product);
            $result = $builder->log([], 'Custom description');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            // Verify custom description is stored
            $reflection = new ReflectionClass($builder);
            $property = $reflection->getProperty('logDescription');
            $property->setAccessible(true);
            
            expect($property->getValue($builder))->toBe('Custom description');
        });

        test('log method includes standard metadata', function () {
            $builder = new AttributeBuilder($this->product);
            $builder->log();
            
            $reflection = new ReflectionClass($builder);
            $property = $reflection->getProperty('logData');
            $property->setAccessible(true);
            $logData = $property->getValue($builder);
            
            expect($logData)->toHaveKey('component', 'AttributesSystem')
                ->toHaveKey('timestamp')
                ->toHaveKey('user_id');
        });
    });

    describe('LogsActivity Trait', function () {
        test('logActivity method generates appropriate descriptions', function () {
            $builder = new AttributeBuilder($this->product);
            $builder->log();  // Enable logging
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('generateLogDescription');
            $method->setAccessible(true);
            
            $description = $method->invoke($builder, 'attribute_set', [
                'attribute_key' => 'brand',
                'attribute_value' => 'Nike'
            ]);
            
            expect($description)->toContain('Attribute')
                ->toContain('brand')
                ->toContain('Product');
        });

        test('logActivity method handles different event types', function () {
            $builder = new AttributeBuilder($this->product);
            $builder->log();
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('generateLogDescription');
            $method->setAccessible(true);
            
            $setDescription = $method->invoke($builder, 'attribute_set', ['attribute_key' => 'brand']);
            $unsetDescription = $method->invoke($builder, 'attribute_unset', ['attribute_key' => 'brand']);
            $bulkDescription = $method->invoke($builder, 'attributes_set_many', ['attributes_count' => 3]);
            
            expect($setDescription)->toContain('set');
            expect($unsetDescription)->toContain('removed');
            expect($bulkDescription)->toContain('3 attributes updated');
        });

        test('logActivity method includes model information', function () {
            $builder = new AttributeBuilder($this->product);
            $builder->log();
            
            $reflection = new ReflectionClass($builder);
            $method = $reflection->getMethod('generateLogDescription');
            $method->setAccessible(true);
            
            $description = $method->invoke($builder, 'attribute_set', ['attribute_key' => 'brand']);
            
            expect($description)->toContain('Product');
        });
    });

    describe('Trait Integration', function () {
        test('all traits work together in complex scenarios', function () {
            $builder = new AttributeBuilder($this->product);
            
            $result = $builder
                ->source('integration_test')
                ->log(['test_run' => true], 'Integration test run')
                ->group('general')
                ->value('Test Value');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
            
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Test Value');
            expect($product->getTypedAttributeValue('material'))->toBe('Test Value');
            
            // Verify source was applied
            $brandAttr = $product->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();
            expect($brandAttr->source)->toBe('integration_test');
        });

        test('trait methods can be called in different orders', function () {
            $builder = new AttributeBuilder($this->product);
            
            // Test different ordering
            $result = $builder
                ->log()
                ->key('brand')
                ->source('test')
                ->value('Nike')
                ->get('brand');

            expect($result)->toBe('Nike');
        });

        test('trait state is maintained across method calls', function () {
            $builder = new AttributeBuilder($this->product);
            
            $builder->source('persistent_source')
                ->log(['session' => 'test']);
            
            // Multiple operations should maintain state
            $builder->key('brand')->value('Nike');
            $builder->key('color')->value('Red');
            
            $product = $this->product->fresh();
            $brandAttr = $product->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();
            $colorAttr = $product->attributes()
                ->where('attribute_definition_id', $this->colorDef->id)
                ->first();
            
            expect($brandAttr->source)->toBe('persistent_source');
            expect($colorAttr->source)->toBe('persistent_source');
        });
    });
});