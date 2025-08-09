<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ProductWizard Complex Integration Debug 🚀', function () {

    beforeEach(function () {
        Storage::fake('public');
        
        // Create required attribute for testing
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);
        
        // Create plenty of barcodes for multiple variants
        BarcodePool::factory()->ean13()->count(20)->create();
    });

    it('debugs complex product creation step by step', function () {
        Log::info('=== STARTING COMPLEX PRODUCT CREATION DEBUG ===');
        
        $image = UploadedFile::fake()->image('product.jpg');
        
        $component = Livewire::test(ProductWizard::class)
            ->set('form.name', 'Complex Integration Test')
            ->set('form.description', 'Testing complex scenario')
            ->set('form.status', 'active')
            ->set('form.product_features_1', 'Feature 1')
            ->set('form.product_features_2', 'Feature 2')
            ->set('parentSku', '001')
            ->set('newImages.0', $image)
            ->set('imageType', 'gallery')
            ->set('attributeValues.color', 'Integration Blue')
            ->set('selectedColors', ['Black', 'White', 'Grey'])
            ->set('selectedWidths', ['120cm', '160cm'])
            ->set('selectedDrops', ['200cm'])
            ->set('barcodeAssignmentMethod', 'auto');
        
        Log::info('Component setup completed');
        
        // Generate variant matrix
        $component->call('generateVariantMatrix');
        $variantMatrix = $component->get('variantMatrix');
        
        Log::info('Generated variant matrix count: ' . count($variantMatrix));
        Log::info('Variant matrix: ' . json_encode($variantMatrix));
        
        // Check barcode assignment
        $variantBarcodes = $component->get('variantBarcodes');
        Log::info('Assigned barcodes count: ' . count($variantBarcodes));
        
        // Check initial database state
        expect(Product::count())->toBe(0);
        expect(ProductVariant::count())->toBe(0);
        
        // Try to create the product
        $component->call('createProduct');
        
        // Check results
        $productCount = Product::count();
        $variantCount = ProductVariant::count();
        
        Log::info("After complex createProduct call:");
        Log::info("Products count: {$productCount}");
        Log::info("Variants count: {$variantCount}");
        
        // Check flash messages
        $flashMessage = session('message');
        $flashError = session('error');
        
        Log::info('Flash message: ' . ($flashMessage ?? 'none'));
        Log::info('Flash error: ' . ($flashError ?? 'none'));
        
        // This should show us exactly what's failing
        expect($productCount)->toBe(1);
        expect($variantCount)->toBe(6); // 3 colors × 2 widths × 1 drop
    });

    it('tests variant creation with multiple variants', function () {
        // First create a product successfully
        $product = Product::factory()->create(['name' => 'Test Product for Multiple Variants']);
        
        Log::info('Created test product: ' . $product->id);
        
        // Test creating multiple variants
        $variants = [
            ['sku' => 'TEST-001', 'color' => 'Black'],
            ['sku' => 'TEST-002', 'color' => 'White'],
            ['sku' => 'TEST-003', 'color' => 'Grey'],
        ];
        
        $createdCount = 0;
        
        foreach ($variants as $variantData) {
            try {
                $builder = ProductVariant::buildFor($product)
                    ->sku($variantData['sku'])
                    ->color($variantData['color'])
                    ->stockLevel(0)
                    ->status('active');

                $variant = $builder->execute();
                $createdCount++;
                
                Log::info("Created variant {$variantData['sku']} successfully: {$variant->id}");
                
            } catch (\Exception $e) {
                Log::error("Failed to create variant {$variantData['sku']}: " . $e->getMessage());
                throw $e;
            }
        }
        
        expect($createdCount)->toBe(3);
        expect(ProductVariant::count())->toBe(3);
    });
});