<?php

use App\Livewire\Import\SimpleProductImport;
use App\Actions\Import\SimpleImportAction;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

describe('Simple Product Import System', function () {
    beforeEach(function () {
        // Clean up any existing data
        ProductVariant::query()->delete();
        Product::query()->delete();
    });

    describe('Livewire Component Tests', function () {
        it('can mount the import component', function () {
            Livewire::test(SimpleProductImport::class)
                ->assertSet('step', 'upload')
                ->assertSet('importing', false)
                ->assertSet('progress', 0)
                ->assertSee('Upload File');
        });

        it('validates file upload requirements', function () {
            Livewire::test(SimpleProductImport::class)
                ->set('file', null)
                ->call('executeImport')
                ->assertHasErrors(['file' => 'required']);
        });

        it('processes CSV file upload correctly', function () {
            // Create test CSV content
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001-White,Test Product White 120cm,19.99,TestBrand\n" .
                         "TEST-001-Black,Test Product Black 120cm,21.99,TestBrand\n";
            
            // Create temporary file
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            // Create uploaded file mock
            $uploadedFile = new UploadedFile($tmpPath, 'test.csv', 'text/csv', null, true);
            
            Livewire::test(SimpleProductImport::class)
                ->set('file', $uploadedFile)
                ->assertSet('step', 'mapping')
                ->assertCount('headers', 4)
                ->assertSee('SKU')
                ->assertSee('Title')
                ->assertSee('Price')
                ->assertSee('Brand');
        });

        it('auto-maps common columns correctly', function () {
            $csvContent = "Linnworks SKU,Item Title,Retail Price,Brand\n" .
                         "TEST-001-White,Test Product White,19.99,TestBrand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $uploadedFile = new UploadedFile($tmpPath, 'test.csv', 'text/csv', null, true);
            
            Livewire::test(SimpleProductImport::class)
                ->set('file', $uploadedFile)
                ->assertSet('mappings.sku', 0)  // Should auto-map to "Linnworks SKU"
                ->assertSet('mappings.title', 1)  // Should auto-map to "Item Title"
                ->assertSet('mappings.price', 2);  // Should auto-map to "Retail Price"
        });

        it('validates required mappings before import', function () {
            $csvContent = "Col1,Col2,Col3\nValue1,Value2,Value3\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $uploadedFile = new UploadedFile($tmpPath, 'test.csv', 'text/csv', null, true);
            
            Livewire::test(SimpleProductImport::class)
                ->set('file', $uploadedFile)
                ->set('mappings.sku', '')  // No SKU mapping
                ->set('mappings.title', '') // No title mapping
                ->call('executeImport')
                ->assertHasErrors(['mappings' => 'SKU and Title columns are required']);
        });

        it('can reset the import state', function () {
            Livewire::test(SimpleProductImport::class)
                ->set('step', 'complete')
                ->set('results', ['some' => 'data'])
                ->call('startOver')
                ->assertSet('step', 'upload')
                ->assertSet('results', null);
        });
    });

    describe('SimpleImportAction Tests', function () {
        it('can execute a basic import', function () {
            // Create test CSV
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001-White,Test Product White 120cm,19.99,TestBrand\n" .
                         "TEST-001-Black,Test Product Black 120cm,21.99,TestBrand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,      // SKU column
                    'title' => 1,    // Title column  
                    'price' => 2,    // Price column
                    'brand' => 3,    // Brand column
                    'barcode' => '', // Not mapped
                ],
            ]);
            
            expect($result['success'])->toBeTrue()
                ->and($result['created_products'])->toBeGreaterThan(0)
                ->and($result['created_variants'])->toBe(2)
                ->and($result['errors'])->toBeEmpty();
        });

        it('creates parent products correctly', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "PARENT-001-White,Parent Product White,19.99,TestBrand\n" .
                         "PARENT-001-Black,Parent Product Black,21.99,TestBrand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            // Should create one parent product
            expect(Product::count())->toBe(1);
            
            $product = Product::first();
            expect($product->parent_sku)->toBe('PARENT-001')
                ->and($product->brand)->toBe('TestBrand');
        });

        it('creates variants with correct attributes', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "VAR-123-Red,Red Variant 60cm x 120cm,29.99,TestBrand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            expect(ProductVariant::count())->toBe(1);
            
            $variant = ProductVariant::first();
            expect($variant->sku)->toBe('VAR-123-Red')
                ->and($variant->color)->toBe('Red')
                ->and($variant->width)->toBe(60)
                ->and($variant->drop)->toBe(120)
                ->and($variant->price)->toBe(29.99);
        });

        it('handles different SKU patterns correctly', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "001-002-003,Pattern1 Product,19.99,Brand1\n" .
                         "010-108,Pattern2 Product,21.99,Brand2\n" .
                         "ABC123-Blue,Pattern3 Product Blue,23.99,Brand3\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            // Should create 3 different products (different parent SKUs)
            expect(Product::count())->toBe(3)
                ->and(ProductVariant::count())->toBe(3);
            
            // Verify parent SKU extraction
            $parentSkus = Product::pluck('parent_sku')->toArray();
            expect($parentSkus)->toContain('001-002')  // Pattern 1
                ->and($parentSkus)->toContain('010')      // Pattern 2  
                ->and($parentSkus)->toContain('ABC123');  // Pattern 3
        });

        it('handles missing data gracefully', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "GOOD-001,Good Product,19.99,GoodBrand\n" .
                         ",Missing SKU Product,21.99,Brand\n" .
                         "GOOD-002,,23.99,Brand\n";  // Missing title
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            expect($result['success'])->toBeTrue()
                ->and($result['created_variants'])->toBe(1)  // Only 1 good row
                ->and($result['skipped_rows'])->toBe(2);    // 2 bad rows skipped
        });

        it('reports progress during import', function () {
            $csvContent = "SKU,Title,Price,Brand\n";
            for ($i = 1; $i <= 10; $i++) {
                $csvContent .= "TEST-{$i}-White,Test Product {$i},19.99,TestBrand\n";
            }
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $progressUpdates = [];
            $action = new SimpleImportAction();
            
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
                'progressCallback' => function ($progress) use (&$progressUpdates) {
                    $progressUpdates[] = $progress;
                },
            ]);
            
            expect($progressUpdates)->not->toBeEmpty()
                ->and(end($progressUpdates))->toBe(100);  // Should end at 100%
        });

        it('handles price parsing correctly', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001,Test Product,$19.99,Brand\n" .
                         "TEST-002,Test Product,£25.50,Brand\n" .
                         "TEST-003,Test Product,30,Brand\n" .
                         "TEST-004,Test Product,,Brand\n";  // Empty price
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            $variants = ProductVariant::orderBy('sku')->get();
            
            expect($variants[0]->price)->toBe(19.99)
                ->and($variants[1]->price)->toBe(25.50)
                ->and($variants[2]->price)->toBe(30.0)
                ->and($variants[3]->price)->toBeNull();
        });
    });

    describe('Integration Tests', function () {
        it('can complete full import workflow through Livewire', function () {
            // Create realistic test data
            $csvContent = "Linnworks SKU,Item Title,Retail Price,Brand\n" .
                         "RB-001-White,Roman Blind White 120cm x 160cm,89.99,Premium\n" .
                         "RB-001-Black,Roman Blind Black 120cm x 160cm,89.99,Premium\n" .
                         "RB-002-Cream,Roman Blind Cream 90cm x 140cm,79.99,Standard\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $uploadedFile = new UploadedFile($tmpPath, 'test_import.csv', 'text/csv', null, true);
            
            $component = Livewire::test(SimpleProductImport::class)
                ->set('file', $uploadedFile)
                ->assertSet('step', 'mapping')
                ->assertSet('mappings.sku', 0)     // Auto-mapped
                ->assertSet('mappings.title', 1)   // Auto-mapped  
                ->assertSet('mappings.price', 2)   // Auto-mapped
                ->call('executeImport')
                ->assertSet('step', 'complete')
                ->assertSet('importing', false);
            
            // Verify database state
            expect(Product::count())->toBe(2)  // RB-001 and RB-002
                ->and(ProductVariant::count())->toBe(3);
            
            // Verify results were set
            $results = $component->get('results');
            expect($results['success'])->toBeTrue()
                ->and($results['created_products'])->toBe(2)
                ->and($results['created_variants'])->toBe(3);
        });

        it('handles import errors gracefully in Livewire', function () {
            // Create invalid CSV that will cause database errors
            $csvContent = "SKU,Title,Price,Brand\n" .
                         // Create a SKU that's too long for the database field
                         str_repeat('VERY_LONG_SKU_', 20) . ",Test Product,19.99,Brand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $uploadedFile = new UploadedFile($tmpPath, 'bad_test.csv', 'text/csv', null, true);
            
            $component = Livewire::test(SimpleProductImport::class)
                ->set('file', $uploadedFile)
                ->set('mappings', [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ])
                ->call('executeImport')
                ->assertSet('step', 'complete')
                ->assertSet('importing', false);
            
            // Should have error results
            $results = $component->get('results');
            expect($results['success'])->toBeFalse()
                ->and($results['message'])->toContain('Import failed');
        });
    });

    describe('Edge Cases and Error Handling', function () {
        it('handles empty CSV files', function () {
            $csvContent = "SKU,Title,Price,Brand\n";  // Headers only
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            expect($result['success'])->toBeTrue()
                ->and($result['created_products'])->toBe(0)
                ->and($result['created_variants'])->toBe(0);
        });

        it('handles malformed CSV data', function () {
            // CSV with inconsistent column counts
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "GOOD-001,Good Product,19.99,Brand\n" .
                         "BAD-001,Missing Columns\n" .  // Missing price and brand
                         "GOOD-002,Another Good,29.99,Brand2\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $result = $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            // Should still process the good rows
            expect($result['success'])->toBeTrue()
                ->and($result['created_variants'])->toBe(2);  // Only the good rows
        });

        it('handles color extraction from complex titles', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001,Premium Roman Blind in Burnt Orange 120cm x 160cm,89.99,Brand\n" .
                         "TEST-002,Luxury Blind Dark Grey with Pattern 90cm,79.99,Brand\n" .
                         "TEST-003,Standard Blind Navy Blue Premium Quality,69.99,Brand\n";
            
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            $tmpPath = stream_get_meta_data($tmpFile)['uri'];
            
            $action = new SimpleImportAction();
            $action->execute([
                'file' => $tmpPath,
                'mappings' => [
                    'sku' => 0,
                    'title' => 1,
                    'price' => 2,
                    'brand' => 3,
                    'barcode' => '',
                ],
            ]);
            
            $variants = ProductVariant::orderBy('sku')->get();
            
            expect($variants[0]->color)->toBe('Burnt Orange')
                ->and($variants[1]->color)->toBe('Dark Grey') 
                ->and($variants[2]->color)->toBe('Navy Blue');
        });
    });
});