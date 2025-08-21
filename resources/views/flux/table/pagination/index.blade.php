@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.pagination.variants.' . $variant">{{ $slot }}</flux:delegate-component>