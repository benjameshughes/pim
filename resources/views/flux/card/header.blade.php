{{-- 🎨 FLUX CARD HEADER - Shadcn Inspired --}}
@php
    $classes = Flux::classes()
        ->add('flex flex-col space-y-1.5 p-6')
        ->add($attributes->get('class'));
@endphp

<div {{ $attributes->class($classes) }}>
    {{ $slot }}
</div>