<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Actions\Import\SimpleImportAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Product Import System', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can import products from CSV', function () {
        $csvContent = implode("\n", [
            "name,description,sku,price",
            "Test Product 1,First test product,TEST-001,29.99",
            "Test Product 2,Second test product,TEST-002,39.99",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->call('processFile');

        expect(Product::count())->toBe(2);
        expect(Product::where('sku', 'TEST-001')->exists())->toBeTrue();
        expect(Product::where('sku', 'TEST-002')->exists())->toBeTrue();
    });

    it('validates CSV structure', function () {
        $invalidCsvContent = "invalid,headers\ndata,more data";
        $file = UploadedFile::fake()->createWithContent('invalid.csv', $invalidCsvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->call('processFile')
            ->assertHasErrors(['uploadedFile']);
    });

    it('handles duplicate SKUs during import', function () {
        // Create existing product
        Product::factory()->create(['sku' => 'EXISTING-SKU']);

        $csvContent = implode("\n", [
            "name,description,sku,price",
            "Duplicate Product,This should update,EXISTING-SKU,49.99",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('duplicates.csv', $csvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->set('handleDuplicates', 'update')
            ->call('processFile');

        expect(Product::count())->toBe(1);
        $product = Product::where('sku', 'EXISTING-SKU')->first();
        expect($product->name)->toBe('Duplicate Product');
    });

    it('can skip rows with errors', function () {
        $csvContent = implode("\n", [
            "name,description,sku,price",
            "Valid Product,Good product,VALID-001,29.99",
            ",Invalid product,INVALID-001,invalid-price", // Missing name
            "Another Valid,Another good,VALID-002,39.99",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('mixed.csv', $csvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->set('skipErrors', true)
            ->call('processFile');

        expect(Product::count())->toBe(2);
        expect(Product::where('sku', 'VALID-001')->exists())->toBeTrue();
        expect(Product::where('sku', 'VALID-002')->exists())->toBeTrue();
        expect(Product::where('sku', 'INVALID-001')->exists())->toBeFalse();
    });

    it('can map CSV columns to product fields', function () {
        $csvContent = implode("\n", [
            "product_name,product_desc,product_sku,selling_price",
            "Mapped Product,Mapped description,MAP-001,59.99",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('mapped.csv', $csvContent);

        $mapping = [
            'product_name' => 'name',
            'product_desc' => 'description',
            'product_sku' => 'sku',
            'selling_price' => 'price',
        ];

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->set('columnMapping', $mapping)
            ->call('processFile');

        expect(Product::count())->toBe(1);
        $product = Product::first();
        expect($product->name)->toBe('Mapped Product');
        expect($product->sku)->toBe('MAP-001');
    });

    it('can import variants with parent products', function () {
        $csvContent = implode("\n", [
            "parent_sku,variant_sku,name,size,color,price",
            "PARENT-001,VAR-001-S-RED,Test Product,S,Red,29.99",
            "PARENT-001,VAR-001-M-RED,Test Product,M,Red,29.99",
            "PARENT-001,VAR-001-L-BLUE,Test Product,L,Blue,34.99",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('variants.csv', $csvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->set('importType', 'variants')
            ->call('processFile');

        expect(Product::count())->toBe(1);
        expect(ProductVariant::count())->toBe(3);
        
        $product = Product::first();
        expect($product->variants)->toHaveCount(3);
        expect($product->variants->where('size', 'S')->count())->toBe(1);
        expect($product->variants->where('color', 'Blue')->count())->toBe(1);
    });

    it('tracks import progress', function () {
        $csvContent = implode("\n", [
            "name,sku",
            "Product 1,P001",
            "Product 2,P002",
            "Product 3,P003",
        ]);
        
        $file = UploadedFile::fake()->createWithContent('progress.csv', $csvContent);

        $component = Livewire::test('import.simple-product-import')
            ->set('uploadedFile', $file)
            ->call('processFile');

        expect($component->get('importStats')['total'])->toBe(3);
        expect($component->get('importStats')['successful'])->toBe(3);
        expect($component->get('importStats')['errors'])->toBe(0);
    });
});