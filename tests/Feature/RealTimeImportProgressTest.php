<?php

use App\Actions\Import\SimpleImportAction;
use App\Events\Products\ProductImportProgress;
use Illuminate\Support\Facades\Event;

describe('Real-Time Import Progress', function () {
    test('broadcasts progress events during import', function () {
        Event::fake([ProductImportProgress::class]);

        $csvContent = "sku,title,price\nTEST123-RED,Test Product Red,25.99\nTEST123-BLUE,Test Product Blue,27.99";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $action = new SimpleImportAction;
        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''],
            'importId' => 'test_import_123',
        ];

        $result = $action->execute($config);

        expect($result['success'])->toBeTrue();

        // Should dispatch initial reading event
        Event::assertDispatched(ProductImportProgress::class, function ($event) {
            return $event->status === 'reading_file'
                && $event->currentAction === 'Reading CSV file...';
        });

        // Should dispatch processing events
        Event::assertDispatched(ProductImportProgress::class, function ($event) {
            return $event->status === 'processing';
        });

        // Should dispatch completion event
        Event::assertDispatched(ProductImportProgress::class, function ($event) {
            return $event->status === 'completed'
                && $event->currentAction === 'Import completed successfully!';
        });

        unlink($filePath);
    });

    test('includes correct statistics in progress events', function () {
        Event::fake([ProductImportProgress::class]);

        $csvContent = "sku,title,price\nTEST123-RED,Test Product Red,25.99";
        $filePath = storage_path('temp_test.csv');
        file_put_contents($filePath, $csvContent);

        $action = new SimpleImportAction;
        $config = [
            'file' => $filePath,
            'mappings' => ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''],
            'importId' => 'test_import_456',
        ];

        $action->execute($config);

        // Check that final statistics are included
        Event::assertDispatched(ProductImportProgress::class, function ($event) {
            return $event->status === 'completed'
                && isset($event->stats['products_created'])
                && isset($event->stats['variants_created']);
        });

        unlink($filePath);
    });

    test('livewire component properly sets up listeners with importId', function () {
        $component = new \App\Livewire\Import\SimpleProductImport;

        // Should return empty listeners without importId
        expect($component->getListeners())->toBeArray()->toHaveCount(0);

        // Should setup proper listener with importId
        $component->importId = 'test_import_123';
        $listeners = $component->getListeners();

        expect($listeners)->toBeArray()
            ->toHaveCount(1)
            ->toHaveKey('echo:product-import.test_import_123,.ProductImportProgress');
    });

    test('handles import errors gracefully with broadcasting', function () {
        Event::fake([ProductImportProgress::class]);

        // Create invalid CSV file path to trigger error
        $invalidPath = storage_path('nonexistent_file.csv');

        $action = new SimpleImportAction;
        $config = [
            'file' => $invalidPath,
            'mappings' => ['sku' => 0, 'title' => 1],
            'importId' => 'test_import_error',
        ];

        try {
            $action->execute($config);
        } catch (Exception $e) {
            // Should dispatch error event
            Event::assertDispatched(ProductImportProgress::class, function ($event) {
                return $event->status === 'error';
            });
        }
    });
});
