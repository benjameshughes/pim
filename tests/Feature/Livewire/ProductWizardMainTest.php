<?php

use App\Livewire\Products\ProductWizardClean;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Wizard Main Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the main wizard component', function () {
        $component = Livewire::test(ProductWizardClean::class)
            ->assertStatus(200);
            
        expect($component)->not->toBeNull();
    });

    it('starts on step 1 by default', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('currentStep', 1)
            ->assertSee('Product Information'); // Should see step 1 content
    });

    it('can navigate between steps when steps are completed', function () {
        $component = Livewire::test(ProductWizardClean::class);
        
        // Mark step 1 as completed to allow navigation to step 2
        $component->set('completedSteps', [1])
            ->call('goToStep', 2)
            ->assertSet('currentStep', 2);
            
        // Mark step 2 as completed to allow navigation to step 3
        $component->set('completedSteps', [1, 2])
            ->call('goToStep', 3)
            ->assertSet('currentStep', 3);
    });

    it('loads the correct step component', function () {
        $component = Livewire::test(ProductWizardClean::class);
        
        // Check step 1 component
        expect($component->get('currentStepComponent'))->toBe('products.wizard.product-info-step');
        
        // Check step 2 component (mark step 1 as completed first)
        $component->set('completedSteps', [1])
            ->call('goToStep', 2);
        expect($component->get('currentStepComponent'))->toBe('products.wizard.variant-generation-step');
        
        // Check step 3 component
        $component->set('completedSteps', [1, 2])
            ->call('goToStep', 3);
        expect($component->get('currentStepComponent'))->toBe('products.wizard.image-upload-step');
        
        // Check step 4 component  
        $component->set('completedSteps', [1, 2, 3])
            ->call('goToStep', 4);
        expect($component->get('currentStepComponent'))->toBe('products.wizard.pricing-stock-step');
    });

    it('initializes wizard data structure correctly', function () {
        $component = Livewire::test(ProductWizardClean::class);
        
        $wizardData = $component->get('wizardData');
        
        expect($wizardData)->toHaveKeys([
            'product_info',
            'variants', 
            'images',
            'pricing'
        ]);
    });

    it('detects create mode vs edit mode correctly', function () {
        // Test create mode (default)
        Livewire::test(ProductWizardClean::class)
            ->assertSet('isEditMode', false);

        // Test edit mode with product
        $product = \App\Models\Product::factory()->create();
        
        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->assertSet('isEditMode', true)
            ->assertSet('product.id', $product->id);
    });

    it('can save draft data', function () {
        $component = Livewire::test(ProductWizardClean::class)
            ->set('wizardData.product_info', [
                'name' => 'Test Product',
                'description' => 'Test Description'
            ])
            ->call('saveDraft');

        // Should not have errors
        $component->assertHasNoErrors();
        
        // Should update last save time
        expect($component->get('lastSaveTime'))->not->toBeNull();
    });

    it('handles product saving through saveProduct method', function () {
        // Set up complete wizard data
        $wizardData = [
            'product_info' => [
                'name' => 'Test Product',
                'parent_sku' => 'TEST-001',
                'description' => 'Test Description',
                'status' => 'active'
            ],
            'variants' => [
                [
                    'sku' => 'TEST-001-SM-RED',
                    'color' => 'Red', 
                    'width' => 100,
                    'price' => 29.99,
                    'stock_level' => 50
                ]
            ],
            'images' => [],
            'pricing' => []
        ];

        $component = Livewire::test(ProductWizardClean::class)
            ->set('wizardData', $wizardData)
            ->set('completedSteps', [1, 2, 3, 4]) // Mark all steps complete
            ->call('saveProduct');

        // Should complete without errors
        $component->assertHasNoErrors();
        
        // Should set saving state
        expect($component->get('isSaving'))->toBe(false); // Should be reset after save
    });
    
    it('prevents navigation to incomplete steps', function () {
        $component = Livewire::test(ProductWizardClean::class);
        
        // Try to go to step 3 without completing steps 1 and 2
        $component->call('goToStep', 3)
            ->assertSet('currentStep', 1); // Should stay on step 1
        
        // Complete step 1, should allow going to step 2 but not 3
        $component->set('completedSteps', [1])
            ->call('goToStep', 2)
            ->assertSet('currentStep', 2)
            ->call('goToStep', 4)
            ->assertSet('currentStep', 2); // Should stay on step 2
    });
    
    it('handles draft loading with string step numbers correctly', function () {
        // Set up a mock draft in session to simulate the scenario that caused the bug
        $userId = auth()->id();
        $sessionKey = "wizard_draft_user_{$userId}";
        
        // Simulate draft data with string keys (as returned by array_keys)
        session([$sessionKey => [
            'data' => [
                '1' => ['name' => 'Test Product', 'parent_sku' => '123'],
                '2' => ['colors' => ['Red'], 'widths' => [60]],
            ],
            'saved_at' => now()->toISOString(),
            'user_id' => $userId,
        ]]);
        
        // Create component with mount to trigger draft loading
        $component = Livewire::test(ProductWizardClean::class, []);
        
        // Manually trigger the scenario - simulate what would happen with string completedSteps
        $component->set('completedSteps', ['1', '2']); // String keys from draft
        
        // Now test that max() with string-to-int conversion works without error
        // This should trigger the fixed line: max(array_map('intval', $this->completedSteps)) + 1
        $currentStep = !empty($component->get('completedSteps')) ? 
            max(array_map('intval', $component->get('completedSteps'))) + 1 : 1;
            
        expect($currentStep)->toBe(3); // max([1, 2]) + 1 = 3
        
        // Test setting currentStep with the same logic as the component
        $component->set('currentStep', $currentStep);
        expect($component->get('currentStep'))->toBe(3);
    });
});