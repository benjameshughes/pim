<?php

use App\UI\Toasts\Toast;
use Livewire\Livewire;

test('multiple toasts stack independently with their own timers', function () {
    // Clear any existing toasts
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    // Create multiple toasts with different durations
    Toast::success('First Toast', 'This appears first')
        ->duration(5000)
        ->send();
    
    Toast::error('Second Toast', 'This appears second')
        ->duration(3000)
        ->send();
    
    Toast::warning('Third Toast', 'This appears third')
        ->duration(7000)
        ->send();
    
    Toast::info('Fourth Toast', 'This appears fourth')
        ->persistent() // This one won't auto-dismiss
        ->send();
    
    $component = Livewire::test('components.toast-container');
    
    // All 4 toasts should be present
    expect($component->get('allToasts'))->toHaveCount(4);
    
    // Verify each toast has independent properties
    $toasts = $component->get('allToasts')->values()->toArray();
    
    expect($toasts[0]['type'])->toBe('success');
    expect($toasts[0]['duration'])->toBe(5000);
    
    expect($toasts[1]['type'])->toBe('error');
    expect($toasts[1]['duration'])->toBe(3000);
    
    expect($toasts[2]['type'])->toBe('warning');
    expect($toasts[2]['duration'])->toBe(7000);
    
    expect($toasts[3]['type'])->toBe('info');
    expect($toasts[3]['persistent'])->toBeTrue();
});

test('toasts in different positions stack independently', function () {
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    // Create toasts in different positions
    Toast::success('Top Right Toast')
        ->position('top-right')
        ->send();
    
    Toast::error('Top Left Toast')
        ->position('top-left')
        ->send();
    
    Toast::warning('Bottom Right Toast')
        ->position('bottom-right')
        ->send();
    
    Toast::info('Bottom Left Toast')
        ->position('bottom-left')
        ->send();
    
    Toast::success('Another Top Right')
        ->position('top-right')
        ->send();
    
    $component = Livewire::test('components.toast-container');
    
    // Should have 5 toasts total
    expect($component->get('allToasts'))->toHaveCount(5);
    
    // Group by position
    $toastsByPosition = $component->get('toastsByPosition');
    
    // Verify positioning
    expect($toastsByPosition->get('top-right'))->toHaveCount(2);
    expect($toastsByPosition->get('top-left'))->toHaveCount(1);
    expect($toastsByPosition->get('bottom-right'))->toHaveCount(1);
    expect($toastsByPosition->get('bottom-left'))->toHaveCount(1);
});

test('toasts maintain maximum limit when stacking', function () {
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    // Create more toasts than the limit (default is 5)
    for ($i = 1; $i <= 10; $i++) {
        Toast::info("Toast {$i}", "Message {$i}")->send();
    }
    
    $component = Livewire::test('components.toast-container');
    
    // Should only keep the last 5 toasts
    expect($component->get('allToasts'))->toHaveCount(5);
    
    // Verify we have the last 5 toasts (6-10)
    $toasts = $component->get('allToasts')->values()->toArray();
    expect($toasts[0]['title'])->toBe('Toast 6');
    expect($toasts[4]['title'])->toBe('Toast 10');
});

test('each toast can be removed independently while others remain', function () {
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    // Create 3 toasts
    Toast::success('Toast 1')->send();
    Toast::error('Toast 2')->send();
    Toast::warning('Toast 3')->send();
    
    $component = Livewire::test('components.toast-container');
    expect($component->get('allToasts'))->toHaveCount(3);
    
    // Get the ID of the middle toast
    $toasts = $component->get('allToasts')->values()->toArray();
    $middleToastId = $toasts[1]['id'];
    
    // Remove only the middle toast
    $component->call('removeToast', $middleToastId);
    
    // Should have 2 toasts remaining
    expect($component->get('allToasts'))->toHaveCount(2);
    
    // Verify the correct toasts remain
    $remainingToasts = $component->get('allToasts')->values()->toArray();
    expect($remainingToasts[0]['title'])->toBe('Toast 1');
    expect($remainingToasts[1]['title'])->toBe('Toast 3');
});

test('toast stacking with different types and actions work independently', function () {
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    // Create toasts with different configurations
    Toast::success('Success with action')
        ->action(\App\UI\Toasts\ToastAction::make('Undo')->url('/undo'))
        ->send();
    
    Toast::error('Error persistent')
        ->persistent()
        ->closable(false)
        ->send();
    
    Toast::warning('Warning with timer')
        ->duration(10000)
        ->send();
    
    $component = Livewire::test('components.toast-container');
    $toasts = $component->get('allToasts')->values()->toArray();
    
    // Each toast should maintain its own configuration
    expect($toasts[0]['actions'])->toHaveCount(1);
    expect($toasts[0]['type'])->toBe('success');
    
    expect($toasts[1]['persistent'])->toBeTrue();
    expect($toasts[1]['closable'])->toBeFalse();
    expect($toasts[1]['type'])->toBe('error');
    
    expect($toasts[2]['duration'])->toBe(10000);
    expect($toasts[2]['type'])->toBe('warning');
    
    // All three should coexist
    expect($component->get('allToasts'))->toHaveCount(3);
});

test('html renders multiple stacked toasts with proper structure', function () {
    app(\App\UI\Toasts\ToastManager::class)->clear();
    
    Toast::success('First')->send();
    Toast::error('Second')->send();
    Toast::info('Third')->send();
    
    $component = Livewire::test('components.toast-container');
    $html = $component->html();
    
    // Should have container wrapper
    expect($html)->toContain('toast-container-wrapper');
    
    // Should have position groups
    expect($html)->toContain('x-for="(toast, index) in toasts"');
    
    // Should have proper Alpine structure for multiple toasts
    expect($html)->toContain('$store.toasts.byPosition');
    expect($html)->toContain(':key="toast.id"');
    
    // Should have stagger animation delay
    expect($html)->toContain('animation-delay');
    expect($html)->toContain('(index * 100) + \'ms\'');
});