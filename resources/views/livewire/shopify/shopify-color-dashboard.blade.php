<div class="space-y-6">
    {{-- Header --}}
    <div class="bg-white rounded-lg border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">🎨 Shopify Color Dashboard</h1>
                <p class="text-gray-600 mt-1">Intelligent color-based product management for Shopify</p>
            </div>
            <div class="flex items-center space-x-3">
                <flux:button wire:click="testConnection" variant="ghost" size="sm" :loading="$isLoading">
                    <flux:icon.arrow-path class="size-4" />
                    Test Connection
                </flux:button>
            </div>
        </div>
    </div>

    {{-- Navigation Tabs --}}
    <div class="bg-white rounded-lg border border-gray-200">
        <nav class="flex space-x-8 px-6 py-4 border-b border-gray-200">
            <button 
                wire:click="setTab('overview')"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $currentTab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                📊 Overview
            </button>
            <button 
                wire:click="setTab('products')"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $currentTab === 'products' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                📦 Products
            </button>
            <button 
                wire:click="setTab('taxonomy')"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $currentTab === 'taxonomy' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                🏷️ Taxonomy
            </button>
            <button 
                wire:click="setTab('sync')"
                class="py-2 px-1 border-b-2 font-medium text-sm {{ $currentTab === 'sync' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                🔄 Sync
            </button>
        </nav>

        {{-- Tab Content --}}
        <div class="p-6">
            {{-- Overview Tab --}}
            @if($currentTab === 'overview')
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {{-- System Stats Cards --}}
                    @php $metrics = $this->getDashboardMetrics() @endphp
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span class="text-blue-600 text-sm">📦</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-blue-900">Total Products</p>
                                <p class="text-2xl font-semibold text-blue-600">{{ $metrics['total_products'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span class="text-green-600 text-sm">🎨</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-900">Unique Colors</p>
                                <p class="text-2xl font-semibold text-green-600">{{ $metrics['total_colors'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                    <span class="text-purple-600 text-sm">🛍️</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-purple-900">Shopify Ready</p>
                                <p class="text-2xl font-semibold text-purple-600">{{ $metrics['shopify_ready_products'] }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                                    <span class="text-orange-600 text-sm">🏷️</span>
                                </div>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-orange-900">Categories</p>
                                <p class="text-2xl font-semibold text-orange-600">{{ $taxonomyStats['categories_count'] ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Taxonomy Status --}}
                <div class="mt-6 bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">🏷️ Taxonomy Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <span class="text-sm text-gray-600">Categories Available:</span>
                            <span class="ml-2 font-semibold">{{ $taxonomyStats['categories_count'] ?? 0 }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Attributes Available:</span>
                            <span class="ml-2 font-semibold">{{ $taxonomyStats['attributes_count'] ?? 0 }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-600">Cache Status:</span>
                            <span class="ml-2 font-semibold {{ ($taxonomyStats['cache_status'] ?? 'empty') === 'loaded' ? 'text-green-600' : 'text-red-600' }}">
                                {{ ucfirst($taxonomyStats['cache_status'] ?? 'empty') }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Products Tab --}}
            @if($currentTab === 'products')
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Product Selection --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">📦 Select Product</h3>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            @forelse($products as $product)
                                <div 
                                    wire:click="selectProduct({{ $product->id }})"
                                    class="p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 {{ $selectedProductId === $product->id ? 'ring-2 ring-indigo-500 bg-indigo-50' : '' }}"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-900">{{ $product->name }}</h4>
                                            <p class="text-sm text-gray-600">{{ $product->variants->count() }} variants</p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm text-gray-500">{{ $product->variants->pluck('color')->unique()->count() }} colors</p>
                                            <p class="text-xs text-gray-400">SKU: {{ $product->parent_sku }}</p>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-gray-500">
                                    No products with variants found.
                                </div>
                            @endforelse
                        </div>
                        <div class="mt-4">
                            {{ $products->links() }}
                        </div>
                    </div>

                    {{-- Color Management --}}
                    <div>
                        @if($selectedProduct && $previewData)
                            <h3 class="text-lg font-medium text-gray-900 mb-4">🎨 Color Selection</h3>
                            
                            {{-- Color Selection Controls --}}
                            <div class="flex items-center space-x-3 mb-4">
                                <flux:button wire:click="selectAllColors" size="sm" variant="ghost">
                                    Select All
                                </flux:button>
                                <flux:button wire:click="clearColorSelection" size="sm" variant="ghost">
                                    Clear All
                                </flux:button>
                                <span class="text-sm text-gray-600">
                                    {{ count($selectedColors) }} of {{ count($previewData['shopify_products_to_create']) }} selected
                                </span>
                            </div>

                            {{-- Color Options --}}
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                @foreach($previewData['shopify_products_to_create'] as $colorProduct)
                                    <div class="flex items-center p-3 border border-gray-200 rounded-lg">
                                        <input 
                                            type="checkbox" 
                                            wire:click="toggleColor('{{ $colorProduct['color'] }}')"
                                            @checked(in_array($colorProduct['color'], $selectedColors))
                                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        >
                                        <div class="ml-3 flex-1">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <h5 class="font-medium text-gray-900">{{ $colorProduct['color'] }}</h5>
                                                    <p class="text-sm text-gray-600">{{ $colorProduct['variants_count'] }} sizes</p>
                                                </div>
                                                <div class="text-right">
                                                    <p class="text-sm font-medium">{{ $colorProduct['price_range'] }}</p>
                                                    <p class="text-xs text-gray-500">{{ $colorProduct['title'] }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Action Buttons --}}
                            <div class="mt-6 flex items-center space-x-3">
                                <flux:button 
                                    wire:click="createInShopify" 
                                    variant="primary"
                                    :loading="$isLoading"
                                    :disabled="empty($selectedColors)"
                                >
                                    🚀 Create in Shopify
                                </flux:button>
                                
                                <flux:button 
                                    wire:click="generatePreview" 
                                    variant="ghost"
                                    :loading="$isLoading"
                                >
                                    🔄 Refresh Preview
                                </flux:button>
                            </div>

                        @elseif($selectedProduct)
                            <div class="text-center py-8">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                                <p class="text-gray-500 mt-2">Generating preview...</p>
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500">
                                Select a product to see color options
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Taxonomy Tab --}}
            @if($currentTab === 'taxonomy')
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900">🏷️ Shopify Taxonomy Browser</h3>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-blue-800">
                            <strong>Taxonomy System:</strong> Shopify's Standard Product Taxonomy provides 
                            {{ $taxonomyStats['categories_count'] ?? 0 }} categories and 
                            {{ $taxonomyStats['attributes_count'] ?? 0 }} attributes for intelligent product categorization.
                        </p>
                    </div>

                    {{-- Taxonomy stats would go here --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">🏠 Window Treatment Categories</h4>
                            <p class="text-sm text-gray-600">Categories specifically relevant to window treatments and blinds.</p>
                            <div class="mt-3">
                                <flux:button size="sm" variant="ghost">
                                    Browse Categories
                                </flux:button>
                            </div>
                        </div>
                        
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-900 mb-3">🔍 Category Search</h4>
                            <p class="text-sm text-gray-600">Find the perfect category for your products.</p>
                            <div class="mt-3">
                                <flux:input placeholder="Search categories..." />
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sync Tab --}}
            @if($currentTab === 'sync')
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">🔄 Sync Management</h3>
                        <flux:button wire:click="pullFromShopify" variant="outline" :loading="$isLoading">
                            <flux:icon.arrow-down class="size-4 mr-2" />
                            Pull from Shopify
                        </flux:button>
                    </div>

                    {{-- Sync Results --}}
                    @if(!empty($syncResults))
                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h4 class="font-medium text-gray-900 mb-4">📊 Sync Results</h4>
                            
                            @if(isset($syncResults['success']) && $syncResults['success'])
                                <div class="space-y-4">
                                    @if(isset($syncResults['results']))
                                        @foreach($syncResults['results'] as $result)
                                            <div class="flex items-center justify-between p-3 {{ $result['success'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }} rounded-lg">
                                                <div>
                                                    <span class="font-medium">{{ $result['color'] ?? 'Unknown' }}</span>
                                                    @if($result['success'])
                                                        <span class="ml-2 text-green-600">✅ Success</span>
                                                    @else
                                                        <span class="ml-2 text-red-600">❌ Failed</span>
                                                    @endif
                                                </div>
                                                @if(isset($result['shopify_id']))
                                                    <span class="text-sm text-gray-600">ID: {{ $result['shopify_id'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            @else
                                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                    <p class="text-red-800">
                                        {{ $syncResults['error'] ?? 'An error occurred during sync.' }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Loading Overlay --}}
    @if($isLoading)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mx-auto"></div>
                <p class="mt-2 text-gray-600">Processing...</p>
            </div>
        </div>
    @endif
</div>