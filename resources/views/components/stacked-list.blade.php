@php
    // Get the table configuration and data
    // Variables passed from Table->toHtml() method
    $config = $table->toArray();
    
    $selectedItems = $livewire->stackedListSelectedItems ?? [];
    $search = $livewire->stackedListSearch ?? '';
    $filters = $livewire->stackedListFilters ?? [];
    $perPage = $livewire->stackedListPerPage ?? 10;
    $sortBy = $livewire->stackedListSortBy ?? '';
    $sortDirection = $livewire->stackedListSortDirection ?? 'asc';
    $sortStack = $livewire->stackedListSortStack ?? [];
    $selectAll = $livewire->stackedListSelectAll ?? false;
    $parentProductsOnly = $livewire->parentProductsOnly ?? false;
@endphp

<div class="space-y-6">
    <!-- 1. Title/Subtitle Section - Clean, typography focused -->
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-50">
                {{ data_get($config, 'title', 'Items') }}
            </flux:heading>
            @if($subtitle = data_get($config, 'subtitle'))
                <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                    {{ $subtitle }}
                </flux:subheading>
            @endif
        </div>

        @if(!empty($config['header_actions']))
            <div class="flex items-center gap-3">
                @foreach($config['header_actions'] as $action)
                    <flux:button
                            variant="{{ $action['variant'] ?? 'primary' }}"
                            icon="{{ $action['icon'] ?? '' }}"
                            :href="$action['href'] ?? ''"
                            wire:navigate="{{ $action['navigate'] ?? true }}"
                            class="shadow-sm hover:shadow-md transition-shadow duration-150"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach
            </div>
        @endif
    </div>

    @php
        $hasSearchOrFilters = !empty($config['searchable']) || !empty($config['filters']) || isset($parentProductsOnly);
        $hasControls = $hasSearchOrFilters || data_get($config, 'export', false) || $search || collect($filters)->filter()->isNotEmpty() || !empty($sortStack);
    @endphp

    @if($hasControls)
        <!-- Search & Filters Controls Section -->
        <div class="bg-zinc-50/30 dark:bg-zinc-800/20 rounded-lg border-0 p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                @if($hasSearchOrFilters)
                    <!-- Search and Filters -->
                    <div class="flex flex-col gap-4 md:flex-row md:items-center">
                        <!-- Search -->
                        @if(!empty($config['searchable']))
                            <div class="relative">
                                <flux:input
                                        wire:model.live.debounce.300ms="stackedListSearch"
                                        type="search"
                                        placeholder="{{ data_get($config, 'search_placeholder', 'Search...') }}"
                                        class="w-full md:w-96 pl-10 bg-transparent dark:bg-transparent border-zinc-300/50 dark:border-zinc-600/50 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-1 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:bg-white/50 dark:focus:bg-zinc-800/50 transition-all duration-200"
                                />
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <flux:icon name="magnifying-glass" class="w-4 h-4 text-zinc-400 dark:text-zinc-500" />
                                </div>
                            </div>
                        @endif

                        <!-- Filters -->
                        @if(!empty($config['filters']))
                            <div class="flex gap-3 flex-wrap">
                                @foreach($config['filters'] as $key => $filter)
                                    @if($filter['type'] === 'select')
                                        <flux:select
                                                wire:model.live="stackedListFilters.{{ $key }}"
                                                placeholder="{{ $filter['placeholder'] ?? 'Filter by ' . $filter['label'] }}"
                                                class="w-full md:w-48 bg-transparent dark:bg-transparent border-zinc-300/50 dark:border-zinc-600/50 focus:border-blue-500 dark:focus:border-blue-400 focus:ring-1 focus:ring-blue-500/20 dark:focus:ring-blue-400/20 focus:bg-white/50 dark:focus:bg-zinc-800/50 transition-all duration-200"
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
                            <div class="flex items-center gap-2 px-3 py-2.5 bg-transparent dark:bg-transparent rounded-lg border border-zinc-300/30 dark:border-zinc-600/30 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/30 transition-colors duration-150">
                                <flux:checkbox wire:model.live="parentProductsOnly" id="parentProductsOnly" class="rounded-md" />
                                <label for="parentProductsOnly" class="text-sm font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer">Parent products only</label>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Per Page and Actions -->
                <div class="flex items-center gap-3">
                    <!-- Per Page Selector -->
                    <div class="flex items-center gap-3 px-3 py-2.5 bg-transparent dark:bg-transparent rounded-lg border border-zinc-300/30 dark:border-zinc-600/30 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/30 transition-colors duration-150">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">Show:</span>
                        <flux:select wire:model.live="stackedListPerPage" class="w-20 border-0 bg-transparent focus:ring-0 focus:border-0 text-sm font-medium">
                            @foreach(data_get($config, 'per_page_options', [15 => '15', 25 => '25', 50 => '50', 100 => '100']) as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <!-- Export Options -->
                    @if(data_get($config, 'export', false))
                        <div class="flex items-center gap-2">
                            <flux:button variant="outline" wire:click="exportStackedListData('csv')" size="sm" icon="download" class="bg-transparent dark:bg-transparent border-zinc-300/50 dark:border-zinc-600/50 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/30 transition-all duration-200">CSV</flux:button>
                            <flux:button variant="outline" wire:click="showStackedListExportModal" size="sm" icon="download" class="bg-transparent dark:bg-transparent border-zinc-300/50 dark:border-zinc-600/50 hover:bg-zinc-100/50 dark:hover:bg-zinc-800/30 transition-all duration-200">Export</flux:button>
                        </div>
                    @endif

                    <!-- Clear Filters Button -->
                    @if($search || collect($filters)->filter()->isNotEmpty() || !empty($sortStack))
                        <flux:button variant="ghost" wire:click="clearStackedListFilters" size="sm" icon="x" class="text-zinc-500 dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 border border-transparent hover:border-red-200 dark:hover:border-red-800 transition-all duration-200">Clear All</flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- 3. Multi-Sort Display - Separate notification element -->
    @if(!empty($sortStack))
        <div class="bg-blue-50 dark:bg-blue-950/50 border border-blue-200 dark:border-blue-800 rounded-xl p-4">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    <flux:icon name="sort-asc" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    <span class="text-sm text-blue-700 dark:text-blue-300 font-semibold">Multi-Sort Active:</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap flex-1">
                    @foreach($sortStack as $sort)
                        <div class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 rounded-lg border border-blue-200 dark:border-blue-700 text-xs font-medium">
                            {{ $sort['priority'] }}. {{ ucwords(str_replace('_', ' ', $sort['column'])) }}
                            <flux:icon name="{{ $sort['direction'] === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                            <button
                                    wire:click="removeStackedListSortColumn('{{ $sort['column'] }}')"
                                    class="ml-1 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors"
                                    title="Remove this sort">
                                <flux:icon name="x" class="w-3 h-3" />
                            </button>
                        </div>
                    @endforeach
                </div>
                <flux:button
                        variant="ghost"
                        wire:click="clearAllStackedListSorts"
                        size="sm"
                        class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300
                           hover:bg-red-100 dark:hover:bg-red-900/20
                           border border-transparent hover:border-red-200 dark:hover:border-red-800
                           transition-all duration-200">
                    Clear All Sorts
                </flux:button>
            </div>
        </div>
    @endif

    @if(!empty($config['bulk_actions']))
        <!-- Premium Floating Bulk Actions Bar -->
        <div class="fixed bottom-6 left-1/2 transform -translate-x-1/2 z-50 w-auto max-w-7xl mx-auto px-4 transition-all duration-300 ease-out {{ !empty($selectedItems) ? 'translate-y-0 opacity-100' : 'translate-y-full opacity-0 pointer-events-none' }}"
             role="toolbar" aria-label="Bulk actions">
        <!-- Enhanced floating bar with premium styling -->
        <div class="relative flex items-center justify-between min-w-96
                    bg-white/95 dark:bg-zinc-900/95
                    backdrop-blur-xl backdrop-saturate-150
                    rounded-2xl
                    border border-zinc-200/80 dark:border-zinc-700/80
                    shadow-xl shadow-zinc-900/10 dark:shadow-black/20
                    ring-1 ring-zinc-950/5 dark:ring-white/10
                    px-6 py-4
                    before:absolute before:inset-0 before:rounded-2xl
                    before:bg-gradient-to-r before:from-white/40 before:via-transparent before:to-white/40
                    dark:before:from-zinc-800/40 dark:before:via-transparent dark:before:to-zinc-800/40
                    before:pointer-events-none">

            <!-- Selection Status with Visual Connection -->
            <div class="flex items-center gap-4">
                <!-- Selection indicator with animated pulse -->
                <div class="flex items-center gap-3">
                    <div class="relative flex items-center justify-center w-8 h-8
                                bg-blue-100 dark:bg-blue-900/50
                                rounded-full
                                ring-2 ring-blue-200 dark:ring-blue-800">
                        <flux:icon name="check-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        <!-- Subtle pulse animation for selected state -->
                        <div class="absolute inset-0 rounded-full bg-blue-400/20 animate-pulse"></div>
                    </div>

                    <!-- Selection text with hierarchy -->
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50 leading-tight">
                            {{ count($selectedItems) }} {{ count($selectedItems) === 1 ? 'item' : 'items' }} selected
                        </span>
                        @if(count($selectedItems) > 1)
                            <span class="text-xs text-zinc-500 dark:text-zinc-400 leading-tight">
                                Ready for bulk operations
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Visual separator -->
                <div class="h-8 w-px bg-gradient-to-b from-transparent via-zinc-300 dark:via-zinc-600 to-transparent"></div>
            </div>

            <!-- Action Buttons with Enhanced Styling -->
            <div class="flex items-center gap-2">
                @foreach($config['bulk_actions'] ?? [] as $action)
                    <flux:button
                            variant="{{ $action['variant'] ?? 'outline' }}"
                            wire:click="executeStackedListBulkAction('{{ $action['key'] }}')"
                            size="sm"
                            icon="{{ $action['icon'] ?? '' }}"
                            wire:key="bulk-action-{{ $action['key'] }}"
                            class="shadow-sm hover:shadow-md transition-shadow duration-150
                               {{ ($action['variant'] ?? 'outline') === 'primary' ?
                                  'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20 hover:shadow-blue-600/30' :
                                  'bg-white dark:bg-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}"
                    >
                        {{ $action['label'] }}
                    </flux:button>
                @endforeach

                <!-- Clear selection with subtle styling -->
                <button wire:click="clearStackedListSelection"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg
                               text-zinc-600 dark:text-zinc-400
                               hover:text-zinc-900 dark:hover:text-zinc-100
                               hover:bg-zinc-100 dark:hover:bg-zinc-800
                               focus:outline-none focus:ring-2 focus:ring-zinc-500/20 dark:focus:ring-zinc-400/20
                               transition-all duration-150"
                        title="Clear selection">
                    <flux:icon name="x" class="w-4 h-4" />
                    <span class="ml-1.5 hidden sm:inline">Clear</span>
                </button>
            </div>

            <!-- Subtle connection line to selected items -->
            <div class="absolute -top-2 left-1/2 transform -translate-x-1/2 w-1 h-2
                        bg-gradient-to-t from-blue-400/30 to-transparent rounded-full"></div>
        </div>

        <!-- Mobile-optimized compact version -->
        <div class="sm:hidden relative flex items-center justify-between w-full max-w-sm
                    bg-white/95 dark:bg-zinc-900/95
                    backdrop-blur-xl backdrop-saturate-150
                    rounded-2xl
                    border border-zinc-200/80 dark:border-zinc-700/80
                    shadow-xl shadow-zinc-900/10 dark:shadow-black/20
                    ring-1 ring-zinc-950/5 dark:ring-white/10
                    px-4 py-3">

            <!-- Compact selection indicator -->
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-7 h-7
                            bg-blue-100 dark:bg-blue-900/50
                            rounded-full
                            ring-2 ring-blue-200 dark:ring-blue-800">
                    <flux:icon name="check-circle" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                </div>
                <span class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                    {{ count($selectedItems) }}
                </span>
            </div>

            <!-- Compact actions -->
            <div class="flex items-center gap-1">
                @foreach($config['bulk_actions'] ?? [] as $action)
                    <button wire:click="executeStackedListBulkAction('{{ $action['key'] }}')"
                            wire:key="bulk-action-mobile-{{ $action['key'] }}"
                            class="inline-flex items-center justify-center w-9 h-9
                                   text-sm font-medium rounded-xl
                                   {{ ($action['variant'] ?? 'outline') === 'primary' ?
                                      'bg-blue-600 hover:bg-blue-700 text-white' :
                                      'bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 text-zinc-700 dark:text-zinc-300' }}
                                   transition-colors duration-150"
                            title="{{ $action['label'] }}">
                        @if($action['icon'] ?? null)
                            <flux:icon name="{{ $action['icon'] }}" class="w-4 h-4" />
                        @else
                            {{ substr($action['label'], 0, 1) }}
                        @endif
                    </button>
                @endforeach

                <button wire:click="clearStackedListSelection"
                        class="inline-flex items-center justify-center w-9 h-9
                               text-zinc-600 dark:text-zinc-400
                               hover:text-zinc-900 dark:hover:text-zinc-100
                               hover:bg-zinc-100 dark:hover:bg-zinc-800
                               rounded-xl transition-colors duration-150"
                        title="Clear selection">
                    <flux:icon name="x" class="w-4 h-4" />
                </button>
            </div>
        </div>
    @endif

    <!-- Data List -->
    @if($data->isNotEmpty())
        <!-- Connected Table Container -->
        <div class="bg-white dark:bg-zinc-900
                    rounded-xl border border-zinc-200 dark:border-zinc-700
                    shadow-sm overflow-hidden">

            <!-- Enhanced Headers -->
            <div class="hidden md:flex items-center gap-4 px-4 py-3
                        bg-zinc-50/80 dark:bg-zinc-900/80
                        border-b border-zinc-200/80 dark:border-zinc-700/80
                        backdrop-blur-sm">
                <!-- Bulk Selection Header -->
                @if(!empty($config['bulk_actions']))
                    <div class="flex items-center justify-center w-10">
                        <flux:checkbox
                                wire:model.live="stackedListSelectAll"
                                class="rounded-md"
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
                                    class="group inline-flex items-center gap-1.5 px-2 py-1 -mx-2 -my-1 rounded-md
                                       text-xs font-semibold uppercase tracking-wider
                                       text-zinc-600 dark:text-zinc-400
                                       hover:text-zinc-900 dark:hover:text-zinc-100
                                       hover:bg-zinc-100/60 dark:hover:bg-zinc-800/60
                                       focus:outline-none focus:bg-zinc-100 dark:focus:bg-zinc-800
                                       transition-all duration-150"
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
                            <span class="px-2 py-1 text-xs font-semibold uppercase tracking-wider
                                       text-zinc-600 dark:text-zinc-400">
                                {{ $column['label'] }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            <!-- Premium Data Rows -->
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach($data as $item)
                    <div class="group relative flex flex-col md:flex-row md:items-center gap-3 px-4 py-4
                           transition-all duration-200 ease-out
                           hover:bg-gradient-to-r hover:from-zinc-50/80 hover:to-zinc-25/50
                           dark:hover:from-zinc-800/50 dark:hover:to-zinc-900/80
                           hover:shadow-sm hover:-translate-y-px
                           focus-within:bg-blue-50/30 dark:focus-within:bg-blue-950/30
                           focus-within:ring-2 focus-within:ring-blue-500/10 dark:focus-within:ring-blue-400/10
                           focus-within:border-blue-200 dark:focus-within:border-blue-800"
                         wire:key="item-{{ $item->getKey() }}">

                        <!-- Bulk Selection Checkbox -->
                        @if(!empty($config['bulk_actions']))
                            <div class="flex items-center justify-center w-5 h-5 shrink-0">
                                <flux:checkbox
                                        wire:model.live="stackedListSelectedItems"
                                        value="{{ $item->getKey() }}"
                                        class="rounded-md"
                                />
                            </div>
                        @endif

                        <!-- Columns -->
                        <div class="flex flex-col md:flex-row md:items-center gap-3 flex-1 min-w-0">
                            @foreach($config['columns'] ?? [] as $column)
                                <div class="flex items-center gap-2 min-w-0 flex-1 {{ $column['class'] ?? '' }}" wire:key="cell-{{ $item->getKey() }}-{{ $column['key'] }}">
                                    @switch($column['type'] ?? 'text')
                                        @case('text')
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-50
                                              truncate {{ $column['font'] ?? '' }}">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                            @break

                                        @case('badge')
                                            @php
                                                $value = data_get($item, $column['key']);
                                                $badgeConfig = data_get($column, "badges.{$value}", $column['badges']['default'] ?? []);
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium
                                               border transition-colors duration-150
                                               {{ $badgeConfig['class'] ?? 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700' }}">
                                        @if($icon = $badgeConfig['icon'] ?? null)
                                                    <flux:icon name="{{ $icon }}" class="w-3 h-3 mr-1 shrink-0" />
                                                @endif
                                                {{ $badgeConfig['label'] ?? ucfirst($value) }}
                                    </span>
                                            @break

                                        @case('actions')
                                            @if(isset($column['actions']) && is_array($column['actions']))
                                                <div class="flex items-center gap-1 opacity-60 group-hover:opacity-100 transition-opacity duration-150">
                                                    @foreach($column['actions'] as $action)
                                                        @if(isset($action['method']))
                                                            <button
                                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                                                               text-zinc-600 dark:text-zinc-400
                                                               hover:text-zinc-900 dark:hover:text-zinc-100
                                                               hover:bg-zinc-100 dark:hover:bg-zinc-800
                                                               focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20
                                                               transition-all duration-150 {{ $action['class'] ?? '' }}"
                                                                    wire:click="{{ $action['method'] }}({{ $item->id }})"
                                                                    title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                            >
                                                                @if($action['icon'] ?? null)
                                                                    <flux:icon name="{{ $action['icon'] }}" class="w-4 h-4 {{ $action['label'] ? 'mr-1' : '' }}" />
                                                                @endif
                                                                {{ $action['label'] ?? '' }}
                                                            </button>
                                                        @elseif(isset($action['route']))
                                                            <a
                                                                    href="{{ route($action['route'], $item) }}"
                                                                    @if($action['navigate'] ?? true) wire:navigate @endif
                                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                                                               text-zinc-600 dark:text-zinc-400
                                                               hover:text-zinc-900 dark:hover:text-zinc-100
                                                               hover:bg-zinc-100 dark:hover:bg-zinc-800
                                                               focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20
                                                               transition-all duration-150 {{ $action['class'] ?? '' }}"
                                                                    title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                            >
                                                                @if($action['icon'] ?? null)
                                                                    <flux:icon name="{{ $action['icon'] }}" class="w-4 h-4 {{ $action['label'] ? 'mr-1' : '' }}" />
                                                                @endif
                                                                {{ $action['label'] ?? '' }}
                                                            </a>
                                                        @elseif(isset($action['href']))
                                                            <a
                                                                    href="{{ $action['href'] }}"
                                                                    @if($action['navigate'] ?? true) wire:navigate @endif
                                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                                                               text-zinc-600 dark:text-zinc-400
                                                               hover:text-zinc-900 dark:hover:text-zinc-100
                                                               hover:bg-zinc-100 dark:hover:bg-zinc-800
                                                               focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20
                                                               transition-all duration-150 {{ $action['class'] ?? '' }}"
                                                                    title="{{ $action['title'] ?? $action['label'] ?? '' }}"
                                                            >
                                                                @if($action['icon'] ?? null)
                                                                    <flux:icon name="{{ $action['icon'] }}" class="w-4 h-4 {{ $action['label'] ? 'mr-1' : '' }}" />
                                                                @endif
                                                                {{ $action['label'] ?? '' }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                            @break

                                        @default
                                            <div class="text-sm text-zinc-600 dark:text-zinc-400 truncate">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                    @endswitch
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
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
            @if(!empty($config['empty_action']))
                <flux:button
                        variant="{{ $config['empty_action']['variant'] ?? 'primary' }}"
                        icon="{{ $config['empty_action']['icon'] ?? 'plus' }}"
                        :href="$config['empty_action']['href'] ?? ''"
                >
                    {{ $config['empty_action']['label'] }}
                </flux:button>
            @endif
        </div>
    @endif
</div>