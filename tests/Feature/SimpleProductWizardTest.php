<?php

use App\Livewire\ProductWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Simple Product Wizard', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('renders without errors', function () {
        Livewire::test(ProductWizard::class)
            ->assertStatus(200);
    });

    it('validates step 1 with simple rules', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('name', 'ab') // Too short
            ->set('parent_sku', 'invalid') // Wrong format  
            ->call('nextStep');
            
        $component->assertHasErrors(['name', 'parent_sku']);
    });

    it('accepts valid step 1 data', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->call('nextStep')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 2);
    });

    it('can add colors and generate variants', function () {
        $component = Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->call('nextStep') // Go to step 2
            ->set('new_color', 'Red')
            ->call('addColor');
            
        expect($component->get('colors'))->toContain('Red');
        expect($component->get('generated_variants'))->toHaveCount(1);
        expect($component->get('generated_variants')[0]['sku'])->toBe('123-001');
    });

    it('validates step 2 requires at least one variant attribute', function () {
        Livewire::test(ProductWizard::class)
            ->set('name', 'Test Product')
            ->set('parent_sku', '123')
            ->set('status', 'active')
            ->call('nextStep') // Go to step 2
            ->call('nextStep') // Try to go to step 3 without variants
            ->assertHasErrors(['variants']);
    });
});