{{-- 🎨 FLUX CARD TITLE - Shadcn Inspired --}}
@php
    $tag = $attributes->get('level', 'h3');
    $classes = Flux::classes()
        ->add('text-2xl font-semibold leading-none tracking-tight')
        ->add($attributes->get('class'));
@endphp

<{{ $tag }} {{ $attributes->except(['level'])->class($classes) }}>
    {{ $slot }}
</{{ $tag }}>