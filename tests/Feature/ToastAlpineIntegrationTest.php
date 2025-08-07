<?php

use App\Toasts\Toast;
use Livewire\Livewire;

test('alpine store integration works without errors', function () {
    // Create some test toasts
    Toast::success('Test Success', 'This is a success message')->send();
    Toast::error('Test Error', 'This is an error message')->send();
    
    // Mount the component
    $component = Livewire::test('components.toast-container');
    
    // Verify the component has toasts
    expect($component->get('allToasts'))->toHaveCount(2);
    
    // Verify the toasts are properly formatted for Alpine
    $toasts = $component->get('allToasts')->values()->toArray();
    expect($toasts)->toBeArray();
    expect($toasts[0])->toHaveKeys(['id', 'title', 'body', 'type', 'position', 'type_config']);
    
    // Verify the view renders with Alpine directives
    $html = $component->html();
    expect($html)->toContain('x-data');
    expect($html)->toContain('$store.toasts');
    expect($html)->toContain('x-init="init()"');
    
    // Verify the data is properly formatted for Alpine
    expect($html)->toContain('toastData:');
});

test('toast data is properly formatted as array for Alpine', function () {
    Toast::info('Test', 'Message')->send();
    
    $component = Livewire::test('components.toast-container');
    $toasts = $component->get('allToasts');
    
    // Test that values()->toArray() produces a proper indexed array
    $arrayData = $toasts->values()->toArray();
    expect($arrayData)->toBeArray();
    expect(array_keys($arrayData))->toBe([0]); // Should be indexed, not associative
});

test('alpine store handles empty toast state', function () {
    // Clear any existing toasts
    app(\App\Toasts\ToastManager::class)->clear();
    
    $component = Livewire::test('components.toast-container');
    
    // Should handle empty state gracefully
    expect($component->get('allToasts'))->toHaveCount(0);
    $arrayData = $component->get('allToasts')->values()->toArray();
    expect($arrayData)->toBe([]);
});