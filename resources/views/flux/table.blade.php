@props([
    'data' => [],                     // Table data (Collection, Eloquent Collection, or Array)
    'paginate' => null,               // Alternative data source for paginated results
    'columns' => [],                  // Column definitions (optional - auto-detects if empty)
    'theme' => 'modern',              // modern, glass, neon, minimal, phoenix
    'size' => 'normal',               // compact, normal, spacious
    'selectable' => false,            // Enable row selection
    'sortable' => true,               // Enable column sorting
    'searchable' => false,            // Enable search functionality
    'filterable' => false,            // Enable dynamic filtering
    'filters' => [],                  // Available filters (auto-detects if empty)
    'loading' => false,               // Show loading state
    'emptyMessage' => 'No data found', // Empty state message
    'emptyIcon' => 'table-cells',     // Empty state icon
    'striped' => false,               // Zebra striping
    'hoverable' => true,              // Hover effects
    'bordered' => false,              // Table borders
    'animation' => 'fade',            // fade, slide, bounce, glitter
    'glitterIntensity' => 'medium',   // none, low, medium, high, maximum
    'autoColumns' => true,            // Auto-detect columns from data
    'relationships' => true,          // Support dot notation for relationships
    'bulkActions' => [],              // Bulk action definitions
    'showBulkActions' => false,       // Show bulk action toolbar
])

@php
// ✨ REACTIVE LIVEWIRE INTEGRATION - Read properties from component if available
if (isset($this)) {
    $hoverable = property_exists($this, 'canHover') ? $this->canHover : $hoverable;
    $filterable = property_exists($this, 'canFilter') ? $this->canFilter : $filterable;
    $sortable = property_exists($this, 'sortField') ? true : $sortable;
    $filters = (property_exists($this, 'filters') && method_exists($this, 'filters')) ? $this->filters() : $filters;
    $searchable = property_exists($this, 'search') ? true : $searchable;
}
@endphp

@php
$classes = Flux::classes()
    ->add('relative overflow-hidden w-full')
    ->add('rounded-xl shadow-sm border')
    ->add(match ($theme) {
        'glass' => 'backdrop-blur-xl bg-white/80 dark:bg-gray-900/80 border-white/20',
        'neon' => 'bg-gray-900 border-2 border-emerald-400 shadow-emerald-400/20 shadow-lg',
        'minimal' => 'bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700',
        'phoenix' => 'bg-gradient-to-br from-white to-gray-50 dark:from-gray-800 dark:to-gray-900 border-orange-200 dark:border-orange-800',
        default => 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'
    });

$tableClasses = Flux::classes()
    ->add('w-full table-fixed divide-y')
    ->add(match ($theme) {
        'glass' => 'divide-white/10',
        'neon' => 'divide-emerald-400/30', 
        'minimal' => 'divide-gray-100 dark:divide-gray-700',
        'phoenix' => 'divide-orange-100 dark:divide-orange-900',
        default => 'divide-gray-200 dark:divide-gray-700'
    });

$headerClasses = Flux::classes()
    ->add(match ($theme) {
        'glass' => 'bg-white/10 dark:bg-gray-900/20',
        'neon' => 'bg-gray-800 border-b border-emerald-400/30',
        'minimal' => 'bg-gray-25 dark:bg-gray-800',
        'phoenix' => 'bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-950 dark:to-red-950',
        default => 'bg-gray-50 dark:bg-gray-900/50'
    });

$rowClasses = match ($theme) {
    'glass' => 'bg-white/5 hover:bg-white/10 dark:bg-gray-900/5 dark:hover:bg-gray-900/10',
    'neon' => 'bg-gray-900 hover:bg-gray-800 border-emerald-400/20',
    'minimal' => 'bg-white hover:bg-gray-25 dark:bg-gray-800 dark:hover:bg-gray-700',
    'phoenix' => 'bg-white hover:bg-gradient-to-r hover:from-orange-25 hover:to-red-25 dark:bg-gray-800 dark:hover:from-orange-950 dark:hover:to-red-950',
    default => 'bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700'
};

$cellPadding = match ($size) {
    'compact' => 'px-3 py-2',
    'spacious' => 'px-8 py-6',  
    default => 'px-6 py-4'
};

// Animation configurations
$animationConfig = match ($animation) {
    'slide' => ['enter' => 'transform transition-all duration-300', 'start' => 'translate-y-4 opacity-0', 'end' => 'translate-y-0 opacity-100'],
    'bounce' => ['enter' => 'transform transition-all duration-500', 'start' => 'scale-95 opacity-0', 'end' => 'scale-100 opacity-100'],
    'glitter' => ['enter' => 'transform transition-all duration-700', 'start' => 'rotate-6 scale-75 opacity-0', 'end' => 'rotate-0 scale-100 opacity-100'],
    default => ['enter' => 'transition-opacity duration-300', 'start' => 'opacity-0', 'end' => 'opacity-100']
};

// Glitter intensity
$glitterConfig = match ($glitterIntensity) {
    'none' => ['sparkles' => 0, 'shine' => false],
    'low' => ['sparkles' => 2, 'shine' => false],
    'medium' => ['sparkles' => 4, 'shine' => true],
    'high' => ['sparkles' => 6, 'shine' => true],
    'maximum' => ['sparkles' => 8, 'shine' => true],
    default => ['sparkles' => 4, 'shine' => true]
};

// ✨ PHOENIX LARAVEL COLLECTION MAGIC ✨
// Use paginate prop if provided, otherwise use data prop
$sourceData = $paginate ?? $data;

// Handle Laravel Paginator objects (extract items if paginated)
$actualData = method_exists($sourceData, 'items') ? $sourceData->items() : $sourceData;

// Convert Laravel Collections to arrays while preserving relationships
$processedData = collect($actualData)->map(function ($item) use ($relationships) {
    // Handle Eloquent models
    if (is_object($item) && method_exists($item, 'toArray')) {
        $array = $item->toArray();
        
        // If relationships are enabled, add dot notation access
        if ($relationships && method_exists($item, 'getRelations')) {
            foreach ($item->getRelations() as $relationName => $relationData) {
                if (is_object($relationData)) {
                    if (method_exists($relationData, 'toArray')) {
                        // Single relationship (belongsTo, hasOne)
                        $relationArray = $relationData->toArray();
                        foreach ($relationArray as $key => $value) {
                            $array["{$relationName}.{$key}"] = $value;
                        }
                    }
                } elseif (is_array($relationData) || (is_object($relationData) && method_exists($relationData, 'toArray'))) {
                    // Collection relationship (hasMany, belongsToMany) - take first few for display
                    $relationCollection = collect($relationData)->take(3);
                    $array["{$relationName}_count"] = collect($relationData)->count();
                    $array["{$relationName}_preview"] = $relationCollection->pluck('name')->join(', ');
                }
            }
        }
        // Add actions field for action column
        $array['actions'] = $item->id ?? null;
        
        return $array;
    }
    
    // Handle arrays and other data
    $array = is_array($item) ? $item : (array) $item;
    // Add actions field for arrays too
    $array['actions'] = $array['id'] ?? null;
    return $array;
})->toArray();

// ✨ AUTO COLUMN DETECTION MAGIC ✨
if ($autoColumns && empty($columns) && !empty($processedData)) {
    $firstRow = reset($processedData);
    $detectedColumns = [];
    
    foreach (array_keys($firstRow) as $key) {
        // Skip certain fields from auto-detection
        if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'pivot'])) {
            continue;
        }
        
        // Smart label generation
        $label = str($key)
            ->replace(['_', '.'], ' ')
            ->title()
            ->replace(' Id', ' ID')
            ->value();
        
        // Determine if sortable based on data type
        $sampleValue = $firstRow[$key];
        $sortable = is_string($sampleValue) || is_numeric($sampleValue) || is_bool($sampleValue);
        
        $detectedColumns[] = [
            'key' => $key,
            'label' => $label,
            'sortable' => $sortable && $key !== 'actions'
        ];
    }
    
    // Limit auto-detected columns to prevent overwhelming tables
    $columns = array_slice($detectedColumns, 0, 8);
}

// ✨ AUTO FILTER DETECTION MAGIC ✨
if ($filterable && empty($filters) && !empty($processedData)) {
    $detectedFilters = [];
    
    foreach ($processedData as $row) {
        foreach ($row as $key => $value) {
            // Skip non-filterable fields
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at']) || str_contains($key, '.id')) {
                continue;
            }
            
            // Only create filters for categorical data
            if (is_string($value) && strlen($value) < 50) {
                if (!isset($detectedFilters[$key])) {
                    $detectedFilters[$key] = [
                        'key' => $key,
                        'label' => str($key)->replace(['_', '.'], ' ')->title()->value(),
                        'type' => 'select',
                        'options' => []
                    ];
                }
                
                // Collect unique values
                if (!in_array($value, $detectedFilters[$key]['options'])) {
                    $detectedFilters[$key]['options'][] = $value;
                }
            }
            
            // Boolean filters
            if (is_bool($value)) {
                $detectedFilters[$key] = [
                    'key' => $key,
                    'label' => str($key)->replace(['_', '.'], ' ')->title()->value(),
                    'type' => 'boolean',
                    'options' => ['true', 'false']
                ];
            }
        }
    }
    
    // Clean up filters with too many options or too few
    $filters = array_filter($detectedFilters, function($filter) {
        return count($filter['options']) >= 2 && count($filter['options']) <= 20;
    });
    
    // Limit to most useful filters
    $filters = array_slice($filters, 0, 5);
}
@endphp

<div {{ $attributes->class($classes) }}
     wire:key="table-{{ md5(serialize($processedData)) }}"
     x-data="{
         data: @js($processedData),
         columns: @js($columns),
         filters: @js($filters),
         bulkActions: @js($bulkActions),
         selectedRows: [],
         activeFilters: @js(array_merge(
             ['status' => property_exists($this, 'status') ? $this->status : 'all'],
             collect($filters)->pluck('key')->mapWithKeys(fn($key) => [
                 $key => property_exists($this, $key) ? $this->$key : null
             ])->toArray()
         )),
         sortColumn: null,
         sortDirection: 'asc',
         searchQuery: @js(property_exists($this, 'search') ? $this->search : ''),
         perPageValue: @js(property_exists($this, 'perPage') ? $this->perPage : 10),
         loading: @js($loading),
         showBulkToolbar: false,
         
         
         get filteredData() {
             let filtered = this.data;
             
             // Apply active filters
             if (@js($filterable) && Object.keys(this.activeFilters).length > 0) {
                 filtered = filtered.filter(row => {
                     return Object.entries(this.activeFilters).every(([filterKey, filterValue]) => {
                         if (!filterValue || filterValue === 'all') return true;
                         
                         const rowValue = this.getNestedValue(row, filterKey);
                         
                         // Handle boolean filters
                         if (filterValue === 'true') return rowValue === true;
                         if (filterValue === 'false') return rowValue === false;
                         
                         // Handle string filters
                         return String(rowValue) === String(filterValue);
                     });
                 });
             }
             
             // Apply search filter with relationship support
             if (this.searchQuery && @js($searchable)) {
                 filtered = filtered.filter(row => {
                     return Object.values(row).some(value => {
                         if (value === null || value === undefined) return false;
                         // Handle objects/nested data
                         if (typeof value === 'object') {
                             return Object.values(value).some(nestedValue => 
                                 String(nestedValue).toLowerCase().includes(this.searchQuery.toLowerCase())
                             );
                         }
                         return String(value).toLowerCase().includes(this.searchQuery.toLowerCase());
                     });
                 });
             }
             
             // Apply sorting with dot notation support
             if (this.sortColumn && @js($sortable)) {
                 filtered = [...filtered].sort((a, b) => {
                     // Handle dot notation (e.g., 'product.name')
                     let aVal = this.getNestedValue(a, this.sortColumn);
                     let bVal = this.getNestedValue(b, this.sortColumn);
                     
                     // Handle null/undefined values
                     if (aVal === null || aVal === undefined) aVal = '';
                     if (bVal === null || bVal === undefined) bVal = '';
                     
                     if (typeof aVal === 'string' || typeof bVal === 'string') {
                         return this.sortDirection === 'asc' 
                             ? String(aVal).localeCompare(String(bVal))
                             : String(bVal).localeCompare(String(aVal));
                     }
                     
                     return this.sortDirection === 'asc' 
                         ? aVal - bVal 
                         : bVal - aVal;
                 });
             }
             
             return filtered;
         },
         
         sort(column) {
             if (!@js($sortable)) return;
             
             if (this.sortColumn === column) {
                 this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
             } else {
                 this.sortColumn = column;
                 this.sortDirection = 'asc';
             }
         },
         
         toggleRowSelection(rowId) {
             if (!@js($selectable)) return;
             
             const index = this.selectedRows.indexOf(rowId);
             if (index > -1) {
                 this.selectedRows.splice(index, 1);
             } else {
                 this.selectedRows.push(rowId);
             }
             
             // Show/hide bulk toolbar based on selection
             this.showBulkToolbar = this.selectedRows.length > 0;
         },
         
         selectAllRows() {
             if (this.selectedRows.length === this.filteredData.length) {
                 this.selectedRows = [];
                 this.showBulkToolbar = false;
             } else {
                 this.selectedRows = this.filteredData.map(row => row.id);
                 this.showBulkToolbar = true;
             }
         },
         
         isRowSelected(rowId) {
             return this.selectedRows.includes(rowId);
         },
         
         // ✨ PHOENIX DOT NOTATION HELPER ✨  
         getNestedValue(obj, path) {
             // Simple key access
             if (!path.includes('.')) {
                 return obj[path];
             }
             
             // Dot notation access (e.g., 'product.name')
             return path.split('.').reduce((current, key) => {
                 return current && current[key] !== undefined ? current[key] : null;
             }, obj);
         },
         
         // ✨ SMART VALUE RENDERING ✨
         renderCellValue(value, column) {
             if (value === null || value === undefined) return '';
             
             // Skip rendering for actions column
             if (column.key === 'actions') return '';
             
             // Handle arrays/collections preview
             if (Array.isArray(value)) {
                 return value.slice(0, 3).join(', ') + (value.length > 3 ? '...' : '');
             }
             
             // Handle objects
             if (typeof value === 'object') {
                 // If it has a 'name' property, show that
                 if (value.name) return value.name;
                 // Otherwise show first available string property
                 for (let key in value) {
                     if (typeof value[key] === 'string') return value[key];
                 }
                 return '[Object]';
             }
             
             // Handle booleans
             if (typeof value === 'boolean') {
                 return value ? '✅' : '❌';
             }
             
             return String(value);
         }
     }">

    {{-- ✨ PHOENIX TABLE HEADER WITH GLITTER --}}
    @if ($glitterConfig['shine'])
        <div class="absolute inset-0 -skew-x-12 bg-gradient-to-r from-transparent via-white/30 to-transparent translate-x-[-100%] animate-[tableShine_3s_ease-in-out_infinite] pointer-events-none"></div>
    @endif
    
    {{-- 🔍 SEARCH & FILTERS BAR --}}
    @if ($searchable || ($filterable && !empty($filters)))
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-4">
            {{-- ✨ ELEGANT FLUX SEARCH WITH ALPINE MAGIC ✨ --}}
            @if ($searchable)
                <div class="flex-1">
                    <flux:input x-model="searchQuery" 
                               x-on:input.debounce.300ms="$wire.set('search', searchQuery)"
                               placeholder="Search products..." 
                               icon="magnifying-glass" />
                </div>
            @endif
            
            {{-- ✨ MAGNIFICENT FLUX FILTERS WITH ALPINE CHOREOGRAPHY ✨ --}}
            @if ($filterable && !empty($filters))
                <div class="flex items-center gap-4">
                    @foreach($filters as $filter)
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $filter['label'] }}:</label>
                            <flux:select x-model="activeFilters.{{ $filter['key'] }}" 
                                        x-on:change="$wire.set('{{ $filter['key'] }}', activeFilters.{{ $filter['key'] }})">
                                @foreach($filter['options'] as $option)
                                    <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endforeach
                    
                    @if(($paginate ?? $data) && method_exists(($paginate ?? $data), 'hasPages'))
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">Per page:</label>
                            <flux:select x-model="perPageValue" 
                                        x-on:change="$wire.set('perPage', perPageValue)">
                                <flux:select.option value="10">10</flux:select.option>
                                <flux:select.option value="25">25</flux:select.option>
                                <flux:select.option value="50">50</flux:select.option>
                                <flux:select.option value="100">100</flux:select.option>
                            </flux:select>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- 📊 TABLE CONTAINER --}}
    <div class="overflow-x-auto">
        <table class="{{ $tableClasses }}">
            {{-- TABLE HEADER --}}
            <thead class="{{ $headerClasses }}">
                <tr>
                    @if ($selectable)
                        <th class="{{ $cellPadding }} text-left">
                            <input type="checkbox" 
                                   x-on:click="selectAllRows()"
                                   :checked="selectedRows.length === filteredData.length && filteredData.length > 0"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </th>
                    @endif
                    
                    <template x-for="column in columns" :key="column.key">
                        <th class="{{ $cellPadding }} text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                            :class="@js($sortable) ? 'cursor-pointer hover:bg-black/5 dark:hover:bg-white/5 transition-colors' : ''"
                            x-on:click="sort(column.key)">
                            <div class="flex items-center gap-2">
                                <span x-text="column.label"></span>
                                <template x-if="@js($sortable) && sortColumn === column.key">
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
            
            {{-- TABLE BODY --}}
            <tbody class="divide-y @if($theme === 'glass') divide-white/10 @elseif($theme === 'neon') divide-emerald-400/30 @elseif($theme === 'minimal') divide-gray-100 dark:divide-gray-700 @elseif($theme === 'phoenix') divide-orange-100 dark:divide-orange-900 @else divide-gray-200 dark:divide-gray-700 @endif">
                {{-- LOADING STATE --}}
                <template x-if="loading">
                    <tr>
                        <td :colspan="columns.length + (@js($selectable) ? 1 : 0)" 
                            class="{{ $cellPadding }} text-center">
                            <div class="flex items-center justify-center gap-3 py-8">
                                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                                <span class="text-gray-500 dark:text-gray-400">Loading...</span>
                            </div>
                        </td>
                    </tr>
                </template>
                
                {{-- EMPTY STATE --}}
                <template x-if="!loading && filteredData.length === 0">
                    <tr>
                        <td :colspan="columns.length + (@js($selectable) ? 1 : 0)" 
                            class="{{ $cellPadding }} text-center">
                            <div class="flex flex-col items-center justify-center py-12 gap-4">
                                <flux:icon name="{{ $emptyIcon }}" class="w-12 h-12 text-gray-400" />
                                <div>
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $emptyMessage }}</h3>
                                    @if ($searchable)
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchQuery">
                                            Try adjusting your search query
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
                
                {{-- DATA ROWS --}}
                <template x-if="!loading && filteredData.length > 0">
                    <template x-for="(row, index) in filteredData" :key="row.id || index">
                        <tr class="{{ $rowClasses }} @if($hoverable) transition-colors duration-150 @endif @if($striped) odd:bg-gray-25 dark:odd:bg-gray-800/50 @endif"
                            x-transition:enter="{{ $animationConfig['enter'] }}"
                            x-transition:enter-start="{{ $animationConfig['start'] }}"  
                            x-transition:enter-end="{{ $animationConfig['end'] }}"
                            :style="`transition-delay: ${index * 50}ms`">
                            
                            @if ($selectable)
                                <td class="{{ $cellPadding }}">
                                    <input type="checkbox"
                                           :checked="isRowSelected(row.id)"
                                           x-on:click="toggleRowSelection(row.id)"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                            @endif
                            
                            <template x-for="column in columns" :key="column.key">
                                <td class="{{ $cellPadding }} text-sm text-gray-900 dark:text-white">
                                    <template x-if="column.key === 'actions'">
                                        <div class="flex items-center justify-end gap-1">
                                            <template x-if="row.id">
                                                <div class="flex items-center gap-1">
                                                    <a :href="'/variants/' + row.id" 
                                                       class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                       title="View">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                        </svg>
                                                    </a>
                                                    <a :href="'/variants/' + row.id + '/edit'" 
                                                       class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-md transition-colors"
                                                       title="Edit">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                        </svg>
                                                    </a>
                                                    <button @click="$dispatch('delete-item', row.id)" 
                                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                            title="Delete">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </template>
                                            <template x-if="!row.id">
                                                <span class="text-gray-400 text-xs">No actions</span>
                                            </template>
                                        </div>
                                    </template>
                                    <template x-if="column.key !== 'actions'">
                                        <span x-html="column.render ? column.render(getNestedValue(row, column.key), row) : renderCellValue(getNestedValue(row, column.key), column)"></span>
                                    </template>
                                </td>
                            </template>
                        </tr>
                    </template>
                </template>
            </tbody>
        </table>
    </div>
    
    {{-- ✨ GLITTER SPARKLES --}}
    @if ($glitterConfig['sparkles'] > 0)
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            @for ($i = 0; $i < $glitterConfig['sparkles']; $i++)
                @php
                    $positions = ['top-4 left-8', 'top-8 right-12', 'bottom-6 left-16', 'bottom-4 right-6'];
                    $delays = ['0s', '0.5s', '1s', '1.5s', '2s', '2.5s'];
                    $pos = $positions[$i % count($positions)];
                    $delay = $delays[$i % count($delays)];
                @endphp
                <div class="absolute {{ $pos }} w-2 h-2 rounded-full animate-ping
                     @if($theme === 'phoenix') bg-orange-400/60 
                     @elseif($theme === 'neon') bg-emerald-400/60
                     @elseif($theme === 'glass') bg-white/40
                     @elseif($theme === 'minimal') bg-gray-400/60
                     @else bg-blue-400/60 @endif"
                     style="animation-delay: {{ $delay }}"></div>
            @endfor
        </div>
    @endif

    {{-- ✨ PHOENIX BULK ACTIONS TOOLBAR ✨ --}}
    @if ($selectable && $showBulkActions)
        <div x-show="showBulkToolbar" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-3 flex items-center justify-between shadow-lg z-10">
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium" x-text="`${selectedRows.length} ${selectedRows.length === 1 ? 'item' : 'items'} selected`"></span>
                
                {{-- Bulk Actions --}}
                <template x-if="bulkActions.length > 0">
                    <div class="flex gap-2">
                        <template x-for="bulkAction in bulkActions" :key="bulkAction.key">
                            <flux:button size="sm" 
                                       variant="outline"
                                       class="border-white/20 text-white hover:bg-white/10"
                                       :icon="bulkAction.icon"
                                       x-on:click="$dispatch('bulk-action', { action: bulkAction.key, selected: selectedRows })">
                                <span x-text="bulkAction.label"></span>
                            </flux:button>
                        </template>
                    </div>
                </template>
            </div>
            
            <button x-on:click="selectedRows = []; showBulkToolbar = false;" 
                    class="text-white/80 hover:text-white transition-colors">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </div>
    @endif
    
    {{-- Legacy Selection Bar (fallback) --}}
    @if ($selectable && !$showBulkActions)
        <div x-show="selectedRows.length > 0" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="absolute top-0 left-0 right-0 bg-blue-600 text-white px-4 py-2 flex items-center justify-between">
            <span x-text="`${selectedRows.length} row${selectedRows.length > 1 ? 's' : ''} selected`"></span>
            <button x-on:click="selectedRows = []" class="text-blue-200 hover:text-white">
                <flux:icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
    @endif

    {{-- ✨ AUTOMATIC PAGINATION LINKS - No faff required! ✨ --}}
    @php 
    $paginationData = $paginate ?? $data;
    @endphp
    
    @if($paginationData && method_exists($paginationData, 'hasPages') && $paginationData->hasPages())
        <div class="flex items-center justify-center pt-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3">
            {{ $paginationData->links() }}
        </div>
    @endif

    <style>
        @keyframes tableShine {
            0% { transform: translateX(-100%) skewX(-12deg); }
            100% { transform: translateX(200%) skewX(-12deg); }
        }
    </style>
</div>