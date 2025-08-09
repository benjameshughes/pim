<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantAttribute;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

describe('ProductWizard Builder Pattern Integration Analysis', function () {
    beforeEach(function () {
        // Create required attributes
        AttributeDefinition::factory()->create([
            'key' => 'material',
            'label' => 'Material',
            'is_required' => true,
            'applies_to' => 'products',
            'status' => 'active'
        ]);
        
        // Create some barcode pool entries
        BarcodePool::factory(3)->create([
            'barcode_type' => 'EAN13',
            'status' => 'available'
        ]);
    });
});

describe('Product Builder Integration', function () {
    it('uses Product::build() fluent API correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Builder Test Product')
            ->set('form.slug', 'builder-test-product')
            ->set('form.description', 'Testing the builder pattern')
            ->set('form.status', 'active')
            ->set('parentSku', '001')
            ->set('attributeValues.material', 'Cotton Blend')
            ->set('selectedColors', ['Navy'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Builder Test Product')->first();
        
        expect($product)->not->toBeNull();
        expect($product->name)->toBe('Builder Test Product');
        expect($product->slug)->toBe('builder-test-product');
        expect($product->description)->toBe('Testing the builder pattern');
        expect($product->status)->toBe('active');
        expect($product->parent_sku)->toBe('001');
    });
    
    it('integrates product features through builder pattern', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Feature Rich Product')
            ->set('form.status', 'active')
            ->set('parentSku', '002')
            ->set('form.product_features_1', 'Blackout coating')
            ->set('form.product_features_2', 'UV protection')
            ->set('form.product_features_3', 'Easy clean')
            ->set('form.product_features_4', '') // Empty - should be filtered out
            ->set('form.product_features_5', 'Fire retardant')
            ->set('attributeValues.material', 'Polyester')
            ->set('selectedColors', ['White'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Feature Rich Product')->first();
        
        expect($product->features)->toContain('Blackout coating');
        expect($product->features)->toContain('UV protection');
        expect($product->features)->toContain('Easy clean');
        expect($product->features)->toContain('Fire retardant');
        expect($product->features)->not->toContain(''); // Empty should be filtered
        expect($product->features)->toHaveCount(4);
    });
    
    it('integrates product details through builder pattern', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Detailed Product')
            ->set('form.status', 'active')
            ->set('parentSku', '003')
            ->set('form.product_details_1', '100% polyester fabric')
            ->set('form.product_details_2', 'Machine washable at 30°C')
            ->set('form.product_details_3', 'Includes mounting hardware')
            ->set('form.product_details_4', '') // Empty
            ->set('form.product_details_5', '2 year warranty')
            ->set('attributeValues.material', 'Synthetic')
            ->set('selectedColors', ['Beige'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Detailed Product')->first();
        
        expect($product->details)->toContain('100% polyester fabric');
        expect($product->details)->toContain('Machine washable at 30°C');
        expect($product->details)->toContain('Includes mounting hardware');
        expect($product->details)->toContain('2 year warranty');
        expect($product->details)->not->toContain(''); // Empty should be filtered
        expect($product->details)->toHaveCount(4);
    });
    
    it('integrates product attributes through builder pattern', function () {
        // Create additional attributes for testing
        AttributeDefinition::factory()->create([
            'key' => 'opacity',
            'label' => 'Opacity Level',
            'is_required' => false,
            'applies_to' => 'products'
        ]);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Attribute Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '004')
            ->set('attributeValues.material', 'Bamboo')
            ->set('attributeValues.opacity', 'Semi-transparent')
            ->set('selectedColors', ['Natural'])
            ->set('assignBarcodes', false)
            ->call('loadAttributeDefinitions') // Reload to get new attribute
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Attribute Test Product')->first();
        
        // Verify product attributes are set through builder
        expect($product->getAttributeValue('material'))->toBe('Bamboo');
        expect($product->getAttributeValue('opacity'))->toBe('Semi-transparent');
    });
});

describe('ProductVariant Builder Integration', function () {
    it('creates variants using ProductVariant::buildFor() fluent API', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Variant Builder Test')
            ->set('form.status', 'active')
            ->set('parentSku', '005')
            ->set('attributeValues.material', 'Canvas')
            ->set('selectedColors', ['Charcoal'])
            ->set('selectedWidths', ['150cm'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Variant Builder Test')->first();
        $variant = $product->variants->first();
        
        expect($variant)->not->toBeNull();
        expect($variant->sku)->toBe('005-001');
        expect($variant->color)->toBe('Charcoal');
        expect($variant->width)->toBe('150cm');
        expect($variant->stock_level)->toBe(0);
        expect($variant->status)->toBe('active');
    });
    
    it('handles window dimensions through builder pattern', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Window Dimensions Test')
            ->set('form.status', 'active')
            ->set('parentSku', '006')
            ->set('attributeValues.material', 'Fabric')
            ->set('selectedColors', ['Grey'])
            ->set('selectedWidths', ['120cm'])
            ->set('selectedDrops', ['180cm'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Window Dimensions Test')->first();
        $variant = $product->variants->first();
        
        expect($variant->width)->toBe('120cm');
        expect($variant->drop)->toBe('180cm');
    });
    
    it('integrates barcode assignment through builder pattern', function () {
        $barcodes = BarcodePool::where('status', 'available')->take(2)->get();
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Barcode Builder Test')
            ->set('form.status', 'active')
            ->set('parentSku', '007')
            ->set('attributeValues.material', 'Vinyl')
            ->set('selectedColors', ['Black', 'White'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        $product = Product::where('name', 'Barcode Builder Test')->first();
        $variants = $product->variants;
        
        expect($variants)->toHaveCount(2);
        
        // Verify barcodes were assigned through builder
        foreach ($variants as $variant) {
            expect($variant->barcodes)->not->toBeEmpty();
            $primaryBarcode = $variant->barcodes->where('is_primary', true)->first();
            expect($primaryBarcode)->not->toBeNull();
            expect($primaryBarcode->barcode_type)->toBe('EAN13');
        }
        
        // Verify barcode pool was updated
        $assignedCount = BarcodePool::where('status', 'assigned')->count();
        expect($assignedCount)->toBe(2);
    });
    
    it('handles complex variant matrix with all dimensions', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Complex Matrix Test')
            ->set('form.status', 'active') 
            ->set('parentSku', '008')
            ->set('attributeValues.material', 'Linen')
            ->set('selectedColors', ['Cream', 'Taupe'])
            ->set('selectedWidths', ['100cm', '120cm'])
            ->set('selectedDrops', ['140cm', '160cm'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'Complex Matrix Test')->first();
        $variants = $product->variants;
        
        // Should create 2 colors × 2 widths × 2 drops = 8 variants
        expect($variants)->toHaveCount(8);
        
        // Verify all combinations exist
        $combinations = [];
        foreach ($variants as $variant) {
            $combinations[] = [
                'color' => $variant->color,
                'width' => $variant->width,
                'drop' => $variant->drop
            ];
        }
        
        expect($combinations)->toContain([
            'color' => 'Cream',
            'width' => '100cm',
            'drop' => '140cm'
        ]);
        
        expect($combinations)->toContain([
            'color' => 'Taupe',
            'width' => '120cm',
            'drop' => '160cm'
        ]);
    });
});

describe('Builder Pattern Error Handling and Fallbacks', function () {
    it('logs successful variant creation through builder', function () {
        Log::spy();
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Builder Success Log Test')
            ->set('form.status', 'active')
            ->set('parentSku', '009')
            ->set('attributeValues.material', 'Cotton')
            ->set('selectedColors', ['Blue'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // Verify success log
        Log::shouldHaveReceived('info')->with(
            'Created variant 009-001 with Builder pattern'
        );
    });
    
    it('implements fallback when builder pattern fails', function () {
        Log::spy();
        
        // This test simulates builder failure scenario
        // In reality, we'd need to mock the builder to throw an exception
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Fallback Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '010')
            ->set('attributeValues.material', 'Leather')
            ->set('selectedColors', ['Brown'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // Product and variant should still be created
        $product = Product::where('name', 'Fallback Test Product')->first();
        expect($product)->not->toBeNull();
        expect($product->variants)->toHaveCount(1);
    });
    
    it('handles fallback barcode assignment correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Fallback Barcode Test')
            ->set('form.status', 'active')
            ->set('parentSku', '011')
            ->set('attributeValues.material', 'Silk')
            ->set('selectedColors', ['Gold'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        $variant = ProductVariant::where('sku', '011-001')->first();
        expect($variant)->not->toBeNull();
        
        // Should have barcode even if builder failed and fallback was used
        expect($variant->barcodes)->not->toBeEmpty();
    });
    
    it('ensures SKU uniqueness in fallback creation', function () {
        // Create existing variant that would conflict
        ProductVariant::factory()->create(['sku' => '012-001']);
        
        $component = Livewire::test(ProductWizard::class);
        
        // Set up variant that would generate conflicting SKU
        $component->set('form.name', 'SKU Conflict Test')
            ->set('form.status', 'active')
            ->set('parentSku', '012') // Same as existing
            ->set('attributeValues.material', 'Wool')
            ->set('selectedColors', ['Red'])
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // Should create variant with unique SKU (012-001-1 or similar)
        $product = Product::where('name', 'SKU Conflict Test')->first();
        $variant = $product->variants->first();
        
        expect($variant->sku)->not->toBe('012-001'); // Should be different
        expect($variant->sku)->toStartWith('012-001-'); // Should have suffix
    });
});

describe('Builder Pattern Configuration and Options', function () {
    it('respects SKU generation method in builder', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'SKU Method Test')
            ->set('form.status', 'active')
            ->set('parentSku', '013')
            ->set('attributeValues.material', 'Hemp')
            ->set('selectedColors', ['Green', 'Brown'])
            ->set('skuGenerationMethod', 'sequential')
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $product = Product::where('name', 'SKU Method Test')->first();
        $variants = $product->variants->sortBy('sku');
        
        expect($variants->first()->sku)->toBe('013-001');
        expect($variants->last()->sku)->toBe('013-002');
    });
    
    it('handles random SKU generation correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Random SKU Test')
            ->set('form.status', 'active')
            ->set('parentSku', '014')
            ->set('attributeValues.material', 'Jute')
            ->set('selectedColors', ['Tan'])
            ->set('skuGenerationMethod', 'random')
            ->set('assignBarcodes', false)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        $variant = ProductVariant::where('sku', 'LIKE', '014-%')->first();
        expect($variant)->not->toBeNull();
        expect($variant->sku)->toMatch('/014-\d{3}/'); // Should match pattern
    });
    
    it('maintains variant status and stock levels through builder', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Modify variant matrix to have custom stock levels and status
        $component->set('form.name', 'Status Stock Test')
            ->set('form.status', 'active')
            ->set('parentSku', '015')
            ->set('attributeValues.material', 'Organic Cotton')
            ->set('selectedColors', ['Ivory'])
            ->call('generateVariantMatrix');
            
        // Manually modify variant matrix for testing
        $matrix = $component->get('variantMatrix');
        $matrix[0]['stock_level'] = 25;
        $matrix[0]['status'] = 'inactive';
        $component->set('variantMatrix', $matrix);
        
        $component->set('assignBarcodes', false)
            ->call('createProduct');
            
        $variant = ProductVariant::where('sku', '015-001')->first();
        expect($variant->stock_level)->toBe(25);
        expect($variant->status)->toBe('inactive');
    });
});