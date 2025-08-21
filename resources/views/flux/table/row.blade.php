@aware(['theme', 'hoverable', 'striped'])

@props([
    'key' => null,
    'actionRoute' => null,
])

@php
$rowClasses = Flux::classes()
    ->add(match ($theme ?? 'default') {
        'glass' => 'bg-white/5 hover:bg-white/10 dark:bg-gray-900/5 dark:hover:bg-gray-900/10',
        'neon' => 'bg-gray-900 hover:bg-gray-800 border-emerald-400/20',
        'minimal' => 'bg-white hover:bg-gray-25 dark:bg-gray-800 dark:hover:bg-gray-700',
        'phoenix' => 'bg-white hover:bg-gradient-to-r hover:from-orange-25 hover:to-red-25 dark:bg-gray-800 dark:hover:from-orange-950 dark:hover:to-red-950',
        default => 'bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700'
    })
    ->add($hoverable !== false ? 'transition-colors duration-150' : '')
    ->add($striped ? 'odd:bg-gray-25 dark:odd:bg-gray-800/50' : '');
@endphp

<tr {{ $attributes->class($rowClasses) }} @if($key) :key="{{ $key }}" @endif>
    {{ $slot }}
</tr>