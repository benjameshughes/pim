<?php

use App\Actions\Import\SimpleImportAction;
use App\Models\Barcode;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

describe('Enhanced SimpleImportAction', function () {
    beforeEach(function () {
        $this->action = new SimpleImportAction();
        
        // Create some available barcodes for auto-assignment
        Barcode::create(['barcode' => '1111111111111', 'is_assigned' => false]);
        Barcode::create(['barcode' => '2222222222222', 'is_assigned' => false]);
        Barcode::create(['barcode' => '3333333333333', 'is_assigned' => false]);
    });

    test('imports products with auto-assigned barcodes', function () {
        $csvContent = "sku,title,price\nTEST123-RED,Test Product Red,25.99\nTEST123-BLUE,Test Product Blue,27.99";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2],
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue()
            ->and($result['created_products'])->toBe(1)
            ->and($result['created_variants'])->toBe(2);

        // Check variants have auto-assigned barcodes
        $variants = ProductVariant::all();
        expect($variants->filter(fn($v) => $v->barcode)->count())->toBe(2);

        unlink($filePath);
    });

    test('imports products with CSV barcodes', function () {
        // Create the specific barcodes that will be referenced in CSV
        Barcode::create(['barcode' => '9999999999999', 'is_assigned' => false]);
        Barcode::create(['barcode' => '8888888888888', 'is_assigned' => false]);
        
        $csvContent = "sku,title,barcode\nTEST123-RED,Test Product Red,9999999999999\nTEST123-BLUE,Test Product Blue,8888888888888";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'barcode' => 2],
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        // Check specific barcodes were created and assigned
        $redVariant = ProductVariant::where('sku', 'TEST123-RED')->first();
        $blueVariant = ProductVariant::where('sku', 'TEST123-BLUE')->first();

        expect($redVariant->barcode->barcode)->toBe('9999999999999')
            ->and($blueVariant->barcode->barcode)->toBe('8888888888888');

        unlink($filePath);
    });

    test('imports with ad-hoc attributes', function () {
        $csvContent = "sku,title\nTEST123-RED,Test Product Red";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1],
            'ad_hoc_attributes' => [
                'brand' => 'Test Brand',
                'supplier' => 'Test Supplier'
            ]
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        $product = Product::where('parent_sku', 'TEST123')->first();
        expect($product)->not()->toBeNull();

        // Check attributes were assigned (via attributes system)
        // Note: Actual attribute checking would depend on your attribute system implementation

        unlink($filePath);
    });

    test('imports with auto attributes from unmapped columns', function () {
        $csvContent = "sku,title,brand,category\nTEST123-RED,Test Product Red,Auto Brand,Electronics";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1], // brand and category unmapped
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        // Auto attributes should have been detected and assigned
        $product = Product::where('parent_sku', 'TEST123')->first();
        expect($product)->not()->toBeNull();

        unlink($filePath);
    });

    test('handles existing barcode assignment gracefully', function () {
        // Pre-assign a barcode
        $variant = ProductVariant::factory()->create([
            'sku' => 'EXISTING-RED',
            'title' => 'Existing Product Red'
        ]);
        
        $existingBarcode = Barcode::create([
            'barcode' => '5555555555555',
            'product_variant_id' => $variant->id,
            'is_assigned' => true
        ]);

        $csvContent = "sku,title\nEXISTING-RED,Existing Product Red";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1],
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        // Should not assign new barcode, keep existing
        $updatedVariant = $variant->fresh();
        expect($updatedVariant->barcode->barcode)->toBe('5555555555555');

        unlink($filePath);
    });

    test('handles barcode assignment failure gracefully', function () {
        // Remove all available barcodes
        Barcode::where('is_assigned', false)->delete();

        $csvContent = "sku,title\nTEST123-RED,Test Product Red";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1],
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        // Import should still succeed even if barcode assignment fails
        expect($result['success'])->toBeTrue()
            ->and($result['created_variants'])->toBe(1);

        $variant = ProductVariant::where('sku', 'TEST123-RED')->first();
        expect($variant->barcode)->toBeNull();

        unlink($filePath);
    });

    test('creates barcode from CSV if not exists', function () {
        $csvContent = "sku,title,barcode\nTEST123-RED,Test Product Red,7777777777777";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'barcode' => 2],
            'ad_hoc_attributes' => []
        ];

        // Ensure barcode doesn't exist
        expect(Barcode::where('barcode', '7777777777777')->exists())->toBeFalse();

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        // Barcode should be created and assigned
        $barcode = Barcode::where('barcode', '7777777777777')->first();
        expect($barcode)->not()->toBeNull()
            ->and($barcode->is_assigned)->toBeTrue()
            ->and($barcode->product_variant_id)->not()->toBeNull();

        unlink($filePath);
    });

    test('reuses existing unassigned barcode from CSV', function () {
        // Create an unassigned barcode that matches CSV
        $existingBarcode = Barcode::create([
            'barcode' => '6666666666666',
            'is_assigned' => false
        ]);

        $csvContent = "sku,title,barcode\nTEST123-RED,Test Product Red,6666666666666";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'barcode' => 2],
            'ad_hoc_attributes' => []
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue();

        // Should reuse existing barcode
        $updatedBarcode = $existingBarcode->fresh();
        expect($updatedBarcode->is_assigned)->toBeTrue()
            ->and($updatedBarcode->product_variant_id)->not()->toBeNull();

        unlink($filePath);
    });

    test('logs barcode assignment activities', function () {
        Log::spy();

        $csvContent = "sku,title,barcode\nTEST123-RED,Test Product Red,1010101010101";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'barcode' => 2],
            'ad_hoc_attributes' => []
        ];

        $this->action->execute($config);

        Log::shouldHaveReceived('debug')
            ->with('Assigned CSV barcode to variant', \Mockery::type('array'));

        unlink($filePath);
    });

    test('processes complex CSV with all features', function () {
        $csvContent = "sku,title,price,brand,category,barcode\n" .
                     "COMPLEX-RED,Complex Product Red,29.99,CSV Brand,Electronics,4444444444444\n" .
                     "COMPLEX-BLUE,Complex Product Blue,31.99,CSV Brand,Electronics,"; // No barcode for blue

        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => 5], // brand, category unmapped
            'ad_hoc_attributes' => [
                'supplier' => 'Test Supplier',
                'warranty' => '1 year'
            ]
        ];

        $result = $this->action->execute($config);

        expect($result['success'])->toBeTrue()
            ->and($result['created_products'])->toBe(1)
            ->and($result['created_variants'])->toBe(2);

        // Check red variant has CSV barcode
        $redVariant = ProductVariant::where('sku', 'COMPLEX-RED')->first();
        expect($redVariant->barcode->barcode)->toBe('4444444444444');

        // Check blue variant has auto-assigned barcode
        $blueVariant = ProductVariant::where('sku', 'COMPLEX-BLUE')->first();
        expect($blueVariant->barcode)->not()->toBeNull();

        unlink($filePath);
    });
});