{{-- ✨ COMPOSED HEADER - Uses specialized sub-components ✨ --}}
@props([
    'title' => null,
    'subtitle' => null,
    'actions' => null,           // flux:table.actions props or content
    'search' => null,            // flux:table.search props or content  
    'filters' => null,           // flux:table.filters props or content
    'perPage' => null,           // flux:table.per-page props or content
    'layout' => 'stacked',       // stacked, inline, custom
])

<div x-data="{
    hasTitle: @js(!empty($title) || !empty($subtitle)),
    hasActions: @js(!empty($actions)),
    hasSearch: @js(!empty($search)), 
    hasFilters: @js(!empty($filters)),
    hasPerPage: @js(!empty($perPage))
}">

    {{-- ✨ TITLE & ACTIONS ROW ✨ --}}
    <div x-show="hasTitle || hasActions || $refs.titleSlot?.children.length" 
         class="flex items-start justify-between p-6 border-b border-gray-200 dark:border-gray-700">
        
        {{-- Title Section --}}
        <div class="flex-1">
            @if($title)
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
            @endif
            @if($subtitle)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>
            @endif
            
            {{-- Custom title slot --}}
            <div x-ref="titleSlot" class="mt-2">
                {{ $title ?? '' }}
            </div>
        </div>
        
        {{-- Actions Section --}}
        @if($actions)
            <div class="flex-shrink-0 ml-6">
                @if(is_array($actions))
                    <flux:table.actions 
                        :primary="$actions['primary'] ?? null"
                        :secondary="$actions['secondary'] ?? []"
                        :align="$actions['align'] ?? 'right'"
                        :gap="$actions['gap'] ?? 'normal'"
                        :size="$actions['size'] ?? 'normal'"
                    />
                @else
                    {{ $actions }}
                @endif
            </div>
        @endif
        
        {{-- Actions slot --}}
        @isset($actions)
            <div class="flex-shrink-0 ml-6">
                {{ $actions }}
            </div>
        @endisset
    </div>
    
    {{-- ✨ SEARCH, FILTERS & OPTIONS ROW ✨ --}}
    <div x-show="hasSearch || hasFilters || hasPerPage || $refs.controlsSlot?.children.length"
         class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-4">
        
        @if($layout === 'stacked')
            {{-- Stacked Layout: Each component on its own row --}}
            <div class="space-y-4">
                
                {{-- Search Row --}}
                @if($search)
                    <div class="flex justify-center">
                        @if(is_array($search))
                            <flux:table.search 
                                :placeholder="$search['placeholder'] ?? 'Search table...'"
                                :clearable="$search['clearable'] ?? true"
                                :size="$search['size'] ?? 'normal'"
                                :width="$search['width'] ?? 'lg'"
                                :shortcuts="$search['shortcuts'] ?? false"
                            />
                        @else
                            {{ $search }}
                        @endif
                    </div>
                @endif
                
                {{-- Filters Row --}}
                @if($filters)
                    <div>
                        @if(is_array($filters))
                            <flux:table.filters 
                                :layout="$filters['layout'] ?? 'grid'"
                                :columns="$filters['columns'] ?? 'auto'"
                                :collapsible="$filters['collapsible'] ?? false"
                                :show-count="$filters['showCount'] ?? true"
                                :clearable="$filters['clearable'] ?? true"
                            />
                        @else
                            {{ $filters }}
                        @endif
                    </div>
                @endif
                
                {{-- Per Page Row --}}
                @if($perPage)
                    <div class="flex justify-end">
                        @if(is_array($perPage))
                            <flux:table.per-page 
                                :options="$perPage['options'] ?? [10, 25, 50, 100]"
                                :current="$perPage['current'] ?? 15"
                                :label="$perPage['label'] ?? 'Show'"
                                :suffix="$perPage['suffix'] ?? 'per page'"
                                :size="$perPage['size'] ?? 'normal'"
                            />
                        @else
                            {{ $perPage }}
                        @endif
                    </div>
                @endif
                
            </div>
            
        @elseif($layout === 'inline')
            {{-- Inline Layout: All components in one row --}}
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center">
                
                {{-- Search --}}
                @if($search)
                    <div class="flex-1">
                        @if(is_array($search))
                            <flux:table.search 
                                :placeholder="$search['placeholder'] ?? 'Search...'"
                                :size="$search['size'] ?? 'normal'"
                                :width="$search['width'] ?? 'full'"
                            />
                        @else
                            {{ $search }}
                        @endif
                    </div>
                @endif
                
                {{-- Filters --}}
                @if($filters)
                    <div class="flex-shrink-0">
                        @if(is_array($filters))
                            <flux:table.filters 
                                :layout="$filters['layout'] ?? 'inline'"
                                :compact="true"
                            />
                        @else
                            {{ $filters }}
                        @endif
                    </div>
                @endif
                
                {{-- Per Page --}}
                @if($perPage)
                    <div class="flex-shrink-0">
                        @if(is_array($perPage))
                            <flux:table.per-page 
                                :options="$perPage['options'] ?? [10, 25, 50]"
                                :current="$perPage['current'] ?? 15"
                                :size="$perPage['size'] ?? 'sm'"
                            />
                        @else
                            {{ $perPage }}
                        @endif
                    </div>
                @endif
                
            </div>
            
        @else
            {{-- Custom Layout: Use slot content --}}
            <div x-ref="controlsSlot">
                {{ $slot }}
            </div>
        @endif
        
    </div>
    
    {{-- ✨ TABLE HEADER ROW ✨ --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead x-data="{
                headerClasses: '',
                cellPadding: ''
            }" x-init="
                // Get styles from parent table config
                if (typeof $el.closest('[data-table-config]') !== 'undefined') {
                    let parentData = Alpine.$data($el.closest('[data-table-config]'));
                    
                    // Apply theme-based header classes  
                    switch (parentData.theme) {
                        case 'glass':
                            headerClasses = 'bg-white/10 dark:bg-gray-900/20';
                            break;
                        case 'neon':
                            headerClasses = 'bg-gray-800 border-b border-emerald-400/30';
                            break;
                        case 'minimal':
                            headerClasses = 'bg-gray-25 dark:bg-gray-800';
                            break;
                        case 'phoenix':
                            headerClasses = 'bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-950 dark:to-red-950';
                            break;
                        default:
                            headerClasses = 'bg-gray-50 dark:bg-gray-900/50';
                    }
                    
                    cellPadding = parentData.cellPadding;
                }
            " :class="headerClasses">
                <tr>
                    <template x-if="selectable">
                        <th :class="cellPadding + ' text-left'">
                            <input type="checkbox" 
                                   x-on:click="selectAllRows()"
                                   :checked="selectedRows.length === filteredData.length && filteredData.length > 0"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                    </template>
                    
                    <template x-for="column in columns" :key="column.key">
                        <th :class="cellPadding + ' text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider ' + (sortable ? 'cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 transition-colors' : '')"
                            x-on:click="sort(column.key)">
                            <div class="flex items-center gap-2">
                                <span x-text="column.label"></span>
                                <template x-if="sortable && sortColumn === column.key">
                                    <div class="w-4 h-4 flex items-center justify-center">
                                        <svg x-show="sortDirection === 'asc'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                        </svg>
                                        <svg x-show="sortDirection === 'desc'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </template>
                            </div>
                        </th>
                    </template>
                </tr>
            </thead>
        </table>
    </div>
    
</div>