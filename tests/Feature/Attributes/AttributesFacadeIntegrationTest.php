<?php

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Attributes\AttributeBuilder;
use App\Services\Attributes\AttributesManager;
use App\Services\Attributes\Exceptions\AttributeValidationException;
use App\Services\Attributes\Exceptions\UnknownAttributeException;
use App\Services\Attributes\Exceptions\AttributeOperationException;
use App\Services\Attributes\Facades\Attributes;

describe('Attributes Facade Integration Tests', function () {
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

        // Create essential attribute definitions
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
    });

    describe('Facade Resolution and Basic Functionality', function () {
        test('facade resolves to AttributesManager instance', function () {
            $manager = Attributes::getFacadeRoot();
            expect($manager)->toBeInstanceOf(AttributesManager::class);
        });

        test('facade for method returns AttributeBuilder', function () {
            $builder = Attributes::for($this->product);
            expect($builder)->toBeInstanceOf(AttributeBuilder::class);
        });

        test('facade maintains fluent interface throughout chain', function () {
            $result = Attributes::for($this->product)
                ->key('brand')
                ->value('Nike')
                ->key('color')
                ->value('Red');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
        });
    });

    describe('Single Attribute Operations', function () {
        test('sets single attribute via fluent interface', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value('Nike');

            $value = $this->product->fresh()->getTypedAttributeValue('brand');
            expect($value)->toBe('Nike');
        });

        test('gets single attribute value', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            
            $value = Attributes::for($this->product)->get('brand');
            expect($value)->toBe('Nike');
        });

        test('unsets single attribute', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            
            Attributes::for($this->product)->unset('brand');
            
            $value = $this->product->fresh()->getTypedAttributeValue('brand');
            expect($value)->toBeNull();
        });

        test('unsets using targeted key approach', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            
            Attributes::for($this->product)
                ->key('brand')
                ->unset();
            
            $value = $this->product->fresh()->getTypedAttributeValue('brand');
            expect($value)->toBeNull();
        });
    });

    describe('Bulk Operations', function () {
        test('sets multiple attributes with keys and array values', function () {
            Attributes::for($this->product)
                ->keys(['brand', 'color'])
                ->value(['Nike', 'Red']);

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
        });

        test('sets multiple attributes with keys and single value', function () {
            Attributes::for($this->product)
                ->keys(['brand', 'color'])
                ->value('Same Value');

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Same Value');
            expect($product->getTypedAttributeValue('color'))->toBe('Same Value');
        });

        test('unsets multiple attributes using keys', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            
            Attributes::for($this->product)
                ->keys(['brand', 'color'])
                ->unset();
            
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('color'))->toBeNull();
        });

        test('gets all attributes as array', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            
            $attributes = Attributes::for($this->product)->all();
            
            expect($attributes)->toBeArray()
                ->toHaveKey('brand', 'Nike')
                ->toHaveKey('color', 'Red');
        });

        test('gets attributes grouped by definition group', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('color', 'Red');
            $this->product->setTypedAttributeValue('size', 'Large');
            
            $grouped = Attributes::for($this->product)->byGroup();
            
            expect($grouped)->toBeArray()
                ->toHaveKey('general')
                ->toHaveKey('appearance')
                ->toHaveKey('dimensions');
        });
    });

    describe('Group-Based Operations', function () {
        test('targets attributes by group', function () {
            // Create multiple attributes in the same group
            AttributeDefinition::factory()->create([
                'key' => 'material',
                'name' => 'Material',
                'data_type' => 'string',
                'group' => 'general',
                'is_active' => true,
            ]);

            Attributes::for($this->product)
                ->group('general')
                ->value('Same Value');

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Same Value');
            expect($product->getTypedAttributeValue('material'))->toBe('Same Value');
        });

        test('unsets entire group of attributes', function () {
            AttributeDefinition::factory()->create([
                'key' => 'material',
                'name' => 'Material',
                'data_type' => 'string',
                'group' => 'general',
                'is_active' => true,
            ]);

            $this->product->setTypedAttributeValue('brand', 'Nike');
            $this->product->setTypedAttributeValue('material', 'Cotton');
            
            Attributes::for($this->product)
                ->group('general')
                ->unset();
            
            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBeNull();
            expect($product->getTypedAttributeValue('material'))->toBeNull();
        });
    });

    describe('Source Tracking and Metadata', function () {
        test('sets source for attribute operations', function () {
            Attributes::for($this->product)
                ->source('api_import')
                ->key('brand')
                ->value('Nike');

            // Verify source was set (would need to check in database)
            $productAttr = $this->product->fresh()->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();

            expect($productAttr)->not()->toBeNull();
            expect($productAttr->source)->toBe('api_import');
        });

        test('source is applied to bulk operations', function () {
            Attributes::for($this->product)
                ->source('csv_import')
                ->keys(['brand', 'color'])
                ->value(['Nike', 'Red']);

            $brandAttr = $this->product->fresh()->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();
            $colorAttr = $this->product->fresh()->attributes()
                ->where('attribute_definition_id', $this->colorDef->id)
                ->first();

            expect($brandAttr->source)->toBe('csv_import');
            expect($colorAttr->source)->toBe('csv_import');
        });

        test('defaults to manual source when not specified', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value('Nike');

            $productAttr = $this->product->fresh()->attributes()
                ->where('attribute_definition_id', $this->brandDef->id)
                ->first();

            expect($productAttr->source)->toBe('manual');
        });
    });

    describe('Error Handling and Edge Cases', function () {
        test('throws UnknownAttributeException for invalid attribute key', function () {
            expect(function () {
                Attributes::for($this->product)
                    ->key('invalid_attribute')
                    ->value('Test Value');
            })->toThrow(UnknownAttributeException::class);
        });

        test('throws AttributeValidationException for invalid values', function () {
            // Create numeric attribute definition
            $numericDef = AttributeDefinition::factory()->create([
                'key' => 'weight',
                'name' => 'Weight',
                'data_type' => 'numeric',
                'validation_rules' => ['min:0'],
                'is_active' => true,
            ]);

            expect(function () {
                Attributes::for($this->product)
                    ->key('weight')
                    ->value('invalid_number');
            })->toThrow(AttributeValidationException::class);
        });

        test('handles empty target keys gracefully', function () {
            $result = Attributes::for($this->product)
                ->keys([])
                ->value('Test Value');

            expect($result)->toBeInstanceOf(AttributeBuilder::class);
        });

        test('handles null values appropriately', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value(null);

            $value = $this->product->fresh()->getTypedAttributeValue('brand');
            expect($value)->toBeNull();
        });

        test('throws AttributeOperationException for models without attribute support', function () {
            $unsupportedModel = new stdClass();

            expect(function () {
                Attributes::for($unsupportedModel)
                    ->key('brand')
                    ->value('Nike');
            })->toThrow(AttributeOperationException::class);
        });
    });

    describe('Method Chaining Scenarios', function () {
        test('complex chaining operations work correctly', function () {
            $result = Attributes::for($this->product)
                ->source('test_suite')
                ->key('brand')
                ->value('Nike')
                ->key('color')
                ->value('Red')
                ->keys(['size'])
                ->value(['Large']);

            expect($result)->toBeInstanceOf(AttributeBuilder::class);

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
            expect($product->getTypedAttributeValue('size'))->toBe('Large');
        });

        test('switching between single and bulk operations', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value('Nike')
                ->keys(['color', 'size'])
                ->value(['Red', 'Large'])
                ->key('brand')  // Switch back to single
                ->value('Adidas');  // Should override previous brand

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Adidas');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
            expect($product->getTypedAttributeValue('size'))->toBe('Large');
        });

        test('read operations do not interfere with write operations', function () {
            $this->product->setTypedAttributeValue('brand', 'Existing Brand');

            $existingValue = Attributes::for($this->product)->get('brand');
            
            Attributes::for($this->product)
                ->key('color')
                ->value('Red');

            expect($existingValue)->toBe('Existing Brand');
            expect($this->product->fresh()->getTypedAttributeValue('color'))->toBe('Red');
        });
    });

    describe('Different Model Types', function () {
        test('works with Product models', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value('Product Brand');

            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Product Brand');
        });

        test('works with ProductVariant models', function () {
            Attributes::for($this->variant)
                ->key('color')
                ->value('Variant Color');

            expect($this->variant->fresh()->getTypedAttributeValue('color'))->toBe('Variant Color');
        });

        test('maintains separate attribute contexts for different models', function () {
            Attributes::for($this->product)
                ->key('brand')
                ->value('Product Brand');

            Attributes::for($this->variant)
                ->key('brand')
                ->value('Variant Brand');

            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Product Brand');
            expect($this->variant->fresh()->getTypedAttributeValue('brand'))->toBe('Variant Brand');
        });
    });

    describe('Concurrent Operations', function () {
        test('multiple facade instances work independently', function () {
            $builder1 = Attributes::for($this->product)->source('source1');
            $builder2 = Attributes::for($this->variant)->source('source2');

            $builder1->key('brand')->value('Brand1');
            $builder2->key('brand')->value('Brand2');

            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Brand1');
            expect($this->variant->fresh()->getTypedAttributeValue('brand'))->toBe('Brand2');
        });

        test('different target keys work independently', function () {
            $facade = Attributes::for($this->product);

            $facade->key('brand')->value('Nike');
            $facade->key('color')->value('Red');

            $product = $this->product->fresh();
            expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
            expect($product->getTypedAttributeValue('color'))->toBe('Red');
        });
    });
});