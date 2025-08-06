@props([
    'livewireComponent',
    'config' => [],
    'data' => collect(),
    'selectedItems' => [],
    'search' => '',
    'filters' => [],
    'perPage' => 15,
    'sortBy' => null,
    'sortDirection' => 'asc',
    'sortStack' => [],
    'selectAll' => false,
    'parentProductsOnly' => false,
])

<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">{{ data_get($config, 'title', 'Items') }}</flux:heading>
            @if($subtitle = data_get($config, 'subtitle'))
                <flux:subheading>{{ $subtitle }}</flux:subheading>
            @endif
        </div>
        
        @if($actions = data_get($config, 'header_actions'))
            <div class="flex items-center gap-2">
                @foreach($actions as $action)
                    <flux:button 
                        variant="{{ $action['variant'] ?? 'primary' }}" 
                        icon="{{ $action['icon'] ?? '' }}"
                        :href="$action['href'] ?? ''"
                        wire:navigate="{{ $action['navigate'] ?? true }}"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Controls Bar -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <!-- Search and Filters -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center">
            <!-- Search -->
            @if(!empty($config['searchable']))
                <flux:input 
                    wire:model.live.debounce.300ms="stackedListSearch" 
                    type="search" 
                    placeholder="{{ data_get($config, 'search_placeholder', 'Search...') }}"
                    class="w-full md:w-96"
                />
            @endif
            
            <!-- Filters -->
            @if(!empty($config['filters']))
                <div class="flex gap-2 flex-wrap">
                    @foreach($config['filters'] as $key => $filter)
                        @if($filter['type'] === 'select')
                            <flux:select 
                                wire:model.live="stackedListFilters.{{ $key }}" 
                                placeholder="{{ $filter['placeholder'] ?? 'Filter by ' . $filter['label'] }}"
                                class="w-full md:w-48"
                            >
                                <flux:select.option value="">All {{ $filter['label'] }}</flux:select.option>
                                @foreach($filter['options'] ?? [] as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    @endforeach
                </div>
            @endif

            <!-- Parent Products Only Toggle -->
            @if(isset($parentProductsOnly))
                <div class="flex items-center gap-2">
                    <input 
                        type="checkbox" 
                        wire:model.live="parentProductsOnly" 
                        id="parentProductsOnly"
                        class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label for="parentProductsOnly" class="text-sm text-zinc-600 dark:text-zinc-400">
                        Parent products only
                    </label>
                </div>
            @endif
        </div>

        <!-- Per Page and Actions -->
        <div class="flex items-center gap-4">
            <!-- Per Page Selector -->
            <div class="flex items-center gap-2">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">Show:</span>
                <flux:select wire:model.live="stackedListPerPage" class="w-32">
                    @foreach([15 => '15', 25 => '25', 50 => '50', 100 => '100'] as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Export Options -->
            @if(data_get($config, 'export', false))
                <div class="flex items-center gap-2">
                    <flux:button variant="outline" wire:click="exportStackedListData('csv')" size="sm" icon="download">
                        CSV
                    </flux:button>
                    <flux:button variant="outline" wire:click="showStackedListExportModal" size="sm" icon="download">
                        Export
                    </flux:button>
                </div>
            @endif

            <!-- Clear Filters Button -->
            @if($search || collect($filters)->filter()->isNotEmpty() || !empty($sortStack))
                <flux:button variant="ghost" wire:click="clearStackedListFilters" size="sm" icon="x">
                    Clear All
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Multi-Sort Display -->
    @if(!empty($sortStack))
        <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <flux:icon name="sort-asc" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            <span class="text-sm text-blue-700 dark:text-blue-300 font-medium">Multi-Sort Active:</span>
            <div class="flex items-center gap-2 flex-wrap">
                @foreach($sortStack as $sort)
                    <flux:badge variant="outline" class="text-xs bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-600">
                        {{ $sort['priority'] }}. {{ ucwords(str_replace('_', ' ', $sort['column'])) }}
                        <flux:icon name="{{ $sort['direction'] === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3 ml-1" />
                        <button wire:click="removeStackedListSortColumn('{{ $sort['column'] }}')" class="ml-1 text-red-500 hover:text-red-700">
                            <flux:icon name="x" class="w-3 h-3" />
                        </button>
                    </flux:badge>
                @endforeach
                <flux:button variant="ghost" wire:click="clearAllStackedListSorts" size="sm" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200">
                    Clear All Sorts
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Beautiful Floating Bulk Actions Bar -->
    <div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 w-auto max-w-6xl mx-auto px-4 transition-all duration-300 ease-out {{ !empty($selectedItems) && !empty($config['bulk_actions']) ? 'translate-y-0 opacity-100' : 'translate-y-full opacity-0 pointer-events-none' }}">
        <div class="flex items-center justify-between p-4 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-lg backdrop-blur-sm whitespace-nowrap">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    <span class="text-sm text-zinc-700 dark:text-zinc-300 font-medium">
                        {{ count($selectedItems) }} {{ count($selectedItems) === 1 ? 'item' : 'items' }} selected
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @foreach($config['bulk_actions'] ?? [] as $action)
                    <flux:button 
                        variant="{{ $action['variant'] ?? 'outline' }}" 
                        wire:click="executeStackedListBulkAction('{{ $action['key'] }}')"
                        size="sm"
                        icon="{{ $action['icon'] ?? '' }}"
                        wire:key="bulk-action-{{ $action['key'] }}"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
                <flux:button variant="ghost" wire:click="clearStackedListSelection" size="sm">
                    Clear Selection
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Data List -->
    @if($data->isNotEmpty())
        <!-- Headers -->
        <div class="hidden md:flex items-center gap-4 pb-4 border-b border-zinc-200 dark:border-zinc-700">
            <!-- Bulk Selection Header -->
            @if(!empty($config['bulk_actions']))
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        wire:model.live="stackedListSelectAll"
                        class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                    />
                </div>
            @endif
            
            @foreach($config['columns'] ?? [] as $column)
                <div class="flex items-center gap-1 min-w-0 flex-1" wire:key="header-{{ $column['key'] }}">
                    @if(($column['sortable'] ?? false) && in_array($column['key'], data_get($config, 'sortable_columns', [])))
                        <button 
                            wire:click="stackedListSortColumn('{{ $column['key'] }}')"
                            wire:click.ctrl="stackedListSortColumn('{{ $column['key'] }}', true)"
                            wire:click.meta="stackedListSortColumn('{{ $column['key'] }}', true)"
                            class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 flex items-center gap-1"
                            title="Click to sort, Ctrl/Cmd+Click for multi-sort"
                        >
                            {{ $column['label'] }}
                            @php
                                $sortInfo = collect($sortStack)->firstWhere('column', $column['key']);
                                $isInStack = $sortInfo !== null;
                                $isCurrent = $sortBy === $column['key'];
                            @endphp
                            
                            @if($isInStack)
                                <div class="flex items-center">
                                    <flux:icon 
                                        name="{{ $sortInfo['direction'] === 'asc' ? 'chevron-up' : 'chevron-down' }}" 
                                        class="w-3 h-3" 
                                    />
                                    <span class="ml-1 text-xs bg-blue-500 text-white rounded-full w-4 h-4 flex items-center justify-center">
                                        {{ $sortInfo['priority'] }}
                                    </span>
                                </div>
                            @elseif($isCurrent && empty($sortStack))
                                <flux:icon 
                                    name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" 
                                    class="w-3 h-3" 
                                />
                            @endif
                        </button>
                    @else
                        <span class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ $column['label'] }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
        
        <!-- Data Rows -->
        <div class="space-y-6">
            @foreach($data as $item)
                <div class="flex flex-col md:flex-row md:items-center gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-6" wire:key="item-{{ $item->getKey() }}">
                    
                    <!-- Bulk Selection Checkbox -->
                    @if(!empty($config['bulk_actions']))
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                wire:model.live="stackedListSelectedItems"
                                value="{{ $item->getKey() }}"
                                class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500"
                            />
                        </div>
                    @endif

                    <!-- Columns -->
                    <div class="flex flex-col md:flex-row md:items-center gap-4 flex-1">
                        @foreach($config['columns'] ?? [] as $column)
                            <div class="flex items-center gap-3 min-w-0 flex-1 {{ $column['class'] ?? '' }}" wire:key="cell-{{ $item->getKey() }}-{{ $column['key'] }}">
                            @switch($column['type'] ?? 'text')
                                @case('text')
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 {{ $column['font'] ?? '' }}">
                                        {{ data_get($item, $column['key']) }}
                                    </div>
                                    @break
                                    
                                @case('badge')
                                    @php
                                        $value = data_get($item, $column['key']);
                                        $badgeConfig = data_get($column, "badges.{$value}", $column['badges']['default'] ?? []);
                                    @endphp
                                    <flux:badge 
                                        variant="outline" 
                                        class="text-xs {{ $badgeConfig['class'] ?? '' }}"
                                    >
                                        @if($icon = $badgeConfig['icon'] ?? null)
                                            <flux:icon name="{{ $icon }}" class="w-3 h-3 mr-1" />
                                        @endif
                                        {{ $badgeConfig['label'] ?? ucfirst($value) }}
                                    </flux:badge>
                                    @break
                                    
                                @case('actions')
                                    @if(isset($column['actions']) && is_array($column['actions']))
                                        <div class="flex items-center gap-1">
                                            @foreach($column['actions'] as $action)
                                                @if(isset($action['method']))
                                                    <flux:button 
                                                        size="sm" 
                                                        variant="{{ $action['variant'] ?? 'ghost' }}" 
                                                        icon="{{ $action['icon'] ?? '' }}"
                                                        wire:click="{{ $action['method'] }}({{ $item->id }})"
                                                        title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                    >
                                                        {{ $action['label'] ?? '' }}
                                                    </flux:button>
                                                @elseif(isset($action['route']))
                                                    <flux:button 
                                                        size="sm" 
                                                        variant="{{ $action['variant'] ?? 'ghost' }}" 
                                                        icon="{{ $action['icon'] ?? '' }}"
                                                        href="{{ route($action['route'], $item) }}"
                                                        wire:navigate="{{ $action['navigate'] ?? true }}"
                                                        title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                    >
                                                        {{ $action['label'] ?? '' }}
                                                    </flux:button>
                                                @elseif(isset($action['href']))
                                                    <flux:button 
                                                        size="sm" 
                                                        variant="{{ $action['variant'] ?? 'ghost' }}" 
                                                        icon="{{ $action['icon'] ?? '' }}"
                                                        href="{{ $action['href'] }}"
                                                        wire:navigate="{{ $action['navigate'] ?? true }}"
                                                        title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                    >
                                                        {{ $action['label'] ?? '' }}
                                                    </flux:button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                    @break
                                    
                                @default
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ data_get($item, $column['key']) }}
                                    </div>
                            @endswitch
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Pagination -->
        <div class="mt-6">
            {{ $data->links() }}
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <flux:icon name="inbox" class="w-16 h-16 text-slate-400 mx-auto mb-4" />
            <flux:heading size="lg" class="mb-2">{{ data_get($config, 'empty_title', 'No items found') }}</flux:heading>
            <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">{{ data_get($config, 'empty_description', 'No items to display.') }}</p>
            @if($emptyAction = data_get($config, 'empty_action'))
                <flux:button 
                    variant="{{ $emptyAction['variant'] ?? 'primary' }}" 
                    icon="{{ $emptyAction['icon'] ?? 'plus' }}"
                    :href="$emptyAction['href'] ?? ''"
                >
                    {{ $emptyAction['label'] }}
                </flux:button>
            @endif
        </div>
    @endif
</div>