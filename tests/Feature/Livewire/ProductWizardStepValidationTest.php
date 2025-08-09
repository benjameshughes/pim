<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use App\Models\Product;
use Livewire\Livewire;

describe('ProductWizard Step Validation Flow Analysis', function () {
    beforeEach(function () {
        // Create required attribute for step 4 testing
        AttributeDefinition::factory()->create([
            'key' => 'material',
            'label' => 'Material',
            'is_required' => true,
            'applies_to' => 'products',
            'status' => 'active'
        ]);
        
        // Create optional attribute
        AttributeDefinition::factory()->create([
            'key' => 'color_family',
            'label' => 'Color Family',
            'is_required' => false,
            'applies_to' => 'products',
            'status' => 'active'
        ]);
        
        // Create barcodes for testing
        BarcodePool::factory(5)->create([
            'barcode_type' => 'EAN13',
            'status' => 'available'
        ]);
    });
});

describe('Step 1: Basic Information Validation', function () {
    it('requires product name to proceed', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->set('form.name', '') // Empty required field
            ->set('form.status', 'active')
            ->set('parentSku', '001')
            ->call('nextStep');
            
        $component->assertHasErrors(['form.name']);
        expect($component->get('currentStep'))->toBe(1); // Should not advance
        expect($component->get('completedSteps'))->not->toContain(1);
    });
    
    it('validates parent SKU uniqueness', function () {
        Product::factory()->create(['parent_sku' => '001', 'name' => 'Existing Product']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->set('form.name', 'New Product')
            ->set('form.status', 'active')
            ->set('parentSku', '001') // Conflicts with existing
            ->call('nextStep');
            
        $component->assertHasErrors(['parentSku']);
        expect($component->get('skuConflictProduct'))->toBe('Existing Product');
        expect($component->get('currentStep'))->toBe(1);
    });
    
    it('auto-generates slug when name is provided', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.name', 'Amazing Window Blind!')
            ->set('updatedFormName'); // Trigger name update
            
        expect($component->get('form.slug'))->toBe('amazing-window-blind');
    });
    
    it('validates status field correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->set('form.name', 'Test Product')
            ->set('form.status', 'invalid_status') // Invalid status
            ->set('parentSku', '002')
            ->call('nextStep');
            
        $component->assertHasErrors(['form.status']);
        expect($component->get('currentStep'))->toBe(1);
    });
    
    it('advances to step 2 with valid basic info', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->set('form.name', 'Valid Product')
            ->set('form.description', 'A great product')
            ->set('form.status', 'active')
            ->set('parentSku', '003')
            ->call('nextStep');
            
        $component->assertHasNoErrors();
        expect($component->get('currentStep'))->toBe(2);
        expect($component->get('completedSteps'))->toContain(1);
    });
});

describe('Step 2: Product Images Validation', function () {
    it('allows skipping images step (images are optional)', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Complete step 1 first
        $component->set('currentStep', 1)
            ->set('form.name', 'Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '004')
            ->call('nextStep');
            
        // Step 2 should allow advancement without images
        $component->set('currentStep', 2)
            ->set('newImages', []) // No images
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(3);
        expect($component->get('completedSteps'))->toContain(2);
    });
    
    it('handles image removal correctly', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Simulate having images (can't actually upload in test)
        $component->set('newImages', ['image1', 'image2', 'image3'])
            ->call('removeNewImage', 1); // Remove middle image
            
        $images = $component->get('newImages');
        expect($images)->toHaveCount(2);
        expect($images)->toBe(['image1', 'image3']); // Should be re-indexed
    });
});

describe('Step 3: Features & Details Validation', function () {
    it('allows optional features and details', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Complete previous steps
        $component->set('currentStep', 1)
            ->set('form.name', 'Feature Test')
            ->set('form.status', 'active')
            ->set('parentSku', '005')
            ->call('nextStep')
            ->call('nextStep'); // Skip step 2
            
        // Step 3 should allow advancement with empty features/details
        $component->set('currentStep', 3)
            ->set('form.product_features_1', '')
            ->set('form.product_details_1', '')
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(4);
        expect($component->get('completedSteps'))->toContain(3);
    });
    
    it('accepts valid features and details', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('form.product_features_1', 'Blackout coating')
            ->set('form.product_features_2', 'Easy installation')
            ->set('form.product_details_1', '100% polyester')
            ->set('form.product_details_2', 'Machine washable');
            
        // Values should be stored correctly
        expect($component->get('form.product_features_1'))->toBe('Blackout coating');
        expect($component->get('form.product_details_1'))->toBe('100% polyester');
    });
});

describe('Step 4: Product Attributes Validation', function () {
    it('requires all mandatory attributes to be filled', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 4)
            ->set('attributeValues.material', '') // Required but empty
            ->set('attributeValues.color_family', 'Warm') // Optional, filled
            ->call('nextStep');
            
        $component->assertHasErrors(['attributeValues.material']);
        expect($component->get('currentStep'))->toBe(4);
    });
    
    it('allows optional attributes to be empty', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 4)
            ->set('attributeValues.material', 'Cotton') // Required, filled
            ->set('attributeValues.color_family', '') // Optional, empty
            ->call('nextStep');
            
        $component->assertHasNoErrors();
    });
    
    it('advances when all required attributes are filled', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 4)
            ->set('attributeValues.material', 'Polyester Blend')
            ->set('attributeValues.color_family', 'Cool Tones')
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(5);
        expect($component->get('completedSteps'))->toContain(4);
    });
});

describe('Step 5: Variants Validation', function () {
    it('requires at least one variant option when auto-generating', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 5)
            ->set('generateVariants', true)
            ->set('selectedColors', [])
            ->set('selectedWidths', [])
            ->set('selectedDrops', [])
            ->call('nextStep');
            
        $component->assertHasErrors(['variants']);
        expect($component->get('currentStep'))->toBe(5);
    });
    
    it('requires at least one custom variant when not auto-generating', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 5)
            ->set('generateVariants', false)
            ->set('customVariants', []) // No custom variants
            ->call('nextStep');
            
        $component->assertHasErrors(['variants']);
        expect($component->get('currentStep'))->toBe(5);
    });
    
    it('advances with valid variant selections', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 5)
            ->set('generateVariants', true)
            ->set('selectedColors', ['Red', 'Blue'])
            ->set('selectedWidths', ['120cm'])
            ->call('generateVariantMatrix')
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(6);
        expect($component->get('variantMatrix'))->toHaveCount(2);
    });
    
    it('advances with custom variants', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $customVariant = [
            'sku' => 'CUSTOM-001',
            'color' => 'Green',
            'width' => '140cm',
            'drop' => '160cm',
            'stock_level' => 10,
            'status' => 'active'
        ];
        
        $component->set('currentStep', 5)
            ->set('generateVariants', false)
            ->set('customVariants', [$customVariant])
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(6);
        expect($component->get('customVariants'))->toHaveCount(1);
    });
});

describe('Step 6: Barcode Assignment Validation', function () {
    it('allows skipping barcode assignment', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 6)
            ->set('assignBarcodes', false)
            ->set('variantMatrix', [['sku' => 'TEST-001']]) // Has variants
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(7);
    });
    
    it('requires variants to exist for barcode assignment', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 6)
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('variantMatrix', []) // No variants
            ->call('nextStep');
            
        $component->assertHasErrors(['barcodes']);
        expect($component->get('currentStep'))->toBe(6);
    });
    
    it('requires sufficient available barcodes', function () {
        // Use all available barcodes
        BarcodePool::query()->update(['status' => 'assigned']);
        
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 6)
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('variantMatrix', [
                ['sku' => 'TEST-001'],
                ['sku' => 'TEST-002']
            ])
            ->call('loadBarcodeStats')
            ->call('nextStep');
            
        $component->assertHasErrors(['barcodes']);
        expect($component->get('availableBarcodesCount'))->toBe(0);
    });
    
    it('requires barcodes to be assigned in auto mode', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 6)
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('variantMatrix', [['sku' => 'TEST-001']])
            ->set('variantBarcodes', []) // No barcodes assigned
            ->call('nextStep');
            
        $component->assertHasErrors(['barcodes']);
    });
    
    it('advances with valid barcode assignment', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 6)
            ->set('assignBarcodes', true)
            ->set('barcodeAssignmentMethod', 'auto')
            ->set('barcodeType', 'EAN13')
            ->set('variantMatrix', [['sku' => 'TEST-001']])
            ->call('assignBarcodesToVariants')
            ->call('nextStep');
            
        expect($component->get('currentStep'))->toBe(7);
        expect($component->get('variantBarcodes'))->not->toBeEmpty();
    });
});

describe('Step 7: Review and Navigation', function () {
    it('allows review step without additional validation', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 7)
            ->call('nextStep');
            
        // Should stay on step 7 (final step)
        expect($component->get('currentStep'))->toBe(7);
    });
});

describe('Step Navigation and Flow Control', function () {
    it('prevents jumping to incomplete steps', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Try to jump to step 5 without completing previous steps
        $component->set('currentStep', 1)
            ->call('goToStep', 5);
            
        // Should not advance
        expect($component->get('currentStep'))->toBe(1);
    });
    
    it('allows going back to previous steps', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 3)
            ->call('previousStep');
            
        expect($component->get('currentStep'))->toBe(2);
    });
    
    it('allows jumping to completed steps', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('completedSteps', [1, 2, 3])
            ->set('currentStep', 4)
            ->call('goToStep', 2);
            
        expect($component->get('currentStep'))->toBe(2);
    });
    
    it('prevents going below step 1', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->call('previousStep');
            
        expect($component->get('currentStep'))->toBe(1);
    });
    
    it('marks steps as completed when advancing', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $component->set('currentStep', 1)
            ->set('form.name', 'Test Product')
            ->set('form.status', 'active')
            ->set('parentSku', '006')
            ->call('nextStep');
            
        expect($component->get('completedSteps'))->toContain(1);
        expect($component->get('currentStep'))->toBe(2);
    });
});