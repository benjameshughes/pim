<div class="relative" x-data="{ 
    open: @entangle('isOpen'), 
    focusedIndex: -1,
    selectFocused() {
        // Will be handled by Livewire events instead
    }
}" @click.away="open = false; $wire.closeDropdown()" @keydown.escape="open = false; $wire.closeDropdown()">
    
    {{-- Search Input --}}
    <div class="relative">
        <flux:input 
            wire:model.live.debounce.300ms="search"
            placeholder="{{ $placeholder }}"
            @focus="$wire.openDropdown()"
            @keydown.arrow-down.prevent=""
            @keydown.arrow-up.prevent=""
            @keydown.enter.prevent=""
        />
        
        {{-- Search/Clear Icon --}}
        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
            @if($selectedId)
                <button 
                    type="button" 
                    wire:click="clear"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                >
                    <flux:icon name="x" class="h-4 w-4" />
                </button>
            @endif
            
            <button 
                type="button" 
                @click="open = !open; open ? $wire.openDropdown() : $wire.closeDropdown()"
                class="text-gray-400 hover:text-gray-600 transition-colors"
            >
                <flux:icon name="chevron-down" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': open }" />
            </button>
        </div>
    </div>

    {{-- Dropdown Results --}}
    <div 
        x-show="open" 
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-64 overflow-y-auto"
    >
        @forelse($this->searchResults as $index => $item)
            <div 
                wire:click="selectItem('{{ $item['type'] }}', {{ $item['id'] }})"
                class="px-4 py-3 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                <div class="flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            {{-- Type Badge --}}
                            @if($item['type'] === 'product')
                                <flux:badge variant="primary" size="sm">
                                    <flux:icon name="package" class="h-3 w-3" />
                                    Product
                                </flux:badge>
                            @else
                                <flux:badge variant="success" size="sm">
                                    <flux:icon name="box" class="h-3 w-3" />
                                    Variant
                                </flux:badge>
                            @endif
                            
                            {{-- Name --}}
                            <p class="font-medium text-gray-900 dark:text-white truncate">
                                {{ $item['name'] }}
                            </p>
                        </div>
                        
                        {{-- SKU --}}
                        <p class="text-sm font-mono text-blue-600 dark:text-blue-400 mb-1">
                            SKU: {{ $item['sku'] }}
                        </p>
                        
                        {{-- Description/Product Name for variants --}}
                        @if($item['description'])
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                @if($item['type'] === 'variant' && isset($item['product_name']))
                                    Product: {{ $item['product_name'] }}
                                @else
                                    {{ Str::limit($item['description'], 60) }}
                                @endif
                            </p>
                        @endif
                        
                        {{-- Variant Count for products --}}
                        @if($item['type'] === 'product' && isset($item['variants_count']))
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $item['variants_count'] }} variant{{ $item['variants_count'] === 1 ? '' : 's' }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            @if(strlen($search) >= 2)
                <div class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                    <flux:icon name="search" class="h-5 w-5 mx-auto mb-2 opacity-50" />
                    <p class="text-sm">No results found for "{{ $search }}"</p>
                </div>
            @endif
        @endforelse
    </div>

    {{-- Loading State --}}
    <div 
        x-show="open" 
        wire:loading.delay.longer 
        wire:target="search"
        class="absolute z-50 w-full mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-4 text-center"
    >
        <flux:icon name="loader" class="h-5 w-5 mx-auto mb-2 animate-spin text-blue-600" />
        <p class="text-sm text-gray-500 dark:text-gray-400">Searching...</p>
    </div>
</div>