@props([
    'data' => null,
    'columns' => [],
    'actions' => [],
    'bulkActions' => [],
    'headerActions' => [],
    'searchable' => false,
    'searchPlaceholder' => 'Search...',
    'filters' => [],
    'paginationSizes' => [10, 25, 50, 100],
    'defaultPerPage' => 25,
    'sortable' => true,
    'selectable' => false,
    'emptyState' => null,
    'loading' => false,
    'striped' => true,
    'bordered' => false,
    'sticky' => true,
])

<div class="space-y-4" 
     x-data="dataTable({ 
         loading: @js($loading), 
         selectable: @js($selectable),
         searchable: @js($searchable)
     })">
     
    {{-- Table Controls --}}
    @if($searchable || !empty($filters) || !empty($headerActions))
        <div class="bg-zinc-50 dark:bg-zinc-900/50 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {{-- Search and Filters --}}
                <div class="flex flex-col sm:flex-row gap-4 flex-1">
                    {{-- Search Input --}}
                    @if($searchable)
                        <div class="relative flex-1 max-w-md">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <flux:icon name="search" class="h-5 w-5 text-zinc-400" />
                            </div>
                            <flux:input 
                                type="search" 
                                wire:model.live.debounce.300ms="search"
                                placeholder="{{ $searchPlaceholder }}"
                                class="pl-10"
                            />
                        </div>
                    @endif
                    
                    {{-- Filters --}}
                    @if(!empty($filters))
                        <div class="flex gap-3 flex-wrap">
                            @foreach($filters as $filter)
                                @switch($filter['type'])
                                    @case('select')
                                        <div class="min-w-[180px]">
                                            <flux:select wire:model.live="filters.{{ $filter['key'] }}">
                                                <flux:select.option value="">
                                                    {{ $filter['placeholder'] ?? 'All ' . ($filter['label'] ?? $filter['key']) }}
                                                </flux:select.option>
                                                @foreach($filter['options'] as $value => $label)
                                                    <flux:select.option value="{{ $value }}">
                                                        {{ $label }}
                                                    </flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        @break
                                        
                                    @case('date-range')
                                        <div class="flex gap-2">
                                            <flux:input 
                                                type="date" 
                                                wire:model.live="filters.{{ $filter['key'] }}_start"
                                                placeholder="{{ $filter['startPlaceholder'] ?? 'Start date' }}"
                                            />
                                            <flux:input 
                                                type="date" 
                                                wire:model.live="filters.{{ $filter['key'] }}_end"
                                                placeholder="{{ $filter['endPlaceholder'] ?? 'End date' }}"
                                            />
                                        </div>
                                        @break
                                        
                                    @case('toggle')
                                        <div class="flex items-center gap-2">
                                            <flux:checkbox 
                                                wire:model.live="filters.{{ $filter['key'] }}"
                                                id="filter-{{ $filter['key'] }}"
                                            />
                                            <flux:label for="filter-{{ $filter['key'] }}">
                                                {{ $filter['label'] ?? $filter['key'] }}
                                            </flux:label>
                                        </div>
                                        @break
                                @endswitch
                            @endforeach
                        </div>
                    @endif
                </div>
                
                {{-- Header Actions --}}
                @if(!empty($headerActions))
                    <div class="flex items-center gap-2">
                        @foreach($headerActions as $action)
                            @if($action['type'] === 'button')
                                <flux:button 
                                    wire:click="{{ $action['action'] }}"
                                    variant="{{ $action['variant'] ?? 'outline' }}"
                                    size="{{ $action['size'] ?? 'sm' }}"
                                    @if(isset($action['icon'])) icon="{{ $action['icon'] }}" @endif
                                    @if(isset($action['loading'])) wire:loading.attr="disabled" @endif
                                >
                                    {{ $action['label'] }}
                                </flux:button>
                            @elseif($action['type'] === 'link')
                                <flux:button 
                                    href="{{ $action['href'] }}"
                                    variant="{{ $action['variant'] ?? 'outline' }}"
                                    size="{{ $action['size'] ?? 'sm' }}"
                                    @if(isset($action['icon'])) icon="{{ $action['icon'] }}" @endif
                                    @if($action['navigate'] ?? false) wire:navigate @endif
                                >
                                    {{ $action['label'] }}
                                </flux:button>
                            @endif
                        @endforeach
                        
                        {{-- Pagination Size Selector --}}
                        @if(count($paginationSizes) > 1)
                            <div class="flex items-center gap-2 ml-4 pl-4 border-l border-zinc-200 dark:border-zinc-700">
                                <span class="text-sm text-zinc-600 dark:text-zinc-400">Show:</span>
                                <flux:select wire:model.live="perPage" size="sm">
                                    @foreach($paginationSizes as $size)
                                        <flux:select.option value="{{ $size }}">{{ $size }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            
            {{-- Clear Filters --}}
            @if($searchable || !empty($filters))
                <div class="flex justify-between items-center mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="text-sm text-zinc-500">
                        @if(isset($data) && method_exists($data, 'total'))
                            Showing {{ number_format($data->count()) }} of {{ number_format($data->total()) }} results
                        @endif
                    </div>
                    <flux:button 
                        wire:click="resetFilters" 
                        variant="ghost" 
                        size="sm"
                        icon="x"
                    >
                        Clear filters
                    </flux:button>
                </div>
            @endif
        </div>
    @endif
    
    {{-- Bulk Actions Bar --}}
    @if($selectable)
        <div x-show="selectedItems.length > 0" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4"
             style="display: none;">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                    <span x-text="selectedItems.length"></span> item(s) selected
                </span>
                <div class="flex items-center gap-2">
                    @foreach($bulkActions as $action)
                        <flux:button 
                            x-on:click="executeBulkAction('{{ $action['key'] }}', selectedItems)"
                            variant="{{ $action['variant'] ?? 'outline' }}"
                            size="sm"
                            @if(isset($action['icon'])) icon="{{ $action['icon'] }}" @endif
                            @if(($action['danger'] ?? false)) class="text-red-600 border-red-300 hover:bg-red-50" @endif
                        >
                            {{ $action['label'] }}
                        </flux:button>
                    @endforeach
                    <flux:button 
                        x-on:click="clearSelection" 
                        variant="ghost" 
                        size="sm"
                        icon="x"
                    >
                        Clear
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
    
    {{-- Data Table --}}
    @if($data && $data->count() > 0)
        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
            {{-- Loading Overlay --}}
            <div x-show="loading" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="absolute inset-0 bg-white/80 dark:bg-zinc-800/80 flex items-center justify-center z-10"
                 style="display: none;">
                <div class="flex items-center gap-3">
                    <div class="w-6 h-6 border-2 border-zinc-200 border-t-indigo-600 rounded-full animate-spin"></div>
                    <span class="text-sm font-medium">Loading...</span>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm @if($striped) divide-y divide-zinc-200 dark:divide-zinc-700 @endif">
                    {{-- Table Header --}}
                    <thead class="bg-zinc-50 dark:bg-zinc-900 @if($sticky) sticky top-0 @endif">
                        <tr>
                            @if($selectable)
                                <th scope="col" class="w-12 px-6 py-3">
                                    <flux:checkbox 
                                        x-model="selectAll"
                                        x-on:change="toggleSelectAll"
                                        aria-label="Select all items"
                                    />
                                </th>
                            @endif
                            
                            @foreach($columns as $column)
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider {{ $column['width'] ?? '' }}">
                                    @if($sortable && ($column['sortable'] ?? true))
                                        <button 
                                            wire:click="sortBy('{{ $column['key'] }}')"
                                            class="group inline-flex items-center gap-2 hover:text-zinc-700 dark:hover:text-zinc-300"
                                        >
                                            {{ $column['label'] ?? Str::title($column['key']) }}
                                            <div class="flex flex-col">
                                                <flux:icon 
                                                    name="chevron-up" 
                                                    class="h-3 w-3 transition-colors {{ isset($this->sortField) && $this->sortField === $column['key'] && $this->sortDirection === 'asc' ? 'text-indigo-600' : 'text-zinc-300 group-hover:text-zinc-400' }}"
                                                />
                                                <flux:icon 
                                                    name="chevron-down" 
                                                    class="h-3 w-3 -mt-1 transition-colors {{ isset($this->sortField) && $this->sortField === $column['key'] && $this->sortDirection === 'desc' ? 'text-indigo-600' : 'text-zinc-300 group-hover:text-zinc-400' }}"
                                                />
                                            </div>
                                        </button>
                                    @else
                                        {{ $column['label'] ?? Str::title($column['key']) }}
                                    @endif
                                </th>
                            @endforeach
                            
                            @if(!empty($actions))
                                <th scope="col" class="w-20 px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            @endif
                        </tr>
                    </thead>
                    
                    {{-- Table Body --}}
                    <tbody class="bg-white dark:bg-zinc-800 @if($striped) divide-y divide-zinc-200 dark:divide-zinc-700 @endif">
                        @foreach($data as $item)
                            <tr 
                                class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50 transition-colors @if($selectable) {{ 'hover:bg-indigo-50 dark:hover:bg-indigo-900/20' }} @endif"
                                @if($selectable) 
                                    x-bind:class="{ 'bg-indigo-50 dark:bg-indigo-900/20': selectedItems.includes({{ $item->id }}) }"
                                @endif
                            >
                                @if($selectable)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:checkbox 
                                            x-model="selectedItems" 
                                            value="{{ $item->id }}"
                                            aria-label="Select item"
                                        />
                                    </td>
                                @endif
                                
                                @foreach($columns as $column)
                                    <td class="px-6 py-4 whitespace-nowrap {{ $column['class'] ?? '' }}">
                                        @switch($column['type'] ?? 'text')
                                            @case('text')
                                                <div class="{{ $column['textClass'] ?? 'text-sm text-zinc-900 dark:text-zinc-100' }}">
                                                    {{ data_get($item, $column['key']) }}
                                                </div>
                                                @break
                                                
                                            @case('badge')
                                                @php
                                                    $value = data_get($item, $column['key']);
                                                    $badgeConfig = $column['badges'][$value] ?? ['variant' => 'neutral', 'label' => $value];
                                                @endphp
                                                <flux:badge variant="{{ $badgeConfig['variant'] ?? 'neutral' }}">
                                                    {{ $badgeConfig['label'] ?? $value }}
                                                </flux:badge>
                                                @break
                                                
                                            @case('avatar')
                                                @php $avatarValue = data_get($item, $column['key']); @endphp
                                                @if($avatarValue)
                                                    <img src="{{ $avatarValue }}" alt="Avatar" class="w-8 h-8 rounded-full">
                                                @else
                                                    <div class="w-8 h-8 bg-zinc-200 dark:bg-zinc-700 rounded-full flex items-center justify-center">
                                                        <flux:icon name="user" class="h-4 w-4 text-zinc-500" />
                                                    </div>
                                                @endif
                                                @break
                                                
                                            @case('date')
                                                <div class="text-sm text-zinc-900 dark:text-zinc-100">
                                                    @php $date = data_get($item, $column['key']); @endphp
                                                    @if($date)
                                                        {{ $date->format($column['dateFormat'] ?? 'M j, Y') }}
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                                @break
                                                
                                            @case('currency')
                                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                    @php 
                                                        $amount = data_get($item, $column['key']);
                                                        $currency = $column['currency'] ?? 'GBP';
                                                        $symbol = $column['symbol'] ?? '£';
                                                    @endphp
                                                    @if($amount)
                                                        {{ $symbol }}{{ number_format($amount, 2) }}
                                                    @else
                                                        —
                                                    @endif
                                                </div>
                                                @break
                                                
                                            @case('boolean')
                                                @php $boolValue = data_get($item, $column['key']); @endphp
                                                @if($boolValue)
                                                    <flux:icon name="check-circle" class="h-5 w-5 text-green-500" />
                                                @else
                                                    <flux:icon name="x-circle" class="h-5 w-5 text-red-500" />
                                                @endif
                                                @break
                                                
                                            @case('custom')
                                                @if(isset($column['render']))
                                                    {!! $column['render']($item) !!}
                                                @else
                                                    {{ data_get($item, $column['key']) }}
                                                @endif
                                                @break
                                                
                                            @default
                                                <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ data_get($item, $column['key']) }}
                                                </div>
                                        @endswitch
                                    </td>
                                @endforeach
                                
                                @if(!empty($actions))
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @foreach($actions as $action)
                                                @php
                                                    $isVisible = !isset($action['visible']) || $action['visible']($item);
                                                @endphp
                                                @if($isVisible)
                                                    @if($action['type'] === 'link')
                                                        <flux:button 
                                                            href="{{ $action['href']($item) }}"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="{{ $action['icon'] ?? 'eye' }}"
                                                            @if($action['navigate'] ?? false) wire:navigate @endif
                                                            aria-label="{{ $action['label'] }}"
                                                        />
                                                    @else
                                                        <flux:button 
                                                            wire:click="{{ $action['action'] }}({{ $item->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="{{ $action['icon'] ?? 'pencil' }}"
                                                            @if(isset($action['loading'])) wire:loading.attr="disabled" @endif
                                                            @if($action['danger'] ?? false) class="text-red-600 hover:text-red-800" @endif
                                                            aria-label="{{ $action['label'] }}"
                                                        />
                                                    @endif
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        
        {{-- Pagination --}}
        @if(method_exists($data, 'links'))
            <div class="flex items-center justify-between">
                <div class="text-sm text-zinc-500">
                    Showing {{ number_format($data->firstItem()) }} to {{ number_format($data->lastItem()) }} 
                    of {{ number_format($data->total()) }} results
                </div>
                {{ $data->links() }}
            </div>
        @endif
    @else
        {{-- Empty State --}}
        <div class="text-center py-12 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
            @if($emptyState)
                {{ $emptyState }}
            @else
                <div class="mx-auto h-12 w-12 text-zinc-400 mb-4">
                    <flux:icon name="inbox" class="h-12 w-12" />
                </div>
                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">
                    No data found
                </h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    No items match your current search and filters.
                </p>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dataTable', (config) => ({
        loading: config.loading || false,
        selectedItems: [],
        selectAll: false,
        selectable: config.selectable || false,
        
        init() {
            // Watch for Livewire loading states
            this.$watch('loading', (value) => {
                if (value) {
                    this.$el.style.pointerEvents = 'none';
                    this.$el.style.opacity = '0.7';
                } else {
                    this.$el.style.pointerEvents = 'auto';
                    this.$el.style.opacity = '1';
                }
            });
            
            // Listen for Livewire events
            Livewire.on('table-loading-start', () => {
                this.loading = true;
            });
            
            Livewire.on('table-loading-end', () => {
                this.loading = false;
            });
            
            // Reset selection on data refresh
            Livewire.on('table-data-refreshed', () => {
                this.clearSelection();
            });
        },
        
        toggleSelectAll() {
            if (this.selectAll) {
                // Select all visible items
                this.selectedItems = Array.from(this.$el.querySelectorAll('tbody input[type="checkbox"]'))
                    .map(input => parseInt(input.value));
            } else {
                this.clearSelection();
            }
        },
        
        clearSelection() {
            this.selectedItems = [];
            this.selectAll = false;
        },
        
        executeBulkAction(actionKey, items) {
            if (items.length === 0) return;
            
            Livewire.emit('executeBulkAction', actionKey, items);
        },
        
        // Watch selectedItems to update selectAll state
        get allSelected() {
            const checkboxes = this.$el.querySelectorAll('tbody input[type="checkbox"]');
            return checkboxes.length > 0 && this.selectedItems.length === checkboxes.length;
        }
    }));
});
</script>
@endpush