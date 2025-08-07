@props([
    'header' => null,
    'footer' => null,
    'class' => '',
])

<div {{ $attributes->merge(['class' => 'rounded-xl border bg-white dark:bg-zinc-800 dark:border-zinc-700 shadow-sm ' . $class]) }}>
    @if($header)
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-6 py-4">
            {{ $header }}
        </div>
    @endif
    
    <div class="p-6">
        {{ $slot }}
    </div>
    
    @if($footer)
        <div class="border-t border-zinc-200 dark:border-zinc-700 px-6 py-4">
            {{ $footer }}
        </div>
    @endif
</div>