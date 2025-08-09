<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

describe('ProductWizard Barcode Assignment Comprehensive Testing', function () {
    beforeEach(function () {
        // Create required attribute
        AttributeDefinition::factory()->create([
            'key' => 'material',
            'label' => 'Material',
            'is_required' => true,
            'applies_to' => 'products',
            'status' => 'active'
        ]);
        
        // Create barcode pool with different types
        BarcodePool::factory()->create([
            'barcode' => '1234567890123',
            'barcode_type' => 'EAN13',
            'status' => 'available',
            'is_legacy' => false
        ]);
        
        BarcodePool::factory()->create([
            'barcode' => '1234567890124',
            'barcode_type' => 'EAN13',
            'status' => 'available',
            'is_legacy' => false
        ]);
        
        BarcodePool::factory()->create([
            'barcode' => '123456789012',
            'barcode_type' => 'UPC',
            'status' => 'available',
            'is_legacy' => false
        ]);
        
        // Create some legacy barcodes (should be ignored)
        BarcodePool::factory()->create([
            'barcode' => '9999999999999',
            'barcode_type' => 'EAN13',
            'status' => 'available',
            'is_legacy' => true
        ]);
    });
});

describe('Barcode Pool Loading and Stats', function () {
    it('loads available barcode count correctly for specific type', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('barcodeType', 'EAN13')
            ->call('loadBarcodeStats');
            
        expect($component->get('availableBarcodesCount'))->toBe(2); // 2 EAN13 available
    });
    
    it('updates barcode count when barcode type changes', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('barcodeType', 'EAN13')
            ->call('loadBarcodeStats');
        expect($component->get('availableBarcodesCount'))->toBe(2);
        
        $component->set('barcodeType', 'UPC');
        expect($component->get('availableBarcodesCount'))->toBe(1); // 1 UPC available
    });
    
    it('excludes legacy barcodes from count', function () {
        // All barcodes are available, but legacy should be excluded
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('barcodeType', 'EAN13')
            ->call('loadBarcodeStats');
            
        // Should be 2 (not 3) because legacy is excluded
        expect($component->get('availableBarcodesCount'))->toBe(2);
    });
    
    it('returns zero when no barcodes are available', function () {
        // Mark all barcodes as assigned
        BarcodePool::query()->update(['status' => 'assigned']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('barcodeType', 'EAN13')
            ->call('loadBarcodeStats');
            
        expect($component->get('availableBarcodesCount'))->toBe(0);
    });
});

describe('Automatic Barcode Assignment', function () {
    it('assigns barcodes automatically when method is auto', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Red', 'Blue'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(2);
        expect($barcodes[0])->toBe('1234567890123');
        expect($barcodes[1])->toBe('1234567890124');
    });
    
    it('assigns barcodes automatically when barcode type changes', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Green'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix')
            ->set('barcodeType', 'EAN13'); // This should trigger auto-assignment
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(1);
        expect($barcodes[0])->toBe('1234567890123');
    });
    
    it('assigns barcodes when assignment method changes to auto', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Purple'])
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->set('barcodeAssignmentMethod', 'auto');
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(1);
        expect($barcodes[0])->toBe('1234567890123');
    });
    
    it('clears barcodes when assignment method changes to skip', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Yellow'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
        expect($component->get('variantBarcodes'))->toHaveCount(1);
        
        $component->set('barcodeAssignmentMethod', 'skip');
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
    
    it('handles insufficient barcode pool gracefully', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Red', 'Blue', 'Green']) // Need 3, have 2
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(2); // Only 2 available
    });
    
    it('does not assign barcodes when variant matrix is empty', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('variantMatrix', []) // Empty
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('assignBarcodesToVariants');
            
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
});

describe('Manual Barcode Assignment', function () {
    it('assigns specific barcode to variant index', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Orange'])
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignSpecificBarcode', 0);
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes[0])->toBe('1234567890123');
    });
    
    it('avoids assigning already assigned barcodes', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Pink', 'Teal'])
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignSpecificBarcode', 0)
            ->call('assignSpecificBarcode', 1);
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes[0])->toBe('1234567890123');
        expect($barcodes[1])->toBe('1234567890124');
        expect($barcodes[0])->not->toBe($barcodes[1]); // Should be different
    });
    
    it('handles no available barcodes for manual assignment', function () {
        // Mark all barcodes as assigned
        BarcodePool::query()->update(['status' => 'assigned']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Cyan'])
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignSpecificBarcode', 0);
            
        // Should flash error message and not assign barcode
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
    
    it('removes variant barcode assignment', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Maroon'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
        expect($component->get('variantBarcodes'))->toHaveCount(1);
        
        $component->call('removeVariantBarcode', 0);
        expect($component->get('variantBarcodes'))->not->toHaveKey(0);
    });
});

describe('Barcode Assignment Refresh and Updates', function () {
    it('refreshes barcode assignment correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Crimson'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('refreshBarcodeAssignment');
            
        expect($component->get('availableBarcodesCount'))->toBe(2);
        expect($component->get('variantBarcodes'))->toHaveCount(1);
    });
    
    it('does not refresh assignment when method is not auto', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Lime'])
            ->set('barcodeAssignmentMethod', 'manual')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('refreshBarcodeAssignment');
            
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
});

describe('Barcode Integration with Product Creation', function () {
    it('creates barcode records when product is created', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Barcode Integration Test')
            ->set('form.status', 'active')
            ->set('parentSku', '001')
            ->set('attributeValues.material', 'Linen')
            ->set('selectedColors', ['Sage'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        $variant = ProductVariant::where('sku', '001-001')->first();
        expect($variant)->not->toBeNull();
        
        // Check barcode record was created
        $barcode = Barcode::where('product_variant_id', $variant->id)->first();
        expect($barcode)->not->toBeNull();
        expect($barcode->barcode)->toBe('1234567890123');
        expect($barcode->barcode_type)->toBe('EAN13');
        expect($barcode->is_primary)->toBeTrue();
    });
    
    it('updates barcode pool status when assigned', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Barcode Pool Test')
            ->set('form.status', 'active')
            ->set('parentSku', '002')
            ->set('attributeValues.material', 'Silk')
            ->set('selectedColors', ['Cream'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        $barcodePool = BarcodePool::where('barcode', '1234567890123')->first();
        expect($barcodePool->status)->toBe('assigned');
        expect($barcodePool->assigned_to_variant_id)->not->toBeNull();
        expect($barcodePool->assigned_at)->not->toBeNull();
        expect($barcodePool->date_first_used)->not->toBeNull();
    });
    
    it('logs barcode assignment success', function () {
        Log::spy();
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Barcode Log Test')
            ->set('form.status', 'active')
            ->set('parentSku', '003')
            ->set('attributeValues.material', 'Wool')
            ->set('selectedColors', ['Navy'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        Log::shouldHaveReceived('info')->with(
            'Barcode 1234567890123 assigned to variant 003-001'
        );
    });
    
    it('logs warning when barcode not found in pool', function () {
        Log::spy();
        
        $component = Livewire::test(ProductWizard::class);
        
        // Manually set a barcode that doesn't exist in pool
        $component->set('form.name', 'Barcode Warning Test')
            ->set('form.status', 'active')
            ->set('parentSku', '004')
            ->set('attributeValues.material', 'Cotton')
            ->set('selectedColors', ['Black'])
            ->set('variantBarcodes', [0 => 'NONEXISTENT'])
            ->set('assignBarcodes', true)
            ->call('generateVariantMatrix')
            ->call('createProduct');
            
        Log::shouldHaveReceived('warning')->with(
            'Barcode NONEXISTENT not found in available pool for variant 004-001'
        );
    });
    
    it('handles fallback barcode assignment when builder fails', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Barcode Fallback Test')
            ->set('form.status', 'active')
            ->set('parentSku', '005')
            ->set('attributeValues.material', 'Bamboo')
            ->set('selectedColors', ['Brown'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants')
            ->call('createProduct');
            
        // Even if builder fails, barcode should be assigned in fallback
        $variant = ProductVariant::where('sku', '005-001')->first();
        expect($variant->barcodes)->not->toBeEmpty();
    });
});

describe('Barcode Assignment Edge Cases', function () {
    it('handles multiple variants with different barcode types', function () {
        // Add more UPC barcodes for testing
        BarcodePool::factory()->create([
            'barcode' => '123456789013',
            'barcode_type' => 'UPC',
            'status' => 'available'
        ]);
        
        $component = Livewire::test(ProductWizard::class);
        
        // Test with UPC barcodes
        $component->set('selectedColors', ['Red', 'Blue'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'UPC')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
            
        $barcodes = $component->get('variantBarcodes');
        expect($barcodes)->toHaveCount(2);
        expect($barcodes[0])->toBe('123456789012');
        expect($barcodes[1])->toBe('123456789013');
    });
    
    it('maintains barcode assignments across variant matrix regeneration', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Generate variants and assign barcodes
        $component->set('selectedColors', ['Purple'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
        expect($component->get('variantBarcodes'))->toHaveCount(1);
        
        // Add more colors (regenerate matrix)
        $component->set('selectedColors', ['Purple', 'Orange'])
            ->call('generateVariantMatrix');
            
        // Should maintain existing assignments and add new ones
        expect($component->get('variantBarcodes'))->toHaveCount(2);
    });
    
    it('handles barcode assignment with empty variant matrix', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('variantMatrix', [])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('assignBarcodesToVariants');
            
        expect($component->get('variantBarcodes'))->toBeEmpty();
    });
    
    it('prevents barcode duplication across variants', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('selectedColors', ['Gold', 'Silver'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->call('generateVariantMatrix')
            ->call('assignBarcodesToVariants');
            
        $barcodes = $component->get('variantBarcodes');
        $uniqueBarcodes = array_unique($barcodes);
        
        expect(count($barcodes))->toBe(count($uniqueBarcodes)); // No duplicates
    });
    
    it('validates barcode assignment before product creation', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Setup product but don't assign barcodes
        $component->set('form.name', 'Validation Test')
            ->set('form.status', 'active')
            ->set('parentSku', '006')
            ->set('attributeValues.material', 'Plastic')
            ->set('selectedColors', ['White'])
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('currentStep', 6)
            ->call('generateVariantMatrix');
            
        // Don't assign barcodes, try to validate
        $component->call('nextStep');
        
        $component->assertHasErrors(['barcodes']);
        expect($component->get('currentStep'))->toBe(6);
    });
});