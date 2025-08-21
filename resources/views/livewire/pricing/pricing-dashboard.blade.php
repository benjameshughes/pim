{{-- 💰🎭 PRICING DASHBOARD - THE FINANCIAL COMMAND CENTER! 🎭💰 --}}
<div class="space-y-6">
    {{-- 🎪 HEADER WITH FINANCIAL SASS --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Pricing Management</h2>
            <p class="text-gray-600 dark:text-gray-400">Manage your financial empire with style ✨</p>
        </div>
        
        {{-- 🔄 REFRESH ANALYSIS BUTTON --}}
        <flux:button wire:click="refreshAnalysis" variant="ghost" size="sm" icon="refresh-cw">
            Refresh Analysis
        </flux:button>
    </div>

    {{-- ⚡ LOADING OVERLAY --}}
    @if($isLoading)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl">
                <div class="flex items-center gap-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-600"></div>
                    <div>
                        <h3 class="font-medium text-gray-900 dark:text-white">Processing...</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $loadingMessage }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 🎨 TAB NAVIGATION --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <button wire:click="switchTab('overview')" 
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'overview' 
                              ? 'border-green-500 text-green-600 dark:text-green-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                💰 Overview
            </button>
            
            <button wire:click="switchTab('pricing')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'pricing' 
                              ? 'border-green-500 text-green-600 dark:text-green-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📋 Pricing Table
            </button>
            
            <button wire:click="switchTab('channels')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'channels' 
                              ? 'border-green-500 text-green-600 dark:text-green-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                🛍️ Channels
            </button>
            
            <button wire:click="switchTab('analysis')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'analysis' 
                              ? 'border-green-500 text-green-600 dark:text-green-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📊 Analytics
            </button>
            
            <button wire:click="switchTab('bulk')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'bulk' 
                              ? 'border-green-500 text-green-600 dark:text-green-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                🚀 Bulk Actions
            </button>
        </nav>
    </div>

    {{-- 📊 OVERVIEW TAB --}}
    @if($activeTab === 'overview')
        <div class="space-y-6">
            {{-- 🎯 MULTI-SELECT PRODUCT & VARIANT FILTERS --}}
            <div class="space-y-4">
                {{-- Selected Items Display --}}
                @if(!empty($selectedProducts) || !empty($selectedVariants))
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl p-4 border border-purple-200 dark:border-purple-700">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-purple-900 dark:text-purple-100">✨ Active Filters</h3>
                            <flux:button wire:click="clearAllFilters" variant="ghost" size="sm" icon="x">
                                Clear All
                            </flux:button>
                        </div>
                        
                        <div class="flex flex-wrap gap-2">
                            {{-- Selected Products --}}
                            @foreach($selectedProducts as $productId)
                                @php $product = \App\Models\Product::find($productId) @endphp
                                @if($product)
                                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                        📦 {{ $product->name }}
                                        <button wire:click="removeProduct({{ $productId }})" class="text-green-600 hover:text-green-800">×</button>
                                    </span>
                                @endif
                            @endforeach
                            
                            {{-- Selected Variants --}}
                            @foreach($selectedVariants as $variantId)
                                @php $variant = \App\Models\ProductVariant::with('product')->find($variantId) @endphp
                                @if($variant)
                                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                        💎 {{ $variant->product->name }} - {{ $variant->sku }}
                                        <button wire:click="removeVariant({{ $variantId }})" class="text-blue-600 hover:text-blue-800">×</button>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Search Controls --}}
                <div class="flex gap-4">
                    {{-- Product Search --}}
                    <div class="flex-1">
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="productSearchQuery" 
                                       placeholder="🔍 Search products..." 
                                       icon="search" />
                            @if($productSearchQuery && isset($searchableProducts) && $searchableProducts->count() > 0)
                                <div class="absolute top-full left-0 right-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 mt-1">
                                    @foreach($searchableProducts as $product)
                                        <button wire:click="addProduct({{ $product->id }})" 
                                                class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                            <span class="text-green-600">📦</span>
                                            <span class="font-medium">{{ $product->name }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Variant Search --}}
                    <div class="flex-1">
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="variantSearchQuery" 
                                       placeholder="🔍 Search variants..." 
                                       icon="search" />
                            @if($variantSearchQuery && isset($searchableVariants) && $searchableVariants->count() > 0)
                                <div class="absolute top-full left-0 right-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 mt-1">
                                    @foreach($searchableVariants as $variant)
                                        <button wire:click="addVariant({{ $variant->id }})" 
                                                class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                            <span class="text-blue-600">💎</span>
                                            <div>
                                                <span class="font-medium">{{ $variant->sku }}</span>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $variant->product->name }}</div>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- ✨ FINANCIAL STATISTICS CARDS --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">£{{ number_format($stats['total_revenue'] ?? 0, 2) }}</p>
                        </div>
                        <flux:icon name="dollar-sign" class="w-8 h-8 text-green-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Profit</p>
                            <p class="text-2xl font-bold text-blue-600">£{{ number_format($stats['total_profit'] ?? 0, 2) }}</p>
                        </div>
                        <flux:icon name="trending-up" class="w-8 h-8 text-blue-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Average Margin</p>
                            <p class="text-2xl font-bold text-purple-600">{{ number_format($stats['average_margin'] ?? 0, 1) }}%</p>
                        </div>
                        <flux:icon name="chart-bar" class="w-8 h-8 text-purple-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Products</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_products'] ?? 0 }}</p>
                        </div>
                        <flux:icon name="box" class="w-8 h-8 text-gray-500" />
                    </div>
                </div>
            </div>

            {{-- 🛍️ CHANNEL PERFORMANCE --}}
            @if(!empty($channelBreakdown))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Channel Performance</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($channelBreakdown as $channelName => $data)
                            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                                <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $channelName }}</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Revenue:</span>
                                        <span class="font-medium">£{{ number_format($data['total_revenue'] ?? 0, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Profit:</span>
                                        <span class="font-medium">£{{ number_format($data['total_profit'] ?? 0, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Margin:</span>
                                        <span class="font-medium">{{ number_format($data['average_margin'] ?? 0, 1) }}%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Items:</span>
                                        <span class="font-medium">{{ $data['item_count'] ?? 0 }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- 📋 PRICING TABLE TAB --}}
    @if($activeTab === 'pricing')
        <div class="space-y-6">
            {{-- 🔍 SEARCH & FILTERS --}}
            <div class="space-y-4">
                {{-- Selected Items Display --}}
                @if(!empty($selectedProducts) || !empty($selectedVariants))
                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl p-4 border border-purple-200 dark:border-purple-700">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-medium text-purple-900 dark:text-purple-100">✨ Active Filters</h3>
                            <flux:button wire:click="clearAllFilters" variant="ghost" size="sm" icon="x">
                                Clear All
                            </flux:button>
                        </div>
                        
                        <div class="flex flex-wrap gap-2">
                            {{-- Selected Products --}}
                            @foreach($selectedProducts as $productId)
                                @php $product = \App\Models\Product::find($productId) @endphp
                                @if($product)
                                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                        📦 {{ $product->name }}
                                        <button wire:click="removeProduct({{ $productId }})" class="text-green-600 hover:text-green-800">×</button>
                                    </span>
                                @endif
                            @endforeach
                            
                            {{-- Selected Variants --}}
                            @foreach($selectedVariants as $variantId)
                                @php $variant = \App\Models\ProductVariant::with('product')->find($variantId) @endphp
                                @if($variant)
                                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">
                                        💎 {{ $variant->product->name }} - {{ $variant->sku }}
                                        <button wire:click="removeVariant({{ $variantId }})" class="text-blue-600 hover:text-blue-800">×</button>
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Search and Filter Controls --}}
                <div class="flex items-center gap-4 flex-wrap">
                    <div class="flex-1 min-w-64">
                        <flux:input wire:model.live.debounce.300ms="searchQuery" 
                                   placeholder="Search products or SKUs..." 
                                   icon="search" />
                    </div>
                    
                    {{-- Product Search --}}
                    <div class="relative">
                        <flux:input wire:model.live.debounce.300ms="productSearchQuery" 
                                   placeholder="🔍 Add products..." 
                                   icon="search" />
                        @if($productSearchQuery && isset($searchableProducts) && $searchableProducts->count() > 0)
                            <div class="absolute top-full left-0 right-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 mt-1">
                                @foreach($searchableProducts as $product)
                                    <button wire:click="addProduct({{ $product->id }})" 
                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                        <span class="text-green-600">📦</span>
                                        <span class="font-medium">{{ $product->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Variant Search --}}
                    <div class="relative">
                        <flux:input wire:model.live.debounce.300ms="variantSearchQuery" 
                                   placeholder="🔍 Add variants..." 
                                   icon="search" />
                        @if($variantSearchQuery && isset($searchableVariants) && $searchableVariants->count() > 0)
                            <div class="absolute top-full left-0 right-0 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 mt-1">
                                @foreach($searchableVariants as $variant)
                                    <button wire:click="addVariant({{ $variant->id }})" 
                                            class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-3 border-b border-gray-100 dark:border-gray-600 last:border-0">
                                        <span class="text-blue-600">💎</span>
                                        <div>
                                            <span class="font-medium">{{ $variant->sku }}</span>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $variant->product->name }}</div>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                <flux:select wire:model.live="selectedChannel">
                    <flux:select.option value="all">All Channels</flux:select.option>
                    <flux:select.option value="null">Default Channel</flux:select.option>
                    @if(isset($channels))
                        @foreach($channels as $channel)
                            <flux:select.option value="{{ $channel->id }}">{{ $channel->display_name }}</flux:select.option>
                        @endforeach
                    @endif
                </flux:select>

                <flux:select wire:model.live="profitabilityFilter">
                    <flux:select.option value="all">All Items</flux:select.option>
                    <flux:select.option value="profitable">Profitable</flux:select.option>
                    <flux:select.option value="low_margin">Low Margin (&lt;10%)</flux:select.option>
                    <flux:select.option value="high_margin">High Margin (&gt;50%)</flux:select.option>
                    <flux:select.option value="loss_making">Loss Making</flux:select.option>
                </flux:select>

                @if(isset($selectedCount) && $selectedCount > 0)
                    <flux:button wire:click="clearSelection" variant="ghost" size="sm" icon="x">
                        Clear ({{ $selectedCount }})
                    </flux:button>
                @endif
            </div>

            {{-- 📋 PRICING TABLE --}}
            @if(isset($pricing))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <input type="checkbox" wire:click="selectAllVisible" 
                                               class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Channel</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cost</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Profit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Margin</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($pricing as $price)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                        <td class="px-6 py-4">
                                            <input type="checkbox" 
                                                   wire:click="togglePricing({{ $price->id }})"
                                                   @checked(in_array($price->id, $selectedPricing))
                                                   class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $price->productVariant->product->name ?? 'Unknown Product' }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    SKU: {{ $price->productVariant->sku ?? 'N/A' }}
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100">
                                                {{ $price->salesChannel->display_name ?? 'Default' }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $price->formatted_price }}
                                            </div>
                                            @if($price->isOnSale())
                                                <span class="text-xs text-orange-600 font-medium">ON SALE!</span>
                                            @endif
                                        </td>
                                        
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            {{ $price->formatted_cost_price }}
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-medium {{ $price->profit_amount > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $price->formatted_profit }}
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <span class="text-sm font-medium {{ $price->profit_margin > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ number_format($price->profit_margin, 1) }}%
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            @php
                                                $statusColor = match($price->status) {
                                                    'active' => 'green',
                                                    'sale' => 'orange',
                                                    'inactive' => 'gray',
                                                    default => 'blue'
                                                };
                                            @endphp
                                            <flux:badge size="sm" color="{{ $statusColor }}">
                                                {{ ucfirst($price->status) }}
                                            </flux:badge>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center gap-3">
                                                <flux:icon name="dollar-sign" class="w-8 h-8 text-gray-400" />
                                                <p class="text-gray-500">No pricing data found</p>
                                                <p class="text-sm text-gray-400">Try adjusting your filters or create some pricing records ✨</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    {{-- Pagination --}}
                    @if(isset($pricing) && $pricing->hasPages())
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                            {{ $pricing->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @endif

    {{-- 🚀 BULK ACTIONS TAB --}}
    @if($activeTab === 'bulk')
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">Bulk Operations</h3>
                
                @if(isset($selectedCount) && $selectedCount > 0)
                    <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                        <p class="text-green-800 dark:text-green-200">
                            {{ $selectedCount }} items selected for bulk operations
                        </p>
                    </div>
                @else
                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-blue-800 dark:text-blue-200">
                            Go to the Pricing Table tab and select items to perform bulk operations
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Bulk Discount --}}
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-4">Apply Bulk Discount</h4>
                        <div class="space-y-3">
                            <flux:input 
                                wire:model="bulkDiscountPercentage"
                                type="number"
                                min="0"
                                max="100"
                                step="0.1"
                                placeholder="Enter discount percentage"
                                label="Discount Percentage (%)"
                            />
                            <flux:button 
                                wire:click="applyBulkDiscount"
                                variant="primary"
                                size="sm"
                                icon="percent"
                                :disabled="empty($selectedPricing)"
                                class="w-full"
                            >
                                Apply Discount
                            </flux:button>
                        </div>
                    </div>

                    {{-- Bulk Markup --}}
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-4">Apply Bulk Markup</h4>
                        <div class="space-y-3">
                            <flux:input 
                                wire:model="bulkMarkupPercentage"
                                type="number"
                                min="0"
                                max="1000"
                                step="0.1"
                                placeholder="Enter markup percentage"
                                label="Markup Percentage (%)"
                            />
                            <flux:button 
                                wire:click="applyBulkMarkup"
                                variant="primary"
                                size="sm"
                                icon="trending-up"
                                :disabled="empty($selectedPricing)"
                                class="w-full"
                            >
                                Apply Markup
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- 📊 ANALYTICS & OTHER TABS --}}
    @if($activeTab === 'channels' || $activeTab === 'analysis')
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12">
            <div class="text-center">
                <flux:icon name="chart-bar" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Coming Soon!</h3>
                <p class="text-gray-600 dark:text-gray-400">Advanced analytics and channel management features are being crafted with love ✨</p>
            </div>
        </div>
    @endif
</div>