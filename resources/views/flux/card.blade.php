{{-- 🎨 FLUX CARD COMPONENT - Shadcn Inspired --}}
{{-- Based on shadcn/ui card component architecture --}}
@php
    $classes = Flux::classes()
        ->add('rounded-lg border border-zinc-200 bg-white text-zinc-950 shadow-sm dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-50')
        ->add($attributes->get('class'));
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>