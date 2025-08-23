<?php

use App\Livewire\ProductWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('Wizard Keyboard Navigation', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    it('renders keyboard navigation hints', function () {
        Livewire::test(ProductWizard::class)
            ->assertSee('⌘/Ctrl + →')
            ->assertSee('Next Step')
            ->assertSee('⌘/Ctrl + ←')
            ->assertSee('Previous Step')
            ->assertSee('⌘/Ctrl + S')
            ->assertSee('Save Product')
            ->assertSee('Esc')
            ->assertSee('Clear Draft');
    });

    it('includes custom toast component', function () {
        Livewire::test(ProductWizard::class)
            ->assertSee('x-data')
            ->assertSee('addToast')
            ->assertSee('toasts');
    });

    it('has Alpine.js keyboard navigation data', function () {
        Livewire::test(ProductWizard::class)
            ->assertSee('wizardKeyboardNavigation()')
            ->assertSee('handleKeyboardNavigation')
            ->assertSee('showNavigationFeedback');
    });

    it('includes glitter toast configuration in Alpine data', function () {
        Livewire::test(ProductWizard::class)
            ->assertSee('showNavigationFeedback') // toast feedback function
            ->assertSee('CustomEvent') // toast event dispatching
            ->assertSee('duration: 2000'); // toast duration
    });
});