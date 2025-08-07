<?php

use App\Models\Product;
use App\Models\User;
use App\Livewire\PIM\Products\Management\ProductIndex;
use App\StackedList\StackedListDirectiveService;
use App\View\Components\StackedList;
use Livewire\Livewire;
use function Pest\Laravel\{get, assertDatabaseHas, actingAs};

beforeEach(function () {
    // Create test user for authentication
    $this->user = User::factory()->create();
    
    // Create test products
    $this->products = Product::factory()->count(5)->create([
        'status' => 'active',
        'parent_sku' => fn() => 'TEST' . rand(100, 999),
    ]);
});

test('products exist in database', function () {
    expect(Product::count())->toBeGreaterThan(0);
    
    $product = Product::first();
    expect($product->name)->not->toBeEmpty();
    expect($product->parent_sku)->not->toBeEmpty();
    expect($product->status)->toBe('active');
});

test('ProductIndex component uses InteractsWithStackedList trait', function () {
    $component = new ProductIndex();
    
    expect(method_exists($component, 'stackedList'))->toBeTrue();
    expect(method_exists($component, 'getStackedListTable'))->toBeTrue();  
    expect(method_exists($component, 'getStackedListDataProperty'))->toBeTrue();
});

test('ProductIndex stackedList configuration is correct', function () {
    $component = new ProductIndex();
    $table = $component->getStackedListTable();
    $config = $table->toArray();
    
    expect($config)->toHaveKey('title');
    expect($config['title'])->toBe('Product Catalog');
    expect($config)->toHaveKey('columns');
    expect($config['columns'])->toHaveCount(3); // name, parent_sku, status
});

test('ProductIndex returns data correctly', function () {
    $component = Livewire::test(ProductIndex::class);
    
    // Test that the component mounts without errors
    $component->assertStatus(200);
    
    // Test that stackedListData property exists and returns data
    $data = $component->get('stackedListData');
    
    expect($data)->not->toBeNull();
    expect($data->count())->toBeGreaterThan(0);
    expect($data->total())->toBe(Product::count());
});

test('ProductIndex table query works correctly', function () {
    $component = new ProductIndex();
    $table = $component->getStackedListTable();
    $query = $table->getQuery();
    
    // Test that query builds correctly
    expect($query->toSql())->toContain('products');
    
    // Test that query returns results
    $results = $query->get();
    expect($results->count())->toBe(Product::count());
    
    // Test pagination works
    $paginated = $query->paginate(10);
    expect($paginated->total())->toBe(Product::count());
    expect($paginated->count())->toBeGreaterThan(0);
});

test('StackedListDirectiveService resolves ProductIndex correctly', function () {
    $componentClass = StackedListDirectiveService::getComponentClass('products');
    expect($componentClass)->toBe(ProductIndex::class);
});

test('StackedList View Component works with products type', function () {
    $component = new StackedList('products');
    
    // Test component properties are set correctly
    expect($component->type)->toBe('products');
    expect($component->parameters)->toBeArray();
    
    // Test that render method doesn't throw errors
    $rendered = $component->render();
    expect($rendered)->not->toBeNull();
});

test('products route renders without errors', function () {
    $response = actingAs($this->user)->get('/products');
    
    $response->assertStatus(200);
    $response->assertSee('Product Catalog');
    $response->assertViewIs('products.index');
});

test('products route shows actual product data', function () {
    // Create a product with specific data we can test for
    $testProduct = Product::factory()->create([
        'name' => 'Test Widget Pro',
        'parent_sku' => 'TEST001',
        'status' => 'active',
    ]);
    
    $response = actingAs($this->user)->get('/products');
    
    $response->assertStatus(200);
    $response->assertSee('Test Widget Pro');
    $response->assertSee('TEST001');
});

test('products table shows correct column headers', function () {
    $response = actingAs($this->user)->get('/products');
    
    $response->assertStatus(200);
    $response->assertSee('Product Name');
    $response->assertSee('SKU');
    $response->assertSee('Status');
});

test('livewire component integration works end-to-end', function () {
    // Test the full chain: Route -> View Component -> Livewire Mount -> Data Rendering
    $component = Livewire::test(ProductIndex::class);
    
    // Component should mount successfully
    $component->assertStatus(200);
    
    // Component should have data
    $data = $component->get('stackedListData');
    expect($data->count())->toBeGreaterThan(0);
    
    // Component should render table
    $tableHtml = $component->get('table');
    expect($tableHtml)->toContain('Product Name');
    expect($tableHtml)->toContain('SKU');
    expect($tableHtml)->toContain('Status');
});