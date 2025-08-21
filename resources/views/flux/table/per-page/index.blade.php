@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.per-page.variants.' . $variant">{{ $slot }}</flux:delegate-component>