<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('🎨 ProductWizard Smart Combobox - New Functionality ✨', function () {

    beforeEach(function () {
        // Create basic attribute definitions for testing
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);

        // Create some barcodes in the pool
        BarcodePool::factory()->ean13()->count(50)->create();
    });

    describe('🌈 Smart Color Selection', function () {
        
        it('adds colors using smart autocomplete method', function () {
            $component = Livewire::test(ProductWizard::class)
                ->assertSet('selectedColors', [])
                ->call('addColor', 'Red')
                ->assertSet('selectedColors', ['Red']);
            
            expect($component->get('selectedColors'))->toContain('Red');
        });

        it('prevents duplicate color additions like a smart cookie', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', 'Blue')
                ->call('addColor', 'Blue') // Try to add same color
                ->assertSet('selectedColors', ['Blue']); // Should only have one
                
            expect(count($component->get('selectedColors')))->toBe(1);
        });

        it('adds custom colors to the custom array automatically', function () {
            $component = Livewire::test(ProductWizard::class)
                ->assertSet('customColors', [])
                ->call('addColor', 'Cosmic Purple') // Not in common colors
                ->assertSet('selectedColors', ['Cosmic Purple']);
                
            expect($component->get('customColors'))->toContain('Cosmic Purple');
        });

        it('removes colors gracefully by index', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', 'Red')
                ->call('addColor', 'Blue')
                ->call('addColor', 'Green')
                ->assertCount('selectedColors', 3)
                ->call('removeColor', 1) // Remove Blue (index 1)
                ->assertCount('selectedColors', 2)
                ->assertSet('selectedColors', ['Red', 'Green']); // Should re-index
        });

        it('provides merged color collections via computed property', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', 'Mystical Teal'); // Custom color
                
            $allColors = $component->get('allColors');
            expect($allColors)->toContain('Black'); // From common colors
            expect($allColors)->toContain('Mystical Teal'); // From custom colors
            expect($allColors->count())->toBeGreaterThan(count($component->get('commonColors')));
        });
    });

    describe('📏 Smart Width Selection', function () {
        
        it('adds widths with auto-formatting magic', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addWidth', '120') // Just number
                ->assertSet('selectedWidths', ['120cm']); // Should auto-format
                
            $component->call('addWidth', '160cm') // Already formatted
                ->assertSet('selectedWidths', ['120cm', '160cm']); // Should keep as-is
        });

        it('provides numerically sorted width collections', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addWidth', '300cm') // Larger number
                ->call('addWidth', '60cm'); // Smaller number
                
            $allWidths = $component->get('allWidths');
            
            // Should be sorted numerically, not alphabetically
            $indexOfSixty = $allWidths->search('60cm');
            $indexOfThreeHundred = $allWidths->search('300cm');
            
            expect($indexOfSixty)->toBeLessThan($indexOfThreeHundred);
        });

        it('removes widths like a precision tool', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addWidth', '120cm')
                ->call('addWidth', '160cm')
                ->call('removeWidth', 0) // Remove first width
                ->assertSet('selectedWidths', ['160cm']);
        });
    });

    describe('📐 Smart Drop Selection', function () {
        
        it('handles drop lengths with the same auto-formatting prowess', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addDrop', '200') // Just number
                ->assertSet('selectedDrops', ['200cm']); // Should auto-format
        });

        it('provides numerically sorted drop collections', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addDrop', '400cm') // Larger
                ->call('addDrop', '120cm'); // Smaller
                
            $allDrops = $component->get('allDrops');
            
            $indexOfOneTwenty = $allDrops->search('120cm');
            $indexOfFourHundred = $allDrops->search('400cm');
            
            expect($indexOfOneTwenty)->toBeLessThan($indexOfFourHundred);
        });
    });

    describe('🎯 Variant Matrix Generation with Smart Selections', function () {
        
        it('generates variant matrix using smart selections like a boss', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '123')
                ->set('skuGenerationMethod', 'sequential')
                ->call('addColor', 'Red')
                ->call('addColor', 'Blue')
                ->call('addWidth', '120cm')
                ->call('addDrop', '200cm')
                ->call('generateVariantMatrix');
            
            $variants = $component->get('variantMatrix');
            expect(count($variants))->toBe(2); // 2 colors × 1 width × 1 drop
            
            // Check first variant
            expect($variants[0]['color'])->toBe('Red');
            expect($variants[0]['width'])->toBe('120cm');
            expect($variants[0]['drop'])->toBe('200cm');
            expect($variants[0]['sku'])->toBe('123-001');
            
            // Check second variant
            expect($variants[1]['color'])->toBe('Blue');
            expect($variants[1]['sku'])->toBe('123-002');
        });

        it('regenerates variants automatically when selections change', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('generateVariants', true)
                ->set('parentSku', '456')
                ->call('addColor', 'Green')
                ->call('addWidth', '160cm');
                
            // Should auto-generate matrix
            expect(count($component->get('variantMatrix')))->toBe(1);
            
            // Add another color
            $component->call('addColor', 'Yellow');
            
            // Should auto-regenerate
            expect(count($component->get('variantMatrix')))->toBe(2);
        });
    });

    describe('🔧 Integration with Existing Features', function () {
        
        it('works seamlessly with barcode assignment', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('parentSku', '789')
                ->set('barcodeAssignmentMethod', 'auto')
                ->call('addColor', 'Black')
                ->call('addWidth', '120cm')
                ->call('generateVariantMatrix');
                
            $variants = $component->get('variantMatrix');
            $barcodes = $component->get('variantBarcodes');
            
            expect(count($variants))->toBe(1);
            expect(count($barcodes))->toBe(1); // Should auto-assign barcode
        });

        it('maintains backward compatibility with existing variant creation', function () {
            $component = Livewire::test(ProductWizard::class)
                ->set('generateVariants', false) // Use custom variants instead
                ->call('addCustomVariant');
                
            expect(count($component->get('customVariants')))->toBe(1);
            
            // Smart selections shouldn't affect custom variants
            $component->call('addColor', 'Purple');
            expect(count($component->get('customVariants')))->toBe(1); // Should remain unchanged
        });
    });

    describe('💪 Edge Cases & Robustness', function () {
        
        it('handles empty inputs gracefully', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', '') // Empty string
                ->call('addColor', '   ') // Whitespace only
                ->assertSet('selectedColors', []); // Should remain empty
        });

        it('trims whitespace like a perfectionist', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', '  Royal Blue  ') // With whitespace
                ->assertSet('selectedColors', ['Royal Blue']); // Should be trimmed
        });

        it('prevents index errors when removing non-existent items', function () {
            $component = Livewire::test(ProductWizard::class)
                ->call('addColor', 'Orange')
                ->call('removeColor', 999) // Non-existent index
                ->assertSet('selectedColors', ['Orange']); // Should remain unchanged
        });

        it('handles rapid-fire additions without breaking', function () {
            $component = Livewire::test(ProductWizard::class);
                
            // Add lots of colors rapidly
            for ($i = 1; $i <= 20; $i++) {
                $component->call('addColor', "Color{$i}");
            }
            
            expect(count($component->get('selectedColors')))->toBe(20);
            expect(count($component->get('customColors')))->toBe(20); // All should be custom
        });
    });
});