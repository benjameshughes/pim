@props([
    'searchable' => false,
    'filterable' => false,
    'title' => null,
    'subtitle' => null,
    'actions' => null,
])

<div x-data="{
    searchable: @js($searchable),
    filterable: @js($filterable)
}" x-init="
    // Inherit from parent table config if available
    if (typeof $el.closest('[data-table-config]') !== 'undefined') {
        let parentData = Alpine.$data($el.closest('[data-table-config]'));
        searchable = parentData.searchable;
        filterable = parentData.filterable;
    }
">
    {{-- Title Section --}}
    @if($title || $subtitle || $actions)
        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
            <div>
                @if($title)
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>
                @endif
                @if($subtitle)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>
                @endif
            </div>
            
            @if($actions)
                <div class="flex items-center gap-3">
                    {{ $actions }}
                </div>
            @endif
        </div>
    @endif
    
    {{-- Search & Filters Bar --}}
    <div x-show="searchable || (filterable && filters && filters.length > 0)" 
         class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-4">
        
        {{-- Search Bar --}}
        <div x-show="searchable" class="relative">
            <flux:icon name="magnifying-glass" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input x-model="searchQuery" 
                   type="text" 
                   placeholder="Search table..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        
        {{-- Smart Filters --}}
        <div x-show="filterable && filters && filters.length > 0" 
             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <template x-for="filter in filters" :key="filter.key">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-gray-700 dark:text-gray-300" x-text="filter.label"></label>
                    <select x-model="activeFilters[filter.key]"
                            class="text-sm border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All</option>
                        <template x-for="option in filter.options" :key="option">
                            <option :value="option" x-text="option"></option>
                        </template>
                    </select>
                </div>
            </template>
        </div>
    </div>
    
    {{-- Custom Header Content --}}
    @if($slot->isNotEmpty())
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            {{ $slot }}
        </div>
    @endif
    
    {{-- Table Header Row --}}
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
                    <template x-if="$store.table && $store.table.selectable">
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