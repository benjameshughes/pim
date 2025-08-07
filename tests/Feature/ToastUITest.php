<?php

use App\Livewire\Components\ToastContainer;
use App\UI\Toasts\Facades\Toast;
use App\UI\Toasts\ToastAction;
use App\UI\Toasts\ToastManager;
use Livewire\Livewire;

beforeEach(function () {
    // Clear any existing toasts before each test
    app(ToastManager::class)->clear();
});

describe('Enhanced Toast UI Component', function () {
    test('toast container renders with accessibility attributes', function () {
        Toast::success('Test Toast', 'Test message')->send();
        
        $component = Livewire::test(ToastContainer::class);
        
        // Check that the component renders successfully
        $component->assertOk();
        
        // Verify toasts are accessible through the component
        $toastsByPosition = $component->get('toastsByPosition');
        expect($toastsByPosition)->not->toBeEmpty();
        expect($toastsByPosition->flatten())->toHaveCount(1);
    });

    test('individual toasts have enhanced styling configuration', function () {
        Toast::info('Test Title', 'Test Body')->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        $toast = $toasts->first();
        
        $typeConfig = $toast->getTypeConfig();
        
        // Verify enhanced styling keys are present
        expect($typeConfig)->toHaveKeys([
            'icon', 'background', 'border', 'text', 'icon_color',
            'icon_background', 'close_hover', 'action_hover',
            'progress_color', 'progress_color_end', 'accent_bar'
        ]);
    });

    test('enhanced animation settings are configured', function () {
        $enterAnimation = config('toasts.animations.enter');
        $exitAnimation = config('toasts.animations.exit');
        
        expect($enterAnimation['duration'])->toBe(500);
        expect($enterAnimation['from'])->toContain('rotate-1');
        expect($enterAnimation['to'])->toContain('rotate-0');
        
        expect($exitAnimation['duration'])->toBe(300);
        expect($exitAnimation['from'])->toContain('rotate-0');
        expect($exitAnimation['to'])->toContain('rotate-1');
    });
});

describe('Toast Type Enhanced Styling', function () {
    test('success toast has enhanced visual configuration', function () {
        Toast::success('Success Test')->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        $toast = $toasts->first();
        
        $typeConfig = $toast->getTypeConfig();
        
        expect($typeConfig['background'])->toContain('bg-status-success-50/90');
        expect($typeConfig['background'])->toContain('backdrop-blur-sm');
        expect($typeConfig['icon_background'])->toBe('bg-status-success-100 dark:bg-status-success-800/50');
        expect($typeConfig['progress_color'])->toBe('rgb(34 197 94)');
        expect($typeConfig['progress_color_end'])->toBe('rgb(21 128 61)');
        expect($typeConfig['accent_bar'])->toContain('bg-gradient-to-b');
    });

    test('error toast has enhanced visual configuration', function () {
        Toast::error('Error Test')->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        $toast = $toasts->first();
        
        $typeConfig = $toast->getTypeConfig();
        
        expect($typeConfig['background'])->toContain('bg-status-error-50/90');
        expect($typeConfig['background'])->toContain('backdrop-blur-sm');
        expect($typeConfig['progress_color'])->toBe('rgb(239 68 68)');
        expect($typeConfig['progress_color_end'])->toBe('rgb(185 28 28)');
    });

    test('warning and info toasts have enhanced configurations', function () {
        Toast::warning('Warning Test')->send();
        Toast::info('Info Test')->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        
        expect($toasts)->toHaveCount(2);
        
        foreach ($toasts as $toast) {
            $typeConfig = $toast->getTypeConfig();
            expect($typeConfig)->toHaveKey('icon_background');
            expect($typeConfig)->toHaveKey('progress_color');
            expect($typeConfig)->toHaveKey('progress_color_end');
            expect($typeConfig)->toHaveKey('accent_bar');
            expect($typeConfig)->toHaveKey('action_hover');
        }
    });
});

describe('Toast Interaction and Behavior', function () {
    test('multiple toasts in same position work correctly', function () {
        Toast::success('Toast 1')->position('top-right')->send();
        Toast::warning('Toast 2')->position('top-right')->send();
        Toast::info('Toast 3')->position('top-right')->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toastsByPosition = $component->get('toastsByPosition');
        
        expect($toastsByPosition->get('top-right'))->toHaveCount(3);
        
        // Test removing individual toast
        $firstToast = $toastsByPosition->get('top-right')->first();
        $component->call('removeToast', $firstToast->getId());
        
        $updatedToasts = $component->get('toastsByPosition');
        expect($updatedToasts->get('top-right'))->toHaveCount(2);
    });

    test('toast actions work with enhanced styling', function () {
        $action = ToastAction::make('Test Action')->url('/test')->shouldCloseToast(false);
        
        Toast::info('Action Test')
            ->action($action)
            ->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        $toast = $toasts->first();
        
        expect($toast->getActions())->toHaveCount(1);
        
        $actionData = $toast->getActions()[0]->toArray();
        
        // Test action handling
        $component->call('handleToastAction', $toast->getId(), $actionData)
            ->assertDispatched('toast-action-clicked', [
                'toastId' => $toast->getId(),
                'actionData' => $actionData
            ]);
    });

    test('toast timing and persistence work correctly', function () {
        // Test timed toast
        Toast::success('Timed Toast')->duration(5000)->send();
        
        // Test persistent toast
        Toast::warning('Persistent Toast')->persistent()->send();
        
        $component = Livewire::test(ToastContainer::class);
        $toasts = $component->get('toastsByPosition')->flatten();
        
        expect($toasts)->toHaveCount(2);
        
        $timedToast = $toasts->first(fn($toast) => !$toast->isPersistent());
        $persistentToast = $toasts->first(fn($toast) => $toast->isPersistent());
        
        expect($timedToast->getDuration())->toBe(5000);
        expect($persistentToast->isPersistent())->toBeTrue();
    });
});

describe('Configuration and Integration', function () {
    test('enhanced position configurations are correctly set', function () {
        $positions = config('toasts.positions');
        
        foreach ($positions as $position => $config) {
            expect($config)->toHaveKeys(['container', 'alignment']);
            expect($config['container'])->toContain('fixed');
            expect($config['container'])->toContain('z-50');
        }
    });

    test('all toast types have complete enhanced styling', function () {
        $types = config('toasts.types');
        
        foreach ($types as $type => $config) {
            expect($config)->toHaveKeys([
                'icon', 'background', 'border', 'text', 'icon_color',
                'icon_background', 'close_hover', 'action_hover',
                'progress_color', 'progress_color_end', 'accent_bar'
            ]);
            
            expect($config['background'])->toContain('backdrop-blur-sm');
            expect($config['progress_color'])->toStartWith('rgb(');
            expect($config['accent_bar'])->toContain('bg-gradient-to-b');
        }
    });

    test('dark mode variants are included in all configurations', function () {
        $types = config('toasts.types');
        
        foreach ($types as $type => $config) {
            expect($config['background'])->toContain('dark:');
            expect($config['border'])->toContain('dark:');
            expect($config['text'])->toContain('dark:');
            expect($config['icon_color'])->toContain('dark:');
            expect($config['icon_background'])->toContain('dark:');
        }
    });
});

describe('Component Integration and Rendering', function () {
    test('toast container renders without errors', function () {
        $component = Livewire::test(ToastContainer::class);
        $component->assertOk();
        $component->assertViewIs('livewire.components.toast-container');
    });

    test('toast container handles empty state correctly', function () {
        $component = Livewire::test(ToastContainer::class);
        $toastsByPosition = $component->get('toastsByPosition');
        
        expect($toastsByPosition->isEmpty())->toBeTrue();
    });

    test('toast container clears all toasts correctly', function () {
        Toast::success('Toast 1')->send();
        Toast::error('Toast 2')->send();
        Toast::warning('Toast 3')->send();
        
        $component = Livewire::test(ToastContainer::class);
        
        // Verify toasts exist
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(3);
        
        // Clear all toasts
        $component->call('clearAllToasts');
        
        // Verify all toasts are cleared
        expect($component->get('toastsByPosition')->flatten())->toHaveCount(0);
    });
});