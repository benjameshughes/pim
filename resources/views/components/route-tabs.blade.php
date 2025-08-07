@props(['tabs' => [], 'class' => ''])

<div {{ $attributes->merge(['class' => "bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm $class"]) }}>
    <div class="border-b border-zinc-200 dark:border-zinc-700">
        <nav class="flex space-x-8 px-6" aria-label="Tabs">
            @foreach($tabs as $tab)
                <a href="{{ $tab['url'] }}"
                   @class([
                       'py-4 px-1 border-b-2 font-medium text-sm transition-all duration-200 ease-out relative group',
                       'border-purple-500 text-purple-600 dark:text-purple-400' => $tab['active'],
                       'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300 hover:-translate-y-0.5' => !$tab['active']
                   ])
                   @if($tab['wireNavigate'] ?? true) wire:navigate @endif>
                    <div class="flex items-center gap-2 transition-all duration-200">
                        <flux:icon :name="$tab['icon']" class="w-4 h-4 transition-transform duration-200" />
                        <span>{{ $tab['label'] }}</span>
                        @if(isset($tab['badge']) && $tab['badge'])
                            <span @class([
                                'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium transition-all duration-200 animate-scale-in',
                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300' => $tab['active'],
                                'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400 group-hover:bg-zinc-200 dark:group-hover:bg-zinc-600' => !$tab['active']
                            ])>
                                {{ $tab['badge'] }}
                            </span>
                        @endif
                    </div>
                </a>
            @endforeach
        </nav>
    </div>
    
    <div class="animate-tab-content">
        {{ $slot }}
    </div>
</div>