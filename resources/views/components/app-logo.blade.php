@props(['size' => 'md'])

@php
$sizes = [
    'sm' => [
        'container' => 'space-x-1.5',
        'icon' => 'size-6',
        'iconInner' => 'size-3.5',
        'text' => 'text-xs'
    ],
    'md' => [
        'container' => 'space-x-2',
        'icon' => 'size-8',
        'iconInner' => 'size-5',
        'text' => 'text-sm'
    ],
    'lg' => [
        'container' => 'space-x-3',
        'icon' => 'size-12',
        'iconInner' => 'size-7',
        'text' => 'text-lg'
    ]
];

$currentSize = $sizes[$size] ?? $sizes['md'];
@endphp

<div class="flex items-center {{ $currentSize['container'] }}">
    <div class="flex aspect-square {{ $currentSize['icon'] }} items-center justify-center rounded-md bg-accent-content text-accent-foreground">
        <x-app-logo-icon class="{{ $currentSize['iconInner'] }} fill-current text-white dark:text-black" />
    </div>
    <div class="{{ $currentSize['text'] }}">
        <span class="truncate leading-tight font-semibold">{{ config('app.name') }}</span>
    </div>
</div>
