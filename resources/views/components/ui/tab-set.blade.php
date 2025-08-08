@props(['tabs' => [], 'model' => null])

@php
    // Handle different input types for tabs
    if (is_array($tabs)) {
        $tabsData = $tabs;
    } elseif (method_exists($tabs, 'buildNavigation')) {
        $navigation = $tabs->buildNavigation($model);
        $tabsData = $navigation instanceof \Illuminate\Support\Collection ? $navigation->toArray() : $navigation;
    } else {
        $tabsData = [];
    }
@endphp

@if(count($tabsData) > 0)
    <div class="border-b border-zinc-200 dark:border-zinc-700 mb-6">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            @foreach($tabsData as $tab)
                @php
                    $isActive = $tab['active'] ?? false;
                    $isDisabled = $tab['disabled'] ?? false;
                    $hasClickHandler = $tab['hasClickHandler'] ?? false;
                    $wireNavigate = $tab['wireNavigate'] ?? true;
                    $badge = $tab['badge'] ?? null;
                    $badgeColor = $tab['badgeColor'] ?? 'zinc';
                @endphp
                
                @if($hasClickHandler)
                    <button
                        type="button"
                        @class([
                            'group inline-flex items-center py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-150',
                            'border-blue-500 text-blue-600 dark:text-blue-400' => $isActive,
                            'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200' => !$isActive && !$isDisabled,
                            'border-transparent text-zinc-300 cursor-not-allowed dark:text-zinc-600' => $isDisabled
                        ])
                        @disabled($isDisabled)
                        @if(isset($tab['extraAttributes']))
                            @foreach($tab['extraAttributes'] as $attr => $value)
                                {{ $attr }}="{{ $value }}"
                            @endforeach
                        @endif
                    >
                        @if(isset($tab['icon']))
                            <flux:icon 
                                name="{{ $tab['icon'] }}" 
                                @class([
                                    'mr-2 h-5 w-5',
                                    'text-blue-500 dark:text-blue-400' => $isActive,
                                    'text-zinc-400 group-hover:text-zinc-500 dark:text-zinc-500 dark:group-hover:text-zinc-400' => !$isActive && !$isDisabled,
                                    'text-zinc-300 dark:text-zinc-600' => $isDisabled
                                ]) 
                            />
                        @endif
                        {{ $tab['label'] }}
                        @if($badge)
                            <flux:badge 
                                size="sm" 
                                color="{{ $badgeColor }}"
                                class="ml-2"
                            >
                                {{ $badge }}
                            </flux:badge>
                        @endif
                    </button>
                @else
                    <a
                        href="{{ $tab['url'] ?? '#' }}"
                        @class([
                            'group inline-flex items-center py-2 px-1 border-b-2 font-medium text-sm transition-colors duration-150',
                            'border-blue-500 text-blue-600 dark:text-blue-400' => $isActive,
                            'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200' => !$isActive && !$isDisabled,
                            'border-transparent text-zinc-300 cursor-not-allowed dark:text-zinc-600' => $isDisabled
                        ])
                        @if($wireNavigate && !$isDisabled) wire:navigate @endif
                        @if($isDisabled) tabindex="-1" aria-disabled="true" @endif
                        @if(isset($tab['extraAttributes']))
                            @foreach($tab['extraAttributes'] as $attr => $value)
                                {{ $attr }}="{{ $value }}"
                            @endforeach
                        @endif
                    >
                        @if(isset($tab['icon']))
                            <flux:icon 
                                name="{{ $tab['icon'] }}" 
                                @class([
                                    'mr-2 h-5 w-5',
                                    'text-blue-500 dark:text-blue-400' => $isActive,
                                    'text-zinc-400 group-hover:text-zinc-500 dark:text-zinc-500 dark:group-hover:text-zinc-400' => !$isActive && !$isDisabled,
                                    'text-zinc-300 dark:text-zinc-600' => $isDisabled
                                ]) 
                            />
                        @endif
                        {{ $tab['label'] }}
                        @if($badge)
                            <flux:badge 
                                size="sm" 
                                color="{{ $badgeColor }}"
                                class="ml-2"
                            >
                                {{ $badge }}
                            </flux:badge>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>
    </div>
@endif