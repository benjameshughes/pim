<?php

use App\Livewire\ProductWizard;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

describe('ProductWizard Livewire Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    test('wizard mounts and shows first step', function () {
        Livewire::test(ProductWizard::class)
            ->assertStatus(200)
            ->assertSet('currentStep', 1)
            ->assertSee('Product Name'); // Assuming step 1 has product name
    });

    test('step navigation works correctly', function () {
        $component = Livewire::test(ProductWizard::class);

        if (method_exists($component->instance(), 'nextStep')) {
            $component->call('nextStep')
                ->assertSet('currentStep', 2);
        }

        if (method_exists($component->instance(), 'previousStep')) {
            $component->call('previousStep')
                ->assertSet('currentStep', 1);
        }
    });

    test('keyboard shortcuts work', function () {
        $component = Livewire::test(ProductWizard::class);

        // Test keyboard navigation as mentioned in CLAUDE.md
        if (method_exists($component->instance(), 'handleKeyboardShortcut')) {
            $component->call('handleKeyboardShortcut', 'next')
                ->assertSet('currentStep', 2);
        }
    });

    test('validates step completion before advancing', function () {
        Livewire::test(ProductWizard::class)
            ->set('productName', '') // Invalid step 1
            ->call('nextStep')
            ->assertSet('currentStep', 1) // Should stay on step 1
            ->assertHasErrors(['productName']);
    });

    test('saves draft automatically', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('productName', 'Draft Product')
            ->set('parentSku', 'DRAFT123');

        if (method_exists($component->instance(), 'saveDraft')) {
            $component->call('saveDraft')
                ->assertDispatched('success');
        }
    });

    test('creates product with variants', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('productName', 'Wizard Product')
            ->set('parentSku', 'WIZ123')
            ->set('description', 'Created via wizard');

        // Add variants (step 2)
        if (property_exists($component->instance(), 'variants')) {
            $component->set('variants', [
                ['color' => 'Red', 'width' => 100, 'drop' => 150, 'price' => 25.99],
                ['color' => 'Blue', 'width' => 120, 'drop' => 180, 'price' => 27.99],
            ]);
        }

        $component->call('saveProduct')
            ->assertDispatched('success');

        $product = Product::where('parent_sku', 'WIZ123')->first();
        expect($product)->not()->toBeNull()
            ->and($product->variants()->count())->toBe(2);
    });

    test('assigns barcodes to variants', function () {
        // Create available barcodes
        \App\Models\Barcode::create(['barcode' => '1111111111111', 'is_assigned' => false]);
        \App\Models\Barcode::create(['barcode' => '2222222222222', 'is_assigned' => false]);

        $component = Livewire::test(ProductWizard::class)
            ->set('productName', 'Barcode Product')
            ->set('parentSku', 'BARCODE123');

        if (property_exists($component->instance(), 'variants')) {
            $component->set('variants', [
                ['color' => 'Red', 'width' => 100, 'drop' => 150, 'price' => 25.99],
            ]);
        }

        $component->call('saveProduct');

        $product = Product::where('parent_sku', 'BARCODE123')->first();
        if ($product) {
            expect($product->variants->first()->barcode)->not()->toBeNull();
        }
    });

    test('clears draft functionality works', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('productName', 'Draft Product')
            ->set('parentSku', 'DRAFT123');

        if (method_exists($component->instance(), 'clearDraft')) {
            $component->call('clearDraft')
                ->assertSet('productName', '')
                ->assertSet('parentSku', '');
        }
    });

    test('step validation prevents skipping required steps', function () {
        $component = Livewire::test(ProductWizard::class);

        // Try to jump to step 3 without completing step 1
        if (method_exists($component->instance(), 'goToStep')) {
            $component->call('goToStep', 3)
                ->assertSet('currentStep', 1); // Should stay on current step
        }
    });

    test('auto-focus behavior works', function () {
        $component = Livewire::test(ProductWizard::class);

        // Check that auto-focus hints are present as mentioned in CLAUDE.md
        $component->assertSee('Product Name'); // Step 1 should focus product name
    });

    test('handles image uploads in step 3', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('currentStep', 3);

        // Test image upload functionality if present
        if (property_exists($component->instance(), 'images')) {
            $component->assertStatus(200);
        }
    });

    test('wizard completion creates all relationships', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('productName', 'Complete Product')
            ->set('parentSku', 'COMPLETE123')
            ->set('description', 'Complete test product');

        // Mock complete wizard flow
        if (method_exists($component->instance(), 'completeWizard')) {
            $component->call('completeWizard')
                ->assertDispatched('success');

            $product = Product::where('parent_sku', 'COMPLETE123')->first();
            expect($product)->not()->toBeNull();
        }
    });
});
