<?php

use App\Livewire\Pim\Products\Management\ProductWizard;
use App\Models\AttributeDefinition;
use App\Models\BarcodePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('🐛 ProductWizard Color Bug Debug', function () {

    beforeEach(function () {
        // Create basic attribute definitions for testing
        AttributeDefinition::factory()->color()->forProducts()->required()->create([
            'is_active' => true,
        ]);

        // Create some barcodes in the pool
        BarcodePool::factory()->ean13()->count(10)->create();
    });

    it('debugs custom color addition and removal', function () {
        $component = Livewire::test(ProductWizard::class)
            ->assertSet('selectedColors', [])
            ->assertSet('customColors', [])
            ->assertSet('colorInput', '');

        // Add a custom color
        $component->call('addColor', 'Cosmic Purple')
            ->assertSet('selectedColors', ['Cosmic Purple'])
            ->assertSet('customColors', ['Cosmic Purple'])
            ->assertSet('colorInput', ''); // Should be cleared

        // Add another custom color  
        $component->call('addColor', 'Ocean Blue')
            ->assertSet('selectedColors', ['Cosmic Purple', 'Ocean Blue'])
            ->assertSet('customColors', ['Cosmic Purple', 'Ocean Blue']);

        // Add a common color
        $component->call('addColor', 'Red')
            ->assertSet('selectedColors', ['Cosmic Purple', 'Ocean Blue', 'Red'])
            ->assertSet('customColors', ['Cosmic Purple', 'Ocean Blue']); // Should not change

        // Remove the first color (Cosmic Purple at index 0)
        $component->call('removeColor', 0)
            ->assertSet('selectedColors', ['Ocean Blue', 'Red']) // Should be re-indexed
            ->assertSet('customColors', ['Cosmic Purple', 'Ocean Blue']); // Custom colors array should remain unchanged

        // Remove Ocean Blue (now at index 0)
        $component->call('removeColor', 0)
            ->assertSet('selectedColors', ['Red']);

        // Remove Red (now at index 0)
        $component->call('removeColor', 0)
            ->assertSet('selectedColors', []);
    });

    it('tests input clearing after adding custom colors', function () {
        $component = Livewire::test(ProductWizard::class);
        
        // Set an input value
        $component->set('colorInput', 'Custom Purple');
        expect($component->get('colorInput'))->toBe('Custom Purple');
        
        // Add the color - should clear the input
        $component->call('addColor', 'Custom Purple');
        expect($component->get('colorInput'))->toBe(''); // Should be cleared
        expect($component->get('selectedColors'))->toContain('Custom Purple');
    });

    it('debugs the allColors computed property', function () {
        $component = Livewire::test(ProductWizard::class);
        
        $allColors = $component->get('allColors');
        $commonColorsCount = count($component->get('commonColors'));
        
        expect($allColors->count())->toBe($commonColorsCount);
        
        // Add a custom color
        $component->call('addColor', 'Mystical Teal');
        
        $newAllColors = $component->get('allColors');
        expect($newAllColors->count())->toBe($commonColorsCount + 1);
        expect($newAllColors)->toContain('Mystical Teal');
    });
});