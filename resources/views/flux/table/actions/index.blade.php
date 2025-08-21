@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.actions.variants.' . $variant">{{ $slot }}</flux:delegate-component>