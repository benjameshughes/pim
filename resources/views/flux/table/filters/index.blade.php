@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.filters.variants.' . $variant">{{ $slot }}</flux:delegate-component>