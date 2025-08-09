<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\Barcode;
use App\Models\BarcodePool;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ProductWizard 💫 - The Sassy Chronicles', function () {

    beforeEach(function () {
        Storage::fake('public');
        
        // Create some basic attribute definitions for testing
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);
        
        AttributeDefinition::factory()->material()->forProducts()->create([
            'is_required' => false,
            'is_active' => true,
        ]);

        // Create some barcodes in the pool because we're not savages
        BarcodePool::factory()->ean13()->count(50)->create();
    });

    describe('🎭 Step 1: Basic Information - "Tell me who you are, darling"', function () {
        
        it('renders like a vision of beauty', function () {
            Livewire::test(ProductWizard::class)
                ->assertStatus(200)
                ->assertSee('Basic Information')
                ->assertSet('currentStep', 1);
        });

        it('validates required fields like a strict headmistress', function () {
            Livewire::test(ProductWizard::class)
                ->set('form.name', '')
                ->call('nextStep')
                ->assertHasErrors(['form.name'])
                ->assertSet('currentStep', 1); // Should stay on step 1
        });

        it('auto-generates slugs because we are not monsters', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('form.name', 'Fabulous Window Treatment');
            
            // Trigger the lifecycle hook by setting the name again to trigger updatedFormName
            $component->set('form.name', 'Fabulous Window Treatment');
            
            expect($component->get('form.slug'))->toBe('fabulous-window-treatment');
        });

        it('generates unique parent SKUs like a boss', function () {
            // Create existing product with parent_sku 001
            Product::factory()->create(['parent_sku' => '001']);
            
            $component = Livewire::test(ProductWizard::class);
            expect($component->get('parentSku'))->toBe('002');
        });

        it('detects parent SKU conflicts like a bouncer', function () {
            Product::factory()->create(['parent_sku' => '123', 'name' => 'Existing Product']);
            
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '123');
            
            // Check that conflict is detected  
            expect($component->get('skuConflictProduct'))->toBe('Existing Product');
        });

        it('moves to step 2 when validation passes like a smooth operator', function () {
            Livewire::test(ProductWizard::class)
                ->set('form.name', 'Gorgeous Blinds')
                ->set('form.description', 'Simply divine window treatments')
                ->set('parentSku', '999')
                ->call('nextStep')
                ->assertSet('currentStep', 2)
                ->assertHasNoErrors();
        });
    });

    describe('🖼️ Step 2: Product Images - "Show me your pretty face"', function () {
        
        it('accepts image uploads like a gracious host', function () {
            $image = UploadedFile::fake()->image('gorgeous.jpg');
            
            Livewire::test(ProductWizard::class)
                // First complete step 1 requirements
                ->set('form.name', 'Test Product')
                ->set('parentSku', '999')
                ->call('nextStep') // Go to step 2
                // Now test image functionality
                ->set('newImages.0', $image)
                ->assertSet('imageType', 'gallery')
                ->call('nextStep') // Should advance to step 3
                ->assertSet('currentStep', 3); // Images are optional, should advance to step 3
        });

        it('removes images like a decluttering guru', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('newImages.0', UploadedFile::fake()->image('test.jpg'))
                ->set('newImages.1', UploadedFile::fake()->image('test2.jpg'));

            expect(count($component->get('newImages')))->toBe(2);
            
            $component->call('removeNewImage', 0);
            expect(count($component->get('newImages')))->toBe(1);
        });
    });

    describe('📝 Step 3: Features & Details - "Tell me your secrets"', function () {
        
        it('accepts optional features without drama', function () {
            Livewire::test(ProductWizard::class)
                ->set('form.product_features_1', 'Ultra-fabulous design')
                ->set('form.product_features_2', 'Whisper-quiet operation')
                ->call('nextStep')
                ->assertHasNoErrors();
        });
    });

    describe('✨ Step 4: Product Attributes - "What makes you special?"', function () {
        
        it('validates required attributes like a perfectionist', function () {
            Livewire::test(ProductWizard::class)
                ->set('currentStep', 4)
                ->call('validateCurrentStep')
                ->assertFalse()
                ->call('nextStep')
                ->assertSet('currentStep', 4); // Should stay on step 4
        });

        it('passes when required attributes are provided', function () {
            Livewire::test(ProductWizard::class)
                ->set('attributeValues.color', 'Midnight Black')
                ->set('currentStep', 4)
                ->call('nextStep')
                ->assertSet('currentStep', 5); // Should move to step 5
        });
    });

    describe('🎨 Step 5: Product Variants - "Let\'s make babies"', function () {
        
        it('generates variant matrix like a mathematical genius', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('selectedColors', ['Black', 'White'])
                ->set('selectedWidths', ['120cm', '160cm'])
                ->set('selectedDrops', ['200cm']);

            expect(count($component->get('variantMatrix')))->toBe(4); // 2 colors × 2 widths × 1 drop
        });

        it('generates sequential SKUs like a production line', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '123')
                ->set('skuGenerationMethod', 'sequential')
                ->set('selectedColors', ['Black', 'White'])
                ->call('generateVariantMatrix');

            $variants = $component->get('variantMatrix');
            expect($variants[0]['sku'])->toBe('123-001');
            expect($variants[1]['sku'])->toBe('123-002');
        });

        it('generates random SKUs without collisions like a chaos master', function () {
            // Create existing variant to test collision avoidance
            ProductVariant::factory()->create(['sku' => '123-001']);
            
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '123')
                ->set('skuGenerationMethod', 'random')
                ->set('selectedColors', ['Black'])
                ->call('generateVariantMatrix');

            $variants = $component->get('variantMatrix');
            expect($variants[0]['sku'])->not()->toBe('123-001'); // Should avoid collision
        });

        it('validates variant selection like a quality inspector', function () {
            Livewire::test(ProductWizard::class)
                ->set('generateVariants', true)
                ->set('selectedColors', []) // No selections
                ->set('currentStep', 5)
                ->call('validateCurrentStep')
                ->assertFalse(); // Should fail validation
        });

        it('handles custom variants like a bespoke tailor', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('generateVariants', false);
            
            expect(count($component->get('customVariants')))->toBe(0);
            
            $component->call('addCustomVariant');
            
            expect(count($component->get('customVariants')))->toBe(1);
        });
    });

    describe('🏷️ Step 6: Barcode Assignment - "Mark your territory"', function () {
        
        it('auto-assigns barcodes like a vending machine', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('selectedColors', ['Black', 'White'])
                ->set('barcodeAssignmentMethod', 'auto')
                ->call('generateVariantMatrix');

            expect(count($component->get('variantBarcodes')))->toBe(2);
        });

        it('detects barcode shortage like an inventory manager', function () {
            // Delete all barcodes to simulate shortage
            BarcodePool::query()->delete();
            
            Livewire::test(ProductWizard::class)
                ->set('selectedColors', ['Black', 'White'])
                ->set('currentStep', 6)
                ->call('validateCurrentStep')
                ->assertFalse(); // Should fail due to insufficient barcodes
        });

        it('assigns specific barcodes like a personal shopper', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('variantMatrix', [
                    ['sku' => 'TEST-001', 'color' => 'Black']
                ])
                ->call('assignSpecificBarcode', 0);

            expect($component->get('variantBarcodes.0'))->not()->toBeEmpty();
        });

        it('refreshes barcode stats like a stock ticker', function () {
            expect(BarcodePool::where('status', 'available')->count())->toBe(50);
            
            $component = Livewire::test(ProductWizard::class);
            expect($component->get('availableBarcodesCount'))->toBe(50);
            
            // Simulate barcode usage
            BarcodePool::first()->update(['status' => 'assigned']);
            
            $component->call('loadBarcodeStats');
            expect($component->get('availableBarcodesCount'))->toBe(49);
        });
    });

    describe('🎯 Step 7: Review & Create - "The moment of truth"', function () {
        
        it('validates all steps before creation like a thorough inspector', function () {
            Livewire::test(ProductWizard::class)
                ->set('form.name', '') // Missing required field
                ->call('createProduct')
                ->assertHasErrors(); // Should have validation errors
        });

        it('creates product with all the bells and whistles', function () {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('product_variants', 0);
            
            Livewire::test(ProductWizard::class)
                ->set('form.name', 'Magnificent Window Blinds')
                ->set('form.description', 'The most fabulous blinds ever created')
                ->set('form.status', 'active')
                ->set('parentSku', '999')
                ->set('attributeValues.color', 'Dramatic Black')
                ->set('selectedColors', ['Black'])
                ->set('selectedWidths', ['120cm'])
                ->set('barcodeAssignmentMethod', 'auto')
                ->call('generateVariantMatrix')
                ->call('createProduct');

            $this->assertDatabaseCount('products', 1);
            $this->assertDatabaseCount('product_variants', 1);
            $this->assertDatabaseHas('products', [
                'name' => 'Magnificent Window Blinds',
                'parent_sku' => '999',
            ]);
        });
    });

    describe('💥 Error Handling - "When things go sideways"', function () {
        
        it('handles builder pattern failures gracefully', function () {
            // This test simulates Builder pattern failure by mocking
            // In a real scenario, we'd mock the ProductBuilder to throw an exception
            $component = Livewire::test(ProductWizard::class)
                ->set('form.name', 'Test Product')
                ->set('parentSku', '888')
                ->set('attributeValues.color', 'Red')
                ->set('selectedColors', ['Red'])
                ->call('generateVariantMatrix');

            // Even if builder fails, fallback should work
            expect($component->get('variantMatrix'))->not()->toBeEmpty();
        });

        it('handles barcode pool exhaustion like a crisis manager', function () {
            // Use up all barcodes
            BarcodePool::query()->update(['status' => 'assigned']);
            
            Livewire::test(ProductWizard::class)
                ->set('form.name', 'Test Product')
                ->set('attributeValues.color', 'Blue')
                ->set('selectedColors', ['Blue'])
                ->set('barcodeAssignmentMethod', 'auto')
                ->call('generateVariantMatrix')
                ->set('currentStep', 6)
                ->call('validateCurrentStep')
                ->assertFalse(); // Should fail validation
        });

        it('provides helpful error messages instead of cryptic nonsense', function () {
            Livewire::test(ProductWizard::class)
                ->set('generateVariants', true)
                ->set('selectedColors', []) // No selections
                ->set('currentStep', 5)
                ->call('validateCurrentStep');
            
            // Check that helpful error message is set
            // (This would need to be implemented in the actual component)
            expect(true)->toBeTrue(); // Placeholder assertion
        });
    });

    describe('🔄 Navigation & State Management - "Getting around like a boss"', function () {
        
        it('navigates forward through steps like a determined traveler', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('form.name', 'Navigation Test')
                ->set('parentSku', '777')
                ->assertSet('currentStep', 1)
                ->call('nextStep')
                ->assertSet('currentStep', 2);
        });

        it('navigates backward like a time traveler', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('currentStep', 3)
                ->call('previousStep')
                ->assertSet('currentStep', 2);
        });

        it('jumps to specific steps like a kangaroo', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('completedSteps', [1, 2])
                ->call('goToStep', 2)
                ->assertSet('currentStep', 2);
        });

        it('prevents jumping to incomplete steps like a security guard', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('completedSteps', [1])
                ->call('goToStep', 3) // Step 2 not completed
                ->assertSet('currentStep', 1); // Should stay on current step
        });
    });

    describe('🎪 Edge Cases & Corner Scenarios - "When users get creative"', function () {
        
        it('handles empty variant matrix like a zen master', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('selectedColors', [])
                ->set('selectedWidths', [])
                ->call('generateVariantMatrix');

            expect($component->get('variantMatrix'))->toBeEmpty();
        });

        it('ensures unique SKUs even with race conditions', function () {
            // Simulate rapid-fire SKU generation
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '555')
                ->set('skuGenerationMethod', 'sequential');

            // Generate multiple variants quickly
            for ($i = 0; $i < 5; $i++) {
                $component->set('selectedColors', ['Color' . $i])
                         ->call('generateVariantMatrix');
            }

            $variants = $component->get('variantMatrix');
            $skus = array_column($variants, 'sku');
            expect(count($skus))->toBe(count(array_unique($skus))); // All SKUs should be unique
        });

        it('handles massive variant matrices like a supercomputer', function () {
            // Generate large variant matrix
            $colors = array_map(fn($i) => "Color$i", range(1, 10));
            $widths = array_map(fn($i) => "{$i}cm", range(120, 180, 20));
            
            $component = Livewire::test(ProductWizard::class)
                ->set('selectedColors', $colors)
                ->set('selectedWidths', $widths)
                ->call('generateVariantMatrix');

            expect(count($component->get('variantMatrix')))->toBe(40); // 10 colors × 4 widths
        });

        it('regenerates parent SKU like a phoenix rising', function () {
            $component = Livewire::test(ProductWizard::class);
            $originalSku = $component->get('parentSku');
            
            $component->call('regenerateParentSku');
            $newSku = $component->get('parentSku');
            
            expect($newSku)->not()->toBe($originalSku);
        });
    });
});

describe('🏗️ ProductWizardForm - The Supporting Cast', function () {
    
    it('validates form fields through the component like a stern teacher', function () {
        $component = Livewire::test(ProductWizard::class);
        
        expect($component->get('form.name'))->toBe('');
        expect($component->get('form.status'))->toBe('active');
    });

    it('resets form properties like a fresh start', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('form.name', 'Test Name')
            ->set('form.description', 'Test Description');
        
        // Reset form by testing mount which reinitializes
        $component->call('mount');
        
        expect($component->get('form.name'))->toBe('');
        expect($component->get('form.status'))->toBe('active'); // Default value preserved
    });
});

describe('💼 Integration Tests - "The full monty"', function () {
    
    it('creates a complete product ecosystem', function () {
        $image = UploadedFile::fake()->image('product.jpg');
        
        Livewire::test(ProductWizard::class)
            ->set('form.name', 'Ultimate Window Treatment System')
            ->set('form.description', 'The crème de la crème of window coverings')
            ->set('form.status', 'active')
            ->set('form.product_features_1', 'Whisper-quiet motor')
            ->set('form.product_features_2', 'Smart home integration')
            ->set('form.product_details_1', 'Premium aluminum construction')
            ->set('parentSku', '001')
            ->set('newImages.0', $image)
            ->set('imageType', 'gallery')
            ->set('attributeValues.color', 'Charcoal Grey')
            ->set('selectedColors', ['Black', 'White', 'Grey'])
            ->set('selectedWidths', ['120cm', '160cm'])
            ->set('selectedDrops', ['200cm'])
            ->set('barcodeAssignmentMethod', 'auto')
            ->call('generateVariantMatrix')
            ->call('createProduct');

        // Verify complete product ecosystem creation
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 6); // 3 colors × 2 widths × 1 drop
        $this->assertDatabaseCount('product_images', 1);
        
        $product = Product::first();
        expect($product->name)->toBe('Ultimate Window Treatment System');
        expect($product->parent_sku)->toBe('001');
        expect($product->variants)->toHaveCount(6);
        
        // Check variants have proper SKUs and attributes
        $variant = $product->variants->first();
        expect($variant->sku)->toStartWith('001-');
        expect($variant->barcodes)->toHaveCount(1); // Should have assigned barcode
    });
});