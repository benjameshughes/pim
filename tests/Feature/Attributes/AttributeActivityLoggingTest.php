<?php

use App\Models\AttributeDefinition;
use App\Models\Product;
use App\Models\User;
use App\Services\Attributes\Facades\Attributes;
use Illuminate\Foundation\Testing\RefreshDatabase;

describe('Attribute Activity Logging Integration', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

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
    });

    describe('Basic Activity Logging', function () {
        test('single attribute set operation logs activity when enabled', function () {
            // Mock the Activity facade to verify calls
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            $builderMock->shouldReceive('with')->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('single attribute set operation does not log when logging disabled', function () {
            // Mock the Activity facade to ensure no calls
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $activityMock->shouldNotReceive('log');

            Attributes::for($this->product)
                ->key('brand')
                ->value('Nike');
        });

        test('unset operation logs activity when enabled', function () {
            $this->product->setTypedAttributeValue('brand', 'Nike');
            
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_unset', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            $builderMock->shouldReceive('with')->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->unset();
        });

        test('bulk attribute operations log activity when enabled', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attributes_set_many', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            $builderMock->shouldReceive('with')->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->keys(['brand', 'color'])
                ->value(['Nike', 'Red']);
        });
    });

    describe('Activity Log Data and Descriptions', function () {
        test('activity log includes standard metadata', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            // Verify the 'with' call includes expected metadata
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) {
                return isset($data['user_id']) &&
                       isset($data['component']) && $data['component'] === 'AttributesSystem' &&
                       isset($data['timestamp']) &&
                       isset($data['model_type']) && $data['model_type'] === 'Product' &&
                       isset($data['model_id']) && $data['model_id'] === $this->product->id &&
                       isset($data['model_name']) &&
                       isset($data['source']) && $data['source'] === 'manual' &&
                       isset($data['attribute_key']) && $data['attribute_key'] === 'brand' &&
                       isset($data['attribute_value']) && $data['attribute_value'] === 'Nike';
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('activity log includes custom data when provided', function () {
            $customData = [
                'import_batch_id' => 'batch_123',
                'import_source' => 'shopify',
            ];

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            // Verify custom data is included
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) use ($customData) {
                return isset($data['import_batch_id']) && $data['import_batch_id'] === 'batch_123' &&
                       isset($data['import_source']) && $data['import_source'] === 'shopify';
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log($customData)
                ->key('brand')
                ->value('Nike');
        });

        test('activity log uses custom description when provided', function () {
            $customDescription = 'Custom attribute update from API';

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->with($customDescription)->andReturn($builderMock);
            $builderMock->shouldReceive('with')->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log([], $customDescription)
                ->key('brand')
                ->value('Nike');
        });

        test('activity log generates appropriate default descriptions', function () {
            $userName = $this->user->name;

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            
            // Verify description contains expected elements
            $builderMock->shouldReceive('description')->with(Mockery::on(function ($description) use ($userName) {
                return str_contains($description, 'brand') &&
                       str_contains($description, 'Product') &&
                       str_contains($description, $userName) &&
                       str_contains($description, 'set');
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('bulk operation descriptions include count information', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attributes_set_many', $this->product)->andReturn($builderMock);
            
            $builderMock->shouldReceive('description')->with(Mockery::on(function ($description) {
                return str_contains($description, '2 attributes updated');
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) {
                return isset($data['attributes_count']) && $data['attributes_count'] === 2;
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->keys(['brand', 'color'])
                ->value(['Nike', 'Red']);
        });
    });

    describe('Source Integration with Activity Logging', function () {
        test('source information is included in activity logs', function () {
            $source = 'api_integration';

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) use ($source) {
                return isset($data['source']) && $data['source'] === $source;
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->source($source)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('source persists across multiple logged operations', function () {
            $source = 'batch_import';

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            // Expect two logging calls with the same source
            $activityMock->shouldReceive('log')->twice()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->twice()->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->twice()->andReturn($builderMock);
            $builderMock->shouldReceive('description')->twice()->andReturn($builderMock);
            $builderMock->shouldReceive('save')->twice();
            
            $builderMock->shouldReceive('with')->twice()->with(Mockery::on(function ($data) use ($source) {
                return isset($data['source']) && $data['source'] === $source;
            }))->andReturn($builderMock);

            $builder = Attributes::for($this->product)
                ->source($source)
                ->log();

            $builder->key('brand')->value('Nike');
            $builder->key('color')->value('Red');
        });
    });

    describe('Model Information in Activity Logs', function () {
        test('product model information is correctly captured', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) {
                return $data['model_type'] === 'Product' &&
                       $data['model_id'] === $this->product->id &&
                       isset($data['model_name']);
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('model name uses name method when available', function () {
            // Product model should have a name method
            $modelName = $this->product->name;

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) use ($modelName) {
                return $data['model_name'] === $modelName;
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log()
                ->key('brand')
                ->value('Nike');
        });

        test('model name falls back to id when name method unavailable', function () {
            // Create a mock model without name method
            $mockModel = Mockery::mock();
            $mockModel->shouldReceive('getAttribute')->with('id')->andReturn(123);
            $mockModel->id = 123;
            
            // Mock the attribute methods to make it "attributable"
            $mockModel->shouldReceive('setTypedAttributeValue')->andReturn(true);

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $mockModel)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) {
                return $data['model_name'] === '#123';
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($mockModel)
                ->log()
                ->key('brand')
                ->value('Nike');
        });
    });

    describe('Multiple Operations and State Management', function () {
        test('logging state persists across multiple operations', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            // Expect three separate logging calls
            $activityMock->shouldReceive('log')->times(3)->andReturn($builderMock);
            $builderMock->shouldReceive('by')->times(3)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->times(3)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->times(3)->andReturn($builderMock);
            $builderMock->shouldReceive('with')->times(3)->andReturn($builderMock);
            $builderMock->shouldReceive('save')->times(3);

            $builder = Attributes::for($this->product)->log();

            $builder->key('brand')->value('Nike');
            $builder->key('color')->value('Red');
            $builder->key('size')->value('Large');
        });

        test('logging can be enabled and disabled per operation', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            // Only expect one logging call (for the middle operation)
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('description')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('with')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('save')->once();

            $builder = Attributes::for($this->product);

            // No logging
            $builder->key('brand')->value('Nike');
            
            // Enable logging for this operation
            $builder->log()->key('color')->value('Red');
            
            // Logging should be disabled again
            $builder->key('size')->value('Large');
        });

        test('custom log data merges with defaults correctly', function () {
            $customData = [
                'custom_field' => 'custom_value',
                'component' => 'CustomComponent',  // Should override default
            ];

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $builderMock = Mockery::mock();
            
            $activityMock->shouldReceive('log')->once()->andReturn($builderMock);
            $builderMock->shouldReceive('by')->with($this->user->id)->andReturn($builderMock);
            $builderMock->shouldReceive('customEvent')->with('attribute_set', $this->product)->andReturn($builderMock);
            $builderMock->shouldReceive('description')->andReturn($builderMock);
            
            $builderMock->shouldReceive('with')->with(Mockery::on(function ($data) {
                return isset($data['custom_field']) && $data['custom_field'] === 'custom_value' &&
                       isset($data['component']) && $data['component'] === 'CustomComponent' &&
                       isset($data['user_id']) && // Default fields should still be present
                       isset($data['timestamp']);
            }))->andReturn($builderMock);
            
            $builderMock->shouldReceive('save')->once();

            Attributes::for($this->product)
                ->log($customData)
                ->key('brand')
                ->value('Nike');
        });
    });

    describe('Error Scenarios and Activity Logging', function () {
        test('failed operations do not log activity', function () {
            // Create an invalid attribute definition
            $invalidDef = AttributeDefinition::factory()->create([
                'key' => 'invalid_attr',
                'name' => 'Invalid Attribute',
                'data_type' => 'numeric',
                'validation_rules' => ['min:0'],
                'is_active' => true,
            ]);

            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $activityMock->shouldNotReceive('log');

            try {
                Attributes::for($this->product)
                    ->log()
                    ->key('invalid_attr')
                    ->value('invalid_number');
            } catch (\Exception $e) {
                // Expected validation failure
            }
        });

        test('partial failures in bulk operations do not log activity', function () {
            $activityMock = Mockery::mock('alias:App\Facades\Activity');
            $activityMock->shouldNotReceive('log');

            try {
                Attributes::for($this->product)
                    ->log()
                    ->keys(['brand', 'invalid_key'])
                    ->value(['Nike', 'Value']);
            } catch (\Exception $e) {
                // Expected to fail due to invalid key
            }
        });
    });
});