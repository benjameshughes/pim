{{-- 🚀 BULK OPERATIONS CENTER - Selection Hub --}}
<div class="space-y-6" x-data="{ 
    selectAll: @entangle('selectAll'),
    selectedItems: @entangle('selectedItems'),
    targetType: @entangle('targetType'),
    successMessage: @entangle('successMessage'),
    
    toggleAll() {
        if (this.selectAll) {
            this.selectedItems = @js($this->currentPageIds);
        } else {
            this.selectedItems = [];
        }
    }
}">
    
    {{-- Success Message --}}
    <div x-show="successMessage" x-transition class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon name="check-circle" class="w-5 h-5 text-green-600" />
                <span x-text="successMessage" class="text-green-800 font-medium"></span>
            </div>
            <flux:button wire:click="clearSuccessMessage" variant="ghost" size="sm" class="text-green-600">
                <flux:icon name="x" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>
    
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
            Bulk Operations Center
        </h1>
        
        {{-- Target Type Toggle --}}
        <flux:radio.group wire:model.live="targetType" variant="segmented" class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border">
            <flux:radio value="products" class="px-4 py-2">Products</flux:radio>
            <flux:radio value="variants" class="px-4 py-2">Variants</flux:radio>
        </flux:radio.group>
    </div>

    {{-- Search and Filters --}}
    <div class="flex gap-4 items-center">
        <div class="flex-1">
            <flux:input 
                wire:model.live.debounce.300ms="search" 
                placeholder="Search by name, SKU, or description..." 
                icon="magnifying-glass"
                class="w-full"
            />
        </div>
        
        <flux:select wire:model.live="filters.status" class="min-w-32">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="draft">Draft</option>
        </flux:select>
        
        <template x-if="targetType === 'products'">
            <flux:select wire:model.live="filters.has_variants" class="min-w-40">
                <option value="">All Products</option>
                <option value="1">With Variants</option>
                <option value="0">Without Variants</option>
            </flux:select>
        </template>
    </div>

    {{-- Action Bar --}}
    <div class="flex justify-between items-center bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
        <div class="flex gap-3 items-center">
            <template x-if="selectedItems.length > 0">
                <div class="flex gap-2 items-center">
                    <flux:badge size="lg" x-text="`${selectedItems.length} selected`"></flux:badge>
                    <flux:button wire:click="clearSelection" size="sm" variant="ghost">
                        Clear Selection
                    </flux:button>
                    <flux:button wire:click="selectAllMatching" size="sm" variant="ghost">
                        <span x-text="`Select All ${targetType} ({{ $this->totalItemsCount }})`"></span>
                    </flux:button>
                </div>
            </template>
            <template x-if="selectedItems.length === 0">
                <p class="text-gray-500 text-sm">
                    <span x-text="`Select ${targetType} to perform bulk operations`"></span>
                </p>
            </template>
        </div>
        
        <div class="flex gap-2">
            <flux:button 
                wire:click="openBulkPricing"
                ::disabled="selectedItems.length === 0"
                icon="currency-dollar"
                variant="primary"
            >
                Update Pricing
            </flux:button>
            
            <flux:button 
                wire:click="openBulkImages"
                ::disabled="selectedItems.length === 0"
                icon="photo"
                variant="outline"
            >
                Add Images
            </flux:button>
            
            <flux:button 
                wire:click="openBulkAttributes"
                ::disabled="selectedItems.length === 0"
                icon="tag"
                variant="outline"
            >
                Update Attributes
            </flux:button>
        </div>
    </div>

    {{-- Data Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left">
                        <flux:checkbox 
                            x-model="selectAll"
                            @change="toggleAll()"
                            class="rounded"
                        />
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <span x-text="targetType === 'products' ? 'Product' : 'Variant'"></span>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        <span x-text="targetType === 'products' ? 'Variants' : 'Product'"></span>
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($this->paginatedItems as $item)
                    <tr wire:key="item-{{ $item->id }}" 
                        :class="selectedItems.includes({{ $item->id }}) ? 'bg-blue-25 dark:bg-blue-950' : ''"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <flux:checkbox 
                                wire:model.live="selectedItems"
                                value="{{ $item->id }}"
                                class="rounded"
                            />
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    @if($this->targetType === 'products')
                                        {{ $item->name }}
                                    @else
                                        {{ $item->title }}
                                    @endif
                                </div>
                                @if($this->targetType === 'variants' && $item->color)
                                    <div class="text-xs text-gray-500">
                                        {{ $item->color }} 
                                        @if($item->width) 
                                            · {{ $item->width }}cm 
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-300">
                            @if($this->targetType === 'products')
                                {{ $item->parent_sku }}
                            @else
                                {{ $item->sku }}
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if(is_string($item->status))
                                {{-- ProductVariant uses string status --}}
                                <flux:badge size="sm" color="{{ $item->status === 'active' ? 'green' : 'zinc' }}">
                                    {{ ucfirst($item->status) }}
                                </flux:badge>
                            @else
                                {{-- Product uses ProductStatus enum --}}
                                <flux:badge size="sm" color="{{ $item->status->value === 'active' ? 'green' : 'zinc' }}">
                                    {{ $item->status->label() }}
                                </flux:badge>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            @if($item->price)
                                ${{ number_format($item->price, 2) }}
                            @else
                                <span class="text-gray-400">No price</span>
                            @endif
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            @if($this->targetType === 'products')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $item->variants_count }} variants
                                </span>
                            @else
                                {{ $item->product->name ?? 'N/A' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <flux:icon name="squares-plus" class="w-8 h-8 text-gray-400" />
                                <p class="text-gray-500">
                                    <span x-text="`No ${targetType} found`"></span>
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        
        {{-- Pagination --}}
        @if($this->paginatedItems->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->paginatedItems->links() }}
            </div>
        @endif
    </div>

</div>