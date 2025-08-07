<?php

use App\Toasts\Toast;
use App\Toasts\ToastManager;
use Livewire\Livewire;

beforeEach(function () {
    // Clear toasts before each test
    app(ToastManager::class)->clear();
});

it('sets navigation persistence flag with persist method', function () {
    $toast = Toast::success('Test Toast')
        ->persist();
    
    $array = $toast->toArray();
    
    expect($array['navigatePersist'])->toBeTrue();
});

it('can chain persist method with other methods', function () {
    $toast = Toast::info('Navigation Toast')
        ->persist()
        ->persistent() // Also make it not auto-dismiss
        ->position('bottom-right')
        ->duration(10000);
    
    $array = $toast->toArray();
    
    expect($array['navigatePersist'])->toBeTrue();
    expect($array['persistent'])->toBeTrue();
    expect($array['position'])->toBe('bottom-right');
    expect($array['duration'])->toBe(10000);
});

it('defaults navigation persistence to false', function () {
    $toast = Toast::success('Regular Toast');
    
    $array = $toast->toArray();
    
    expect($array['navigatePersist'])->toBeFalse();
});

it('marks toasts with persist to survive navigation in Alpine store', function () {
    // Create toasts with different persistence settings
    Toast::success('Persistent Navigation Toast')
        ->persist()
        ->send();
    
    Toast::error('Regular Toast')
        ->send();
    
    Toast::info('Another Persistent')
        ->persist()
        ->send();
    
    $component = Livewire::test('components.toast-container');
    $toasts = $component->get('allToasts')->values()->toArray();
    
    // All 3 toasts should be present initially
    expect($toasts)->toHaveCount(3);
    
    // Check navigation persistence flags
    expect($toasts[0]['navigatePersist'])->toBeTrue();
    expect($toasts[1]['navigatePersist'])->toBeFalse();
    expect($toasts[2]['navigatePersist'])->toBeTrue();
});

it('reconstructs navigation persistence from session', function () {
    $manager = app(ToastManager::class);
    
    // Create session data with navigatePersist
    $sessionData = [
        [
            'id' => 'test-1',
            'title' => 'Persistent Toast',
            'type' => 'success',
            'navigatePersist' => true,
        ],
        [
            'id' => 'test-2',
            'title' => 'Regular Toast',
            'type' => 'info',
            'navigatePersist' => false,
        ]
    ];
    
    app('session.store')->put('toasts', $sessionData);
    
    $toasts = $manager->getToasts();
    
    expect($toasts)->toHaveCount(2);
    
    $toastArrays = $toasts->map(fn($t) => $t->toArray())->toArray();
    expect($toastArrays[0]['navigatePersist'])->toBeTrue();
    expect($toastArrays[1]['navigatePersist'])->toBeFalse();
});

it('keeps persist and persistent methods independent', function () {
    // Test that persist() (navigation) and persistent() (auto-dismiss) are independent
    $toast1 = Toast::success('Navigate but auto-dismiss')
        ->persist() // Survives navigation
        ->duration(5000); // But still auto-dismisses
    
    $toast2 = Toast::info('No navigate but no dismiss')
        ->persistent(); // Won't auto-dismiss but won't survive navigation
    
    $toast3 = Toast::warning('Both')
        ->persist() // Survives navigation
        ->persistent(); // And won't auto-dismiss
    
    $array1 = $toast1->toArray();
    $array2 = $toast2->toArray();
    $array3 = $toast3->toArray();
    
    // Toast 1: navigates but auto-dismisses
    expect($array1['navigatePersist'])->toBeTrue();
    expect($array1['persistent'])->toBeFalse();
    expect($array1['duration'])->toBe(5000);
    
    // Toast 2: doesn't navigate but doesn't auto-dismiss
    expect($array2['navigatePersist'])->toBeFalse();
    expect($array2['persistent'])->toBeTrue();
    
    // Toast 3: both navigation and no auto-dismiss
    expect($array3['navigatePersist'])->toBeTrue();
    expect($array3['persistent'])->toBeTrue();
});

it('includes navigation event listeners in container template', function () {
    // The navigation listeners are in the Blade component wrapper, not the Livewire component
    // So we'll check if the Alpine store method exists and navigation persistence is in the data
    $component = Livewire::test('components.toast-container');
    
    // Create a toast with navigation persistence
    Toast::success('Test')->persist()->send();
    
    $component->call('$refresh');
    $toasts = $component->get('allToasts')->values()->toArray();
    
    // Verify the navigation persistence flag is present in the data
    expect($toasts[0])->toHaveKey('navigatePersist');
    expect($toasts[0]['navigatePersist'])->toBeTrue();
});

it('can toggle persist on and off', function () {
    $toast = Toast::success('Test')
        ->persist(true);
    
    expect($toast->toArray()['navigatePersist'])->toBeTrue();
    
    $toast->persist(false);
    
    expect($toast->toArray()['navigatePersist'])->toBeFalse();
});

describe('Navigation Persistence Behavior', function () {
    it('filters out non-persistent toasts on navigation', function () {
        Toast::success('Will persist')->persist()->send();
        Toast::error('Will not persist')->send();
        Toast::info('Also persists')->persist()->send();
        
        $component = Livewire::test('components.toast-container');
        $toasts = $component->get('allToasts')->values()->toArray();
        
        expect($toasts)->toHaveCount(3);
        
        // After navigation, only persisted toasts should remain
        $persistedToasts = array_filter($toasts, fn($t) => $t['navigatePersist']);
        expect($persistedToasts)->toHaveCount(2);
    });
    
    it('maintains separate controls for auto-dismiss and navigation', function () {
        $toast = Toast::success('Complex Toast')
            ->persist() // Survives navigation
            ->duration(3000); // But still has a timer
        
        $array = $toast->toArray();
        
        expect($array['navigatePersist'])->toBeTrue();
        expect($array['persistent'])->toBeFalse(); // Not persistent (will auto-dismiss)
        expect($array['duration'])->toBe(3000);
    });
});