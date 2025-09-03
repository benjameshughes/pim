<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- 📦 PRODUCT INFO --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Basic Info Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Product Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                    @if($editingName)
                        <div class="mt-1 flex items-center gap-2">
                            <flux:input 
                                wire:model="tempName" 
                                wire:keydown.enter="saveName"
                                wire:keydown.escape="cancelEditingName"
                                class="flex-1"
                                autofocus
                            />
                            <flux:button 
                                wire:click="saveName" 
                                size="sm" 
                                icon="check" 
                                variant="filled"
                                class="text-green-600"
                            />
                            <flux:button 
                                wire:click="cancelEditingName" 
                                size="sm" 
                                icon="x-mark" 
                                variant="ghost"
                                class="text-gray-500"
                            />
                        </div>
                        @error('tempName') 
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @else
                        <p class="mt-1 text-sm text-gray-900 dark:text-white cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 rounded px-2 py-1 -mx-2 -my-1 transition-colors group flex items-center"
                           wire:click="startEditingName"
                           title="Click to edit">
                            {{ $product->name }}
                            <flux:icon name="pencil" class="w-3 h-3 ml-2 opacity-0 group-hover:opacity-50 transition-opacity" />
                        </p>
                    @endif
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Parent SKU</label>
                    <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ $product->parent_sku }}</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <div class="mt-1">
                        <flux:badge :color="$product->status->value === 'active' ? 'green' : 'gray'" size="sm">
                            {{ $product->status->label() }}
                        </flux:badge>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                    <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $product->created_at->format('M j, Y') }}</p>
                </div>
            </div>

            <div class="mt-6">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                @if($editingDescription)
                    <div class="mt-1 space-y-2">
                        <flux:textarea 
                            wire:model="tempDescription" 
                            wire:keydown.escape="cancelEditingDescription"
                            rows="3"
                            placeholder="Add a description..."
                            autofocus
                        />
                        <div class="flex items-center gap-2">
                            <flux:button 
                                wire:click="saveDescription" 
                                size="sm" 
                                icon="check" 
                                variant="filled"
                                class="text-green-600"
                            />
                            <flux:button 
                                wire:click="cancelEditingDescription" 
                                size="sm" 
                                icon="x-mark" 
                                variant="ghost"
                                class="text-gray-500"
                            />
                        </div>
                    </div>
                    @error('tempDescription') 
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                @else
                    @if($product->description)
                        <p class="mt-1 text-sm text-gray-900 dark:text-white cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 rounded px-2 py-1 -mx-2 -my-1 transition-colors group"
                           wire:click="startEditingDescription"
                           title="Click to edit">
                            {{ $product->description }}
                            <flux:icon name="pencil" class="w-3 h-3 ml-2 opacity-0 group-hover:opacity-50 transition-opacity inline" />
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 rounded px-2 py-1 -mx-2 -my-1 transition-colors group flex items-center italic"
                           wire:click="startEditingDescription"
                           title="Click to add description">
                            No description - click to add
                            <flux:icon name="plus" class="w-3 h-3 ml-2 opacity-0 group-hover:opacity-50 transition-opacity" />
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- 🎨 COLOR PALETTE SHOWCASE --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Color Palette</h3>
            
            <div class="flex flex-wrap gap-3">
                @foreach ($product->variants->groupBy('color') as $color => $colorVariants)
                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                        <div class="w-6 h-6 rounded-full border-2 border-white shadow-sm
                            @if(strtolower($color) === 'black') bg-gray-900
                            @elseif(strtolower($color) === 'white') bg-white border-gray-300
                            @elseif(strtolower($color) === 'red') bg-red-500
                            @elseif(strtolower($color) === 'blue') bg-blue-500
                            @elseif(strtolower($color) === 'green') bg-green-500
                            @elseif(str_contains(strtolower($color), 'grey') || str_contains(strtolower($color), 'gray')) bg-gray-500
                            @elseif(str_contains(strtolower($color), 'orange')) bg-orange-500
                            @elseif(str_contains(strtolower($color), 'yellow') || str_contains(strtolower($color), 'lemon')) bg-yellow-500
                            @elseif(str_contains(strtolower($color), 'purple') || str_contains(strtolower($color), 'lavender')) bg-purple-500
                            @elseif(str_contains(strtolower($color), 'pink')) bg-pink-500
                            @elseif(str_contains(strtolower($color), 'brown') || str_contains(strtolower($color), 'cappuccino')) bg-amber-700
                            @elseif(str_contains(strtolower($color), 'navy')) bg-blue-900
                            @elseif(str_contains(strtolower($color), 'natural')) bg-amber-200
                            @elseif(str_contains(strtolower($color), 'lime')) bg-lime-500
                            @elseif(str_contains(strtolower($color), 'aubergine')) bg-purple-900
                            @elseif(str_contains(strtolower($color), 'ochre')) bg-yellow-700
                            @else bg-gradient-to-br from-orange-400 to-red-500
                            @endif"
                            title="{{ $color }}">
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $color }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $colorVariants->count() }} sizes</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 🖼️ PRODUCT IMAGE & STATS --}}
    <div class="space-y-6">
        {{-- Product Image --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Product Image</h3>
            
            @php
                $primaryImage = $product->primaryImage();
            @endphp
            
            @if ($primaryImage)
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden">
                    <img src="{{ $primaryImage->url }}" alt="{{ $primaryImage->alt_text ?? $product->name }}" class="w-full h-full object-cover">
                </div>
            @elseif ($product->images->count() > 0)
                {{-- Fallback to first image if no primary image is set --}}
                @php $firstImage = $product->images->first(); @endphp
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden">
                    <img src="{{ $firstImage->url }}" alt="{{ $firstImage->alt_text ?? $product->name }}" class="w-full h-full object-cover">
                </div>
            @elseif ($product->image_url)
                {{-- Legacy fallback --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                </div>
            @else
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                    <flux:icon name="photo" class="w-12 h-12 text-gray-400" />
                </div>
            @endif
        </div>

        {{-- Quick Stats --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Stats</h3>
            
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Variants</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->count() }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Active Variants</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->where('status', 'active')->count() }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Unique Colors</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->pluck('color')->unique()->count() }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Size Range</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->min('width') }}cm - {{ $product->variants->max('width') }}cm</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Price Range</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">£{{ number_format($product->variants->min('price'), 2) }} - £{{ number_format($product->variants->max('price'), 2) }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Stock</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($product->variants->sum('stock_level')) }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Barcodes</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->filter(fn($v) => $v->barcode)->count() }}</span>
                </div>
            </div>

            {{-- 🛍️ MARKETPLACE SYNC STATUS --}}
            @php
                $shopifyStatus = $product->getSmartAttributeValue('shopify_status');
                $shopifyIds = $product->getSmartAttributeValue('shopify_product_ids');
                $shopifyMeta = $product->getSmartAttributeValue('shopify_metadata');
                
                // Parse JSON data
                $productIds = [];
                if ($shopifyIds) {
                    $productIds = is_string($shopifyIds) ? json_decode($shopifyIds, true) : $shopifyIds;
                }
                
                $metadata = [];
                if ($shopifyMeta) {
                    $metadata = is_string($shopifyMeta) ? json_decode($shopifyMeta, true) : $shopifyMeta;
                }
            @endphp
            
            @if($shopifyStatus)
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Shopify Sync</h4>
                    
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <flux:badge color="green" size="sm">
                                @if($shopifyStatus === 'synced' && is_array($productIds))
                                    {{ count($productIds) }} products synced
                                @else
                                    {{ ucfirst($shopifyStatus) }}
                                @endif
                            </flux:badge>
                        </div>
                        
                        @if(is_array($productIds) && !empty($productIds))
                            <div class="mt-2 space-y-1">
                                @foreach($productIds as $color => $shopifyId)
                                    @php
                                        $numericId = str_replace('gid://shopify/Product/', '', $shopifyId);
                                    @endphp
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-gray-600 dark:text-gray-400">{{ $color }}: {{ $numericId }}</span>
                                        <a href="https://admin.shopify.com/store/your-store/products/{{ $numericId }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                            View →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- 🛍️ MARKETPLACE ACTIONS --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Marketplace Actions</h4>
                
                <div class="space-y-2">
                    @if(!$shopifyStatus)
                        <flux:button 
                            wire:click="linkToShopify" 
                            variant="outline" 
                            size="sm"
                            class="w-full text-blue-600 hover:text-blue-700 border-blue-300 hover:border-blue-400"
                            icon="link"
                        >
                            Link to Shopify
                        </flux:button>
                    @endif

                    <flux:button 
                        wire:click="pushToShopify" 
                        variant="filled" 
                        size="sm"
                        class="w-full"
                        icon="shopping-bag"
                    >
                        {{ $shopifyStatus ? 'Full Update' : 'Push to Shopify' }}
                    </flux:button>

                    @if($shopifyStatus)
                        <flux:button 
                            wire:click="updateShopifyTitle" 
                            variant="outline" 
                            size="sm"
                            class="w-full"
                            icon="pencil"
                        >
                            Update Title
                        </flux:button>

                        <flux:button 
                            wire:click="updateShopifyPricing" 
                            variant="outline" 
                            size="sm"
                            class="w-full"
                            icon="currency-dollar"
                        >
                            Update Pricing
                        </flux:button>

                        <flux:button 
                            wire:click="deleteFromShopify" 
                            variant="outline" 
                            size="sm"
                            class="w-full text-red-600 hover:text-red-700 border-red-300 hover:border-red-400"
                            icon="trash"
                        >
                            Delete from Shopify
                        </flux:button>
                    @endif
                    
                    @if($shopifyPushResult)
                        <div class="text-xs {{ $shopifyPushResult['success'] ? 'text-green-600' : 'text-red-600' }}">
                            {{ $shopifyPushResult['message'] }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>