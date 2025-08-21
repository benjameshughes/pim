{{-- 🎨 FLUX CARD CONTENT - Shadcn Inspired --}}
@php
    $classes = Flux::classes()
        ->add('p-6 pt-0')
        ->add($attributes->get('class'));
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>