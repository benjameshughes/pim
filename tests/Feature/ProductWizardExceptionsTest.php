<?php

use App\Exceptions\ProductWizard\NoVariantsException;
use App\Exceptions\ProductWizard\ProductSaveException;
use App\Exceptions\ProductWizard\WizardValidationException;
use App\Livewire\ProductWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Wizard Custom Exceptions', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('throws NoVariantsException with helpful message', function () {
        $exception = new NoVariantsException();
        
        expect($exception->getMessage())->toBe('Cannot save product without variants. Please add at least one color, width, or drop to generate variants.');
        expect($exception->getUserMessage())->toBe('Please add colors, widths, or drops to create product variants before saving.');
        expect($exception->getSuggestedActions())->toHaveCount(3);
        expect($exception->getSuggestedActions())->toContain('Go back to Step 2: Variants');
    });

    it('throws WizardValidationException for missing variant attributes', function () {
        $exception = WizardValidationException::missingVariantAttributes();
        
        expect($exception->getStep())->toBe('variants');
        expect($exception->getUserMessage())->toBe('Please add variant attributes (colors, widths, or drops) to continue.');
        expect($exception->getErrors())->toHaveKey('variants');
    });

    it('throws WizardValidationException for missing variants in pricing', function () {
        $exception = WizardValidationException::missingVariantsForPricing();
        
        expect($exception->getStep())->toBe('pricing');
        expect($exception->getUserMessage())->toBe('No variants available for pricing. Please go back and create variants first.');
        expect($exception->getErrors())->toHaveKey('variants');
    });

    it('provides helpful error messages in the wizard interface', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->call('nextStep') // Go to step 2
            ->call('nextStep') // Try to go to step 3 without variants
            ->assertHasErrors(['variants'])
            ->assertSee('Please add variant attributes (colors, widths, or drops) to continue.');
    });

    it('creates ProductSaveException for transaction failures', function () {
        $originalException = new \Exception('Database connection failed');
        $productData = ['name' => 'Test Product', 'sku' => '123'];
        
        $exception = ProductSaveException::transactionFailed($originalException, $productData);
        
        expect($exception->getProductData())->toBe($productData);
        expect($exception->getUnderlyingException())->toBe($originalException);
        expect($exception->getMessage())->toContain('Database connection failed');
    });

    it('creates ProductSaveException for attribute creation failures', function () {
        $originalException = new \Exception('Constraint violation');
        
        $exception = ProductSaveException::attributeCreationFailed('brand', $originalException);
        
        expect($exception->getMessage())->toContain("Failed to create attribute 'brand'");
        expect($exception->getProductData())->toHaveKey('failed_attribute');
        expect($exception->getProductData()['failed_attribute'])->toBe('brand');
    });

    it('creates ProductSaveException for variant creation failures', function () {
        $variantData = ['sku' => '123-001', 'color' => 'Red'];
        $originalException = new \Exception('Duplicate SKU');
        
        $exception = ProductSaveException::variantCreationFailed($variantData, $originalException);
        
        expect($exception->getMessage())->toContain('Failed to create product variant');
        expect($exception->getProductData())->toHaveKey('failed_variant');
        expect($exception->getProductData()['failed_variant'])->toBe($variantData);
    });

    it('provides user-friendly messages for common database errors', function () {
        $uniqueConstraintException = ProductSaveException::transactionFailed(
            new \Exception('UNIQUE constraint failed: products.parent_sku'),
            ['name' => 'Test', 'parent_sku' => '123']
        );
        
        expect($uniqueConstraintException->getUserMessage())
            ->toBe('This product SKU already exists. Please choose a different Parent SKU.');
    });
});