@props([
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'space-y-1 ' . $class]) }}>
    {{ $slot }}
</div>