@props([
    'variant' => 'default',
])

<flux:delegate-component :component="'table.footer.variants.' . $variant">{{ $slot }}</flux:delegate-component>