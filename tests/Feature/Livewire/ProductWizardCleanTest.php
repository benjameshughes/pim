<?php

use App\Livewire\Products\ProductWizardClean;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ProductWizardClean Component', function () {
    it('renders successfully for new product creation', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSee('Create Product')
            ->assertSee('Product Info')
            ->assertSet('currentStep', 1)
            ->assertSet('isEditMode', false)
            ->assertSet('completedSteps', []);
    });

    it('initializes correctly for product editing', function () {
        $product = Product::factory()->create([
            'name' => 'Existing Product',
            'parent_sku' => 'EXIST-001',
            'status' => 'active',
        ]);

        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->assertSee('Edit Product')
            ->assertSet('isEditMode', true)
            ->assertSet('product.id', $product->id)
            ->assertSet('wizardData.product_info.name', 'Existing Product')
            ->assertSet('wizardData.product_info.parent_sku', 'EXIST-001')
            ->assertSet('completedSteps', [1]);
    });

    it('handles step navigation correctly', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('currentStep', 1)
            ->call('goToStep', 2)
            ->assertSet('currentStep', 1) // Should not advance without completing step 1
            ->set('completedSteps', [1])
            ->call('goToStep', 2)
            ->assertSet('currentStep', 2) // Now should advance
            ->call('previousStep')
            ->assertSet('currentStep', 1)
            ->call('nextStep')
            ->assertSet('currentStep', 2);
    });

    it('prevents navigation to incomplete steps', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('currentStep', 1)
            ->call('goToStep', 4) // Try to skip to step 4
            ->assertSet('currentStep', 1) // Should remain at step 1
            ->assertMethodWired('canProceedToStep');
    });

    it('handles step completion event', function () {
        $stepData = [
            'name' => 'Test Product',
            'status' => 'active',
        ];

        Livewire::test(ProductWizardClean::class)
            ->assertSet('currentStep', 1)
            ->assertSet('completedSteps', [])
            ->dispatch('step-completed', 1, $stepData)
            ->assertSet('currentStep', 2)
            ->assertSet('completedSteps', [1])
            ->assertSet('wizardData.product_info', $stepData);
    });

    it('auto-saves draft for new products', function () {
        $stepData = ['name' => 'Draft Product', 'status' => 'draft'];

        Livewire::test(ProductWizardClean::class)
            ->dispatch('step-completed', 1, $stepData);

        // Verify draft is saved in session
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        expect(session()->has($sessionKey))->toBeTrue();
    });

    it('does not auto-save draft for existing products', function () {
        $product = Product::factory()->create();
        $stepData = ['name' => 'Updated Product', 'status' => 'active'];

        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->dispatch('step-completed', 1, $stepData);

        // Verify no draft is saved for edit mode
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        expect(session()->has($sessionKey))->toBeFalse();
    });

    it('loads existing draft on mount', function () {
        // Pre-save a draft
        $draftData = [
            'product_info' => ['name' => 'Draft Product', 'status' => 'draft'],
            'variants' => ['generated_variants' => []],
        ];

        $sessionKey = "wizard.product.draft.{$this->user->id}";
        session([
            $sessionKey => [
                'data' => $draftData,
                'saved_at' => now()->toISOString(),
                'user_id' => $this->user->id,
            ],
        ]);

        Livewire::test(ProductWizardClean::class)
            ->assertSet('wizardData.product_info.name', 'Draft Product')
            ->assertSet('completedSteps', ['product_info', 'variants'])
            ->assertSet('currentStep', 3); // Should advance past completed steps
    });

    it('can manually save draft', function () {
        Livewire::test(ProductWizardClean::class)
            ->set('wizardData', [
                'product_info' => ['name' => 'Manual Draft', 'status' => 'draft'],
            ])
            ->call('saveDraft')
            ->assertSet('isSaving', false);

        // Verify draft saved
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        expect(session()->has($sessionKey))->toBeTrue();
    });

    it('can clear draft', function () {
        // Pre-save a draft
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        session([$sessionKey => ['data' => ['test' => 'data']]]);

        Livewire::test(ProductWizardClean::class)
            ->call('clearDraft')
            ->assertDispatched('notify');

        expect(session()->has($sessionKey))->toBeFalse();
    });

    it('handles wizard completion for new product', function () {
        $wizardData = [
            'product_info' => [
                'name' => 'Complete New Product',
                'status' => 'active',
            ],
            'variants' => [
                'generated_variants' => [
                    ['sku' => 'COMP-001', 'color' => 'Red', 'price' => 29.99, 'stock' => 10],
                ],
            ],
        ];

        Livewire::test(ProductWizardClean::class)
            ->set('wizardData', $wizardData)
            ->set('completedSteps', [1, 2, 3, 4])
            ->dispatch('wizard-ready-to-save')
            ->call('saveProduct');

        // Verify product was created
        expect(Product::where('name', 'Complete New Product')->exists())->toBeTrue();
    });

    it('handles wizard completion for existing product', function () {
        $product = Product::factory()->create(['name' => 'Original Name']);

        $wizardData = [
            'product_info' => [
                'name' => 'Updated Name',
                'status' => 'active',
            ],
        ];

        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->set('wizardData', $wizardData)
            ->set('completedSteps', [1, 2, 3, 4])
            ->call('saveProduct');

        // Verify product was updated
        expect($product->fresh()->name)->toBe('Updated Name');
    });

    it('validates before saving product', function () {
        $invalidWizardData = [
            'product_info' => [
                // Missing required name
                'status' => 'active',
            ],
        ];

        Livewire::test(ProductWizardClean::class)
            ->set('wizardData', $invalidWizardData)
            ->call('saveProduct')
            ->assertDispatched('notify');

        // Verify no product was created
        expect(Product::count())->toBe(0);
    });

    it('calculates progress percentage correctly', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('progressPercentage', 0)
            ->set('completedSteps', [1])
            ->assertSet('progressPercentage', 25)
            ->set('completedSteps', [1, 2])
            ->assertSet('progressPercentage', 50)
            ->set('completedSteps', [1, 2, 3, 4])
            ->assertSet('progressPercentage', 100);
    });

    it('provides correct step component names', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('currentStepComponent', 'products.wizard.product-info-step')
            ->set('currentStep', 2)
            ->assertSet('currentStepComponent', 'products.wizard.variant-generation-step')
            ->set('currentStep', 3)
            ->assertSet('currentStepComponent', 'products.wizard.image-upload-step')
            ->set('currentStep', 4)
            ->assertSet('currentStepComponent', 'products.wizard.pricing-stock-step');
    });

    it('provides correct step names', function () {
        $stepNames = [
            1 => '📋 Product Info',
            2 => '💎 Variants',
            3 => '🖼️ Images',
            4 => '💰 Pricing & Stock',
        ];

        Livewire::test(ProductWizardClean::class)
            ->assertSet('stepNames', $stepNames);
    });

    it('shows correct draft status for new products', function () {
        Livewire::test(ProductWizardClean::class)
            ->assertSet('draftStatus.exists', false)
            ->assertSet('draftStatus.message', 'Login to use drafts'); // When not authenticated

        // Test with draft
        $sessionKey = "wizard.product.draft.{$this->user->id}";
        session([$sessionKey => [
            'data' => ['product_info' => ['name' => 'Draft']],
            'saved_at' => now()->toISOString(),
        ]]);

        Livewire::test(ProductWizardClean::class)
            ->assertSet('draftStatus.exists', true);
    });

    it('shows correct draft status for edit mode', function () {
        $product = Product::factory()->create();

        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->assertSet('draftStatus.exists', false)
            ->assertSet('draftStatus.message', 'Editing existing product');
    });

    it('provides correct auto-save status messages', function () {
        // New product, not authenticated
        Livewire::test(ProductWizardClean::class)
            ->assertSet('autoSaveStatus', 'Login required for auto-save');

        // Edit mode
        $product = Product::factory()->create();
        Livewire::test(ProductWizardClean::class, ['product' => $product])
            ->assertSet('autoSaveStatus', 'Changes saved directly to product');

        // Saving state
        Livewire::test(ProductWizardClean::class)
            ->set('isSaving', true)
            ->assertSet('autoSaveStatus', 'Saving draft...');
    });

    it('enforces step progression rules', function () {
        $component = Livewire::test(ProductWizardClean::class);

        // Can proceed to current step or earlier
        expect($component->instance()->canProceedToStep(1))->toBeTrue();

        // Cannot proceed to future steps without completing previous
        expect($component->instance()->canProceedToStep(2))->toBeFalse();
        expect($component->instance()->canProceedToStep(3))->toBeFalse();
        expect($component->instance()->canProceedToStep(4))->toBeFalse();

        // After completing step 1, can proceed to step 2
        $component->set('completedSteps', [1]);
        expect($component->instance()->canProceedToStep(2))->toBeTrue();
        expect($component->instance()->canProceedToStep(3))->toBeFalse();
    });
});
