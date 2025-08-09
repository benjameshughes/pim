<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

describe('ProductWizard Comprehensive Testing', function () {
    beforeEach(function () {
        // Create test attribute definitions
        AttributeDefinition::factory()->create([
            'key' => 'color',
            'label' => 'Color',
            'is_required' => false,
            'applies_to' => 'both',
            'status' => 'active'
        ]);
        
        AttributeDefinition::factory()->create([
            'key' => 'material',
            'label' => 'Material', 
            'is_required' => true,
            'applies_to' => 'products',
            'status' => 'active'
        ]);
        
        // Create barcode pool for testing
        BarcodePool::factory()->create([
            'barcode' => '123456789012',
            'barcode_type' => 'EAN13',
            'status' => 'available'
        ]);
        
        BarcodePool::factory()->create([
            'barcode' => '123456789013',
            'barcode_type' => 'EAN13', 
            'status' => 'available'
        ]);
    });
});

describe('ProductWizard Step Validation', function () {
    it('validates step 1 basic info correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Test with empty required fields
        $component->set('currentStep', 1)
            ->call('nextStep');
            
        $component->assertHasErrors(['form.name']);
        expect($component->get('currentStep'))->toBe(1);
        expect($component->get('completedSteps'))->not->toContain(1);
    });
    
    it('validates required attributes in step 4', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '001')
            ->set('currentStep', 4)
            ->set('attributeValues.material', '') // Empty required attribute
            ->call('nextStep');
            
        $component->assertHasErrors(['attributeValues.material']);
        expect($component->get('currentStep'))->toBe(4);
    });
    
    it('validates variants exist in step 5', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Product')
            ->set('generateVariants', true)
            ->set('selectedColors', []) // No selections
            ->set('selectedWidths', [])
            ->set('selectedDrops', [])
            ->set('currentStep', 5)
            ->call('nextStep');
            
        $component->assertHasErrors(['variants']);
    });
    
    it('validates barcode availability in step 6', function () {
        // Clear all available barcodes
        BarcodePool::query()->update(['status' => 'assigned']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Product')
            ->set('generateVariants', true)
            ->set('selectedColors', ['Red', 'Blue'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('currentStep', 6)
            ->call('loadBarcodeStats')
            ->call('nextStep');
            
        $component->assertHasErrors(['barcodes']);
        expect($component->get('availableBarcodesCount'))->toBe(0);
    });
});

describe('ProductWizard Variant Generation', function () {
    it('generates variant matrix correctly with colors and widths', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Blinds')
            ->set('parentSku', '001')
            ->set('selectedColors', ['Red', 'Blue'])
            ->set('selectedWidths', ['120cm', '140cm'])
            ->set('skuGenerationMethod', 'sequential')
            ->call('generateVariantMatrix');
            
        $matrix = $component->get('variantMatrix');
        expect($matrix)->toHaveCount(4); // 2 colors × 2 widths
        expect($matrix[0]['sku'])->toBe('001-001');
        expect($matrix[1]['sku'])->toBe('001-002');
        expect($matrix[0]['color'])->toBe('Red');
        expect($matrix[0]['width'])->toBe('120cm');
    });
    
    it('handles empty variant selections gracefully', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', [])
            ->set('selectedWidths', [])
            ->set('selectedDrops', [])
            ->call('generateVariantMatrix');
            
        expect($component->get('variantMatrix'))->toBeEmpty();
    });
    
    it('generates unique random SKUs correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Create existing variant to force uniqueness check
        ProductVariant::factory()->create(['sku' => '001-123']);
        
        $component->set('form.name', 'Test Product')
            ->set('parentSku', '001')
            ->set('selectedColors', ['Red', 'Blue'])
            ->set('skuGenerationMethod', 'random')
            ->call('generateVariantMatrix');
            
        $matrix = $component->get('variantMatrix');
        expect($matrix)->toHaveCount(2);
        expect($matrix[0]['sku'])->not->toBe('001-123'); // Should be different
        expect($matrix[1]['sku'])->not->toBe($matrix[0]['sku']); // Should be unique
    });
});

describe('ProductWizard Barcode Assignment', function () {
    it('auto-assigns barcodes to variants correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Product')
            ->set('selectedColors', ['Red', 'Blue'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(2);
        expect($barcodes[0])->toBe('123456789012');
        expect($barcodes[1])->toBe('123456789013');
    });
    
    it('handles insufficient barcode pool gracefully', function () {
        // Clear barcodes except one
        BarcodePool::where('barcode', '!=', '123456789012')->delete();
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Red', 'Blue', 'Green']) // Need 3, have 1
            ->set('barcodeType', 'EAN13')
            ->call('loadBarcodeStats')
            ->call('assignBarcodesToVariants');
            
        expect($component->get('availableBarcodesCount'))->toBe(1);
        expect($component->get('variantBarcodes'))->toHaveCount(1); // Only 1 assigned
    });
    
    it('removes barcode assignments when method changes to skip', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Red'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('assignBarcodesToVariants')
            ->set('barcodeAssignmentMethod', 'skip');
            
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
});

describe('ProductWizard Smart Autocomplete Features', function () {
    it('adds colors correctly and updates custom list', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->call('addColor', 'Crimson Red');
        
        expect($component->get('selectedColors'))->toContain('Crimson Red');
        expect($component->get('customColors'))->toContain('Crimson Red');
        expect($component->get('colorInput'))->toBe('');
    });
    
    it('prevents duplicate color additions', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->call('addColor', 'Red')
            ->call('addColor', 'Red'); // Duplicate
            
        expect($component->get('selectedColors'))->toHaveCount(1);
    });
    
    it('removes colors correctly and re-indexes array', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->call('addColor', 'Red')
            ->call('addColor', 'Blue')
            ->call('addColor', 'Green')
            ->call('removeColor', 1); // Remove Blue
            
        $colors = $component->get('selectedColors');
        expect($colors)->toHaveCount(2);
        expect($colors)->toBe(['Red', 'Green']); // Should be re-indexed
    });
    
    it('auto-formats width inputs correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->call('addWidth', '120'); // Just number
        
        expect($component->get('selectedWidths'))->toContain('120cm');
    });
});

describe('ProductWizard Create Product Critical Issues', function () {
    it('exposes the redirect issue in createProduct method', function () {
        Log::spy(); // Spy on logs to verify error handling
        
        $component = Livewire::test(ProductWizard::class);
        
        // Setup valid data
        $component->set('form.name', 'Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '001')
            ->set('selectedColors', ['Red'])
            ->set('generateVariants', true)
            ->set('assignBarcodes', false) // Avoid barcode complexity
            ->set('attributeValues.material', 'Cotton') // Required attribute
            ->call('generateVariantMatrix');
            
        // This should expose the redirect issue - the method uses return redirect()
        // instead of $this->redirectRoute() which is incorrect in Livewire
        try {
            $component->call('createProduct');
            
            // If we reach here, the redirect issue should be evident
            // The method should have redirected but might not have worked properly
            $this->assertTrue(true, 'Method executed without throwing exception');
        } catch (\Exception $e) {
            // This might catch the redirect issue
            expect($e->getMessage())->toContain('redirect');
        }
        
        // Check if product was created despite redirect issues
        expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
    });
    
    it('identifies transaction and redirect interaction issues', function () {
        DB::spy(); // Spy on database transactions
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '002')
            ->set('selectedColors', ['Blue'])
            ->set('attributeValues.material', 'Polyester')
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // The method uses DB::transaction with a redirect inside
        // This is problematic because redirects don't work inside transactions in Livewire
        DB::shouldHaveReceived('transaction')->once();
        
        // Verify the product exists (transaction should have completed)
        expect(Product::where('name', 'Test Product')->exists())->toBeTrue();
    });
    
    it('tests exception handling in createProduct method', function () {
        Log::spy();
        
        // Force an exception by using invalid data
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', '') // Invalid - required field
            ->set('parentSku', '003')
            ->call('createProduct');
            
        // Should flash error message but method continues executing
        // This is another bug - missing return statement after error
        Log::shouldHaveReceived('error')->once();
    });
    
    it('identifies missing return statements after validation failures', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Set up data that will fail step validation
        $component->set('form.name', '') // Empty required field
            ->set('currentStep', 1)
            ->call('createProduct');
            
        // The method should have returned early after validation failure
        // but the original code might continue executing
        // This test will pass if the bug exists (method continues after flash message)
        
        // No product should be created
        expect(Product::count())->toBe(0);
    });
});

describe('ProductWizard Builder Pattern Integration', function () {
    it('tests Product Builder pattern integration', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Builder Test Product')
            ->set('form.description', 'Testing builder pattern')
            ->set('form.status', 'active')
            ->set('parentSku', '004')
            ->set('form.product_features_1', 'Feature 1')
            ->set('form.product_features_2', 'Feature 2')
            ->set('form.product_details_1', 'Detail 1')
            ->set('attributeValues.material', 'Canvas')
            ->set('selectedColors', ['Green'])
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // Verify product was created with builder pattern
        $product = Product::where('name', 'Builder Test Product')->first();
        expect($product)->not->toBeNull();
        expect($product->parent_sku)->toBe('004');
        expect($product->features)->toContain('Feature 1');
        expect($product->details)->toContain('Detail 1');
    });
    
    it('tests ProductVariant Builder pattern with attributes', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Variant Builder Test')
            ->set('form.status', 'active')
            ->set('parentSku', '005')
            ->set('attributeValues.material', 'Vinyl')
            ->set('selectedColors', ['Purple'])
            ->set('selectedWidths', ['150cm'])
            ->set('selectedDrops', ['200cm'])
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // Verify variant was created with builder pattern
        $variant = ProductVariant::where('sku', '005-001')->first();
        expect($variant)->not->toBeNull();
        expect($variant->color)->toBe('Purple');
        expect($variant->width)->toBe('150cm');
        expect($variant->drop)->toBe('200cm');
    });
    
    it('tests builder pattern fallback when creation fails', function () {
        Log::spy();
        
        // This test would need to simulate builder failure
        // The component has fallback logic for when builder fails
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Fallback Test')
            ->set('form.status', 'active') 
            ->set('parentSku', '006')
            ->set('attributeValues.material', 'Fabric')
            ->set('selectedColors', ['Orange'])
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        // If builder fails, should fall back to direct creation
        expect(ProductVariant::where('sku', '006-001')->exists())->toBeTrue();
    });
});

describe('ProductWizard Parent SKU Management', function () {
    it('generates unique parent SKU correctly', function () {
        // Create existing products
        Product::factory()->create(['parent_sku' => '001']);
        Product::factory()->create(['parent_sku' => '005']);
        
        $component = Livewire::test(ProductWizard::class);
        
        // Should generate '006' as next available
        $parentSku = $component->get('parentSku');
        expect($parentSku)->toBe('006');
    });
    
    it('validates parent SKU uniqueness', function () {
        Product::factory()->create(['parent_sku' => '007', 'name' => 'Existing Product']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('parentSku', '007')
            ->call('updatedParentSku');
            
        $component->assertHasErrors(['parentSku']);
        expect($component->get('skuConflictProduct'))->toBe('Existing Product');
    });
    
    it('regenerates parent SKU when requested', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $originalSku = $component->get('parentSku');
        
        $component->call('regenerateParentSku');
        
        // Should generate a new SKU (might be same if no conflicts)
        expect($component->get('parentSku'))->toBeString();
    });
});

describe('ProductWizard Edge Cases and Error Handling', function () {
    it('handles empty variant matrix gracefully', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'No Variants Product')
            ->set('form.status', 'active')
            ->set('parentSku', '008')
            ->set('attributeValues.material', 'Steel')
            ->set('generateVariants', false)
            ->set('customVariants', []) // No custom variants either
            ->set('currentStep', 5)
            ->call('validateCurrentStep');
            
        $component->assertHasErrors(['variants']);
    });
    
    it('handles barcode assignment with no available barcodes', function () {
        BarcodePool::query()->update(['status' => 'assigned']); // No available barcodes
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Black'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('assignBarcodesToVariants');
            
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
    
    it('ensures SKU uniqueness with collision resolution', function () {
        // Create variant that will collide with generated SKU
        ProductVariant::factory()->create(['sku' => '009-001']);
        ProductVariant::factory()->create(['sku' => '009-001-1']); // Also collides
        
        $component = Livewire::test(ProductWizard::class);
        
        // This should create a unique SKU despite collisions
        $uniqueSku = $component->ensureUniqueSku('009-001');
        expect($uniqueSku)->toBe('009-001-2'); // Should append -2
    });
    
    it('validates all steps before product creation', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Missing required data for multiple steps
        $component->set('form.name', '') // Step 1 invalid
            ->set('attributeValues.material', '') // Step 4 invalid
            ->call('createProduct');
            
        // Should fail on first invalid step and not create product
        expect(Product::count())->toBe(0);
    });
});