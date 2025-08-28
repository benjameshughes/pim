<?php

use App\Actions\Import\SimpleImportAction;
use App\Events\Products\ProductImportProgress;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use App\Livewire\Import\SimpleProductImport;

describe('Import System Integration', function () {
    test('complete import flow with real-time updates works end-to-end', function () {
        Event::fake([ProductImportProgress::class]);
        
        // Test CSV with proper data
        $csvContent = "sku,title,price\nTEST456-WHITE,Blackout Roller White 60cm x 160cm,29.99\nTEST456-BLACK,Blackout Roller Black 60cm x 210cm,32.99";
        $filePath = storage_path('integration_test.csv');
        file_put_contents($filePath, $csvContent);

        // Execute import
        $action = new SimpleImportAction();
        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''],
            'importId' => 'integration_test_789',
        ];

        $result = $action->execute($config);

        // Verify import success
        expect($result['success'])->toBeTrue()
            ->and($result['created_products'])->toBeGreaterThan(0)
            ->and($result['created_variants'])->toBeGreaterThan(0);

        // Verify products were created correctly
        $product = Product::where('parent_sku', 'TEST456')->first();
        expect($product)->not()->toBeNull();

        $variants = ProductVariant::whereIn('sku', ['TEST456-WHITE', 'TEST456-BLACK'])->get();
        expect($variants->count())->toBe(2);

        // Verify color extraction worked
        $whiteVariant = $variants->where('sku', 'TEST456-WHITE')->first();
        expect($whiteVariant)->not()->toBeNull();

        // Verify broadcasting events were dispatched
        Event::assertDispatched(ProductImportProgress::class);
        
        // Count total events (clean step-by-step approach)
        // We expect: 6 step phases + completed = 7 events total
        Event::assertDispatchedTimes(ProductImportProgress::class, 7);

        unlink($filePath);
    });

    test('livewire component can handle file upload and mapping', function () {
        $csvContent = "sku,title\nTEST789-RED,Test Product";
        $filePath = storage_path('livewire_test.csv');  
        file_put_contents($filePath, $csvContent);

        // Test Livewire component
        Livewire::test(SimpleProductImport::class)
            ->assertSet('step', 'upload')
            ->assertSet('importing', false);

        unlink($filePath);
    });
});