<?php

use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Illuminate\Support\Collection;

it('can create TabSet with Collections', function () {
    $tabSet = TabSet::make()
        ->tabs(collect([
            Tab::make('overview')->label('Overview'),
            Tab::make('details')->label('Details'),
        ]))
        ->baseRoute('products')
        ->defaultRouteParameters(collect(['product' => 1]));

    expect($tabSet->getTabs())->toBeInstanceOf(Collection::class);
    expect($tabSet->getTabs())->toHaveCount(2);
});

it('can handle Collection parameters in Tab objects', function () {
    $tab = Tab::make('test')
        ->label('Test Tab')
        ->route('test.route', collect(['id' => 1, 'type' => 'example']))
        ->extraAttributes(collect(['class' => 'test-class', 'data-test' => 'true']));

    $params = $tab->getRouteParameters();
    $attributes = $tab->getExtraAttributes();

    expect($params)->toBeArray();
    expect($params)->toEqual(['id' => 1, 'type' => 'example']);
    expect($attributes)->toBeArray();
    expect($attributes)->toEqual(['class' => 'test-class', 'data-test' => 'true']);
});

it('returns Collection from buildNavigation method', function () {
    $tabSet = TabSet::make()
        ->tab(Tab::make('overview')->label('Overview'))
        ->tab(Tab::make('details')->label('Details'));

    $navigation = $tabSet->buildNavigation();

    expect($navigation)->toBeInstanceOf(Collection::class);
    expect($navigation->count())->toBe(2);
});

it('provides both toArray and toCollection methods', function () {
    $tabSet = TabSet::make()
        ->tab(Tab::make('overview')->label('Overview'))
        ->tab(Tab::make('details')->label('Details'));

    $asArray = $tabSet->toArray();
    $asCollection = $tabSet->toCollection();

    expect($asArray)->toBeArray();
    expect($asCollection)->toBeInstanceOf(Collection::class);
    expect(count($asArray))->toBe($asCollection->count());
});

it('can use Collection methods for advanced manipulation', function () {
    $tabSet = TabSet::make()
        ->tab(Tab::make('overview')->label('Overview')->badge(5))
        ->tab(Tab::make('details')->label('Details'))
        ->tab(Tab::make('analytics')->label('Analytics')->badge(10));

    $navigation = $tabSet->toCollection();
    $tabsWithBadges = $navigation->filter(fn ($tab) => isset($tab['badge']));
    $totalBadges = $navigation->sum('badge');

    expect($tabsWithBadges->count())->toBe(2);
    expect($totalBadges)->toBe(15);
});

it('maintains backwards compatibility with array inputs', function () {
    $tabSet = TabSet::make()
        ->tabs([
            ['key' => 'overview', 'label' => 'Overview'],
            ['key' => 'details', 'label' => 'Details'],
        ]);

    expect($tabSet->getTabs())->toBeInstanceOf(Collection::class);
    expect($tabSet->getTabs())->toHaveCount(2);
});
