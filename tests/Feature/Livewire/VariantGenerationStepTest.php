<?php

use App\Livewire\Products\Wizard\VariantGenerationStep;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Variant Generation Step Component', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can render the variant generation step component', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->assertStatus(200);
            
        expect($component)->not->toBeNull();
    });

    it('initializes with default values', function () {
        Livewire::test(VariantGenerationStep::class)
            ->assertSet('colors', [])
            ->assertSet('widths', [])
            ->assertSet('drops', [])
            ->assertSet('parent_sku', '')
            ->assertSet('enable_sku_grouping', true)
            ->assertSet('generated_variants', [])
            ->assertSet('total_variants', 0)
            ->assertSet('currentStep', 2)
            ->assertSet('isActive', false)
            ->assertSet('isEditMode', false);
    });

    it('loads existing step data on mount', function () {
        $stepData = [
            'colors' => ['Red', 'Blue'],
            'widths' => [60, 90],
            'drops' => [140, 160],
            'parent_sku' => 'TEST-001',
            'enable_sku_grouping' => false,
            'generated_variants' => [
                ['sku' => 'VAR-RED-60W-140D-01', 'color' => 'Red', 'width' => 60, 'drop' => 140, 'price' => 0, 'stock' => 0]
            ],
        ];

        Livewire::test(VariantGenerationStep::class, [
            'stepData' => $stepData,
            'isActive' => true,
            'currentStep' => 2,
            'isEditMode' => false
        ])
            ->assertSet('colors', ['Red', 'Blue'])
            ->assertSet('widths', [60, 90])
            ->assertSet('drops', [140, 160])
            ->assertSet('parent_sku', 'TEST-001')
            ->assertSet('enable_sku_grouping', false)
            ->assertSet('total_variants', 1)
            ->assertSet('isActive', true);
    });

    it('can add colors', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('new_color', 'Red')
            ->call('addColor')
            ->assertSet('colors', ['Red'])
            ->assertSet('new_color', ''); // Should reset input

        // Should generate variants
        expect($component->get('generated_variants'))->toHaveCount(1);
    });

    it('prevents duplicate colors', function () {
        Livewire::test(VariantGenerationStep::class)
            ->set('new_color', 'Red')
            ->call('addColor')
            ->set('new_color', 'Red') // Same color
            ->call('addColor')
            ->assertSet('colors', ['Red']); // Should still only have one
    });

    it('can remove colors', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('colors', ['Red', 'Blue'])
            ->call('removeColor', 'Red')
            ->assertSet('colors', ['Blue']);

        // Should regenerate variants without the removed color
        $variants = $component->get('generated_variants');
        foreach ($variants as $variant) {
            expect($variant['color'])->not->toBe('Red');
        }
    });

    it('can add widths', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('new_width', '60')
            ->call('addWidth')
            ->assertSet('widths', [60])
            ->assertSet('new_width', ''); // Should reset input

        // Should generate variants
        expect($component->get('generated_variants'))->toHaveCount(1);
    });

    it('prevents duplicate widths', function () {
        Livewire::test(VariantGenerationStep::class)
            ->set('new_width', '60')
            ->call('addWidth')
            ->set('new_width', '60') // Same width
            ->call('addWidth')
            ->assertSet('widths', [60]); // Should still only have one
    });

    it('can remove widths', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('widths', [60, 90])
            ->call('removeWidth', 60)
            ->assertSet('widths', [90]);

        // Should regenerate variants without the removed width
        $variants = $component->get('generated_variants');
        foreach ($variants as $variant) {
            expect($variant['width'])->not->toBe(60);
        }
    });

    it('can add drops', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('new_drop', '140')
            ->call('addDrop')
            ->assertSet('drops', [140])
            ->assertSet('new_drop', ''); // Should reset input

        // Should generate variants
        expect($component->get('generated_variants'))->toHaveCount(1);
    });

    it('prevents duplicate drops', function () {
        Livewire::test(VariantGenerationStep::class)
            ->set('new_drop', '140')
            ->call('addDrop')
            ->set('new_drop', '140') // Same drop
            ->call('addDrop')
            ->assertSet('drops', [140]); // Should still only have one
    });

    it('can remove drops', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('drops', [140, 160])
            ->call('removeDrop', 140)
            ->assertSet('drops', [160]);

        // Should regenerate variants without the removed drop
        $variants = $component->get('generated_variants');
        foreach ($variants as $variant) {
            expect($variant['drop'])->not->toBe(140);
        }
    });

    it('can quick add colors', function () {
        Livewire::test(VariantGenerationStep::class)
            ->call('quickAddColor', 'Black')
            ->assertSet('colors', ['Black']);
    });

    it('can quick add widths', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->call('quickAddWidth', 120);
        
        $widths = $component->get('widths');
        expect($widths)->toContain(120);
    });

    it('can quick add drops', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->call('quickAddDrop', 180);
        
        $drops = $component->get('drops');
        expect($drops)->toContain(180);
    });

    it('generates all variant combinations', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('colors', ['Red', 'Blue'])
            ->set('widths', [60, 90])
            ->set('drops', [140, 160])
            ->call('generateVariants'); // Manually trigger generation

        $variants = $component->get('generated_variants');
        
        // Should have 2 colors × 2 widths × 2 drops = 8 variants
        expect($variants)->toHaveCount(8);
        
        // Check that all combinations exist
        $combinations = [];
        foreach ($variants as $variant) {
            $combinations[] = $variant['color'] . '-' . $variant['width'] . '-' . $variant['drop'];
        }
        
        expect($combinations)->toContain('Red-60-140');
        expect($combinations)->toContain('Blue-90-160');
    });

    it('generates SKUs with grouping enabled', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('parent_sku', 'BLIND-001')
            ->set('enable_sku_grouping', true)
            ->set('colors', ['Red'])
            ->set('widths', [60])
            ->set('drops', [140])
            ->call('generateVariants');

        $variants = $component->get('generated_variants');
        
        expect($variants)->toHaveCount(1);
        expect($variants[0]['sku'])->toBe('BLIND-001-001');
    });

    it('generates SKUs without grouping', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('enable_sku_grouping', false)
            ->set('colors', ['Red'])
            ->set('widths', [60])
            ->set('drops', [140])
            ->call('generateVariants');

        $variants = $component->get('generated_variants');
        
        expect($variants)->toHaveCount(1);
        expect($variants[0]['sku'])->toBe('VAR-RED-60W-140D-01');
    });

    it('loads roller blinds preset', function () {
        Livewire::test(VariantGenerationStep::class)
            ->call('loadPreset', 'roller_blinds')
            ->assertSet('colors', ['White', 'Cream', 'Grey', 'Black'])
            ->assertSet('widths', [60, 90, 120, 150, 180, 210, 240])
            ->assertSet('drops', [140, 160, 210]);
    });

    it('loads venetian blinds preset', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->call('loadPreset', 'venetian_blinds');

        // Should have 5 colors
        expect($component->get('colors'))->toHaveCount(5);
        expect($component->get('colors'))->toContain('Natural');
        
        // Should have widths from 45cm to 240cm in 15cm increments  
        $widths = $component->get('widths');
        expect($widths)->toContain(45);
        expect($widths)->toContain(240);
        expect($widths[1] - $widths[0])->toBe(15); // 15cm increments
    });

    it('emits step-completed event with valid data', function () {
        Livewire::test(VariantGenerationStep::class)
            ->set('colors', ['Red'])
            ->set('widths', [60])
            ->set('drops', [140])
            ->set('parent_sku', 'TEST-001')
            ->call('generateVariants')
            ->call('completeStep')
            ->assertDispatched('step-completed', 2);
    });

    it('emits variant-data-updated event on changes', function () {
        Livewire::test(VariantGenerationStep::class)
            ->set('new_color', 'Red')
            ->call('addColor') // This should trigger data update
            ->assertDispatched('variant-data-updated');
    });

    it('handles empty state gracefully', function () {
        $component = Livewire::test(VariantGenerationStep::class);
        
        // With no colors/widths/drops, should have no variants
        expect($component->get('generated_variants'))->toHaveCount(0);
        expect($component->get('total_variants'))->toBe(0);
    });

    it('handles single dimension variants', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('colors', ['Red']) // Only colors, no widths or drops
            ->call('generateVariants');

        $variants = $component->get('generated_variants');
        
        expect($variants)->toHaveCount(1);
        expect($variants[0]['color'])->toBe('Red');
        expect($variants[0]['width'])->toBe(0);
        expect($variants[0]['drop'])->toBe(0);
    });

    it('keeps widths sorted when adding', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->call('quickAddWidth', 180)
            ->call('quickAddWidth', 60)
            ->call('quickAddWidth', 120);

        $widths = $component->get('widths');
        expect($widths)->toBe([60, 120, 180]); // Should be sorted
    });

    it('keeps drops sorted when adding', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->call('quickAddDrop', 200)
            ->call('quickAddDrop', 140)
            ->call('quickAddDrop', 180);

        $drops = $component->get('drops');
        expect($drops)->toBe([140, 180, 200]); // Should be sorted
    });

    it('validates step completion with variants', function () {
        $component = Livewire::test(VariantGenerationStep::class)
            ->set('colors', ['Red'])
            ->set('widths', [60])
            ->call('generateVariants')
            ->call('completeStep');

        // Should complete successfully
        $component->assertDispatched('step-completed');
    });

    it('displays component sections correctly', function () {
        Livewire::test(VariantGenerationStep::class)
            ->assertSee('Product Variants')
            ->assertSee('Parent SKU for Variants')
            ->assertSee('Colors')
            ->assertSee('Widths (cm)')
            ->assertSee('Drops (cm)')
            ->assertSee('Quick Add')
            ->assertSee('Variants Generated');
    });

    it('shows preset buttons', function () {
        Livewire::test(VariantGenerationStep::class)
            ->assertSee('Roller Blinds')
            ->assertSee('Venetian Blinds');
    });

    it('shows popular color options', function () {
        Livewire::test(VariantGenerationStep::class)
            ->assertSee('Black')
            ->assertSee('White')
            ->assertSee('Grey')
            ->assertSee('Blue')
            ->assertSee('Red');
    });

    it('handles edit mode with existing product', function () {
        $product = Product::factory()->create();
        
        Livewire::test(VariantGenerationStep::class, [
            'product' => $product,
            'isEditMode' => true
        ])
            ->assertSet('isEditMode', true)
            ->assertSet('product', $product);
    });
});