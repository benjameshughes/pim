<?php

use App\Events\Products\ProductImportProgress;
use App\Jobs\ProcessProductImport;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

describe('Queued Product Import', function () {
    test('dispatches ProcessProductImport job correctly', function () {
        Queue::fake();

        $csvContent = "sku,title,price\nTEST789-RED,Test Product Red,29.99";
        $filePath = storage_path('queue_test.csv');
        file_put_contents($filePath, $csvContent);

        $importId = 'test_queue_123';
        $mappings = ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''];

        // Dispatch the job
        ProcessProductImport::dispatch($filePath, $importId, $mappings);

        // Assert the job was pushed to the queue
        Queue::assertPushed(ProcessProductImport::class);

        unlink($filePath);
    });

    test('ProcessProductImport job executes import correctly', function () {
        Event::fake([ProductImportProgress::class]);

        $csvContent = "sku,title,price\nTEST888-BLUE,Test Product Blue,19.99";
        $filePath = storage_path('job_test.csv');
        file_put_contents($filePath, $csvContent);

        $importId = 'test_job_456';
        $mappings = ['sku' => 0, 'title' => 1, 'price' => 2, 'barcode' => '', 'brand' => ''];

        // Create and handle the job directly
        $job = new ProcessProductImport($filePath, $importId, $mappings);
        $job->handle();

        // Verify product was created
        $product = Product::where('parent_sku', 'TEST888')->first();
        expect($product)->not()->toBeNull();

        $variant = ProductVariant::where('sku', 'TEST888-BLUE')->first();
        expect($variant)->not()->toBeNull();

        // Verify events were dispatched
        Event::assertDispatched(ProductImportProgress::class);

        // File should be cleaned up
        expect(file_exists($filePath))->toBeFalse();
    });
});
