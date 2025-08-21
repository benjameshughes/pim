@aware(['theme', 'striped'])

@props([
    'variant' => 'default',
])

@php
$divideClasses = match ($theme ?? 'default') {
    'glass' => 'divide-white/10',
    'neon' => 'divide-emerald-400/30',
    'minimal' => 'divide-gray-100 dark:divide-gray-700',
    'phoenix' => 'divide-orange-100 dark:divide-orange-900',
    default => 'divide-gray-200 dark:divide-gray-700'
};

$classes = Flux::classes()
    ->add('divide-y')
    ->add($divideClasses);
@endphp

<tbody {{ $attributes->class($classes) }}>
    {{ $slot }}
</tbody>