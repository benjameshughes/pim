@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.row.variants.' . $variant">{{ $slot }}</flux:delegate-component>