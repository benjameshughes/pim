@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.search.variants.' . $variant">{{ $slot }}</flux:delegate-component>