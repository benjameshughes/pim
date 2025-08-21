@props([
    'layout' => 'grid',          // grid, inline, accordion
    'columns' => 'auto',         // auto, 1, 2, 3, 4, 5, 6
    'collapsible' => false,      // Can collapse/expand filters
    'showCount' => true,         // Show active filter count
    'clearable' => true,         // Show "Clear All" button
    'compact' => false,          // Compact layout
])

@php
$containerClasses = collect([
    'filter-container',
    $layout === 'inline' ? 'flex flex-wrap items-center gap-3' : '',
    $layout === 'grid' ? 'grid gap-3' : '',
    $layout === 'accordion' ? 'space-y-2' : '',
])->filter()->implode(' ');

$gridCols = match($columns) {
    1 => 'grid-cols-1',
    2 => 'grid-cols-1 sm:grid-cols-2',
    3 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    5 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
    6 => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6',
    default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
};

$filterSize = $compact ? 'sm' : 'normal';
@endphp

<div x-data="{
    filtersExpanded: !@js($collapsible),
    activeFilterCount: 0,
    
    updateFilterCount() {
        this.activeFilterCount = Object.values(this.activeFilters || {}).filter(value => 
            value && value !== '' && value !== 'all'
        ).length;
    },
    
    clearAllFilters() {
        this.activeFilters = {};
        this.updateFilterCount();
    }
}" x-init="
    $watch('activeFilters', () => updateFilterCount());
    updateFilterCount();
">

    {{-- ✨ FILTERS HEADER WITH SPARKLE ✨ --}}
    @if($collapsible || $showCount || $clearable)
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
            
            {{-- Toggle & Count --}}
            <div class="flex items-center gap-3">
                @if($collapsible)
                    <button 
                        @click="filtersExpanded = !filtersExpanded"
                        class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300 
                               hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        <flux:icon 
                            name="funnel"
                            class="w-4 h-4"
                        />
                        <span>Filters</span>
                        <flux:icon 
                            :name="filtersExpanded ? 'chevron-up' : 'chevron-down'"
                            class="w-4 h-4 transition-transform"
                        />
                    </button>
                @else
                    <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <flux:icon name="funnel" class="w-4 h-4" />
                        <span>Filters</span>
                    </div>
                @endif
                
                @if($showCount)
                    <div x-show="activeFilterCount > 0"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-90"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="inline-flex items-center px-2 py-1 bg-blue-100 dark:bg-blue-900/50 
                                text-blue-800 dark:text-blue-200 text-xs font-medium rounded-full">
                        <span x-text="activeFilterCount"></span>
                        <span class="ml-1">active</span>
                    </div>
                @endif
            </div>
            
            {{-- Clear All Button --}}
            @if($clearable)
                <button 
                    x-show="activeFilterCount > 0"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-x-2"
                    x-transition:enter-end="opacity-100 translate-x-0"
                    @click="clearAllFilters()"
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 
                           dark:hover:text-gray-200 underline transition-colors"
                >
                    Clear all
                </button>
            @endif
        </div>
    @endif
    
    {{-- ✨ FILTERS CONTENT ✨ --}}
    <div x-show="filtersExpanded" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2" 
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">
        
        {{-- Auto-detected Filters --}}
        <div x-show="filters && filters.length > 0" 
             class="{{ $containerClasses }} {{ $layout === 'grid' ? $gridCols : '' }}">
             
            <template x-for="filter in filters" :key="filter.key">
                <div class="filter-item group">
                    
                    {{-- Filter Label with Glamour --}}
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1
                                  group-focus-within:text-blue-600 dark:group-focus-within:text-blue-400 
                                  transition-colors duration-200" 
                           x-text="filter.label">
                    </label>
                    
                    {{-- Filter Select with Style --}}
                    <flux:select 
                        x-model="activeFilters[filter.key]"
                        size="{{ $filterSize }}"
                        class="w-full transition-all duration-200 group-focus-within:ring-2 
                               group-focus-within:ring-blue-500/20"
                    >
                        <flux:select.option value="">
                            <span class="text-gray-500" x-text="`All ${filter.label}`"></span>
                        </flux:select.option>
                        
                        <template x-for="(filterOption, index) in filter.options" :key="index">
                            <flux:select.option x-bind:value="filterOption" x-text="filterOption"></flux:select.option>
                        </template>
                    </flux:select>
                    
                    {{-- Active Filter Indicator --}}
                    <div x-show="activeFilters[filter.key] && activeFilters[filter.key] !== ''"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-90"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="absolute -top-1 -right-1 w-2 h-2 bg-blue-500 rounded-full 
                                animate-pulse shadow-lg shadow-blue-500/50">
                    </div>
                </div>
            </template>
        </div>
        
        {{-- ✨ CUSTOM FILTERS SLOT ✨ --}}
        @if($slot->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="{{ $containerClasses }} {{ $layout === 'grid' ? $gridCols : '' }}">
                    {{ $slot }}
                </div>
            </div>
        @endif
        
        {{-- ✨ QUICK FILTER CHIPS ✨ --}}
        <div x-show="activeFilterCount > 0" 
             class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
            <div class="flex flex-wrap gap-2">
                <template x-for="filterEntry in Object.entries(activeFilters || {})" 
                         :key="filterEntry[0]">
                    <div x-show="filterEntry[1] && filterEntry[1] !== '' && filterEntry[1] !== 'all'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-90"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 dark:bg-blue-900/30
                                text-blue-700 dark:text-blue-300 text-xs rounded-md border border-blue-200 
                                dark:border-blue-700">
                        <span x-text="filterEntry[0].replace('_', ' ')"></span>
                        <span class="font-medium" x-text="filterEntry[1]"></span>
                        <button @click="activeFilters[filterEntry[0]] = ''; updateFilterCount();"
                                class="ml-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 
                                       dark:hover:text-blue-200 transition-colors">
                            <flux:icon name="x-mark" class="w-3 h-3" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>
    
</div>