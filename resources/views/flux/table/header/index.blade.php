@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.header.variants.' . $variant">{{ $slot }}</flux:delegate-component>