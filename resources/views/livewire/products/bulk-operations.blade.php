<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <!-- Breadcrumb -->
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => 'Bulk Operations']
            ]" class="mb-4" />
            
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center">
                            <flux:icon name="zap" class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                Bulk Operations & Automation
                            </flux:heading>
                            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                Manage data at scale with AI-powered tools
                            </flux:subheading>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <button type="button" 
                        wire:click="setActiveTab('overview')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'overview' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="chart-bar" class="w-4 h-4 inline mr-2" />
                    Overview
                </button>
                
                <button type="button" 
                        wire:click="setActiveTab('templates')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'templates' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="layout-grid" class="w-4 h-4 inline mr-2" />
                    Title Templates
                </button>
                
                <button type="button" 
                        wire:click="setActiveTab('attributes')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'attributes' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="tag" class="w-4 h-4 inline mr-2" />
                    Bulk Attributes
                </button>
                
                <button type="button" 
                        wire:click="setActiveTab('quality')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'quality' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="shield-check" class="w-4 h-4 inline mr-2" />
                    Data Quality
                </button>
                
                <button type="button" 
                        wire:click="setActiveTab('recommendations')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'recommendations' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="lightbulb" class="w-4 h-4 inline mr-2" />
                    Smart Recommendations
                </button>
                
                <button type="button" 
                        wire:click="setActiveTab('ai')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'ai' ? 'border-purple-500 text-purple-600 dark:text-purple-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon name="zap" class="w-4 h-4 inline mr-2" />
                    AI Assistant
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            @if($activeTab === 'overview')
                <!-- Overview Tab -->
                <div class="space-y-6">
                    
                    <!-- Search & Filter Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="lg">Select Variants for Bulk Operations</flux:heading>
                            <div class="flex items-center gap-3">
                                <flux:field class="flex items-center gap-2">
                                    <flux:checkbox wire:model.live="selectAll" wire:change="toggleSelectAll" />
                                    <flux:label>Select All ({{ number_format(count($selectedVariants)) }} selected)</flux:label>
                                </flux:field>
                            </div>
                        </div>
                        
                        <!-- Search Controls -->
                        <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 border border-zinc-200 dark:border-zinc-600">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <flux:field>
                                        <flux:label>Search Variants</flux:label>
                                        <flux:input 
                                            wire:model.live.debounce.300ms="search"
                                            type="search"
                                            placeholder="Search by SKU, barcode, or product name..."
                                            icon="search"
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
                                        <flux:icon name="x" class="w-4 h-4 mr-1" />
                                        Clear
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Results Summary -->
                        <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-400">
                            <div>
                                @if($isSearching)
                                    Showing {{ $variants->count() }} of {{ $variants->total() }} variants
                                    (filtered by "{{ $search }}")
                                @else
                                    Showing {{ $products->count() }} of {{ $products->total() }} products
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
                        
                        <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600 overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-600">
                                    <thead class="bg-zinc-50 dark:bg-zinc-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Select</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                                {{ $isSearching ? 'Variant Title' : 'Product' }}
                                            </th>
                                            @if($isSearching)
                                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Barcode</th>
                                            @endif
                                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Marketplaces</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Attributes</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-600">
                                        @if($isSearching)
                                            {{-- Search Mode: Show matching variants --}}
                                            @foreach($variants as $variant)
                                            <tr wire:key="variant-{{ $variant->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <flux:checkbox 
                                                        wire:model.live="selectedVariants" 
                                                        value="{{ $variant->id }}" />
                                                </td>
                                                <td class="px-6 py-4">
                                                    @php
                                                        // Build comprehensive product title
                                                        $title = $variant->product->name;
                                                        $width = $variant->width ?? $variant->attributes->where('attribute_key', 'width')->first()?->attribute_value;
                                                        $drop = $variant->drop ?? $variant->attributes->where('attribute_key', 'drop')->first()?->attribute_value;
                                                        $color = $variant->color ?? $variant->attributes->where('attribute_key', 'color')->first()?->attribute_value;
                                                        
                                                        $parts = [];
                                                        if ($width) $parts[] = $width . (str_contains($width, 'cm') ? '' : 'cm');
                                                        if ($drop) $parts[] = $drop . (str_contains($drop, 'cm') ? '' : 'cm');  
                                                        if ($color) $parts[] = $color;
                                                        
                                                        $fullTitle = $title . ($parts ? ' - ' . implode(' x ', $parts) : '');
                                                    @endphp
                                                    
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100 leading-relaxed">
                                                        {{ $fullTitle }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono bg-zinc-50 dark:bg-zinc-800 px-2 py-1 rounded mt-2 inline-block">
                                                        {{ $variant->sku }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($variant->barcodes->isNotEmpty())
                                                        @php $primaryBarcode = $variant->barcodes->where('is_primary', true)->first() ?? $variant->barcodes->first(); @endphp
                                                        <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100 bg-green-50 dark:bg-green-900/20 px-2 py-1 rounded border border-green-200 dark:border-green-800">
                                                            {{ $primaryBarcode->barcode }}
                                                        </div>
                                                        @if($variant->barcodes->count() > 1)
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                                +{{ $variant->barcodes->count() - 1 }} more
                                                            </div>
                                                        @endif
                                                    @else
                                                        <div class="text-sm text-zinc-400 dark:text-zinc-500 italic">No barcode</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {{ $variant->marketplaceVariants->count() }} / {{ $marketplaces->count() }}
                                                    </div>
                                                    @if($variant->marketplaceVariants->count() < $marketplaces->count())
                                                        <span class="text-xs text-amber-600 dark:text-amber-400">Incomplete</span>
                                                    @else
                                                        <span class="text-xs text-emerald-600 dark:text-emerald-400">Complete</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {{ $variant->attributes->count() }} variant
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <flux:button variant="ghost" size="sm" wire:click="previewTitle({{ $variant->id }})">
                                                        <flux:icon name="eye" class="w-4 h-4" />
                                                    </flux:button>
                                                </td>
                                            </tr>
                                            @endforeach
                                        @else
                                            {{-- Default Mode: Show products with expandable variants --}}
                                            @foreach($products as $product)
                                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if(in_array($product->id, $selectedProducts))
                                                        <flux:checkbox checked wire:click="toggleProduct({{ $product->id }})" />
                                                    @else
                                                        <flux:checkbox wire:click="toggleProduct({{ $product->id }})" />
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="flex items-center gap-3">
                                                        @if($product->variants_count > 0)
                                                            <flux:button 
                                                                size="sm" 
                                                                variant="ghost" 
                                                                icon="{{ in_array($product->id, $expandedProducts) ? 'chevron-down' : 'chevron-right' }}"
                                                                wire:click="toggleProductExpansion({{ $product->id }})"
                                                                class="!p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300"
                                                            />
                                                        @endif
                                                        <div class="flex-1">
                                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                                {{ $product->name }}
                                                            </div>
                                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono bg-zinc-50 dark:bg-zinc-800 px-2 py-1 rounded mt-2 inline-block">
                                                                {{ $product->parent_sku ?? 'No SKU' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {{ $product->variants_count }} variants
                                                    </div>
                                                    @if(in_array($product->id, $selectedProducts))
                                                        <span class="text-xs text-blue-600 dark:text-blue-400">All selected</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        Product level
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <flux:button variant="ghost" size="sm">
                                                        <flux:icon name="ellipsis-horizontal" class="w-4 h-4" />
                                                    </flux:button>
                                                </td>
                                            </tr>
                                            
                                            {{-- Expanded Variants --}}
                                            @if(in_array($product->id, $expandedProducts) && isset($product->expandedVariants))
                                                @foreach($product->expandedVariants as $variant)
                                                <tr wire:key="variant-{{ $variant->id }}" class="bg-gradient-to-r from-slate-50/50 to-blue-50/20 dark:from-slate-800/50 dark:to-blue-950/10 border-l-4 border-blue-200 dark:border-blue-700">
                                                    <td class="px-6 py-3 pl-12">
                                                        <flux:checkbox 
                                                            wire:model.live="selectedVariants" 
                                                            value="{{ $variant->id }}" />
                                                    </td>
                                                    <td class="px-6 py-3">
                                                        @php
                                                            $width = $variant->width ?? $variant->attributes->where('attribute_key', 'width')->first()?->attribute_value;
                                                            $drop = $variant->drop ?? $variant->attributes->where('attribute_key', 'drop')->first()?->attribute_value;
                                                            $color = $variant->color ?? $variant->attributes->where('attribute_key', 'color')->first()?->attribute_value;
                                                            
                                                            $parts = [];
                                                            if ($width) $parts[] = $width . (str_contains($width, 'cm') ? '' : 'cm');
                                                            if ($drop) $parts[] = $drop . (str_contains($drop, 'cm') ? '' : 'cm');  
                                                            if ($color) $parts[] = $color;
                                                            
                                                            $variantTitle = $parts ? implode(' x ', $parts) : 'Standard';
                                                        @endphp
                                                        <div class="flex items-center gap-2">
                                                            <flux:icon name="corner-down-right" class="h-4 w-4 text-zinc-400" />
                                                            <div>
                                                                <div class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                                                    {{ $variantTitle }}
                                                                </div>
                                                                <div class="text-xs text-zinc-500 dark:text-zinc-400 font-mono bg-indigo-50 dark:bg-indigo-900/20 px-2 py-1 rounded mt-1 inline-block">
                                                                    {{ $variant->sku }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-3">
                                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                            {{ $variant->marketplaceVariants->count() }} / {{ $marketplaces->count() }}
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-3">
                                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                            {{ $variant->attributes->count() }} attrs
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-3 text-right">
                                                        <flux:button variant="ghost" size="sm" wire:click="previewTitle({{ $variant->id }})">
                                                            <flux:icon name="eye" class="w-4 h-4" />
                                                        </flux:button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @endif
                                            @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            @if($isSearching)
                                {{ $variants->links() }}
                            @else
                                {{ $products->links() }}
                            @endif
                        </div>
                    </div>
                </div>

            @elseif($activeTab === 'templates')
                <!-- Template Generation Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="layout-grid" class="h-5 w-5" />
                        AI-Powered Title & Description Templates
                    </flux:heading>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Template Configuration -->
                        <div class="space-y-6">
                            <div class="bg-gradient-to-br from-indigo-50 to-purple-100 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-6 border border-indigo-200 dark:border-indigo-800">
                                <flux:heading size="base" class="mb-4">Template Variables Available</flux:heading>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Brand]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[ProductName]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Color]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Size]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Material]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[SKU]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Marketplace]</code>
                                    <code class="bg-white/50 dark:bg-black/20 px-2 py-1 rounded">[Platform]</code>
                                </div>
                                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-3">Plus any product or variant attributes you've defined!</p>
                            </div>
                            
                            <flux:field>
                                <flux:label>Title Template</flux:label>
                                <flux:textarea wire:model="titleTemplate" rows="3" placeholder="e.g., [Brand] [ProductName] - [Color] [Size] | Premium Quality [Material]" />
                            </flux:field>
                            
                            <flux:field>
                                <flux:label>Description Template</flux:label>
                                <flux:textarea wire:model="descriptionTemplate" rows="4" placeholder="e.g., High-quality [Material] [ProductName] in [Color]. Perfect for [RoomType]. [Features]" />
                            </flux:field>
                            
                            <flux:field>
                                <flux:label>Target Marketplaces</flux:label>
                                <div class="grid grid-cols-2 gap-2 mt-2">
                                    @foreach($marketplaces as $marketplace)
                                    <flux:field class="flex items-center gap-2">
                                        <flux:checkbox wire:model="selectedMarketplaces" value="{{ $marketplace->id }}" />
                                        <flux:label>{{ $marketplace->name }}</flux:label>
                                    </flux:field>
                                    @endforeach
                                </div>
                            </flux:field>
                            
                            <flux:button 
                                wire:click="generateTitles" 
                                variant="primary" 
                                class="w-full"
                                :disabled="empty($selectedVariants)">
                                <flux:icon name="zap" class="w-4 h-4 mr-2" />
                                Generate Titles for {{ count($selectedVariants) }} Selected Variants
                            </flux:button>
                        </div>
                        
                        <!-- Preview Section -->
                        <div class="space-y-6">
                            @if($showTitlePreview && $previewVariant)
                            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-6 border border-zinc-200 dark:border-zinc-600">
                                <flux:heading size="base" class="mb-4">Preview for {{ $previewVariant->sku }}</flux:heading>
                                <div class="space-y-4">
                                    @foreach($this->getPreviewTitles() as $preview)
                                    <div class="bg-white dark:bg-zinc-800 rounded-lg p-4 border border-zinc-200 dark:border-zinc-600">
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100 mb-2">{{ $preview['marketplace'] }}</div>
                                        <div class="text-sm text-zinc-700 dark:text-zinc-300 mb-1"><strong>Title:</strong> {{ $preview['title'] }}</div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400"><strong>Description:</strong> {{ $preview['description'] }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @else
                            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-6 border border-zinc-200 dark:border-zinc-600 text-center">
                                <flux:icon name="eye" class="w-12 h-12 text-zinc-400 mx-auto mb-3" />
                                <p class="text-zinc-600 dark:text-zinc-400">Select a variant from the overview tab to see preview</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

            @elseif($activeTab === 'attributes')
                <!-- Bulk Attributes Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="tag" class="h-5 w-5" />
                        Bulk Attribute Management
                    </flux:heading>
                    
                    @if(empty($selectedVariants))
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-6 border border-amber-200 dark:border-amber-800 text-center">
                            <flux:icon name="triangle-alert" class="w-12 h-12 text-amber-600 dark:text-amber-400 mx-auto mb-3" />
                            <h3 class="text-lg font-medium text-amber-900 dark:text-amber-100 mb-2">No Variants Selected</h3>
                            <p class="text-amber-700 dark:text-amber-300 mb-4">Please select variants from the Overview tab to manage their attributes.</p>
                            <flux:button wire:click="setActiveTab('overview')" variant="outline">
                                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                                Go to Overview
                            </flux:button>
                        </div>
                    @else
                        <div class="bg-gradient-to-br from-emerald-50 to-green-100 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-800 mb-6">
                            <p class="text-emerald-800 dark:text-emerald-200 text-sm">
                                Managing attributes for {{ count($selectedVariants) }} selected variants. 
                                Product attributes apply to all variants of a product, while variant attributes are specific to each variant.
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Existing Attributes -->
                            <div class="space-y-6">
                                <div class="flex items-center justify-between">
                                    <flux:heading size="base">Existing Attributes</flux:heading>
                                    <flux:button wire:click="getExistingAttributes" variant="ghost" size="sm">
                                        <flux:icon name="refresh-ccw" class="w-4 h-4 mr-2" />
                                        Refresh
                                    </flux:button>
                                </div>
                                
                                @php $existingAttrs = $this->getExistingAttributes(); @endphp
                                
                                @if(empty($existingAttrs['product']) && empty($existingAttrs['variant']))
                                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-6 border border-zinc-200 dark:border-zinc-600 text-center">
                                        <flux:icon name="tag" class="w-8 h-8 text-zinc-400 mx-auto mb-2" />
                                        <p class="text-zinc-600 dark:text-zinc-400 text-sm">No existing attributes found for selected variants.</p>
                                        <p class="text-zinc-500 dark:text-zinc-500 text-xs mt-1">Create new attributes using the form on the right.</p>
                                    </div>
                                @else
                                    <div class="space-y-4">
                                        @if(!empty($existingAttrs['product']))
                                            <div>
                                                <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-3">Product Attributes</h4>
                                                <div class="space-y-2">
                                                    @foreach($existingAttrs['product'] as $attr)
                                                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex-1">
                                                                    <div class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $attr['key'] }}</div>
                                                                    <div class="text-xs text-blue-700 dark:text-blue-300">{{ $attr['summary'] }}</div>
                                                                    <div class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                                        {{ ucfirst($attr['data_type']) }} • {{ ucfirst($attr['category']) }}
                                                                        @if(!$attr['is_consistent'])
                                                                            <span class="ml-2 text-amber-600 dark:text-amber-400">⚠ Inconsistent values</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <flux:button 
                                                                    wire:click="$set('selectedExistingAttribute', 'product:{{ $attr['key'] }}')"
                                                                    variant="ghost" 
                                                                    size="sm">
                                                                    Update
                                                                </flux:button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        
                                        @if(!empty($existingAttrs['variant']))
                                            <div>
                                                <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-100 mb-3">Variant Attributes</h4>
                                                <div class="space-y-2">
                                                    @foreach($existingAttrs['variant'] as $attr)
                                                        <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-3">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex-1">
                                                                    <div class="text-sm font-medium text-purple-900 dark:text-purple-100">{{ $attr['key'] }}</div>
                                                                    <div class="text-xs text-purple-700 dark:text-purple-300">{{ $attr['summary'] }}</div>
                                                                    <div class="text-xs text-purple-600 dark:text-purple-400 mt-1">
                                                                        {{ ucfirst($attr['data_type']) }} • {{ ucfirst($attr['category']) }}
                                                                        @if(!$attr['is_consistent'])
                                                                            <span class="ml-2 text-amber-600 dark:text-amber-400">⚠ Inconsistent values</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <flux:button 
                                                                    wire:click="$set('selectedExistingAttribute', 'variant:{{ $attr['key'] }}')"
                                                                    variant="ghost" 
                                                                    size="sm">
                                                                    Update
                                                                </flux:button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        
                                        <!-- Update Existing Attribute Form -->
                                        @if($selectedExistingAttribute)
                                            @php 
                                                [$selectedType, $selectedKey] = explode(':', $selectedExistingAttribute, 2);
                                            @endphp
                                            <div class="bg-gradient-to-br from-indigo-50 to-purple-100 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
                                                <h4 class="text-sm font-medium text-indigo-900 dark:text-indigo-100 mb-3">
                                                    Update {{ ucfirst($selectedType) }} Attribute: {{ $selectedKey }}
                                                </h4>
                                                <div class="space-y-3">
                                                    <flux:field>
                                                        <flux:label>New Value</flux:label>
                                                        <flux:input wire:model="updateAttributeValue" placeholder="Enter new value" />
                                                    </flux:field>
                                                    <div class="flex gap-2">
                                                        <flux:button 
                                                            wire:click="updateExistingAttribute"
                                                            variant="primary"
                                                            size="sm"
                                                            :disabled="!$updateAttributeValue">
                                                            <flux:icon name="check" class="w-4 h-4 mr-2" />
                                                            Update {{ count($selectedVariants) }} Items
                                                        </flux:button>
                                                        <flux:button 
                                                            wire:click="$set('selectedExistingAttribute', '')"
                                                            variant="ghost"
                                                            size="sm">
                                                            Cancel
                                                        </flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            
                            <!-- Create New Attributes -->
                            <div class="space-y-6">
                                <flux:heading size="base">Create New Attribute</flux:heading>
                                
                                <div class="space-y-4">
                                    <flux:field>
                                        <flux:label>Attribute Type</flux:label>
                                        <flux:select wire:model="bulkAttributeType">
                                            <flux:select.option value="product">Product Attribute (applies to all variants)</flux:select.option>
                                            <flux:select.option value="variant">Variant Attribute (specific to each variant)</flux:select.option>
                                        </flux:select>
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Attribute Key</flux:label>
                                        <flux:input wire:model="bulkAttributeKey" placeholder="e.g., material, warranty_years, width_mm" />
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Attribute Value</flux:label>
                                        <flux:input wire:model="bulkAttributeValue" placeholder="e.g., Aluminum, 5, 1200" />
                                    </flux:field>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <flux:field>
                                            <flux:label>Data Type</flux:label>
                                            <flux:select wire:model="bulkAttributeDataType">
                                                <flux:select.option value="string">Text</flux:select.option>
                                                <flux:select.option value="number">Number</flux:select.option>
                                                <flux:select.option value="boolean">Yes/No</flux:select.option>
                                                <flux:select.option value="json">JSON Data</flux:select.option>
                                            </flux:select>
                                        </flux:field>
                                        
                                        <flux:field>
                                            <flux:label>Category</flux:label>
                                            <flux:select wire:model="bulkAttributeCategory">
                                                <flux:select.option value="general">General</flux:select.option>
                                                <flux:select.option value="physical">Physical</flux:select.option>
                                                <flux:select.option value="functional">Functional</flux:select.option>
                                                <flux:select.option value="compliance">Compliance</flux:select.option>
                                            </flux:select>
                                        </flux:field>
                                    </div>
                                    
                                    <flux:button 
                                        wire:click="applyBulkAttribute" 
                                        variant="primary" 
                                        class="w-full"
                                        :disabled="!$bulkAttributeKey || !$bulkAttributeValue">
                                        <flux:icon name="tag" class="w-4 h-4 mr-2" />
                                        Create Attribute for {{ count($selectedVariants) }} Items
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

            @elseif($activeTab === 'quality')
                <!-- Data Quality Tab -->
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg" class="flex items-center gap-2">
                            <flux:icon name="shield-check" class="h-5 w-5" />
                            Data Quality Scanner
                        </flux:heading>
                        
                        <flux:button wire:click="scanDataQuality" variant="primary" :loading="$qualityScanning">
                            <flux:icon name="search" class="w-4 h-4 mr-2" />
                            {{ $qualityScanning ? 'Scanning...' : 'Run Quality Scan' }}
                        </flux:button>
                    </div>
                    
                    @if(!empty($qualityResults))
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="bg-gradient-to-br from-red-50 to-pink-100 dark:from-red-900/20 dark:to-pink-900/20 rounded-lg p-6 border border-red-200 dark:border-red-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-red-900 dark:text-red-100">{{ number_format($qualityResults['missing_marketplace_variants']) }}</div>
                                    <div class="text-sm text-red-700 dark:text-red-300">Missing Marketplace Variants</div>
                                </div>
                                <flux:icon name="triangle-alert" class="h-8 w-8 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-amber-50 to-orange-100 dark:from-amber-900/20 dark:to-orange-900/20 rounded-lg p-6 border border-amber-200 dark:border-amber-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ number_format($qualityResults['products_without_attributes']) }}</div>
                                    <div class="text-sm text-amber-700 dark:text-amber-300">Products Without Attributes</div>
                                </div>
                                <flux:icon name="tag" class="h-8 w-8 text-amber-600 dark:text-amber-400" />
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-yellow-50 to-amber-100 dark:from-yellow-900/20 dark:to-amber-900/20 rounded-lg p-6 border border-yellow-200 dark:border-yellow-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ number_format($qualityResults['variants_without_asin']) }}</div>
                                    <div class="text-sm text-yellow-700 dark:text-yellow-300">Variants Without ASIN</div>
                                </div>
                                <flux:icon name="hashtag" class="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-50 to-violet-100 dark:from-purple-900/20 dark:to-violet-900/20 rounded-lg p-6 border border-purple-200 dark:border-purple-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ number_format($qualityResults['duplicate_asins']) }}</div>
                                    <div class="text-sm text-purple-700 dark:text-purple-300">Duplicate ASINs</div>
                                </div>
                                <flux:icon name="copy" class="h-8 w-8 text-purple-600 dark:text-purple-400" />
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ number_format($qualityResults['incomplete_titles']) }}</div>
                                    <div class="text-sm text-blue-700 dark:text-blue-300">Incomplete Titles</div>
                                </div>
                                <flux:icon name="pencil" class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-emerald-50 to-green-100 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-800">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format(($qualityResults['total_variants'] - $qualityResults['missing_marketplace_variants']) / $qualityResults['total_variants'] * 100, 1) }}%</div>
                                    <div class="text-sm text-emerald-700 dark:text-emerald-300">Data Completeness</div>
                                </div>
                                <flux:icon name="check-circle" class="h-8 w-8 text-emerald-600 dark:text-emerald-400" />
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-12 border border-zinc-200 dark:border-zinc-600 text-center">
                        <flux:icon name="shield-check" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                        <p class="text-zinc-600 dark:text-zinc-400 text-lg mb-2">Data Quality Scanner</p>
                        <p class="text-zinc-500 dark:text-zinc-500">Run a scan to identify data quality issues and optimization opportunities</p>
                    </div>
                    @endif
                </div>

            @elseif($activeTab === 'recommendations')
                <!-- Smart Recommendations Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="lightbulb" class="h-5 w-5" />
                        Smart Data Recommendations
                    </flux:heading>
                    
                    @if(empty($selectedVariants))
                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-6 border border-amber-200 dark:border-amber-800 text-center">
                            <flux:icon name="triangle-alert" class="w-12 h-12 text-amber-600 dark:text-amber-400 mx-auto mb-3" />
                            <h3 class="text-lg font-medium text-amber-900 dark:text-amber-100 mb-2">No Variants Selected</h3>
                            <p class="text-amber-700 dark:text-amber-300 mb-4">Select variants from the Overview tab to get smart recommendations.</p>
                            <flux:button wire:click="setActiveTab('overview')" variant="outline">
                                <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                                Go to Overview
                            </flux:button>
                        </div>
                    @else
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800 mb-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">Analyzing {{ count($selectedVariants) }} Selected Variants</h3>
                                    <p class="text-blue-700 dark:text-blue-300 text-sm">Get intelligent suggestions to improve your product data quality and marketplace readiness.</p>
                                </div>
                                <flux:button wire:click="loadSmartRecommendations" variant="primary">
                                    <flux:icon name="refresh-ccw" class="w-4 h-4 mr-2" />
                                    Analyze Data
                                </flux:button>
                            </div>
                        </div>
                        
                        @if($recommendationsLoaded && !empty($recommendations))
                            <!-- Data Health Score -->
                            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Data Health Score</h3>
                                    <div class="text-3xl font-bold {{ $recommendations['summary']['health_score'] >= 80 ? 'text-emerald-600' : ($recommendations['summary']['health_score'] >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                        {{ $recommendations['summary']['health_score'] }}%
                                    </div>
                                </div>
                                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                                    <div class="h-3 rounded-full {{ $recommendations['summary']['health_score'] >= 80 ? 'bg-emerald-500' : ($recommendations['summary']['health_score'] >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                         style="width: {{ $recommendations['summary']['health_score'] }}%"></div>
                                </div>
                                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $recommendations['summary']['total'] }} recommendations found
                                    @if($recommendations['summary']['critical'] > 0)
                                        • <span class="text-red-600 dark:text-red-400 font-medium">{{ $recommendations['summary']['critical'] }} critical issues</span>
                                    @endif
                                    @if($recommendations['summary']['quick_wins'] > 0)
                                        • <span class="text-emerald-600 dark:text-emerald-400 font-medium">{{ $recommendations['summary']['quick_wins'] }} quick wins</span>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Recommendations List -->
                            <div class="space-y-4">
                                @foreach($recommendations['recommendations'] as $recommendation)
                                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-3 mb-2">
                                                    <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                        {{ $recommendation['priority'] === 'critical' ? 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-300' : 
                                                           ($recommendation['priority'] === 'high' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300' : 
                                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300') }}">
                                                        {{ ucfirst($recommendation['priority']) }}
                                                    </span>
                                                    @if($recommendation['is_quick_win'])
                                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">
                                                            Quick Win
                                                        </span>
                                                    @endif
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $recommendation['estimated_time'] }}
                                                    </span>
                                                </div>
                                                
                                                <h4 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-2">
                                                    {{ $recommendation['title'] }}
                                                </h4>
                                                
                                                <p class="text-zinc-600 dark:text-zinc-400 mb-3">
                                                    {{ $recommendation['description'] }}
                                                </p>
                                                
                                                <div class="flex items-center gap-4 text-sm text-zinc-500 dark:text-zinc-400">
                                                    <span>{{ $recommendation['affected_count'] }} variants affected</span>
                                                    <span>Impact: {{ $recommendation['impact_score'] }}/100</span>
                                                    <span>Effort: {{ $recommendation['effort_score'] }}/100</span>
                                                </div>
                                            </div>
                                            
                                            <div class="ml-6">
                                                <flux:button 
                                                    wire:click="executeRecommendation('{{ $recommendation['id'] }}')"
                                                    variant="{{ $recommendation['priority'] === 'critical' ? 'danger' : 'primary' }}"
                                                    size="sm">
                                                    <flux:icon name="play" class="w-4 h-4 mr-2" />
                                                    Execute
                                                </flux:button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                        @elseif($recommendationsLoaded && empty($recommendations['recommendations']))
                            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-800 text-center">
                                <flux:icon name="check-circle" class="w-12 h-12 text-emerald-600 dark:text-emerald-400 mx-auto mb-3" />
                                <h3 class="text-lg font-medium text-emerald-900 dark:text-emerald-100 mb-2">Great Job!</h3>
                                <p class="text-emerald-700 dark:text-emerald-300">No data quality issues found for the selected variants. Your data is in excellent shape!</p>
                            </div>
                            
                        @elseif(!$recommendationsLoaded)
                            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-12 border border-zinc-200 dark:border-zinc-600 text-center">
                                <flux:icon name="lightbulb" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                                <p class="text-zinc-600 dark:text-zinc-400 text-lg mb-2">Smart Recommendations Ready</p>
                                <p class="text-zinc-500 dark:text-zinc-500">Click "Analyze Data" to get intelligent suggestions for improving your selected variants.</p>
                            </div>
                        @endif
                    @endif
                </div>

            @elseif($activeTab === 'ai')
                <!-- AI Assistant Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="flex items-center gap-2">
                        <flux:icon name="zap" class="h-5 w-5" />
                        AI Assistant Integration
                    </flux:heading>
                    
                    <div class="max-w-4xl">
                        <div class="bg-gradient-to-br from-indigo-50 to-purple-100 dark:from-indigo-900/20 dark:to-purple-900/20 rounded-lg p-6 border border-indigo-200 dark:border-indigo-800 mb-6">
                            <div class="flex items-start gap-4">
                                <flux:icon name="lightbulb" class="h-6 w-6 text-indigo-600 dark:text-indigo-400 mt-1" />
                                <div>
                                    <h3 class="font-medium text-indigo-900 dark:text-indigo-100 mb-2">AI-Powered Data Operations</h3>
                                    <p class="text-indigo-800 dark:text-indigo-200 text-sm">
                                        Ask the AI assistant to help with complex data operations, generate content, analyze patterns, or provide recommendations for your window shades catalog.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- AI Input -->
                            <div class="space-y-4">
                                <flux:field>
                                    <flux:label>Ask AI Assistant</flux:label>
                                    <flux:textarea 
                                        wire:model="aiPrompt" 
                                        rows="6" 
                                        placeholder="e.g., 'Generate SEO-optimized titles for my venetian blinds that are missing Amazon listings' or 'Analyze my product attributes and suggest missing data'" />
                                </flux:field>
                                
                                <div class="grid grid-cols-2 gap-2">
                                    <flux:button 
                                        wire:click="processAIRequest" 
                                        variant="primary"
                                        :loading="$aiProcessing"
                                        :disabled="!$aiPrompt">
                                        <flux:icon name="zap" class="w-4 h-4 mr-2" />
                                        Ask AI
                                    </flux:button>
                                    
                                    <flux:button 
                                        wire:click="generateAITitles" 
                                        variant="outline"
                                        :loading="$aiProcessing"
                                        :disabled="empty($selectedVariants) || empty($selectedMarketplaces)">
                                        <flux:icon name="wand-2" class="w-4 h-4 mr-2" />
                                        AI Titles
                                    </flux:button>
                                </div>
                                
                                <flux:button 
                                    wire:click="analyzeDataQuality" 
                                    variant="outline" 
                                    size="sm"
                                    class="w-full"
                                    :loading="$aiProcessing">
                                    <flux:icon name="search" class="w-4 h-4 mr-2" />
                                    {{ $aiProcessing ? 'Analyzing...' : 'AI Data Quality Analysis' }}
                                </flux:button>
                                
                                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                    <p class="mb-2"><strong>Example prompts:</strong></p>
                                    <ul class="list-disc list-inside space-y-1 text-xs">
                                        <li>Generate marketplace titles for products missing eBay listings</li>
                                        <li>Suggest missing attributes for my roller blind products</li>
                                        <li>Analyze pricing consistency across marketplaces</li>
                                        <li>Create SEO-optimized descriptions for Amazon</li>
                                        <li>Find products that need better categorization</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- AI Response -->
                            <div class="space-y-4">
                                <flux:field>
                                    <flux:label>AI Response</flux:label>
                                    <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 border border-zinc-200 dark:border-zinc-600 min-h-[200px]">
                                        @if($aiResponse)
                                            <pre class="whitespace-pre-wrap text-sm text-zinc-900 dark:text-zinc-100">{{ $aiResponse }}</pre>
                                        @else
                                            <div class="flex items-center justify-center h-full text-zinc-500 dark:text-zinc-400">
                                                <div class="text-center">
                                                    <flux:icon name="message-square" class="w-8 h-8 mx-auto mb-2" />
                                                    <p>AI responses will appear here</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </flux:field>
                                
                                @if($aiResponse)
                                <div class="flex gap-2">
                                    <flux:button variant="outline" size="sm">
                                        <flux:icon name="copy" class="w-4 h-4 mr-2" />
                                        Copy Response
                                    </flux:button>
                                    <flux:button variant="outline" size="sm" wire:click="$set('aiResponse', '')">
                                        <flux:icon name="trash-2" class="w-4 h-4 mr-2" />
                                        Clear
                                    </flux:button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Title Preview Modal -->
    @if($showTitlePreview)
    <flux:modal wire:model="showTitlePreview" class="max-w-4xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Title Preview - {{ $previewVariant->sku ?? '' }}</flux:heading>
            
            <div class="space-y-4 mb-6">
                @foreach($this->getPreviewTitles() as $preview)
                <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 border border-zinc-200 dark:border-zinc-600">
                    <div class="font-medium text-zinc-900 dark:text-zinc-100 mb-2 flex items-center gap-2">
                        <flux:icon name="globe" class="w-4 h-4" />
                        {{ $preview['marketplace'] }}
                    </div>
                    <div class="text-sm space-y-2">
                        <div><strong>Title:</strong> {{ $preview['title'] }}</div>
                        <div><strong>Description:</strong> {{ $preview['description'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            
            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="outline">Close</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
    @endif
</div>