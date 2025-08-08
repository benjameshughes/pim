<?php

use App\UI\Components\Tab;
use App\UI\Components\TabSet;

it('handles route parameter merging correctly', function () {
    $tabSet = TabSet::make()
        ->baseRoute('products')
        ->defaultRouteParameters(['default' => 'value'])
        ->tab(
            Tab::make('overview')
                ->label('Overview')
                ->route('products.view', ['product' => 1, 'tab' => 'overview'])
        );

    // This should not throw an "array to string conversion" error
    $navigation = $tabSet->buildNavigation();

    expect($navigation)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($navigation->count())->toBe(1);

    $tab = $navigation->first();
    expect($tab)->toHaveKey('url');
    expect($tab['url'])->toBeString();
});

it('handles base route with parameters correctly', function () {
    $tabSet = TabSet::make()
        ->defaultRouteParameters(['product' => 1])
        ->tab(
            Tab::make('overview')
                ->label('Overview')
                ->route('dashboard', ['category' => 'electronics']) // Use existing route directly
        );

    // This should build navigation without throwing errors
    $navigation = $tabSet->buildNavigation();

    expect($navigation)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($navigation->count())->toBe(1);
});

it('merges Collection and array parameters without errors', function () {
    $tabSet = TabSet::make()
        ->defaultRouteParameters(collect(['default' => 'value']))
        ->tab(
            Tab::make('test')
                ->label('Test')
                ->route('dashboard', collect(['tab' => 'param'])) // Use existing route
        );

    // This tests the specific Collection + array merge that was causing issues
    expect(function () use ($tabSet) {
        return $tabSet->buildNavigation();
    })->not->toThrow(\TypeError::class);

    $navigation = $tabSet->buildNavigation();
    expect($navigation)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});
