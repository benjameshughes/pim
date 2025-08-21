{{-- 🚀 UNIFIED PRODUCTS & VARIANTS - EXPANDABLE INTERFACE ✨ --}}
<div class="space-y-6">
    {{-- Header & Stats --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                📦 Products & Variants
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Unified product catalog with expandable variant details
            </p>
        </div>
        <div class="flex gap-3">
            <flux:button href="{{ route('variants.create') }}" icon="plus" variant="outline">
                New Variant
            </flux:button>
            <flux:button href="{{ route('products.create') }}" icon="plus" variant="primary">
                New Product
            </flux:button>
        </div>
    </div>

    {{-- Enhanced Search & Filter Bar --}}
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex gap-4 items-center">
            <div class="flex-1">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search products, variants, SKUs, colors..." 
                    icon="magnifying-glass"
                    class="w-full"
                />
            </div>
            <flux:select wire:model.live="status" class="min-w-40">
                <option value="all">All Status</option>
                <option value="active">Active Only</option>
                <option value="inactive">Inactive Only</option>
                <option value="draft">Draft Only</option>
            </flux:select>
            <flux:select wire:model.live="perPage" class="min-w-24">
                <option value="10">10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </flux:select>
        </div>
    </div>

    {{-- 🚀 MAIN PRODUCTS TABLE WITH SUBTABLE EXPANSION ✨ --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        {{-- Main Products Table --}}
        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/3">
                        Product & Variants
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        SKU
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Stats
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($products as $product)
                    {{-- Main Product Row --}}
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                {{-- Simple Expand Button --}}
                                <flux:button 
                                    wire:click="toggleExpand({{ $product->id }})"
                                    variant="ghost" 
                                    size="sm"
                                    icon="{{ isset($this->expandedProducts[$product->id]) ? 'chevron-down' : 'chevron-right' }}"
                                    class="text-gray-400 hover:text-gray-600"
                                />
                                
                                {{-- Product Info --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                            {{ $product->name }}
                                        </h4>
                                        {{-- Inline Loading indicator --}}
                                        <div wire:loading wire:target="toggleExpand({{ $product->id }})" 
                                             class="flex items-center justify-center gap-1 text-blue-600 text-sm font-medium">
                                            <flux:icon name="loader" class="w-4 h-4 animate-spin" />
                                            {{-- <span class="leading-none">Loading...</span> --}}
                                        </div>
                                        <flux:badge 
                                            size="sm" 
                                            color="blue" 
                                            inset="top bottom"
                                        >
                                            {{ $product->variants_count }} variants
                                        </flux:badge>
                                    </div>
                                    @if($product->description)
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">
                                            {{ $product->description }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">
                                {{ $product->parent_sku }}
                            </span>
                        </td>
                        
                        <td class="px-6 py-4">
                            <flux:badge 
                                size="sm" 
                                color="{{ $product->status->value === 'active' ? 'green' : ($product->status->value === 'draft' ? 'yellow' : 'zinc') }}" 
                                inset="top bottom"
                            >
                                {{ $product->status->label() }}
                            </flux:badge>
                        </td>
                        
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3 text-sm text-gray-600">
                                @if($product->shopifySyncStatus)
                                    <div class="flex items-center gap-1">
                                        <flux:icon name="check-circle" class="w-3 h-3 text-green-500" />
                                        <span class="text-xs">Synced</span>
                                    </div>
                                @endif
                                <span class="text-xs">
                                    Updated {{ $product->updated_at->diffForHumans() }}
                                </span>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4">
                            <div class="flex gap-2 opacity-100 transition-opacity">
                                <flux:button 
                                    href="{{ route('products.edit', $product) }}" 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="pencil"
                                >
                                    Edit
                                </flux:button>
                                <flux:button 
                                    href="{{ route('products.show', $product) }}" 
                                    size="sm" 
                                    variant="ghost" 
                                    icon="eye"
                                >
                                    View
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                    
                    {{-- 🎉 EXPANDABLE VARIANTS - BACK TO WORKING VERSION --}}
                    @if(isset($this->expandedProducts[$product->id]))
                        <tr class="animate-in slide-in-from-top-1 duration-300 ease-out">
                            <td colspan="5" class="p-0 bg-gray-50/80 border-b border-gray-200">
                                <div class="px-6 py-4">
                                    <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                        <flux:icon name="package" class="w-4 h-4 text-gray-500" />
                                        {{ $product->name }} Variants ({{ count($this->expandedProducts[$product->id]) }})
                                    </h4>
                                    
                                    <div class="space-y-2">
                                        @foreach($this->expandedProducts[$product->id] as $index => $variant)
                                            <div 
                                                class="p-3 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200"
                                                style="animation-delay: {{ $index * 50 }}ms"
                                            >
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-4">
                                                        <span class="font-mono font-medium text-gray-900">{{ $variant['sku'] }}</span>
                                                        <div class="flex items-center gap-1">
                                                            <div class="w-3 h-3 rounded-full border border-gray-300" 
                                                                 style="background-color: {{ $variant['color'] === 'red' ? '#ef4444' : ($variant['color'] === 'blue' ? '#3b82f6' : ($variant['color'] === 'green' ? '#22c55e' : '#6b7280')) }}">
                                                            </div>
                                                            <span class="text-sm text-gray-600">{{ $variant['color'] ?? 'No color' }}</span>
                                                        </div>
                                                        @if($variant['width'] ?? false)
                                                            <span class="text-sm text-gray-500 bg-gray-100 px-2 py-1 rounded">{{ $variant['width'] }}cm × {{ $variant['drop'] ?? 'N/A' }}cm</span>
                                                        @endif
                                                        <span class="font-semibold text-green-600">£{{ number_format($variant['price'] ?? 0, 2) }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        <div class="text-sm text-gray-500 flex items-center gap-1">
                                                            <flux:icon name="package" class="w-3 h-3" />
                                                            <span>{{ $variant['stock_level'] ?? 0 }}</span>
                                                        </div>
                                                        
                                                        {{-- Variant Action Buttons --}}
                                                        <div class="flex gap-1">
                                                            <flux:button 
                                                                href="{{ route('variants.edit', $variant['id']) }}" 
                                                                size="xs" 
                                                                variant="ghost" 
                                                                icon="pencil"
                                                                class="text-gray-400 hover:text-gray-600"
                                                            />
                                                            <flux:button 
                                                                href="{{ route('variants.show', $variant['id']) }}" 
                                                                size="xs" 
                                                                variant="ghost" 
                                                                icon="eye"
                                                                class="text-gray-400 hover:text-gray-600"
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <div class="flex flex-col items-center gap-4">
                                <flux:icon name="squares-plus" class="w-12 h-12 text-gray-400" />
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white">No products found</h4>
                                    <p class="text-gray-500 dark:text-gray-400 mt-1">
                                        @if($search)
                                            No products match "{{ $search }}" - try adjusting your search
                                        @else
                                            Start building your amazing product catalog!
                                        @endif
                                    </p>
                                </div>
                                <div class="flex gap-3">
                                    @if($search)
                                        <flux:button wire:click="$set('search', '')" size="sm" variant="outline">
                                            Clear Search
                                        </flux:button>
                                    @endif
                                    <flux:button href="{{ route('products.wizard') }}" variant="primary" size="sm">
                                        Create First Product
                                    </flux:button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        
        {{-- Enhanced Pagination with Stats --}}
        @if($products->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
                    </div>
                    {{ $products->links() }}
                </div>
            </div>
        @endif
    </div>
</div>