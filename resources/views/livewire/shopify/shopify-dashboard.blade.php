{{-- 🎭💅 SHOPIFY DASHBOARD - THE ULTIMATE SYNC SYMPHONY! 💅🎭 --}}
<div class="space-y-6">
    {{-- 🎪 HEADER WITH SASS --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Shopify Sync Dashboard</h2>
            <p class="text-gray-600 dark:text-gray-400">Manage your Shopify integration with style ✨</p>
        </div>
        
        {{-- 🔄 REFRESH BUTTON --}}
        <flux:button wire:click="refreshStats" variant="ghost" size="sm" icon="arrow-path">
            Refresh Stats
        </flux:button>
    </div>

    {{-- ⚡ LOADING OVERLAY --}}
    @if($isLoading)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl">
                <div class="flex items-center gap-4">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
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
                              ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📊 Overview
            </button>
            
            <button wire:click="switchTab('products')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'products' 
                              ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📦 Products
            </button>
            
            <button wire:click="switchTab('discovery')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'discovery' 
                              ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                🔍 Discovery
            </button>
            
            <button wire:click="switchTab('push_products')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'push_products' 
                              ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📤 Push Products
            </button>
            
            <button wire:click="switchTab('sync_history')"
                    class="py-2 px-1 border-b-2 font-medium text-sm transition-colors
                           {{ $activeTab === 'sync_history' 
                              ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                📈 Sync History
            </button>
        </nav>
    </div>

    {{-- 📊 OVERVIEW TAB --}}
    @if($activeTab === 'overview')
        <div class="space-y-6">
            {{-- ✨ STATISTICS CARDS --}}
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Products</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_products'] }}</p>
                        </div>
                        <flux:icon name="squares-plus" class="w-8 h-8 text-blue-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Ready to Sync</p>
                            <p class="text-2xl font-bold text-green-600">{{ $stats['syncable'] }}</p>
                        </div>
                        <flux:icon name="check-circle" class="w-8 h-8 text-green-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Synced</p>
                            <p class="text-2xl font-bold text-blue-600">{{ $stats['synced'] }}</p>
                        </div>
                        <flux:icon name="cloud" class="w-8 h-8 text-blue-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Needs Update</p>
                            <p class="text-2xl font-bold text-orange-600">{{ $stats['needs_update'] }}</p>
                        </div>
                        <flux:icon name="exclamation-triangle" class="w-8 h-8 text-orange-500" />
                    </div>
                </div>
            </div>

            {{-- 🚀 QUICK ACTIONS --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($quickActions as $action)
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:border-{{ $action['color'] }}-300 transition-colors">
                            <div class="flex items-center gap-3 mb-3">
                                <flux:icon name="{{ $action['icon'] }}" class="w-6 h-6 text-{{ $action['color'] }}-500" />
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ $action['label'] }}</h4>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $action['count'] }} items</p>
                            <div class="flex gap-2">
                                <flux:button wire:click="{{ $action['action'] }}(true)" variant="ghost" size="sm">
                                    Preview
                                </flux:button>
                                <flux:button wire:click="{{ $action['action'] }}" variant="primary" size="sm">
                                    Execute
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 📝 RECENT ACTIVITY --}}
            @if(!empty($recentActivity))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
                    <div class="space-y-3">
                        @foreach($recentActivity as $activity)
                            <div class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0">
                                <div class="flex items-center gap-3">
                                    <flux:badge size="sm" color="green">Synced</flux:badge>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $activity['product']['name'] ?? 'Unknown Product' }}</span>
                                </div>
                                <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($activity['created_at'])->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- 📦 PRODUCTS TAB --}}
    @if($activeTab === 'products')
        <div class="space-y-6">
            {{-- 🔍 SEARCH & FILTERS --}}
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <flux:input wire:model.live.debounce.300ms="searchQuery" 
                               placeholder="Search products..." 
                               icon="magnifying-glass" />
                </div>
                
                <flux:select wire:model.live="syncStatus">
                    <flux:select.option value="all">All Status</flux:select.option>
                    <flux:select.option value="never_synced">Never Synced</flux:select.option>
                    <flux:select.option value="synced">Synced</flux:select.option>
                    <flux:select.option value="pending">Pending</flux:select.option>
                    <flux:select.option value="failed">Failed</flux:select.option>
                </flux:select>

                @if(!empty($selectedProducts))
                    <flux:button wire:click="syncSelected(true)" variant="ghost" size="sm">
                        Preview ({{ count($selectedProducts) }})
                    </flux:button>
                    <flux:button wire:click="syncSelected" variant="primary" size="sm">
                        Sync Selected
                    </flux:button>
                    <flux:button wire:click="clearSelection" variant="ghost" size="sm" icon="x-mark">
                        Clear
                    </flux:button>
                @endif
            </div>

            {{-- 📋 PRODUCTS TABLE --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <input type="checkbox" wire:click="selectAllVisible" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Product Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                SKU
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Sync Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Variants
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Last Synced
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <input type="checkbox" 
                                           wire:click="toggleProduct({{ $product->id }})"
                                           @checked(in_array($product->id, $selectedProducts))
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $product->name }}
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        {{ $product->parent_sku }}
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $syncStatus = $product->shopifySyncStatus?->first();
                                        $status = $syncStatus?->sync_status ?? 'never_synced';
                                        $color = match($status) {
                                            'synced' => 'green',
                                            'pending' => 'orange', 
                                            'failed' => 'red',
                                            default => 'gray'
                                        };
                                    @endphp
                                    <flux:badge size="sm" color="{{ $color }}">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </flux:badge>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $product->variants_count ?? $product->variants->count() }} variants
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($syncStatus?->last_synced_at)
                                        <span class="text-xs text-gray-500">
                                            {{ $syncStatus->last_synced_at->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">Never</span>
                                    @endif
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-1">
                                        <flux:button href="{{ route('products.show', $product->id) }}" 
                                                   variant="ghost" size="sm" icon="eye">
                                            View
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <flux:icon name="squares-plus" class="w-8 h-8 text-gray-400" />
                                        <p class="text-gray-500">No products found matching your criteria ✨</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- 🔍 DISCOVERY TAB --}}
    @if($activeTab === 'discovery')
        <div class="space-y-6">
            @if(!$hasDiscovered)
                {{-- Initial discovery prompt --}}
                <div class="text-center py-12">
                    <flux:icon name="magnifying-glass" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Discover Shopify Products</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">Find products on Shopify that aren't in your PIM system yet</p>
                    <flux:button wire:click="discoverProducts" variant="primary" icon="magnifying-glass">
                        Start Discovery
                    </flux:button>
                </div>
            @else
                {{-- Discovery results --}}
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Discovered {{ count($discoveredProducts) }} Products
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ count(array_filter($discoveredProducts, fn($p) => $p['match_status'] === 'new')) }} new,
                            {{ count(array_filter($discoveredProducts, fn($p) => $p['match_status'] === 'existing')) }} existing
                        </p>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @if(!empty($selectedDiscoveryProducts))
                            <flux:button wire:click="importSelectedDiscoveryProducts" variant="primary" size="sm">
                                Import Selected ({{ count($selectedDiscoveryProducts) }})
                            </flux:button>
                            <flux:button wire:click="clearDiscoverySelection" variant="ghost" size="sm">
                                Clear Selection
                            </flux:button>
                        @else
                            <flux:button wire:click="selectAllDiscoveryProducts" variant="ghost" size="sm">
                                Select All New
                            </flux:button>
                        @endif
                        
                        <flux:button wire:click="discoverProducts" variant="ghost" size="sm" icon="arrow-path">
                            Refresh
                        </flux:button>
                    </div>
                </div>
                
                {{-- Products table --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <input type="checkbox" 
                                           wire:click="selectAllDiscoveryProducts"
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    SKU
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Price
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Variants
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Match
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($discoveredProducts as $product)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($product['match_status'] === 'new')
                                            <input type="checkbox"
                                                   wire:click="toggleDiscoveryProduct('{{ $product['shopify_id'] }}')"
                                                   @checked(in_array($product['shopify_id'], $selectedDiscoveryProducts))
                                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        @else
                                            <div class="w-4 h-4"></div>
                                        @endif
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            @if(!empty($product['images'][0]))
                                                <img src="{{ $product['images'][0] }}" 
                                                     alt="{{ $product['title'] }}"
                                                     class="w-10 h-10 rounded object-cover">
                                            @else
                                                <div class="w-10 h-10 rounded bg-gray-200 dark:bg-gray-700"></div>
                                            @endif
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $product['title'] }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $product['vendor'] ?: 'No vendor' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm font-mono text-gray-900 dark:text-white">
                                            {{ $product['variants'][0]['sku'] ?? '-' }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-900 dark:text-white">
                                            ${{ number_format($product['variants'][0]['price'] ?? 0, 2) }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            {{ $product['variant_count'] ?? count($product['variants'] ?? []) }} variant{{ ($product['variant_count'] ?? count($product['variants'] ?? [])) !== 1 ? 's' : '' }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" color="{{ $product['status'] === 'active' ? 'green' : 'gray' }}">
                                            {{ ucfirst($product['status']) }}
                                        </flux:badge>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($product['match_status'] === 'existing')
                                            <div class="flex items-center gap-2">
                                                <flux:badge size="sm" color="blue">Linked</flux:badge>
                                                <a href="{{ route('products.show', $product['local_product_id']) }}" 
                                                   class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                                    View
                                                </a>
                                            </div>
                                        @else
                                            <flux:badge size="sm" color="green">New</flux:badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- 📤 PUSH PRODUCTS TAB --}}
    @if($activeTab === 'push_products')
        <div class="space-y-6">
            {{-- 📊 Push Statistics --}}
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Ready to Push</p>
                            <p class="text-2xl font-bold text-green-600">{{ $pushStats['ready_to_push'] ?? 0 }}</p>
                        </div>
                        <flux:icon name="arrow-up-tray" class="w-8 h-8 text-green-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Never Synced</p>
                            <p class="text-2xl font-bold text-orange-600">{{ $pushStats['never_synced'] ?? 0 }}</p>
                        </div>
                        <flux:icon name="cloud-arrow-up" class="w-8 h-8 text-orange-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Needs Update</p>
                            <p class="text-2xl font-bold text-blue-600">{{ $pushStats['needs_update'] ?? 0 }}</p>
                        </div>
                        <flux:icon name="arrow-path" class="w-8 h-8 text-blue-500" />
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Selected</p>
                            <p class="text-2xl font-bold text-purple-600">{{ count($selectedPushProducts) }}</p>
                        </div>
                        <flux:icon name="check-circle" class="w-8 h-8 text-purple-500" />
                    </div>
                </div>
            </div>

            {{-- 🎛️ Push Controls --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Push Controls</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- Search --}}
                    <div class="lg:col-span-2">
                        <flux:input wire:model.live.debounce.300ms="pushSearchQuery" 
                                   placeholder="Search products to push..." 
                                   icon="magnifying-glass" />
                    </div>
                    
                    {{-- Filter --}}
                    <div>
                        <flux:select wire:model.live="pushProductsFilter">
                            <flux:select.option value="ready_to_push">Ready to Push</flux:select.option>
                            <flux:select.option value="never_synced">Never Synced</flux:select.option>
                            <flux:select.option value="needs_update">Needs Update</flux:select.option>
                            <flux:select.option value="all">All Products</flux:select.option>
                        </flux:select>
                    </div>
                    
                    {{-- Push Method --}}
                    <div>
                        <flux:select wire:model="pushMethod">
                            <flux:select.option value="colors">Color Splitting</flux:select.option>
                            <flux:select.option value="regular">Regular Push</flux:select.option>
                        </flux:select>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-3 mt-6">
                    @if(!empty($selectedPushProducts))
                        <flux:button wire:click="pushSelectedProducts(true)" variant="ghost" size="sm" icon="eye">
                            Preview Selected ({{ count($selectedPushProducts) }})
                        </flux:button>
                        <flux:button wire:click="pushSelectedProducts" variant="primary" size="sm" icon="arrow-up-tray">
                            Push Selected
                        </flux:button>
                        <flux:button wire:click="clearPushSelection" variant="ghost" size="sm" icon="x-mark">
                            Clear Selection
                        </flux:button>
                    @endif
                    
                    <div class="flex items-center gap-2">
                        <flux:button wire:click="bulkPushByFilter(true)" variant="ghost" size="sm" icon="eye">
                            Preview All
                        </flux:button>
                        <flux:button wire:click="bulkPushByFilter" variant="danger" size="sm" icon="rocket-launch">
                            Bulk Push All
                        </flux:button>
                    </div>
                    
                    <flux:button wire:click="selectAllVisiblePushProducts" variant="ghost" size="sm" icon="check-circle">
                        Select All Visible
                    </flux:button>
                </div>
            </div>

            {{-- 📋 Products Table --}}
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-12">
                                <input type="checkbox" wire:click="selectAllVisiblePushProducts" 
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-1/3">
                                Product Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                SKU
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Variants
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Sync Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($pushProducts as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 group">
                                <td class="px-6 py-4">
                                    <input type="checkbox" 
                                           wire:click="togglePushProduct({{ $product->id }})"
                                           @checked(in_array($product->id, $selectedPushProducts))
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                                {{ $product->name }}
                                            </h4>
                                            <flux:badge 
                                                size="sm" 
                                                color="blue" 
                                                inset="top bottom"
                                            >
                                                {{ $product->variants_count ?? $product->variants->count() }} variants
                                            </flux:badge>
                                        </div>
                                        @if($product->description)
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">
                                                {{ $product->description }}
                                            </p>
                                        @endif
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <span class="font-mono text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $product->parent_sku }}
                                    </span>
                                </td>
                                
                                <td class="px-6 py-4">
                                    @php
                                        $status = $product->status instanceof \App\Enums\ProductStatus 
                                            ? $product->status 
                                            : \App\Enums\ProductStatus::from($product->status);
                                        $statusValue = $status->value;
                                        $statusLabel = $status->label();
                                    @endphp
                                    <flux:badge 
                                        size="sm" 
                                        color="{{ $statusValue === 'active' ? 'green' : ($statusValue === 'draft' ? 'yellow' : 'zinc') }}" 
                                        inset="top bottom"
                                    >
                                        {{ $statusLabel }}
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
                                    @php
                                        $syncStatus = $product->shopifySyncStatus?->first();
                                        $status = $syncStatus?->sync_status ?? 'never_synced';
                                        $color = match($status) {
                                            'synced' => 'green',
                                            'pending' => 'orange', 
                                            'failed' => 'red',
                                            default => 'gray'
                                        };
                                    @endphp
                                    <flux:badge 
                                        size="sm" 
                                        color="{{ $color }}"
                                        inset="top bottom"
                                    >
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </flux:badge>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div class="flex gap-2 opacity-100 transition-opacity">
                                        <flux:button 
                                            wire:click="pushSingleProduct({{ $product->id }})" 
                                            size="sm" 
                                            variant="primary" 
                                            icon="arrow-up-tray"
                                            wire:loading.attr="disabled"
                                            wire:target="pushSingleProduct({{ $product->id }})"
                                        >
                                            <span wire:loading.remove wire:target="pushSingleProduct({{ $product->id }})">Push</span>
                                            <span wire:loading wire:target="pushSingleProduct({{ $product->id }})">Pushing...</span>
                                        </flux:button>
                                        <flux:button 
                                            href="{{ route('products.show', $product->id) }}" 
                                            size="sm" 
                                            variant="ghost" 
                                            icon="eye"
                                        >
                                            View
                                        </flux:button>
                                        <flux:button 
                                            href="{{ route('products.edit', $product->id) }}" 
                                            size="sm" 
                                            variant="ghost" 
                                            icon="pencil"
                                        >
                                            Edit
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <flux:icon name="arrow-up-tray" class="w-12 h-12 text-gray-400" />
                                        <div>
                                            <h4 class="font-semibold text-gray-900 dark:text-white">No products found</h4>
                                            <p class="text-gray-500 dark:text-gray-400 mt-1">
                                                @if($pushSearchQuery)
                                                    No products match "{{ $pushSearchQuery }}" with the current filter
                                                @else
                                                    No products match the current filter criteria
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex gap-3">
                                            @if($pushSearchQuery)
                                                <flux:button wire:click="$set('pushSearchQuery', '')" size="sm" variant="outline">
                                                    Clear Search
                                                </flux:button>
                                            @endif
                                            <flux:button wire:click="$set('pushProductsFilter', 'all')" variant="primary" size="sm">
                                                Show All Products
                                            </flux:button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                
                {{-- Enhanced Pagination with Stats --}}
                @if($pushProducts->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Showing {{ $pushProducts->firstItem() ?? 0 }} to {{ $pushProducts->lastItem() ?? 0 }} of {{ $pushProducts->total() }} products
                            </div>
                            {{ $pushProducts->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- 📈 SYNC HISTORY TAB --}}
    @if($activeTab === 'sync_history')
        <div class="space-y-6">
            @if(isset($syncHistory) && $syncHistory->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Product
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Shopify ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Synced At
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    Error
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($syncHistory as $sync)
                                <tr wire:key="sync-{{ $sync->id }}" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ $sync->product->name ?? 'Unknown Product' }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @php
                                            $color = match($sync->sync_status) {
                                                'synced' => 'green',
                                                'pending' => 'orange',
                                                'failed' => 'red',
                                                default => 'gray'
                                            };
                                        @endphp
                                        <flux:badge size="sm" color="{{ $color }}">
                                            {{ ucfirst($sync->sync_status) }}
                                        </flux:badge>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-mono text-gray-900 dark:text-white">
                                            {{ $sync->external_id ?? '-' }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-white">
                                            {{ $sync->last_synced_at?->diffForHumans() ?? '-' }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($sync->error_message)
                                            <span class="text-xs text-red-600" title="{{ $sync->error_message }}">
                                                {{ Str::limit($sync->error_message, 30) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-12">
                    <flux:icon name="clock" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Sync History</h3>
                    <p class="text-gray-600 dark:text-gray-400">Start syncing products to see history here ✨</p>
                </div>
            @endif
        </div>
    @endif

    {{-- 📊 LAST SYNC RESULTS MODAL --}}
    @if($lastSyncResults)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 flex items-center justify-center" 
             wire:click="$set('lastSyncResults', null)">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl max-w-md w-full mx-4"
                 wire:click.stop>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Sync Results</h3>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $lastSyncResults['message'] }}</p>
                    @if(isset($lastSyncResults['total']))
                        <div class="text-sm">
                            <span class="font-medium">Total:</span> {{ $lastSyncResults['total'] }}
                        </div>
                    @endif
                    @if(isset($lastSyncResults['successful']))
                        <div class="text-sm text-green-600">
                            <span class="font-medium">Successful:</span> {{ $lastSyncResults['successful'] }}
                        </div>
                    @endif
                    @if(isset($lastSyncResults['failed']) && $lastSyncResults['failed'] > 0)
                        <div class="text-sm text-red-600">
                            <span class="font-medium">Failed:</span> {{ $lastSyncResults['failed'] }}
                        </div>
                    @endif
                </div>
                <div class="mt-6 flex justify-end">
                    <flux:button wire:click="$set('lastSyncResults', null)" variant="primary">
                        Close
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- 📤 LAST PUSH RESULTS MODAL --}}
    @if($lastPushResults)
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 flex items-center justify-center" 
             wire:click="$set('lastPushResults', null)">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-6 shadow-xl max-w-md w-full mx-4"
                 wire:click.stop>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                    {{ isset($lastPushResults['dry_run']) && $lastPushResults['dry_run'] ? 'Push Preview' : 'Push Results' }}
                </h3>
                <div class="space-y-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $lastPushResults['message'] }}</p>
                    @if(isset($lastPushResults['total']))
                        <div class="text-sm">
                            <span class="font-medium">Total:</span> {{ $lastPushResults['total'] }}
                        </div>
                    @endif
                    @if(isset($lastPushResults['successful']))
                        <div class="text-sm text-green-600">
                            <span class="font-medium">{{ isset($lastPushResults['dry_run']) && $lastPushResults['dry_run'] ? 'Would succeed' : 'Successful' }}:</span> {{ $lastPushResults['successful'] }}
                        </div>
                    @endif
                    @if(isset($lastPushResults['failed']) && $lastPushResults['failed'] > 0)
                        <div class="text-sm text-red-600">
                            <span class="font-medium">{{ isset($lastPushResults['dry_run']) && $lastPushResults['dry_run'] ? 'Would fail' : 'Failed' }}:</span> {{ $lastPushResults['failed'] }}
                        </div>
                    @endif
                    @if(isset($lastPushResults['dry_run']) && $lastPushResults['dry_run'])
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <p class="text-sm text-blue-800 dark:text-blue-200">
                                🔍 This was a preview - no products were actually pushed to Shopify.
                            </p>
                        </div>
                    @endif
                </div>
                <div class="mt-6 flex justify-end">
                    <flux:button wire:click="$set('lastPushResults', null)" variant="primary">
                        Close
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>