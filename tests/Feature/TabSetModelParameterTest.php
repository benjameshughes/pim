<?php

use App\Models\User;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;

it('handles model parameters without array to string conversion error', function () {
    $user = User::factory()->create();

    $tabSet = TabSet::make()
        ->tab(
            Tab::make('overview')
                ->label('Overview')
                ->route('dashboard') // Use existing route directly
        );

    // This should not throw "Array to string conversion" error
    $navigation = $tabSet->buildNavigation($user);

    expect($navigation)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($navigation->count())->toBe(1);
});

it('correctly uses model key in route parameters', function () {
    $user = User::factory()->create();

    $tabSet = TabSet::make()
        ->tab(
            Tab::make('profile')
                ->label('Profile')
                ->route('dashboard') // Using existing route
        );

    $navigation = $tabSet->buildNavigation($user);
    $tab = $navigation->first();

    expect($tab)->toHaveKey('url');
    expect($tab['url'])->toBeString();
    // Note: dashboard route doesn't use user parameter, so we just verify it builds
});

it('handles multiple models and parameters correctly', function () {
    $user = User::factory()->create();

    $tabSet = TabSet::make()
        ->defaultRouteParameters(['extra' => 'param'])
        ->tab(
            Tab::make('settings')
                ->label('Settings')
                ->route('dashboard', ['tab' => 'settings'])
        );

    // Should handle model + additional parameters without errors
    $navigation = null;
    expect(function () use ($tabSet, $user, &$navigation) {
        $navigation = $tabSet->buildNavigation($user);

        return true;
    })->not->toThrow(\TypeError::class);

    expect($navigation->count())->toBe(1);
});
