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
                    @php $hasColorImages = $product->getImagesForColor($color)->count() > 0; @endphp
                    <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                         wire:click="openColorImageModal('{{ $color }}')"
                         title="Manage images for {{ $color }}{{ $hasColorImages ? ' (' . $product->getImagesForColor($color)->count() . ' images)' : '' }}">
                        <div class="relative w-6 h-6 rounded-full border-2 border-white shadow-sm
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
                            {{-- 📷 Subtle image indicator --}}
                            @if($hasColorImages)
                                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 border-2 border-white rounded-full flex items-center justify-center">
                                    <flux:icon name="camera" class="w-1.5 h-1.5 text-white" />
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1">
                                {{ $color }}
                                @if($hasColorImages)
                                    <flux:icon name="camera" class="w-3 h-3 text-green-500" />
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $colorVariants->count() }} sizes
                                @if($hasColorImages)
                                    • {{ $product->getImagesForColor($color)->count() }} images
                                @endif
                            </div>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                            <flux:icon name="camera" class="w-4 h-4 {{ $hasColorImages ? 'text-green-500 group-hover:text-green-600' : 'text-gray-400 group-hover:text-blue-500' }}" />
                        </div>
                    </div>
                @endforeach
            </div>
</div>

{{-- 📐 SIZES SHOWCASE --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Sizes</h3>
            
            <div class="space-y-6">
                {{-- Width Section --}}
                <div>
                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Width</h4>
                    <div class="flex flex-wrap gap-3">
                        @php
                            $uniqueWidths = $product->variants
                                ->groupBy('width')
                                ->map(fn($variants, $width) => [
                                    'width' => $width,
                                    'variant_count' => $variants->count()
                                ])
                                ->sortBy('width')
                                ->values();
                        @endphp
                        
                        @foreach ($uniqueWidths as $widthData)
                            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                                <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/50 border-2 border-blue-300 dark:border-blue-600 rounded flex items-center justify-center">
                                    <flux:icon name="arrow-left-right" class="w-3 h-3 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $widthData['width'] }}cm</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $widthData['variant_count'] }} variant{{ $widthData['variant_count'] > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Drop Section --}}
                <div>
                    <h4 class="text-md font-medium text-gray-700 dark:text-gray-300 mb-3">Drop</h4>
                    <div class="flex flex-wrap gap-3">
                        @php
                            $uniqueDrops = $product->variants
                                ->groupBy('drop')
                                ->map(fn($variants, $drop) => [
                                    'drop' => $drop,
                                    'variant_count' => $variants->count()
                                ])
                                ->sortBy('drop')
                                ->values();
                        @endphp
                        
                        @foreach ($uniqueDrops as $dropData)
                            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
                                <div class="w-6 h-6 bg-green-100 dark:bg-green-900/50 border-2 border-green-300 dark:border-green-600 rounded flex items-center justify-center">
                                    <flux:icon name="arrow-up-down" class="w-3 h-3 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $dropData['drop'] }}cm</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $dropData['variant_count'] }} variant{{ $dropData['variant_count'] > 1 ? 's' : '' }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 🖼️ PRODUCT IMAGE & STATS --}}
    <div class="space-y-6">
        {{-- 🌟 Enhanced Product Images with Images Facade --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Product Images</h3>
                <div class="flex items-center gap-2">
                    <flux:badge color="blue" size="sm">
                        {{ \App\Facades\Images::product($product)->count() }}
                    </flux:badge>
                    @if(\App\Facades\Images::product($product)->primary())
                        <flux:badge color="green" size="sm">Has Primary</flux:badge>
                    @endif
                </div>
            </div>
            
            @php
                $primaryImage = \App\Facades\Images::product($product)->primary();
            @endphp
            
            {{-- Primary Image Display --}}
            @if ($primaryImage)
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity group relative"
                     wire:click="openImageModal"
                     title="Click to manage images">
                    <img src="{{ $primaryImage->url }}" alt="{{ $primaryImage->alt_text ?? $product->name }}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity bg-blue-600 rounded-full p-2">
                            <flux:icon name="pencil" class="w-4 h-4 text-white" />
                        </div>
                    </div>
                </div>
            @elseif (count($enhancedImages) > 0)
                {{-- Fallback to first enhanced image if no primary image is set --}}
                @php $firstImage = reset($enhancedImages); @endphp
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity group relative"
                     wire:click="openImageModal"
                     title="Click to manage images">
                    <img src="{{ $firstImage['url'] }}" alt="{{ $firstImage['alt_text'] ?? $product->name }}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity bg-blue-600 rounded-full p-2">
                            <flux:icon name="pencil" class="w-4 h-4 text-white" />
                        </div>
                    </div>
                </div>
            @elseif ($product->image_url)
                {{-- Legacy fallback --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity group relative"
                     wire:click="openImageModal"
                     title="Click to manage images">
                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="opacity-0 group-hover:opacity-100 transition-opacity bg-blue-600 rounded-full p-2">
                            <flux:icon name="pencil" class="w-4 h-4 text-white" />
                        </div>
                    </div>
                </div>
            @else
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors group"
                     wire:click="openImageModal"
                     title="Click to add images">
                    <div class="text-center">
                        <flux:icon name="photo" class="w-12 h-12 text-gray-400 group-hover:text-gray-500 mx-auto mb-2" />
                        <p class="text-sm text-gray-500 group-hover:text-gray-600 dark:text-gray-400">
                            Click to add images
                        </p>
                    </div>
                </div>
            @endif

            {{-- 🌟 Enhanced Image Thumbnails --}}
            @if(count($enhancedImages) > 1)
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">All Images (Enhanced)</p>
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach(array_slice($enhancedImages, 0, 5) as $image)
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-md overflow-hidden border-2 {{ $image['is_primary'] ? 'border-blue-500' : 'border-transparent' }} relative group"
                                 title="{{ $image['display_title'] }} (Family: {{ $image['family_stats']['family_size'] ?? 0 }} images)">
                                <img src="{{ $image['thumb_url'] }}" alt="{{ $image['alt_text'] }}" class="w-full h-full object-cover">
                                @if($image['is_primary'])
                                    <div class="absolute top-0 right-0 w-2 h-2 bg-blue-500 rounded-bl"></div>
                                @endif
                            </div>
                        @endforeach
                        @if(count($enhancedImages) > 5)
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center text-xs text-gray-500">
                                +{{ count($enhancedImages) - 5 }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="mt-4">
                <flux:button 
                    wire:click="openImageModal" 
                    variant="outline" 
                    size="sm" 
                    icon="photo" 
                    class="w-full"
                >
                    {{ \App\Facades\Images::product($product)->count() > 0 ? 'Manage Images (Enhanced)' : 'Add Images' }}
                </flux:button>
            </div>
        </div>

        {{-- Quick Stats --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Stats</h3>
            
            <div class="space-y-4">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Variants</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->count() }}</span>
                </div>
                
                @php
                    $inactiveCount = $product->variants->where('status', '!=', 'active')->count();
                @endphp
                @if($inactiveCount > 0)
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Inactive Variants</span>
                        <span class="text-sm font-medium text-red-600 dark:text-red-400">{{ $inactiveCount }}</span>
                    </div>
                @endif
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Unique Colors</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->pluck('color')->unique()->count() }}</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Width Range</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->min('width') }}cm - {{ $product->variants->max('width') }}cm</span>
                </div>
                
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Drop Range</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $product->variants->min('drop') }}cm - {{ $product->variants->max('drop') }}cm</span>
                </div>
                
                @php
                    $priceRange = $this->getPriceRange();
                @endphp
                <div class="flex justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Price Range</span>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                        @if($priceRange['min'] > 0 && $priceRange['max'] > 0)
                            £{{ number_format($priceRange['min'], 2) }} - £{{ number_format($priceRange['max'], 2) }}
                        @else
                            No pricing data
                        @endif
                    </span>
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

            {{-- 🛍️ MARKETPLACE SYNC STATUS (Shopify) --}}
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
                
                // Show status if we have shopify status or recent sync data
                $shouldShowStatus = $shopifyStatus || $shopifyIds || $shopifyMeta;
            @endphp
            
            @if($shouldShowStatus)
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Shopify Sync</h4>
                    
                    {{-- Marketplace Status Section --}}
                    <div class="mb-6 space-y-4">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[60px]">Status:</span>
                            <livewire:marketplace-status :product="$product" channel="shopify" />
                        </div>
                        
                        @if($shopifyStatus === 'synced' && is_array($productIds))
                            <div class="flex items-center gap-4">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[60px]">Products:</span>
                                <flux:badge color="green" size="sm">
                                    {{ count($productIds) }} synced
                                </flux:badge>
                            </div>
                        @endif
                        
                    @if(is_array($productIds) && !empty($productIds))
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <div class="space-y-2">
                                @foreach($productIds as $color => $shopifyId)
                                    @php
                                        $numericId = str_replace('gid://shopify/Product/', '', $shopifyId);
                                    @endphp
                                    <div class="flex items-center justify-between py-1">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $color }}: <span class="font-mono">{{ $numericId }}</span></span>
                                        <a href="https://admin.shopify.com/store/your-store/products/{{ $numericId }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                            View →
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    </div>
                    </div>
            @endif

            {{-- 🛍️ MARKETPLACE ACTIONS (Shopify) --}}
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
                            wire:click="updateShopifyImages" 
                            variant="outline" 
                            size="sm"
                            class="w-full"
                            icon="photo"
                        >
                            Update Images
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

            {{-- 🏬 FREEMANS SYNC STATUS --}}
            @php
                $freemansStatus = $product->getSmartAttributeValue('freemans_status');
                $freemansIds = $product->getSmartAttributeValue('freemans_product_ids');
                $freemansMeta = $product->getSmartAttributeValue('freemans_metadata');
                $freemansShouldShowStatus = $freemansStatus || $freemansIds || $freemansMeta;
            @endphp

            @if($freemansShouldShowStatus)
                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Freemans Sync</h4>

                    <div class="mb-4 flex items-center gap-4">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300 min-w-[60px]">Status:</span>
                        <livewire:marketplace-status :product="$product" channel="freemans" />
                    </div>
                </div>
            @endif

            {{-- 🏬 FREEMANS ACTIONS --}}
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Freemans Actions</h4>

                <div class="space-y-2">
                    <flux:button
                        wire:click="pushToFreemans"
                        variant="filled"
                        size="sm"
                        class="w-full"
                        icon="arrow-up-tray"
                    >
                        {{ $freemansStatus ? 'Update on Freemans' : 'Push to Freemans' }}
                    </flux:button>

                    @if($freemansStatus)
                        <flux:button
                            wire:click="updateFreemansTitle"
                            variant="outline"
                            size="sm"
                            class="w-full"
                            icon="pencil"
                        >
                            Update Title
                        </flux:button>

                        <flux:button
                            wire:click="updateFreemansPricing"
                            variant="outline"
                            size="sm"
                            class="w-full"
                            icon="currency-dollar"
                        >
                            Update Pricing
                        </flux:button>
                    @endif

                    <flux:button
                        wire:click="linkToFreemans"
                        variant="outline"
                        size="sm"
                        class="w-full"
                        icon="link"
                    >
                        Link on Freemans
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    {{-- 🖼️ IMAGE MANAGEMENT MODAL --}}
    <flux:modal wire:model="showImageModal" class="!max-w-[80vw] !w-[80vw]">
        <div class="space-y-6">
            {{-- Modal Header --}}
            <div>
                <flux:heading size="lg">Manage Product Images</flux:heading>
                <flux:text class="mt-2">{{ $product->name }}</flux:text>
            </div>

            {{-- Modal Body --}}
            <div class="min-h-[60vh] max-h-[70vh] overflow-y-auto">
                        {{-- 🌟 Enhanced Current Images --}}
                        @if(count($enhancedImages) > 0)
                            <div class="mb-8">
                                <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                    <flux:icon name="photo" class="w-5 h-5 text-blue-600" />
                                    Current Images ({{ count($enhancedImages) }}) - Enhanced
                                </h4>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    @foreach($enhancedImages as $image)
                                        <div class="relative bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden aspect-square group hover:shadow-lg transition-all duration-200">
                                            <img src="{{ $image['thumb_url'] }}" alt="{{ $image['alt_text'] }}" class="w-full h-full object-cover">
                                            
                                            {{-- 🎯 SIMPLIFIED TOP STATUS BAR --}}
                                            <div class="absolute top-2 left-2 right-2 flex justify-between items-start">
                                                {{-- Combined Status Badge --}}
                                                <div class="flex items-center gap-1">
                                                    @if($image['is_primary'])
                                                        <flux:badge color="yellow" size="sm" class="shadow-sm">
                                                            <flux:icon name="star" class="w-3 h-3" />
                                                        </flux:badge>
                                                    @endif
                                                    @if(isset($image['family_stats']['family_size']) && $image['family_stats']['family_size'] > 1)
                                                        <flux:badge color="blue" size="sm" class="shadow-sm">
                                                            {{ $image['family_stats']['family_size'] }}
                                                        </flux:badge>
                                                    @endif
                                                </div>

                                                {{-- Quick Actions (Always Visible but Subtle) --}}
                                                <div class="flex gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                                                    @if(!$image['is_primary'])
                                                        <flux:button 
                                                            wire:click="setPrimaryImage({{ $image['id'] }})" 
                                                            size="xs" 
                                                            variant="ghost" 
                                                            icon="star"
                                                            class="w-6 h-6 bg-black bg-opacity-20 text-yellow-300 hover:bg-yellow-500 hover:text-white backdrop-blur-sm"
                                                            title="Set as primary"
                                                        />
                                                    @endif
                                                    <flux:button 
                                                        wire:click="detachImage({{ $image['id'] }})" 
                                                        size="xs" 
                                                        variant="ghost" 
                                                        icon="x"
                                                        class="w-6 h-6 bg-black bg-opacity-20 text-red-300 hover:bg-red-500 hover:text-white backdrop-blur-sm"
                                                        title="Remove image"
                                                    />
                                                </div>
                                            </div>

                                            {{-- 📝 CLEAN BOTTOM INFO (Only on Hover) --}}
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/50 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <p class="text-white text-xs font-medium truncate">{{ $image['display_title'] }}</p>
                                                <p class="text-gray-300 text-xs truncate">
                                                    {{ $image['family_stats']['family_size'] ?? 1 }} variant{{ ($image['family_stats']['family_size'] ?? 1) > 1 ? 's' : '' }}
                                                </p>
                                            </div>

                                            {{-- Hover Ring Effect --}}
                                            <div class="absolute inset-0 rounded-lg ring-2 ring-transparent group-hover:ring-blue-300 transition-all pointer-events-none"></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- 📑 TABBED INTERFACE - Select Existing | Upload New --}}
                        <div>
                            {{-- Tab Headers --}}
                            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                                <nav class="-mb-px flex space-x-8">
                                    <button
                                        wire:click="setActiveTab('select')"
                                        class="py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap flex items-center space-x-2 transition-colors
                                               {{ $activeTab === 'select' 
                                                  ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}"
                                    >
                                        <flux:icon name="photo" class="w-4 h-4" />
                                        <span>Select Existing</span>
                                        <flux:badge color="gray" size="sm">{{ $totalImages }}</flux:badge>
                                    </button>
                                    
                                    <button
                                        wire:click="setActiveTab('upload')"
                                        class="py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap flex items-center space-x-2 transition-colors
                                               {{ $activeTab === 'upload' 
                                                  ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                                                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}"
                                    >
                                        <flux:icon name="upload" class="w-4 h-4" />
                                        <span>Upload New</span>
                                    </button>
                                </nav>
                            </div>

                            {{-- Tab Content --}}
                            @if($activeTab === 'select')
                                {{-- 📷 SELECT EXISTING IMAGES TAB --}}
                                <div>

                                {{-- 🔍 SEARCH BAR --}}
                                <div class="mb-4">
                                    <flux:input 
                                        wire:model.live="searchTerm" 
                                        placeholder="Search images by filename, title, or alt text..." 
                                        icon="magnifying-glass"
                                        class="w-full"
                                    />
                                </div>

                                {{-- 📊 RESULTS SUMMARY --}}
                                @if($totalImages > 0)
                                    <div class="mb-4 flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                        <span>
                                            Showing {{ count($availableImages) }} of {{ $totalImages }} images
                                            @if(!empty($searchTerm))
                                                for "{{ $searchTerm }}"
                                            @endif
                                        </span>
                                        @if($totalPages > 1)
                                            <span>Page {{ $currentPage }} of {{ $totalPages }}</span>
                                        @endif
                                    </div>
                                @endif

                            @if(empty($availableImages))
                                <div class="text-center py-8">
                                    <flux:icon name="photo" class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <p class="text-gray-500 dark:text-gray-400">
                                        @if(!empty($searchTerm))
                                            No images found for "{{ $searchTerm }}"
                                        @else
                                            No images found
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                                        @if(!empty($searchTerm))
                                            Try adjusting your search terms
                                        @else
                                            Upload some images to get started
                                        @endif
                                    </p>
                                </div>
                            @else
                                {{-- Selected Images Summary --}}
                                @if(!empty($selectedImages))
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-4 border border-blue-200 dark:border-blue-700/50">
                                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">
                                            {{ count($selectedImages) }} image{{ count($selectedImages) > 1 ? 's' : '' }} selected
                                        </p>
                                        <flux:button 
                                            wire:click="attachSelectedImages" 
                                            variant="primary" 
                                            size="sm"
                                            icon="link"
                                        >
                                            Attach Selected Images
                                        </flux:button>
                                    </div>
                                @endif

                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    @foreach($availableImages as $image)
                                        <div class="relative bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden aspect-square cursor-pointer group hover:shadow-lg transition-all duration-200 {{ in_array($image['id'], $selectedImages) ? 'ring-2 ring-blue-500 ring-offset-2' : '' }}"
                                             wire:click="toggleImageSelection({{ $image['id'] }})">
                                            <img src="{{ $image['thumb_url'] }}" alt="{{ $image['alt_text'] }}" class="w-full h-full object-cover">
                                            
                                            {{-- 🎯 ATTACHMENT STATUS BADGE --}}
                                            <div class="absolute top-2 left-2">
                                                @if($image['is_attached'])
                                                    <flux:badge color="green" size="sm" class="shadow-sm">
                                                        <flux:icon name="link" class="w-3 h-3" />
                                                        Attached
                                                    </flux:badge>
                                                @endif
                                            </div>

                                            {{-- 🎯 CLEAN SELECTION STATE --}}
                                            <div class="absolute top-2 right-2">
                                                @if(in_array($image['id'], $selectedImages))
                                                    <div class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center shadow-lg">
                                                        <flux:icon name="check" class="w-4 h-4 text-white" />
                                                    </div>
                                                @else
                                                    <div class="w-7 h-7 bg-black bg-opacity-20 backdrop-blur-sm rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                                        <flux:icon name="plus" class="w-4 h-4 text-white" />
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- 📝 CLEAN INFO (Only on Hover for Non-Selected) --}}
                                            @if(!in_array($image['id'], $selectedImages))
                                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/50 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <p class="text-white text-xs font-medium truncate">{{ $image['display_title'] }}</p>
                                                    <p class="text-gray-300 text-xs">Click to select</p>
                                                </div>
                                            @else
                                                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-blue-600 via-blue-500/50 to-transparent p-2">
                                                    <p class="text-white text-xs font-medium truncate">{{ $image['display_title'] }}</p>
                                                    <p class="text-blue-100 text-xs">Selected</p>
                                                </div>
                                            @endif

                                            {{-- Hover Ring Effect --}}
                                            <div class="absolute inset-0 rounded-lg ring-2 ring-transparent group-hover:ring-blue-300 transition-all pointer-events-none"></div>
                                        </div>
                                    @endforeach
                                </div>

                                {{-- 📄 PAGINATION CONTROLS --}}
                                @if($totalPages > 1)
                                    <div class="mt-6 flex justify-between items-center">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            Showing {{ count($availableImages) }} of {{ $totalImages }} images
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <flux:button 
                                                wire:click="previousPage" 
                                                variant="ghost" 
                                                size="sm" 
                                                icon="chevron-left"
                                                :disabled="$currentPage <= 1"
                                            >
                                                Previous
                                            </flux:button>
                                            
                                            <div class="flex items-center space-x-1">
                                                @php
                                                    $start = max(1, $currentPage - 2);
                                                    $end = min($totalPages, $currentPage + 2);
                                                @endphp
                                                
                                                @if($start > 1)
                                                    <flux:button wire:click="goToPage(1)" variant="ghost" size="sm">1</flux:button>
                                                    @if($start > 2)
                                                        <span class="text-gray-400">...</span>
                                                    @endif
                                                @endif
                                                
                                                @for($i = $start; $i <= $end; $i++)
                                                    <flux:button 
                                                        wire:click="goToPage({{ $i }})" 
                                                        variant="{{ $i === $currentPage ? 'primary' : 'ghost' }}" 
                                                        size="sm"
                                                    >
                                                        {{ $i }}
                                                    </flux:button>
                                                @endfor
                                                
                                                @if($end < $totalPages)
                                                    @if($end < $totalPages - 1)
                                                        <span class="text-gray-400">...</span>
                                                    @endif
                                                    <flux:button wire:click="goToPage({{ $totalPages }})" variant="ghost" size="sm">{{ $totalPages }}</flux:button>
                                                @endif
                                            </div>
                                            
                                            <flux:button 
                                                wire:click="nextPage" 
                                                variant="ghost" 
                                                size="sm" 
                                                icon="chevron-right"
                                                :disabled="$currentPage >= $totalPages"
                                            >
                                                Next
                                            </flux:button>
                                        </div>
                                    </div>
                                @endif
                            @endif
                                </div>
                            @elseif($activeTab === 'upload')
                                {{-- 📤 UPLOAD NEW IMAGES TAB --}}
                                <div>
                                    <form wire:submit="uploadImages" class="space-y-4">
                                        {{-- File Upload --}}
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                Select Images (max 10)
                                            </label>
                                            <input
                                                type="file"
                                                wire:model="newImages"
                                                multiple
                                                accept="image/*"
                                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/20 dark:file:text-blue-400"
                                            />
                                            @error('newImages.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                        </div>

                                        {{-- Upload Metadata --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <flux:input
                                                    label="Title (optional)"
                                                    wire:model="uploadMetadata.title"
                                                    placeholder="Enter image title..."
                                                />
                                            </div>

                                            <div>
                                                <flux:input
                                                    label="Alt Text (optional)"
                                                    wire:model="uploadMetadata.alt_text"
                                                    placeholder="Describe the image..."
                                                />
                                            </div>
                                        </div>

                                        <div>
                                            <flux:textarea
                                                label="Description (optional)"
                                                wire:model="uploadMetadata.description"
                                                placeholder="Enter image description..."
                                                rows="3"
                                            />
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <flux:input
                                                    label="Folder"
                                                    wire:model="uploadMetadata.folder"
                                                    placeholder="uncategorized"
                                                />
                                            </div>

                                            <div>
                                                <flux:input
                                                    label="Tags (comma-separated)"
                                                    wire:model="uploadMetadata.tags"
                                                    placeholder="product, hero, banner..."
                                                />
                                            </div>
                                        </div>

                                        {{-- Upload Actions --}}
                                        <div class="flex justify-end pt-4">
                                            <flux:button
                                                icon="upload"
                                                type="submit"
                                                variant="primary"
                                                wire:loading.attr="disabled"
                                                wire:loading.class="opacity-75"
                                                wire:target="uploadImages"
                                            >
                                                <span wire:loading.remove wire:target="uploadImages">Upload & Attach to Product</span>
                                                <span wire:loading wire:target="uploadImages">
                                                    <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin"/>
                                                    Uploading...
                                                </span>
                                            </flux:button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    @if($activeTab === 'select')
                        Click images to select, then attach to product
                    @else
                        Upload new images and they'll be automatically attached
                    @endif
                </flux:text>
                <div class="flex gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">
                            Close
                        </flux:button>
                    </flux:modal.close>
                    @if(!empty($selectedImages))
                        <flux:button wire:click="attachSelectedImages" variant="primary" icon="link">
                            Attach {{ count($selectedImages) }} Image{{ count($selectedImages) > 1 ? 's' : '' }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- 🎨 COLOR GROUP IMAGE MODAL --}}
    <flux:modal wire:model="showColorImageModal" class="!max-w-[80vw] !w-[80vw]">
        <div class="space-y-6">
            {{-- Modal Header --}}
            <div>
                <flux:heading size="lg" class="flex items-center gap-2">
                    <div class="w-4 h-4 rounded-full border border-gray-300
                        @if(strtolower($currentColor) === 'black') bg-gray-900
                        @elseif(strtolower($currentColor) === 'white') bg-white border-gray-400
                        @elseif(strtolower($currentColor) === 'red') bg-red-500
                        @elseif(strtolower($currentColor) === 'blue') bg-blue-500
                        @elseif(strtolower($currentColor) === 'green') bg-green-500
                        @elseif(str_contains(strtolower($currentColor), 'grey') || str_contains(strtolower($currentColor), 'gray')) bg-gray-500
                        @elseif(str_contains(strtolower($currentColor), 'orange')) bg-orange-500
                        @elseif(str_contains(strtolower($currentColor), 'yellow') || str_contains(strtolower($currentColor), 'lemon')) bg-yellow-500
                        @elseif(str_contains(strtolower($currentColor), 'purple') || str_contains(strtolower($currentColor), 'lavender')) bg-purple-500
                        @elseif(str_contains(strtolower($currentColor), 'pink')) bg-pink-500
                        @elseif(str_contains(strtolower($currentColor), 'brown') || str_contains(strtolower($currentColor), 'cappuccino')) bg-amber-700
                        @elseif(str_contains(strtolower($currentColor), 'navy')) bg-blue-900
                        @elseif(str_contains(strtolower($currentColor), 'natural')) bg-amber-200
                        @elseif(str_contains(strtolower($currentColor), 'lime')) bg-lime-500
                        @elseif(str_contains(strtolower($currentColor), 'aubergine')) bg-purple-900
                        @elseif(str_contains(strtolower($currentColor), 'ochre')) bg-yellow-700
                        @else bg-gradient-to-br from-orange-400 to-red-500
                        @endif">
                    </div>
                    Manage {{ $currentColor }} Images
                </flux:heading>
                <flux:text class="mt-2">{{ $product->name }} - {{ $currentColor }} Color Group</flux:text>
            </div>

            {{-- Modal Body --}}
            <div class="min-h-[60vh] max-h-[70vh] overflow-y-auto">
                        {{-- 🔍 SEARCH BAR --}}
                        <div class="mb-4">
                            <flux:input 
                                wire:model.live="colorSearchTerm" 
                                placeholder="Search images by filename, title, or alt text..." 
                                icon="magnifying-glass"
                                class="w-full"
                            />
                        </div>

                        {{-- 📊 RESULTS SUMMARY --}}
                        @if($colorTotalImages > 0)
                            <div class="mb-4 flex justify-between items-center text-sm text-gray-600 dark:text-gray-400">
                                <span>
                                    Showing {{ count($colorImages) }} of {{ $colorTotalImages }} images
                                    @if(!empty($colorSearchTerm))
                                        for "{{ $colorSearchTerm }}"
                                    @endif
                                </span>
                                @if($colorTotalPages > 1)
                                    <span>Page {{ $colorCurrentPage }} of {{ $colorTotalPages }}</span>
                                @endif
                            </div>
                        @endif

                        @if(empty($colorImages))
                            <div class="text-center py-8">
                                <flux:icon name="photo" class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                <p class="text-gray-500 dark:text-gray-400">
                                    @if(!empty($colorSearchTerm))
                                        No images found for "{{ $colorSearchTerm }}"
                                    @else
                                        No images found
                                    @endif
                                </p>
                                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                                    @if(!empty($colorSearchTerm))
                                        Try adjusting your search terms
                                    @else
                                        Upload some images to get started
                                    @endif
                                </p>
                            </div>
                        @else

                            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                @foreach($colorImages as $image)
                                    <div class="relative bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden aspect-square cursor-pointer group hover:shadow-lg transition-all duration-200 {{ $image['is_attached'] ? 'ring-2 ring-green-500 ring-offset-2' : '' }}"
                                         wire:click="{{ $image['is_attached'] ? 'detachColorImage(' . $image['id'] . ')' : 'attachColorImage(' . $image['id'] . ')' }}">
                                        <img src="{{ $image['thumb_url'] }}" alt="{{ $image['alt_text'] }}" class="w-full h-full object-cover">
                                        
                                        {{-- 🎯 ATTACHMENT STATUS BADGE --}}
                                        <div class="absolute top-2 left-2">
                                            @if($image['is_attached'])
                                                <flux:badge color="green" size="sm" class="shadow-sm">
                                                    <flux:icon name="link" class="w-3 h-3" />
                                                    Attached
                                                </flux:badge>
                                            @endif
                                        </div>

                                        {{-- 🎯 ACTION STATE INDICATOR --}}
                                        <div class="absolute top-2 right-2">
                                            @if($image['is_attached'])
                                                <div class="w-7 h-7 bg-red-600 rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                                                </div>
                                            @else
                                                <div class="w-7 h-7 bg-green-600 rounded-full flex items-center justify-center shadow-lg opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <flux:icon name="plus" class="w-4 h-4 text-white" />
                                                </div>
                                            @endif
                                        </div>

                                        {{-- 📝 ACTION INFO --}}
                                        @if($image['is_attached'])
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-green-600 via-green-500/50 to-transparent p-2">
                                                <p class="text-white text-xs font-medium truncate">{{ $image['display_title'] }}</p>
                                                <p class="text-green-100 text-xs">Attached - Click to detach</p>
                                            </div>
                                        @else
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black via-black/50 to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <p class="text-white text-xs font-medium truncate">{{ $image['display_title'] }}</p>
                                                <p class="text-gray-300 text-xs">Click to attach</p>
                                            </div>
                                        @endif

                                        {{-- Hover Ring Effect --}}
                                        <div class="absolute inset-0 rounded-lg ring-2 ring-transparent group-hover:ring-blue-300 transition-all pointer-events-none"></div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- 📄 PAGINATION CONTROLS --}}
                            @if($colorTotalPages > 1)
                                <div class="mt-6 flex justify-between items-center">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        Showing {{ count($colorImages) }} of {{ $colorTotalImages }} images
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <flux:button 
                                            wire:click="colorPreviousPage" 
                                            variant="ghost" 
                                            size="sm" 
                                            icon="chevron-left"
                                            :disabled="$colorCurrentPage <= 1"
                                        >
                                            Previous
                                        </flux:button>
                                        
                                        <div class="flex items-center space-x-1">
                                            @php
                                                $start = max(1, $colorCurrentPage - 2);
                                                $end = min($colorTotalPages, $colorCurrentPage + 2);
                                            @endphp
                                            
                                            @if($start > 1)
                                                <flux:button wire:click="colorGoToPage(1)" variant="ghost" size="sm">1</flux:button>
                                                @if($start > 2)
                                                    <span class="text-gray-400">...</span>
                                                @endif
                                            @endif
                                            
                                            @for($i = $start; $i <= $end; $i++)
                                                <flux:button 
                                                    wire:click="colorGoToPage({{ $i }})" 
                                                    variant="{{ $i === $colorCurrentPage ? 'primary' : 'ghost' }}" 
                                                    size="sm"
                                                >
                                                    {{ $i }}
                                                </flux:button>
                                            @endfor
                                            
                                            @if($end < $colorTotalPages)
                                                @if($end < $colorTotalPages - 1)
                                                    <span class="text-gray-400">...</span>
                                                @endif
                                                <flux:button wire:click="colorGoToPage({{ $colorTotalPages }})" variant="ghost" size="sm">{{ $colorTotalPages }}</flux:button>
                                            @endif
                                        </div>
                                        
                                        <flux:button 
                                            wire:click="colorNextPage" 
                                            variant="ghost" 
                                            size="sm" 
                                            icon="chevron-right"
                                            :disabled="$colorCurrentPage >= $colorTotalPages"
                                        >
                                            Next
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        @endif
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:text class="text-sm text-gray-600 dark:text-gray-400">
                    Click images to attach/detach to {{ $currentColor }} color group
                </flux:text>
                <div class="flex gap-3">
                    <flux:modal.close>
                        <flux:button variant="ghost">
                            Close
                        </flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </div>
    </flux:modal>
</div>

{{-- 🔌 Real-time marketplace sync progress --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        try {
            if (window.Echo) {
                const productId = @json($product->id);
                const channel = `product-sync.${productId}`;

                // Avoid duplicate listeners by tracking on window scope
                const key = `__sync_listening_${productId}`;
                if (!window[key]) {
                    window[key] = true;
                    window.Echo.channel(channel)
                        .listen('.ProductSyncProgress', (e) => {
                            // Forward to Livewire for state + toasts
                            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                                window.Livewire.dispatch('productSyncProgress', e);
                            }
                        });
                }
            } else {
                console.warn('Echo not available; marketplace sync updates will not be real-time.');
            }
        } catch (err) {
            console.error('Failed to attach sync progress listener:', err);
        }
    });
</script>
