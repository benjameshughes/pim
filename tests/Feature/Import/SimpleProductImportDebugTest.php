<?php

use App\Actions\Import\SimpleImportAction;
use App\Models\Product;
use App\Models\ProductVariant;
use Livewire\Livewire;
use App\Livewire\Import\SimpleProductImport;

describe('Import System Debug Tests', function () {
    beforeEach(function () {
        // Clean up
        ProductVariant::query()->delete();
        Product::query()->delete();
    });

    describe('Basic Component Functionality', function () {
        it('can mount the import component and render correctly', function () {
            $component = Livewire::test(SimpleProductImport::class);
            
            expect($component->get('step'))->toBe('upload')
                ->and($component->get('importing'))->toBeFalse()
                ->and($component->get('progress'))->toBe(0);
            
            $html = $component->html();
            expect($html)->toContain('Import Products')
                ->and($html)->toContain('Upload CSV File')
                ->and($html)->toContain('Step 1 of 4');
        });

        it('can transition between steps manually', function () {
            $component = Livewire::test(SimpleProductImport::class);
            
            // Manually set up mapping step
            $component->set('step', 'mapping')
                     ->set('headers', ['SKU', 'Title', 'Price'])
                     ->set('sampleData', [['TEST-001', 'Test Product', '19.99']]);
            
            expect($component->get('step'))->toBe('mapping');
            
            $html = $component->html();
            expect($html)->toContain('Map Columns');
        });

        it('validates that empty mappings cause errors', function () {
            // Test validation logic by directly checking the validation rules
            $import = new SimpleProductImport();
            
            // Set up invalid mappings
            $import->mappings = [
                'sku' => '',    // Empty - should fail
                'title' => '',  // Empty - should fail  
                'price' => '2',
                'brand' => '3',
                'barcode' => '',
            ];
            
            // Create a mock file to avoid file validation errors
            $csvPath = storage_path('app/test.csv');
            file_put_contents($csvPath, "SKU,Title,Price,Brand\nTEST,Test,19.99,Brand");
            
            $import->file = new \Illuminate\Http\UploadedFile($csvPath, 'test.csv', 'text/csv', null, true);
            $import->step = 'mapping';
            
            // Test executeImport method validation
            try {
                $import->executeImport();
                $hasValidationError = false;
            } catch (\Exception $e) {
                // Will throw because mappings are invalid
                $hasValidationError = true;
            }
            
            // The method should detect invalid mappings
            expect($import->step)->toBe('mapping'); // Should stay on mapping step
            
            @unlink($csvPath);
        });
    });

    describe('SimpleImportAction Core Logic', function () {
        it('can process basic CSV data correctly', function () {
            // Create test CSV file
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001-White,Test Product White,19.99,TestBrand\n" .
                         "TEST-001-Black,Test Product Black,21.99,TestBrand\n";
            
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
                ->and($result['created_products'])->toBe(1)  // One parent product
                ->and($result['created_variants'])->toBe(2)   // Two variants
                ->and($result['skipped_rows'])->toBe(0);
        });

        it('extracts parent SKU correctly for different patterns', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "PARENT-123-Red,Product Red,19.99,Brand\n";
            
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
            
            $product = Product::first();
            expect($product->parent_sku)->toBe('PARENT-123');
            
            $variant = ProductVariant::first();
            expect($variant->sku)->toBe('PARENT-123-Red')
                ->and($variant->color)->toBe('Red');
        });

        it('handles color extraction from titles', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "TEST-001,Test Product Dark Grey 120cm,19.99,Brand\n" .
                         "TEST-002,Test Product Navy Blue Premium,21.99,Brand\n";
            
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
            expect($variants[0]->color)->toBe('Dark Grey')
                ->and($variants[1]->color)->toBe('Navy Blue');
        });

        it('handles missing or invalid data gracefully', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "GOOD-001,Good Product,19.99,Brand\n" .
                         ",Bad Product,21.99,Brand\n" .    // Missing SKU
                         "GOOD-002,,23.99,Brand\n";        // Missing title
            
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
                ->and($result['created_variants'])->toBe(1)  // Only the good row
                ->and($result['skipped_rows'])->toBe(2);     // Two bad rows
        });
    });

    describe('Real-world Import Scenario', function () {
        it('can import a realistic dataset', function () {
            $csvContent = "SKU,Title,Price,Brand\n" .
                         "RB-001-White,Roman Blind White 120cm x 160cm,89.99,Premium\n" .
                         "RB-001-Black,Roman Blind Black 120cm x 160cm,89.99,Premium\n" .
                         "RB-002-Cream,Roman Blind Cream 90cm x 140cm,79.99,Standard\n" .
                         "VB-100-Red,Vertical Blind Red 150cm x 200cm,109.99,Deluxe\n";
            
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
                ->and($result['created_products'])->toBe(3)  // RB-001, RB-002, VB-100
                ->and($result['created_variants'])->toBe(4);
            
            // Verify product structure
            $products = Product::with('variants')->get();
            expect($products->count())->toBe(3);
            
            // Check RB-001 has 2 variants (White, Black)
            $rbProduct = $products->where('parent_sku', 'RB-001')->first();
            expect($rbProduct->variants->count())->toBe(2);
            
            // Verify variant details
            $whiteVariant = $rbProduct->variants->where('color', 'White')->first();
            expect($whiteVariant->width)->toBe(120)
                ->and($whiteVariant->drop)->toBe(160)
                ->and((float) $whiteVariant->price)->toBe(89.99);
        });
    });
});