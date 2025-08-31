<div class="space-y-6">
    {{-- Header --}}
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
            <flux:icon name="link" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            Attach Image
        </h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
            Search and select products or variants to attach this image to
        </p>
    </div>

    {{-- Current Attachments --}}
    @if($this->currentAttachments)
        <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium text-purple-800 dark:text-purple-200">
                    Currently attached to {{ count($this->currentAttachments) }} item(s):
                </p>
                <flux:button
                    @click="confirmAction({
                        title: 'Detach All',
                        message: 'This will remove the image from all products and variants.\\n\\nThe image itself will remain in the library.',
                        confirmText: 'Yes, Detach All',
                        cancelText: 'Cancel',
                        variant: 'warning',
                        onConfirm: () => $wire.detachFromAll()
                    })"
                    variant="danger"
                    size="sm"
                    icon="unlink-2"
                >
                    Detach All
                </flux:button>
            </div>
            
            <div class="space-y-2">
                @foreach($this->currentAttachments as $attachment)
                    <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-purple-100 dark:border-purple-800">
                        <div class="flex items-center gap-3">
                            @if($attachment['type'] === 'product')
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
                            
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">
                                    {{ $attachment['name'] }}
                                </p>
                                <p class="text-xs font-mono text-gray-500">
                                    SKU: {{ $attachment['sku'] }}
                                </p>
                            </div>
                        </div>
                        
                        <flux:button
                            wire:click="detachFromItem('{{ $attachment['type'] }}', {{ $attachment['id'] }})"
                            variant="ghost"
                            size="sm"
                            icon="x"
                            class="text-red-600 hover:text-red-700"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Selected Items (moved above search for better visibility) --}}
    @if($this->selectedItemsWithDetails)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700/50">
            <div class="flex items-center justify-between mb-3">
                <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                    Selected Items ({{ count($this->selectedItemsWithDetails) }}):
                </p>
                <flux:button
                    wire:click="attachSelectedItems"
                    variant="primary"
                    size="sm"
                    icon="link"
                    :disabled="empty($this->selectedItemsWithDetails)"
                >
                    Attach Selected
                </flux:button>
            </div>
            
            <div class="space-y-2">
                @foreach($this->selectedItemsWithDetails as $item)
                    <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-blue-100 dark:border-blue-800">
                        <div class="flex items-center gap-3">
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
                            
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">
                                    {{ $item['display_name'] }}
                                </p>
                                <p class="text-xs font-mono text-gray-500">
                                    {{ $item['sku'] }}
                                </p>
                            </div>
                        </div>
                        
                        <flux:button
                            wire:click="removeFromSelection({{ $item['index'] }})"
                            variant="ghost"
                            size="sm"
                            icon="x"
                            class="text-red-600 hover:text-red-700"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Unified Search Interface --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        {{-- Search Type Toggle --}}
        <div class="flex items-center gap-1 mb-4">
            <flux:button 
                wire:click="setSearchType('products')"
                variant="{{ $searchType === 'products' ? 'primary' : 'ghost' }}"
                size="sm"
                icon="package"
            >
                Products
            </flux:button>
            <flux:button 
                wire:click="setSearchType('variants')"
                variant="{{ $searchType === 'variants' ? 'primary' : 'ghost' }}"
                size="sm"
                icon="box"
            >
                Variants
            </flux:button>
            <flux:button 
                wire:click="setSearchType('both')"
                variant="{{ $searchType === 'both' ? 'primary' : 'ghost' }}"
                size="sm"
                icon="squares-2x2"
            >
                Both
            </flux:button>
        </div>

        {{-- Search Input --}}
        <div class="relative">
            <flux:input
                wire:model.live.debounce.300ms="searchTerm"
                placeholder="Search {{ $searchType === 'both' ? 'products and variants' : $searchType }}..."
                class="w-full"
            >
                <x-slot name="iconTrailing">
                    <flux:icon name="magnifying-glass" class="w-5 h-5 text-gray-400"/>
                </x-slot>
            </flux:input>

            {{-- Search Results Dropdown --}}
            @if($showResults && $this->searchResults)
                <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                    @foreach($this->searchResults as $result)
                        <div 
                            wire:click="addToSelection('{{ $result['type'] }}', {{ $result['id'] }})"
                            class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                        >
                            <div class="flex items-center gap-3">
                                @if($result['type'] === 'product')
                                    <flux:badge variant="primary" size="xs">
                                        <flux:icon name="package" class="h-3 w-3" />
                                    </flux:badge>
                                @else
                                    <flux:badge variant="success" size="xs">
                                        <flux:icon name="box" class="h-3 w-3" />
                                    </flux:badge>
                                @endif
                                
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">
                                        {{ $result['display_name'] }}
                                    </p>
                                    <p class="text-xs font-mono text-gray-500">
                                        {{ $result['sku'] }}
                                    </p>
                                </div>
                            </div>
                            
                            @if($result['is_attached'])
                                <flux:badge variant="success" size="xs">
                                    Already attached
                                </flux:badge>
                            @else
                                <flux:icon name="plus" class="h-4 w-4 text-gray-400" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        
        @if(!$this->selectedItemsWithDetails)
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-3">
                Search and select items above to attach them to this image
            </p>
        @endif
    </div>
</div>