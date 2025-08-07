<?php

use App\Toasts\Toast;
use Livewire\Livewire;

test('closing one toast does not close other toasts', function () {
    // Clear existing toasts
    app(\App\Toasts\ToastManager::class)->clear();
    
    // Create 3 toasts
    Toast::success('Toast 1', 'First toast message')->send();
    Toast::error('Toast 2', 'Second toast message')->send();
    Toast::warning('Toast 3', 'Third toast message')->send();
    
    $component = Livewire::test('components.toast-container');
    
    // Verify we have 3 toasts
    expect($component->get('allToasts'))->toHaveCount(3);
    
    // Get the IDs
    $toasts = $component->get('allToasts')->values()->toArray();
    $firstToastId = $toasts[0]['id'];
    $secondToastId = $toasts[1]['id'];
    $thirdToastId = $toasts[2]['id'];
    
    // Close only the second toast
    $component->call('removeToast', $secondToastId);
    
    // Should have 2 toasts remaining
    expect($component->get('allToasts'))->toHaveCount(2);
    
    // Verify the correct toasts remain
    $remainingToasts = $component->get('allToasts')->values()->toArray();
    $remainingIds = array_column($remainingToasts, 'id');
    
    expect($remainingIds)->toContain($firstToastId);
    expect($remainingIds)->toContain($thirdToastId);
    expect($remainingIds)->not->toContain($secondToastId);
});

test('close button only closes its own toast', function () {
    app(\App\Toasts\ToastManager::class)->clear();
    
    // Create multiple toasts
    Toast::success('Success Toast')->send();
    Toast::error('Error Toast')->send();
    Toast::info('Info Toast')->send();
    
    $component = Livewire::test('components.toast-container');
    
    // Initial count
    expect($component->get('allToasts'))->toHaveCount(3);
    
    // Get the HTML and verify close buttons are toast-specific
    $html = $component->html();
    
    // Each close button should reference its specific toast ID
    expect($html)->toContain('$store.toasts.remove(toastData.id)');
    
    // Should NOT have click.away that would close all toasts
    expect($html)->not->toContain('@click.away');
});

test('toasts remain independent when interacting with one', function () {
    app(\App\Toasts\ToastManager::class)->clear();
    
    // Create toasts with different configurations
    Toast::success('Persistent Toast')->persistent()->send();
    Toast::error('Auto-dismiss Toast')->duration(5000)->send();
    Toast::info('Another Toast')->duration(3000)->send();
    
    $component = Livewire::test('components.toast-container');
    
    // All 3 should exist
    expect($component->get('allToasts'))->toHaveCount(3);
    
    $toasts = $component->get('allToasts')->values()->toArray();
    
    // Remove the auto-dismiss toast
    $component->call('removeToast', $toasts[1]['id']);
    
    // Other toasts should remain
    expect($component->get('allToasts'))->toHaveCount(2);
    
    // Verify remaining toasts kept their properties
    $remaining = $component->get('allToasts')->values()->toArray();
    expect($remaining[0]['persistent'])->toBeTrue();
    expect($remaining[1]['duration'])->toBe(3000);
});

test('multiple toasts can be closed in any order', function () {
    app(\App\Toasts\ToastManager::class)->clear();
    
    // Create 5 toasts
    for ($i = 1; $i <= 5; $i++) {
        Toast::info("Toast {$i}")->send();
    }
    
    $component = Livewire::test('components.toast-container');
    expect($component->get('allToasts'))->toHaveCount(5);
    
    $toasts = $component->get('allToasts')->values()->toArray();
    
    // Close toasts in random order: 3rd, 1st, 5th
    $component->call('removeToast', $toasts[2]['id']); // Close 3rd
    expect($component->get('allToasts'))->toHaveCount(4);
    
    $component->call('removeToast', $toasts[0]['id']); // Close 1st
    expect($component->get('allToasts'))->toHaveCount(3);
    
    $component->call('removeToast', $toasts[4]['id']); // Close 5th
    expect($component->get('allToasts'))->toHaveCount(2);
    
    // Verify the 2nd and 4th toasts remain
    $remaining = $component->get('allToasts')->values()->toArray();
    expect($remaining[0]['title'])->toBe('Toast 2');
    expect($remaining[1]['title'])->toBe('Toast 4');
});