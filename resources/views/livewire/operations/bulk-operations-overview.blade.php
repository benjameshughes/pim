<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'Overview']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - Overview</flux:heading>
        <flux:subheading>Select and manage multiple products and variants efficiently</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            <!-- Search & Filter Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Select Variants for Bulk Operations</flux:heading>
                    <div class="flex items-center gap-3">
                        <flux:field class="flex items-center gap-2">
                            <flux:checkbox wire:model.live="selectAll" />
                            <flux:label>Select All ({{ number_format(count($selectedVariants)) }} selected)</flux:label>
                        </flux:field>
                    </div>
                </div>
                
                <!-- Search Controls -->
                <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 border border-zinc-200 dark:border-zinc-600">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <flux:field>
                                <flux:label>Search Products & Variants</flux:label>
                                <flux:input 
                                    wire:model.live.debounce.300ms="search"
                                    type="search"
                                    placeholder="Search by SKU, barcode, or product name..."
                                    icon="magnifying-glass"
                                />
                            </flux:field>
                        </div>
                        <div>
                            <flux:field>
                                <flux:label>Search In</flux:label>
                                <flux:select wire:model.live="searchFilter">
                                    <flux:select.option value="all">All Fields</flux:select.option>
                                    <flux:select.option value="parent_sku">Parent SKU Only</flux:select.option>
                                    <flux:select.option value="variant_sku">Variant SKU Only</flux:select.option>
                                    <flux:select.option value="barcode">Barcode Only</flux:select.option>
                                </flux:select>
                            </flux:field>
                        </div>
                    </div>
                    
                    @if($search)
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                Searching for "{{ $search }}" in {{ $searchFilter === 'all' ? 'all fields' : str_replace('_', ' ', $searchFilter) }}
                            </div>
                            <flux:button wire:click="$set('search', '')" variant="ghost" size="sm">
                                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                                Clear
                            </flux:button>
                        </div>
                    @endif
                </div>
                
                <!-- Results Summary -->
                <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400">
                    <div>
                        Showing {{ $products->count() }} of {{ $products->total() }} products
                        @if($search)
                            (filtered by "{{ $search }}")
                        @endif
                    </div>
                    @if(count($selectedVariants) > 0)
                        <div class="bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 px-3 py-1 rounded-full text-xs font-medium">
                            {{ count($selectedVariants) }} variants selected
                            @if(count($selectedProducts) > 0)
                                ({{ count($selectedProducts) }} products)
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-600">
                        <thead class="bg-zinc-50 dark:bg-zinc-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Select</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Product</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Variants</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-600">
                            @foreach($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:checkbox 
                                        wire:model.live="selectedProducts" 
                                        value="{{ $product->id }}" />
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $product->name }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono bg-zinc-50 dark:bg-zinc-800 px-2 py-1 rounded mt-1 inline-block">
                                        {{ $product->parent_sku }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-zinc-900 dark:text-zinc-100">
                                        {{ $product->variants->count() }} variants
                                    </div>
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm" 
                                        wire:click="toggleProductExpansion({{ $product->id }})"
                                        class="text-xs mt-1">
                                        @if(in_array($product->id, $expandedProducts))
                                            <flux:icon name="chevron-up" class="w-3 h-3 mr-1" />
                                            Hide
                                        @else
                                            <flux:icon name="chevron-down" class="w-3 h-3 mr-1" />
                                            Show
                                        @endif
                                    </flux:button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $variantsWithBarcodes = $product->variants->filter(fn($v) => $v->barcodes->isNotEmpty())->count();
                                        $variantsWithPricing = $product->variants->filter(fn($v) => $v->pricing->isNotEmpty())->count();
                                    @endphp
                                    <div class="text-xs space-y-1">
                                        <div class="flex items-center">
                                            <span class="w-16">Barcodes:</span>
                                            <span class="{{ $variantsWithBarcodes === $product->variants->count() ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ $variantsWithBarcodes }}/{{ $product->variants->count() }}
                                            </span>
                                        </div>
                                        <div class="flex items-center">
                                            <span class="w-16">Pricing:</span>
                                            <span class="{{ $variantsWithPricing === $product->variants->count() ? 'text-emerald-600' : 'text-amber-600' }}">
                                                {{ $variantsWithPricing }}/{{ $product->variants->count() }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex gap-2">
                                        <flux:button 
                                            variant="ghost" 
                                            size="sm"
                                            wire:click="navigateToTab('templates')"
                                            class="text-purple-600 hover:text-purple-700">
                                            Templates
                                        </flux:button>
                                        <flux:button 
                                            variant="ghost" 
                                            size="sm"
                                            wire:click="navigateToTab('attributes')"
                                            class="text-blue-600 hover:text-blue-700">
                                            Attributes
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Expanded Variants -->
                            @if(in_array($product->id, $expandedProducts))
                                @foreach($product->variants as $variant)
                                <tr wire:key="variant-{{ $variant->id }}" class="bg-zinc-25 dark:bg-zinc-750">
                                    <td class="px-6 py-3 pl-12">
                                        <flux:checkbox 
                                            wire:model.live="selectedVariants" 
                                            value="{{ $variant->id }}" />
                                    </td>
                                    <td class="px-6 py-3 pl-12">
                                        <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                            @if($variant->color || $variant->size)
                                                {{ $variant->color }} {{ $variant->size }}
                                            @else
                                                Variant
                                            @endif
                                        </div>
                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono">
                                            {{ $variant->sku }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        @if($variant->barcodes->isNotEmpty())
                                            @php $primaryBarcode = $variant->barcodes->where('is_primary', true)->first() ?? $variant->barcodes->first(); @endphp
                                            <div class="text-xs font-mono text-emerald-700 dark:text-emerald-300">
                                                {{ $primaryBarcode->barcode }}
                                            </div>
                                        @else
                                            <div class="text-xs text-zinc-400 italic">No barcode</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="text-xs">
                                            {{ $variant->attributes->count() }} attributes
                                        </div>
                                    </td>
                                    <td class="px-6 py-3"></td>
                                </tr>
                                @endforeach
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            @if($products->hasPages())
                <div class="mt-6">
                    {{ $products->links() }}
                </div>
            @endif

            <!-- Selected Variants Actions -->
            @if(count($selectedVariants) > 0)
                <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                {{ count($selectedVariants) }} variants selected
                            </h3>
                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                Choose an operation to perform on selected variants
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <flux:button 
                                variant="outline" 
                                size="sm"
                                wire:click="navigateToTab('templates')"
                                class="border-blue-300 text-blue-700 hover:bg-blue-100">
                                <flux:icon name="layout-grid" class="w-4 h-4 mr-2" />
                                Generate Templates
                            </flux:button>
                            <flux:button 
                                variant="outline" 
                                size="sm"
                                wire:click="navigateToTab('attributes')"
                                class="border-blue-300 text-blue-700 hover:bg-blue-100">
                                <flux:icon name="tag" class="w-4 h-4 mr-2" />
                                Bulk Attributes
                            </flux:button>
                            <flux:button 
                                variant="outline" 
                                size="sm"
                                wire:click="navigateToTab('quality')"
                                class="border-blue-300 text-blue-700 hover:bg-blue-100">
                                <flux:icon name="shield-check" class="w-4 h-4 mr-2" />
                                Quality Check
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-route-tabs>
</div>