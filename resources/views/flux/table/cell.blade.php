@php use Flux\Flux; @endphp
@aware(['theme', 'size'])

@props([
    'variant' => 'default',
    'colspan' => null,
])

@php
$cellPadding = match ($size ?? 'normal') {
    'compact' => 'px-3 py-2',
    'spacious' => 'px-8 py-6',  
    default => 'px-6 py-4'
};

$classes = Flux::classes()
    ->add($cellPadding)
    ->add('text-sm')
    ->add(match ($variant) {
        'strong' => 'font-medium text-gray-900 dark:text-white',
        default => 'text-gray-900 dark:text-white'
    });
@endphp

<td {{ $attributes->class($classes) }} @if($colspan) colspan="{{ $colspan }}" @endif>
    {{ $slot }}
</td>