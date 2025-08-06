@props([
    'header' => null,
    'footer' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 shadow-xs ' . $class]) }}>
    @if($header)
        <div class="border-b border-stone-200 dark:border-stone-800 px-6 py-4">
            {{ $header }}
        </div>
    @endif
    
    <div class="p-6">
        {{ $slot }}
    </div>
    
    @if($footer)
        <div class="border-t border-stone-200 dark:border-stone-800 px-6 py-4">
            {{ $footer }}
        </div>
    @endif
</div>