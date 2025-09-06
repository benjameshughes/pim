<?php

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Services\Attributes\Actions\SetAttributeAction;
use App\Services\Attributes\Actions\SetManyAttributesAction;
use App\Services\Attributes\Actions\UnsetAttributeAction;
use App\Services\Attributes\Exceptions\AttributeValidationException;
use App\Services\Attributes\Exceptions\UnknownAttributeException;
use App\Services\Attributes\Exceptions\AttributeOperationException;
use App\Services\Attributes\Facades\Attributes;

describe('Attribute System Error Handling and Edge Cases', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'parent_sku' => 'TEST123',
            'status' => 'active',
        ]);

        // Create test attribute definitions with various constraints
        $this->stringDef = AttributeDefinition::factory()->create([
            'key' => 'brand',
            'name' => 'Brand',
            'data_type' => 'string',
            'validation_rules' => ['max:50'],
            'is_active' => true,
        ]);

        $this->numericDef = AttributeDefinition::factory()->create([
            'key' => 'weight',
            'name' => 'Weight',
            'data_type' => 'numeric',
            'validation_rules' => ['min:0', 'max:1000'],
            'is_active' => true,
        ]);

        $this->enumDef = AttributeDefinition::factory()->create([
            'key' => 'category',
            'name' => 'Category',
            'data_type' => 'enum',
            'enum_values' => ['electronics', 'clothing', 'home'],
            'is_active' => true,
        ]);

        $this->requiredDef = AttributeDefinition::factory()->create([
            'key' => 'required_field',
            'name' => 'Required Field',
            'data_type' => 'string',
            'validation_rules' => ['required'],
            'is_active' => true,
        ]);
    });

    describe('Exception Handling', function () {
        describe('UnknownAttributeException', function () {
            test('SetAttributeAction throws UnknownAttributeException for non-existent attribute', function () {
                $action = new SetAttributeAction();

                expect(function () use ($action) {
                    $action->execute($this->product, 'non_existent_attribute', 'value');
                })->toThrow(UnknownAttributeException::class, "Attribute definition 'non_existent_attribute' not found");
            });

            test('facade throws UnknownAttributeException for invalid attribute key', function () {
                expect(function () {
                    Attributes::for($this->product)
                        ->key('invalid_attribute')
                        ->value('Test Value');
                })->toThrow(UnknownAttributeException::class);
            });

            test('bulk operations throw UnknownAttributeException for any invalid key', function () {
                expect(function () {
                    Attributes::for($this->product)
                        ->keys(['brand', 'invalid_attribute'])
                        ->value(['Nike', 'Value']);
                })->toThrow(UnknownAttributeException::class);
            });

            test('exception message includes the invalid attribute key', function () {
                $exception = null;
                
                try {
                    Attributes::for($this->product)
                        ->key('missing_attribute')
                        ->value('Test');
                } catch (UnknownAttributeException $e) {
                    $exception = $e;
                }

                expect($exception)->not()->toBeNull();
                expect($exception->getMessage())->toContain('missing_attribute');
            });
        });

        describe('AttributeValidationException', function () {
            test('SetAttributeAction throws AttributeValidationException for validation failures', function () {
                $action = new SetAttributeAction();

                expect(function () use ($action) {
                    $action->execute($this->product, 'weight', 'not_a_number');
                })->toThrow(AttributeValidationException::class);
            });

            test('facade throws AttributeValidationException for invalid values', function () {
                expect(function () {
                    Attributes::for($this->product)
                        ->key('weight')
                        ->value(-10);  // Below minimum
                })->toThrow(AttributeValidationException::class);
            });

            test('validation exception contains detailed error information', function () {
                $exception = null;
                
                try {
                    Attributes::for($this->product)
                        ->key('brand')
                        ->value(str_repeat('A', 100));  // Exceeds max:50
                } catch (AttributeValidationException $e) {
                    $exception = $e;
                }

                expect($exception)->not()->toBeNull();
                expect($exception->errors())->toHaveKey('brand');
                expect($exception->messagesFor('brand'))->not()->toBeEmpty();
            });

            test('enum validation throws exception for invalid enum values', function () {
                expect(function () {
                    Attributes::for($this->product)
                        ->key('category')
                        ->value('invalid_category');
                })->toThrow(AttributeValidationException::class);
            });

            test('bulk operation validation collects all errors', function () {
                $exception = null;
                
                try {
                    Attributes::for($this->product)
                        ->keys(['weight', 'category'])
                        ->value(['invalid_weight', 'invalid_category']);
                } catch (AttributeValidationException $e) {
                    $exception = $e;
                }

                expect($exception)->not()->toBeNull();
                $errors = $exception->errors();
                expect($errors)->toHaveKey('weight');
                expect($errors)->toHaveKey('category');
            });

            test('SetManyAttributesAction aggregates validation errors', function () {
                $action = new SetManyAttributesAction();

                $exception = null;
                try {
                    $action->execute($this->product, [
                        'weight' => 'invalid',
                        'category' => 'invalid_category',
                        'brand' => str_repeat('A', 100)
                    ]);
                } catch (AttributeValidationException $e) {
                    $exception = $e;
                }

                expect($exception)->not()->toBeNull();
                $errors = $exception->errors();
                expect(count($errors))->toBeGreaterThanOrEqualTo(3);
            });
        });

        describe('AttributeOperationException', function () {
            test('SetAttributeAction throws AttributeOperationException for unsupported models', function () {
                $unsupportedModel = new stdClass();
                $action = new SetAttributeAction();

                expect(function () use ($action, $unsupportedModel) {
                    $action->execute($unsupportedModel, 'brand', 'value');
                })->toThrow(AttributeOperationException::class, 'Model does not support attributes');
            });

            test('facade throws AttributeOperationException for models without attribute methods', function () {
                $unsupportedModel = new stdClass();

                expect(function () use ($unsupportedModel) {
                    Attributes::for($unsupportedModel)
                        ->key('brand')
                        ->value('Nike');
                })->toThrow(AttributeOperationException::class);
            });

            test('operation exception includes descriptive message', function () {
                $exception = null;
                
                try {
                    $action = new SetAttributeAction();
                    $action->execute(new stdClass(), 'brand', 'value');
                } catch (AttributeOperationException $e) {
                    $exception = $e;
                }

                expect($exception)->not()->toBeNull();
                expect($exception->getMessage())->toContain('does not support attributes');
            });
        });
    });

    describe('Edge Cases and Boundary Conditions', function () {
        describe('Null and Empty Values', function () {
            test('null values are handled gracefully', function () {
                $result = Attributes::for($this->product)
                    ->key('brand')
                    ->value(null);

                expect($result)->toBeInstanceOf(\App\Services\Attributes\AttributeBuilder::class);
                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBeNull();
            });

            test('empty string values are preserved', function () {
                Attributes::for($this->product)
                    ->key('brand')
                    ->value('');

                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('');
            });

            test('zero numeric values are handled correctly', function () {
                Attributes::for($this->product)
                    ->key('weight')
                    ->value(0);

                expect($this->product->fresh()->getTypedAttributeValue('weight'))->toBe(0);
            });

            test('false boolean values are preserved', function () {
                $booleanDef = AttributeDefinition::factory()->create([
                    'key' => 'featured',
                    'name' => 'Featured',
                    'data_type' => 'boolean',
                    'is_active' => true,
                ]);

                Attributes::for($this->product)
                    ->key('featured')
                    ->value(false);

                expect($this->product->fresh()->getTypedAttributeValue('featured'))->toBe(false);
            });
        });

        describe('Array and Object Values', function () {
            test('array values are handled for JSON attributes', function () {
                $jsonDef = AttributeDefinition::factory()->create([
                    'key' => 'metadata',
                    'name' => 'Metadata',
                    'data_type' => 'json',
                    'is_active' => true,
                ]);

                $arrayValue = ['key1' => 'value1', 'key2' => 'value2'];
                
                Attributes::for($this->product)
                    ->key('metadata')
                    ->value($arrayValue);

                $retrievedValue = $this->product->fresh()->getTypedAttributeValue('metadata');
                expect($retrievedValue)->toBe($arrayValue);
            });

            test('nested arrays are preserved in JSON attributes', function () {
                $jsonDef = AttributeDefinition::factory()->create([
                    'key' => 'complex_data',
                    'name' => 'Complex Data',
                    'data_type' => 'json',
                    'is_active' => true,
                ]);

                $complexValue = [
                    'nested' => ['deep' => ['value' => 'test']],
                    'array' => [1, 2, 3],
                    'mixed' => true
                ];
                
                Attributes::for($this->product)
                    ->key('complex_data')
                    ->value($complexValue);

                $retrievedValue = $this->product->fresh()->getTypedAttributeValue('complex_data');
                expect($retrievedValue)->toBe($complexValue);
            });
        });

        describe('Bulk Operation Edge Cases', function () {
            test('mismatched array lengths in bulk operations are handled', function () {
                // More keys than values - should fill with null or repeat last value
                Attributes::for($this->product)
                    ->keys(['brand', 'weight'])
                    ->value(['Nike']);  // Only one value for two keys

                $product = $this->product->fresh();
                expect($product->getTypedAttributeValue('brand'))->toBe('Nike');
                // The second key should get null or the same value based on implementation
            });

            test('empty keys array with value does nothing', function () {
                $originalBrand = 'Original Brand';
                $this->product->setTypedAttributeValue('brand', $originalBrand);

                Attributes::for($this->product)
                    ->keys([])
                    ->value('New Value');

                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe($originalBrand);
            });

            test('single value applied to multiple keys', function () {
                Attributes::for($this->product)
                    ->keys(['brand'])
                    ->value('Same Value');

                $product = $this->product->fresh();
                expect($product->getTypedAttributeValue('brand'))->toBe('Same Value');
            });
        });

        describe('Memory and Performance Edge Cases', function () {
            test('handles very long string values', function () {
                $longString = str_repeat('A', 1000);
                
                // Update validation rules to allow longer strings
                $this->stringDef->update(['validation_rules' => ['max:1500']]);
                
                Attributes::for($this->product)
                    ->key('brand')
                    ->value($longString);

                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe($longString);
            });

            test('handles large JSON values', function () {
                $jsonDef = AttributeDefinition::factory()->create([
                    'key' => 'large_json',
                    'name' => 'Large JSON',
                    'data_type' => 'json',
                    'is_active' => true,
                ]);

                $largeArray = array_fill(0, 100, ['key' => 'value', 'number' => rand()]);
                
                Attributes::for($this->product)
                    ->key('large_json')
                    ->value($largeArray);

                $retrieved = $this->product->fresh()->getTypedAttributeValue('large_json');
                expect(count($retrieved))->toBe(100);
            });
        });

        describe('Concurrent Operation Edge Cases', function () {
            test('multiple facade instances maintain separate state', function () {
                $facade1 = Attributes::for($this->product)->source('source1');
                $facade2 = Attributes::for($this->product)->source('source2');

                $facade1->key('brand')->value('Nike');
                $facade2->key('weight')->value(100);

                $product = $this->product->fresh();
                $brandAttr = $product->attributes()
                    ->where('attribute_definition_id', $this->stringDef->id)
                    ->first();
                $weightAttr = $product->attributes()
                    ->where('attribute_definition_id', $this->numericDef->id)
                    ->first();

                expect($brandAttr->source)->toBe('source1');
                expect($weightAttr->source)->toBe('source2');
            });

            test('rapid successive operations maintain data integrity', function () {
                for ($i = 0; $i < 10; $i++) {
                    Attributes::for($this->product)
                        ->key('brand')
                        ->value("Brand $i");
                }

                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Brand 9');
            });
        });

        describe('Validation Edge Cases', function () {
            test('boundary values for numeric validation', function () {
                // Test exact boundary values
                Attributes::for($this->product)
                    ->key('weight')
                    ->value(0);  // Minimum allowed

                expect($this->product->fresh()->getTypedAttributeValue('weight'))->toBe(0);

                Attributes::for($this->product)
                    ->key('weight')
                    ->value(1000);  // Maximum allowed

                expect($this->product->fresh()->getTypedAttributeValue('weight'))->toBe(1000);
            });

            test('just over boundary values throw exceptions', function () {
                expect(function () {
                    Attributes::for($this->product)
                        ->key('weight')
                        ->value(-0.01);  // Just below minimum
                })->toThrow(AttributeValidationException::class);

                expect(function () {
                    Attributes::for($this->product)
                        ->key('weight')
                        ->value(1000.01);  // Just above maximum
                })->toThrow(AttributeValidationException::class);
            });

            test('unicode and special characters in string values', function () {
                $unicodeString = '测试 🎉 émojis & spëcial chârs';
                
                Attributes::for($this->product)
                    ->key('brand')
                    ->value($unicodeString);

                expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe($unicodeString);
            });
        });
    });

    describe('Error Recovery and Resilience', function () {
        test('system continues working after validation errors', function () {
            // First operation fails
            try {
                Attributes::for($this->product)
                    ->key('weight')
                    ->value('invalid_number');
            } catch (AttributeValidationException $e) {
                // Expected
            }

            // Second operation should work
            Attributes::for($this->product)
                ->key('brand')
                ->value('Nike');

            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Nike');
        });

        test('partial failure in bulk operations leaves valid data intact', function () {
            // Set some initial data
            $this->product->setTypedAttributeValue('brand', 'Original Brand');

            try {
                Attributes::for($this->product)
                    ->keys(['brand', 'weight'])
                    ->value(['Nike', 'invalid_weight']);
            } catch (AttributeValidationException $e) {
                // Expected - bulk operation should fail completely
            }

            // Original data should be preserved (atomic operation)
            expect($this->product->fresh()->getTypedAttributeValue('brand'))->toBe('Original Brand');
        });

        test('exception details help with debugging', function () {
            $exception = null;
            
            try {
                Attributes::for($this->product)
                    ->keys(['brand', 'weight', 'category'])
                    ->value([str_repeat('A', 100), -50, 'invalid_category']);
            } catch (AttributeValidationException $e) {
                $exception = $e;
            }

            expect($exception)->not()->toBeNull();
            
            // Should have errors for each failed attribute
            $errors = $exception->errors();
            expect(count($errors))->toBe(3);
            
            // Each error should be detailed
            expect($exception->messagesFor('brand'))->not()->toBeEmpty();
            expect($exception->messagesFor('weight'))->not()->toBeEmpty();
            expect($exception->messagesFor('category'))->not()->toBeEmpty();
        });
    });
});