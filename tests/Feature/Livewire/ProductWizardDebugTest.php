<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ProductWizard Debug - Finding the Culprit 🕵️', function () {

    beforeEach(function () {
        // Create required attribute for testing
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);
        
        // Create barcodes
        BarcodePool::factory()->ean13()->count(10)->create();
    });

    it('debugs the createProduct method step by step', function () {
        // Clear any previous logs
        Log::info('=== STARTING PRODUCT CREATION DEBUG ===');
        
        $component = Livewire::test(ProductWizard::class)
            ->set('form.name', 'Debug Test Product')
            ->set('form.description', 'Testing what breaks')
            ->set('form.status', 'active')
            ->set('parentSku', '888')
            ->set('attributeValues.color', 'Debug Red')
            ->set('selectedColors', ['Red'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix');

        Log::info('Generated variant matrix:', $component->get('variantMatrix'));
        
        // Check initial database state
        expect(Product::count())->toBe(0);
        expect(ProductVariant::count())->toBe(0);
        
        // Now try to create the product
        $component->call('createProduct');
        
        // Check what happened
        Log::info('After createProduct call:');
        Log::info('Products count: ' . Product::count());
        Log::info('Variants count: ' . ProductVariant::count());
        
        // Check if we have any flash messages or errors
        $flashMessage = session('message');
        $flashError = session('error');
        
        Log::info('Flash message: ' . ($flashMessage ?? 'none'));
        Log::info('Flash error: ' . ($flashError ?? 'none'));
        
        // This will show us exactly what's failing
        expect(Product::count())->toBe(1);
    });

    it('tests each step validation individually', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('form.name', 'Step Validation Test')
            ->set('form.status', 'active')
            ->set('parentSku', '777')
            ->set('attributeValues.color', 'Step Blue')
            ->set('selectedColors', ['Blue'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix');

        // Test each step validation
        for ($step = 1; $step <= 7; $step++) {
            $component->set('currentStep', $step);
            $isValid = $component->call('validateCurrentStep');
            Log::info("Step {$step} validation result: " . ($isValid ? 'PASS' : 'FAIL'));
            
            if (!$isValid) {
                Log::info("Step {$step} failed validation");
                // This will help us identify which step is failing
                expect($isValid)->toBeTrue("Step {$step} should pass validation");
            }
        }
    });

    it('tests ProductBuilder directly to isolate the issue', function () {
        try {
            // Test the ProductBuilder directly
            $builder = Product::build()
                ->name('Direct Builder Test')
                ->slug('direct-builder-test')
                ->description('Testing builder directly')
                ->status('active')
                ->set('parent_sku', '666');

            Log::info('ProductBuilder created successfully');
            
            $product = $builder->execute();
            
            Log::info('ProductBuilder executed successfully: ' . $product->id);
            expect($product->id)->not()->toBeNull();
            
        } catch (\Exception $e) {
            Log::error('ProductBuilder failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    });

    it('tests variant creation with ProductVariant builder', function () {
        // First create a product
        $product = Product::factory()->create(['name' => 'Test Product for Variants']);
        
        try {
            // Test ProductVariant builder
            $builder = ProductVariant::buildFor($product)
                ->sku('TEST-001')
                ->color('Test Color')
                ->stockLevel(0)
                ->status('active');

            Log::info('ProductVariant builder created successfully');
            
            $variant = $builder->execute();
            
            Log::info('ProductVariant builder executed successfully: ' . $variant->id);
            expect($variant->id)->not()->toBeNull();
            
        } catch (\Exception $e) {
            Log::error('ProductVariant builder failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    });
});