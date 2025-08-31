<?php

use App\Actions\Activity\ExtractModelDataAction;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;

it('extracts product data using ProductExtractor', function () {
    $product = Product::factory()->create([
        'name' => 'Test Product',
        'sku' => 'TEST-SKU',
        'status' => 'active'
    ]);
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($product);
    
    expect($extracted)->toHaveKeys(['id', 'type', 'name', 'sku', 'status'])
        ->and($extracted['type'])->toBe('Product')
        ->and($extracted['name'])->toBe('Test Product')
        ->and($extracted['sku'])->toBe('TEST-SKU')
        ->and($extracted['status'])->toBe('active');
});

it('extracts user data using UserExtractor', function () {
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($user);
    
    expect($extracted)->toHaveKeys(['id', 'type', 'name', 'email'])
        ->and($extracted['type'])->toBe('User')
        ->and($extracted['name'])->toBe('John Doe')
        ->and($extracted['email'])->toBe('john@example.com');
});

it('extracts variant data using VariantExtractor', function () {
    $product = Product::factory()->create(['name' => 'Parent Product']);
    $variant = Variant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'VAR-SKU',
        'stock_quantity' => 100,
        'retail_price' => 25.99
    ]);
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($variant);
    
    expect($extracted)->toHaveKeys(['id', 'type', 'sku', 'product_id', 'stock_quantity', 'retail_price'])
        ->and($extracted['type'])->toBe('Variant')
        ->and($extracted['sku'])->toBe('VAR-SKU')
        ->and($extracted['stock_quantity'])->toBe(100)
        ->and($extracted['retail_price'])->toBe(25.99);
});

it('falls back to default extraction for unknown models', function () {
    $mockModel = new class {
        public $id = 123;
        public $name = 'Test Model';
        public $status = 'active';
        public $unwanted_field = 'should not appear';
        
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'status' => $this->status,
                'unwanted_field' => $this->unwanted_field,
                'another_unwanted' => 'also should not appear'
            ];
        }
    };
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($mockModel);
    
    expect($extracted)->toHaveKeys(['id', 'name', 'status', 'type'])
        ->and($extracted['type'])->toBe('class@anonymous')
        ->and($extracted['name'])->toBe('Test Model')
        ->and($extracted['status'])->toBe('active')
        ->and($extracted)->not->toHaveKey('unwanted_field')
        ->and($extracted)->not->toHaveKey('another_unwanted');
});

it('handles models without toArray method', function () {
    $mockModel = new class {
        public $id = 456;
        // No toArray method
    };
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($mockModel);
    
    expect($extracted)->toHaveKey('type')
        ->and($extracted['type'])->toBe('class@anonymous');
});

it('filters out null values in default extraction', function () {
    $mockModel = new class {
        public $id = 789;
        public $name = 'Test';
        public $title = null;
        public $sku = '';
        public $status = 'active';
        
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'title' => $this->title,
                'sku' => $this->sku,
                'status' => $this->status
            ];
        }
    };
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($mockModel);
    
    expect($extracted)->not->toHaveKey('title') // null value filtered out
        ->and($extracted)->toHaveKey('name') // non-null value kept
        ->and($extracted)->toHaveKey('status')
        ->and($extracted)->toHaveKey('type'); // always added
});

it('uses extractor class name correctly', function () {
    $product = Product::factory()->create();
    
    $action = app(ExtractModelDataAction::class);
    
    // Use reflection to test the protected method
    $reflection = new ReflectionClass($action);
    $method = $reflection->getMethod('getExtractorClass');
    $method->setAccessible(true);
    
    $extractorClass = $method->invokeArgs($action, [$product]);
    
    expect($extractorClass)->toBe('App\\Extractors\\ProductExtractor');
});

it('handles collection of standard fields correctly', function () {
    $mockModel = new class {
        public $id = 1;
        public $name = 'Test';
        public $title = 'Test Title';
        public $sku = 'TEST-SKU';
        public $status = 'active';
        public $type = 'original_type';
        public $extra_field = 'should be filtered';
        
        public function toArray() {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'title' => $this->title,
                'sku' => $this->sku,
                'status' => $this->status,
                'type' => $this->type,
                'extra_field' => $this->extra_field,
            ];
        }
    };
    
    $action = app(ExtractModelDataAction::class);
    $extracted = $action->extractData($mockModel);
    
    expect($extracted)->toHaveKeys(['id', 'name', 'title', 'sku', 'status', 'type'])
        ->and($extracted)->not->toHaveKey('extra_field')
        ->and($extracted['type'])->toBe('class@anonymous'); // Type gets overridden
});