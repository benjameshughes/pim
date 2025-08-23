<?php

use App\Livewire\ProductWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Product Wizard Navigation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('can navigate through all steps without toJSON errors', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->call('nextStep') // Step 1 -> 2
            ->assertSet('currentStep', 2)
            ->set('new_color', 'Red')
            ->call('addColor')
            ->call('nextStep') // Step 2 -> 3
            ->assertSet('currentStep', 3)
            ->call('nextStep') // Step 3 -> 4
            ->assertSet('currentStep', 4)
            ->call('previousStep') // Step 4 -> 3
            ->assertSet('currentStep', 3)
            ->call('previousStep') // Step 3 -> 2
            ->assertSet('currentStep', 2)
            ->call('nextStep') // Step 2 -> 3
            ->assertSet('currentStep', 3)
            ->call('nextStep') // Step 3 -> 4
            ->assertSet('currentStep', 4);

        expect($component->get('currentStep'))->toBe(4);
    });

    it('maintains state when navigating back and forth', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->set('brand', 'Test Brand')
            ->call('nextStep')
            ->set('new_color', 'Blue')
            ->call('addColor')
            ->set('new_width', '30')
            ->call('addWidth')
            ->call('previousStep')
            ->assertSet('currentStep', 1)
            ->assertSet('name', 'Test Product')
            ->assertSet('brand', 'Test Brand')
            ->call('nextStep')
            ->assertSet('currentStep', 2)
            ->assertContains('colors', 'Blue')
            ->assertContains('widths', 30);
    });

    it('can handle rapid navigation changes', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('name', 'Rapid Test')
            ->set('parent_sku', '999')
            ->set('status', 'active');

        // Rapid navigation
        for ($i = 0; $i < 5; $i++) {
            $component->call('nextStep')
                ->call('previousStep')
                ->call('nextStep');
        }

        expect($component->get('currentStep'))->toBe(2);
    });

    it('preserves generated variants during navigation', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'Variant Test')
            ->set('parent_sku', '456')
            ->set('status', 'active')
            ->call('nextStep')
            ->set('new_color', 'Green')
            ->call('addColor')
            ->set('new_color', 'Yellow')
            ->call('addColor')
            ->assertCount('generated_variants', 2)
            ->call('nextStep') // To step 3
            ->call('previousStep') // Back to step 2
            ->assertCount('generated_variants', 2)
            ->assertCount('colors', 2);
    });
});