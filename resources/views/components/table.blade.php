@php
    $config = $table->toArray();
@endphp

<div class="space-y-6">
    {{-- Header Section --}}
    @if($config['title'])
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ $config['title'] }}
                </h2>
                @if($config['subtitle'])
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $config['subtitle'] }}
                    </p>
                @endif
            </div>
            
            {{-- Header Actions would go here --}}
            @if(!empty($config['headerActions']))
                <div class="flex items-center space-x-3">
                    {{-- TODO: Render header actions --}}
                </div>
            @endif
        </div>
    @endif

    {{-- Controls Section --}}
    @if(!empty($config['searchable']) || !empty($config['filters']) || count($config['paginationPageOptions']) > 1)
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                {{-- Search & Filters --}}
                <div class="flex flex-col gap-4 md:flex-row md:items-center">
                    {{-- Search --}}
                    @if(!empty($config['searchable']))
                        <div class="relative">
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="tableSearch"
                                placeholder="Search..."
                                class="w-full md:w-96 pl-10 pr-4 py-2 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>
                    @endif

                    {{-- Filters would go here --}}
                    @if(!empty($config['filters']))
                        <div class="flex gap-3 flex-wrap">
                            {{-- TODO: Render filters --}}
                            <div class="text-xs text-gray-500">Filters: {{ count($config['filters']) }}</div>
                        </div>
                    @endif
                </div>

                {{-- Per Page & Actions --}}
                <div class="flex items-center gap-3">
                    {{-- Per Page Selector --}}
                    @if(count($config['paginationPageOptions']) > 1)
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Show:</span>
                            <select 
                                wire:model.live="tableRecordsPerPage"
                                class="border border-gray-300 dark:border-gray-600 rounded text-sm bg-white dark:bg-gray-900"
                            >
                                @foreach($config['paginationPageOptions'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Clear Search/Filters --}}
                    @if($config['currentSearch'] || !empty($config['currentFilters']))
                        <button 
                            wire:click="resetTable"
                            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            Clear All
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Bulk Actions Bar (when items selected) --}}
    @if(!empty($config['selectedRecords']))
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                    {{ count($config['selectedRecords']) }} items selected
                </span>
                <div class="flex items-center space-x-2">
                    {{-- TODO: Render bulk actions --}}
                    <button 
                        wire:click="clearSelectedTableRecords"
                        class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200"
                    >
                        Clear Selection
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Data Table --}}
    @if($data->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            {{-- Table Headers --}}
            <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-3 border-b border-gray-200 dark:border-gray-600">
                <div class="flex items-center space-x-4">
                    {{-- Bulk Selection Header --}}
                    @if(!empty($config['bulkActions']))
                        <div class="w-4">
                            <input 
                                type="checkbox" 
                                wire:model="selectAllTableRecords"
                                class="rounded border-gray-300 dark:border-gray-600"
                            />
                        </div>
                    @endif

                    @foreach($config['columns'] as $column)
                        <div class="flex-1 text-left">
                            @if($column['sortable'] ?? false)
                                <button 
                                    wire:click="sortTableBy('{{ $column['key'] }}')"
                                    class="group inline-flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider hover:text-gray-700 dark:hover:text-gray-100"
                                >
                                    {{ $column['label'] ?? $column['key'] }}
                                    
                                    {{-- Sort Icons --}}
                                    @if($config['currentSortColumn'] === $column['key'])
                                        @if($config['currentSortDirection'] === 'asc')
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                            </svg>
                                        @else
                                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                                            </svg>
                                        @endif
                                    @else
                                        <svg class="w-3 h-3 opacity-0 group-hover:opacity-50" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z"/>
                                        </svg>
                                    @endif
                                </button>
                            @else
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    {{ $column['label'] ?? $column['key'] }}
                                </span>
                            @endif
                        </div>
                    @endforeach

                    {{-- Actions Column --}}
                    @if(!empty($config['actions']))
                        <div class="w-20 text-right">
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Table Rows --}}
            <div class="divide-y divide-gray-200 dark:divide-gray-600">
                @foreach($data as $item)
                    <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                        <div class="flex items-center space-x-4">
                            {{-- Bulk Selection --}}
                            @if(!empty($config['bulkActions']))
                                <div class="w-4">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectedTableRecords"
                                        value="{{ $item->getKey() }}"
                                        class="rounded border-gray-300 dark:border-gray-600"
                                    />
                                </div>
                            @endif

                            @foreach($config['columns'] as $column)
                                <div class="flex-1 {{ $column['class'] ?? '' }}">
                                    @switch($column['type'] ?? 'text')
                                        @case('text')
                                            <div class="text-sm text-gray-900 dark:text-gray-100 {{ $column['font'] ?? '' }}">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                            @break

                                        @case('badge')
                                            @php
                                                $value = data_get($item, $column['key']);
                                                $badgeConfig = $column['badges'][$value] ?? ['class' => 'bg-gray-100 text-gray-800', 'label' => $value];
                                                if (is_string($badgeConfig)) {
                                                    $badgeConfig = ['class' => $badgeConfig, 'label' => $value];
                                                }
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badgeConfig['class'] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ $badgeConfig['label'] ?? $value }}
                                            </span>
                                            @break

                                        @default
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ data_get($item, $column['key']) }}
                                            </div>
                                    @endswitch
                                </div>
                            @endforeach

                            {{-- Row Actions --}}
                            @if(!empty($config['actions']))
                                <div class="w-20 text-right">
                                    <div class="flex items-center justify-end space-x-1">
                                        {{-- TODO: Render row actions --}}
                                        <span class="text-xs text-gray-400">Actions</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $data->links() }}
        </div>
    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <div class="text-6xl mb-4">{{ $config['emptyStateIcon'] }}</div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
                {{ $config['emptyStateHeading'] }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                {{ $config['emptyStateDescription'] }}
            </p>
            {{-- TODO: Empty state actions --}}
        </div>
    @endif
</div>