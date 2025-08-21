{{-- 🎨 FLUX CARD DESCRIPTION - Shadcn Inspired --}}
@php
    $classes = Flux::classes()
        ->add('text-sm text-zinc-500 dark:text-zinc-400')
        ->add($attributes->get('class'));
@endphp

<p {{ $attributes->class($classes) }}>
    {{ $slot }}
</p>