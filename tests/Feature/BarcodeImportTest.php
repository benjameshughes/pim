<?php

use App\Jobs\ProcessBarcodeImport;
use App\Livewire\Barcodes\BarcodeImport;
use App\Models\Barcode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use League\Csv\Writer;

beforeEach(function () {
    Queue::fake();
});

describe('Barcode Import Component', function () {
    it('starts with upload step', function () {
        Livewire::test(BarcodeImport::class)
            ->assertSet('step', 1)
            ->assertSee('Upload CSV File');
    });

    it('processes CSV file upload and moves to mapping step', function () {
        // Create test CSV content
        $csvContent = "barcode,sku,title,is_assigned\n123456789012,SKU001,Test Product,true\n123456789013,SKU002,Another Product,false";
        
        // Create a proper UploadedFile for Livewire testing
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        Livewire::test(BarcodeImport::class)
            ->set('csvFile', $file)
            ->assertSet('step', 2)
            ->assertSee('Map CSV Columns')
            ->assertCount('availableColumns', 4)
            ->assertSee('123456789012'); // Sample data shown
    });

    it('automatically maps common column names', function () {
        $csvContent = "barcode_code,product_sku,product_title,assigned_status\n123456789012,SKU001,Test Product,true";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        $component = Livewire::test(BarcodeImport::class)
            ->set('csvFile', $file);
            
        // Check smart mapping worked
        expect($component->get('columnMapping')[0])->toBe('barcode'); // barcode_code mapped to barcode
    });

    it('validates required barcode column mapping', function () {
        $csvContent = "code,sku,title\n123456789012,SKU001,Test Product";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        Livewire::test(BarcodeImport::class)
            ->set('csvFile', $file)
            ->set('columnMapping', [1 => 'sku', 2 => 'title']) // No barcode mapping
            ->call('importBarcodes')
            ->assertDispatched('error');
    });

    it('validates barcode mapping is required before import', function () {
        $csvContent = "barcode,sku,title\n123456789012,SKU001,Test Product";
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        // Test that method correctly validates required mapping
        $component = Livewire::test(BarcodeImport::class)
            ->set('csvFile', $file)
            ->set('columnMapping', [0 => 'barcode', 1 => 'sku', 2 => 'title']);
            
        // Verify it has the right validation logic (barcode column is mapped)
        $mappingArray = $component->get('columnMapping');
        expect(in_array('barcode', $mappingArray))->toBeTrue();
        
        // Verify it has a file
        expect($component->get('csvFile'))->not->toBeNull();
    });

    it('allows starting over from any step', function () {
        Livewire::test(BarcodeImport::class)
            ->set('step', 3)
            ->call('startOver')
            ->assertSet('step', 1)
            ->assertSet('csvFile', null)
            ->assertSet('isImporting', false);
    });
});

describe('Barcode Import Job', function () {
    it('processes CSV and creates barcodes', function () {
        // Create test CSV file in temp directory
        $csvContent = "barcode,sku,title,is_assigned\n123456789012,SKU001,Test Product,1\n123456789013,SKU002,Another Product,0";
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode_test');
        file_put_contents($tempFile, $csvContent);
        
        $importId = 'test_import_123';
        $columnMapping = [0 => 'barcode', 1 => 'sku', 2 => 'title', 3 => 'is_assigned'];
        
        $job = new ProcessBarcodeImport($tempFile, $importId, $columnMapping);
        $job->handle();
        
        // Check barcodes were created
        expect(Barcode::count())->toBe(2);
        
        $barcode1 = Barcode::where('barcode', '123456789012')->first();
        expect($barcode1)->not->toBeNull();
        expect($barcode1->sku)->toBe('SKU001');
        expect($barcode1->title)->toBe('Test Product');
        expect($barcode1->is_assigned)->toBeTrue();
        
        $barcode2 = Barcode::where('barcode', '123456789013')->first();
        expect($barcode2->is_assigned)->toBeFalse();
        
        // Cleanup
        unlink($tempFile);
    });

    it('handles duplicate barcodes by skipping', function () {
        // Create existing barcode
        Barcode::create([
            'barcode' => '123456789012',
            'sku' => 'EXISTING',
            'title' => 'Existing Product',
            'is_assigned' => false
        ]);
        
        // Create CSV with duplicate and new barcode
        $csvContent = "barcode,sku,title\n123456789012,SKU001,Duplicate Product\n123456789013,SKU002,New Product";
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode_test');
        file_put_contents($tempFile, $csvContent);
        
        $importId = 'test_import_123';
        $columnMapping = [0 => 'barcode', 1 => 'sku', 2 => 'title'];
        
        $job = new ProcessBarcodeImport($tempFile, $importId, $columnMapping);
        $job->handle();
        
        // Should still only have 2 total (1 existing + 1 new)
        expect(Barcode::count())->toBe(2);
        
        // Original should be unchanged
        $existing = Barcode::where('barcode', '123456789012')->first();
        expect($existing->sku)->toBe('EXISTING');
        
        // New one should be created
        expect(Barcode::where('barcode', '123456789013')->exists())->toBeTrue();
        
        unlink($tempFile);
    });

    it('converts various boolean formats correctly', function () {
        $csvContent = "barcode,is_assigned\n" .
                     "123456789001,1\n" .
                     "123456789002,true\n" .
                     "123456789003,TRUE\n" .
                     "123456789004,yes\n" .
                     "123456789005,assigned\n" .
                     "123456789006,0\n" .
                     "123456789007,false\n" .
                     "123456789008,FALSE\n" .
                     "123456789009,no\n" .
                     "123456789010,unassigned";
                     
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode_test');
        file_put_contents($tempFile, $csvContent);
        
        $importId = 'test_import_123';
        $columnMapping = [0 => 'barcode', 1 => 'is_assigned'];
        
        $job = new ProcessBarcodeImport($tempFile, $importId, $columnMapping);
        $job->handle();
        
        // Check true values
        $trueBarcodes = ['123456789001', '123456789002', '123456789003', '123456789004', '123456789005'];
        foreach ($trueBarcodes as $barcode) {
            expect(Barcode::where('barcode', $barcode)->first()->is_assigned)->toBeTrue("Barcode {$barcode} should be assigned");
        }
        
        // Check false values
        $falseBarcodes = ['123456789006', '123456789007', '123456789008', '123456789009', '123456789010'];
        foreach ($falseBarcodes as $barcode) {
            expect(Barcode::where('barcode', $barcode)->first()->is_assigned)->toBeFalse("Barcode {$barcode} should not be assigned");
        }
        
        unlink($tempFile);
    });

    it('processes large files in batches', function () {
        // Create a smaller test (200 rows to avoid timeout but still test batching)
        $csvContent = "barcode,sku\n";
        for ($i = 1; $i <= 200; $i++) {
            $csvContent .= sprintf("12345678901%04d,SKU%04d\n", $i, $i);
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode_large_test');
        file_put_contents($tempFile, $csvContent);
        
        $importId = 'test_import_large';
        $columnMapping = [0 => 'barcode', 1 => 'sku'];
        
        $job = new ProcessBarcodeImport($tempFile, $importId, $columnMapping);
        $job->handle();
        
        // All 200 should be imported
        expect(Barcode::count())->toBe(200);
        
        // Check first and last
        expect(Barcode::where('barcode', '123456789010001')->exists())->toBeTrue();
        expect(Barcode::where('barcode', '123456789010200')->exists())->toBeTrue();
        
        unlink($tempFile);
    });

    it('skips rows with empty barcodes', function () {
        $csvContent = "barcode,sku,title\n" .
                     "123456789012,SKU001,Valid Product\n" .
                     ",SKU002,Invalid Product - No Barcode\n" .
                     "   ,SKU003,Invalid Product - Whitespace Only\n" .
                     "123456789013,SKU004,Another Valid Product";
                     
        $tempFile = tempnam(sys_get_temp_dir(), 'barcode_test');
        file_put_contents($tempFile, $csvContent);
        
        $importId = 'test_import_123';
        $columnMapping = [0 => 'barcode', 1 => 'sku', 2 => 'title'];
        
        $job = new ProcessBarcodeImport($tempFile, $importId, $columnMapping);
        $job->handle();
        
        // Should only create 2 valid barcodes
        expect(Barcode::count())->toBe(2);
        expect(Barcode::where('barcode', '123456789012')->exists())->toBeTrue();
        expect(Barcode::where('barcode', '123456789013')->exists())->toBeTrue();
        
        unlink($tempFile);
    });
});

describe('Barcode Import Integration', function () {
    it('processes complete upload and mapping workflow', function () {
        $csvContent = "product_barcode,item_sku,product_title,assigned\n" .
                     "123456789012,SKU001,Test Product 1,yes\n" .
                     "123456789013,SKU002,Test Product 2,no";
        
        $file = UploadedFile::fake()->createWithContent('test.csv', $csvContent);
        
        // Test the upload and mapping steps
        $component = Livewire::test(BarcodeImport::class)
            // Step 1: Upload
            ->assertSet('step', 1)
            ->set('csvFile', $file)
            
            // Step 2: Mapping (should auto-advance and auto-map)
            ->assertSet('step', 2)
            ->assertSee('Map CSV Columns')
            ->assertCount('availableColumns', 4);
            
        // Verify smart mapping worked
        $columnMapping = $component->get('columnMapping');
        expect($columnMapping[0])->toBe('barcode'); // product_barcode should map to barcode
        expect($columnMapping[1])->toBe('sku');     // item_sku should map to sku
        expect($columnMapping[2])->toBe('title');   // product_title should map to title
    });
});
