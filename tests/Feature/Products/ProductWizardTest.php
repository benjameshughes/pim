<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Image;
use App\Actions\Products\Wizard\SaveProductWizardDataAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Creation Wizard', function () {
    beforeEach(function () {
        Storage::fake('public');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can complete product info step', function () {
        $component = Livewire::test('products.wizard.product-info-step')
            ->set('name', 'Wizard Product')
            ->set('description', 'Created via wizard')
            ->set('parent_sku', '123') // Valid 3-digit SKU format
            ->call('completeStep');

        $component->assertDispatched('step-completed', 1);
    });

    it('validates product info step', function () {
        $component = Livewire::test('products.wizard.product-info-step')
            ->set('name', '') // Empty name should fail
            ->set('parent_sku', '') // Empty parent_sku should also fail
            ->call('completeStep');

        $component->assertHasErrors(['name', 'parent_sku']);
    });

    it('can upload images in image step', function () {
        $files = [
            UploadedFile::fake()->image('product1.jpg'),
            UploadedFile::fake()->image('product2.jpg'),
        ];

        $component = Livewire::test('products.wizard.image-upload-step')
            ->set('newProductImages', $files);

        // The component should process the images and store them in productImages
        expect($component->get('productImages'))->toHaveCount(2);
        $component->assertSet('isUploading', false);
    });

    it('can generate variants in variant step', function () {
        $component = Livewire::test('products.wizard.variant-generation-step')
            ->set('colors', ['Red', 'Blue'])
            ->set('widths', [60, 90, 120])
            ->set('parent_sku', '123')
            ->call('generateVariants');

        // Should generate 2 colors × 3 widths = 6 variants
        expect($component->get('total_variants'))->toBe(6);
        expect($component->get('generated_variants'))->toHaveCount(6);
    });

    it('can set pricing for variants', function () {
        // Create some mock variant data as would come from previous step
        $variantData = [
            [
                'sku' => '123-001',
                'color' => 'Red',
                'width' => 60,
                'price' => 25.00
            ],
            [
                'sku' => '123-002', 
                'color' => 'Blue',
                'width' => 60,
                'price' => 25.00
            ]
        ];

        $component = Livewire::test('products.wizard.pricing-stock-step')
            ->set('stepData', ['variants' => ['generated_variants' => $variantData]])
            ->call('completeStep');

        $component->assertDispatched('step-completed', 4);
    });

    it('can complete full wizard workflow', function () {
        // Test each step can be completed individually
        
        // Step 1: Product Info
        $productStep = Livewire::test('products.wizard.product-info-step')
            ->set('name', 'Complete Wizard Product')
            ->set('description', 'Full wizard test')
            ->set('parent_sku', '123')
            ->call('completeStep');
        $productStep->assertDispatched('step-completed', 1);

        // Step 2: Variant Generation
        $variantStep = Livewire::test('products.wizard.variant-generation-step')
            ->set('colors', ['Red'])
            ->set('widths', [60, 90])
            ->set('parent_sku', '123')
            ->call('generateVariants')
            ->call('completeStep');
        $variantStep->assertDispatched('step-completed', 2);
        expect($variantStep->get('total_variants'))->toBe(2);

        // Step 3: Image Upload (just test it can complete)
        $imageStep = Livewire::test('products.wizard.image-upload-step')
            ->call('completeStep');
        $imageStep->assertDispatched('step-completed', 3);

        // Step 4: Pricing (just test it can complete)
        $pricingStep = Livewire::test('products.wizard.pricing-stock-step')
            ->call('completeStep');
        $pricingStep->assertDispatched('step-completed', 4);
    });
});