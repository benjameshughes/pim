@props(['tabs' => [], 'class' => ''])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm $class"]) }}>
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
            @foreach($tabs as $tab)
                <a href="{{ $tab['url'] }}"
                   @class([
                       'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                       'border-purple-500 text-purple-600 dark:text-purple-400' => $tab['active'],
                       'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' => !$tab['active']
                   ])
                   wire:navigate>
                    <flux:icon :name="$tab['icon']" class="w-4 h-4 inline mr-2" />
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </nav>
    </div>
    
    {{ $slot }}
</div>