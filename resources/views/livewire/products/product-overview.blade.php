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
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Product Images</h3>
                <flux:badge color="blue" size="sm">
                    {{ $product->images->count() }}
                </flux:badge>
            </div>
            
            @php
                $primaryImage = $product->primaryImage();
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
            @elseif ($product->images->count() > 0)
                {{-- Fallback to first image if no primary image is set --}}
                @php $firstImage = $product->images->first(); @endphp
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden cursor-pointer hover:opacity-90 transition-opacity group relative"
                     wire:click="openImageModal"
                     title="Click to manage images">
                    <img src="{{ $firstImage->url }}" alt="{{ $firstImage->alt_text ?? $product->name }}" class="w-full h-full object-cover">
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

            {{-- Image Thumbnails --}}
            @if($product->images->count() > 1)
                <div class="mt-4">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">All Images</p>
                    <div class="flex gap-2 overflow-x-auto pb-2">
                        @foreach($product->images->take(5) as $image)
                            @php
                                $thumbnailImage = \App\Models\Image::where('folder', 'variants')
                                    ->whereJsonContains('tags', "original-{$image->id}")
                                    ->whereJsonContains('tags', 'thumb')
                                    ->first();
                                $displayUrl = $thumbnailImage ? $thumbnailImage->url : $image->url;
                            @endphp
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-md overflow-hidden border-2 {{ $image->pivot->is_primary ? 'border-blue-500' : 'border-transparent' }}"
                                 title="{{ $image->display_title }}">
                                <img src="{{ $displayUrl }}" alt="{{ $image->alt_text }}" class="w-full h-full object-cover">
                            </div>
                        @endforeach
                        @if($product->images->count() > 5)
                            <div class="flex-shrink-0 w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-md flex items-center justify-center text-xs text-gray-500">
                                +{{ $product->images->count() - 5 }}
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
                    {{ $product->images->count() > 0 ? 'Manage Images' : 'Add Images' }}
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

    {{-- 🖼️ IMAGE MANAGEMENT MODAL --}}
    @if($showImageModal)
        <div class="fixed inset-0 z-50 overflow-y-auto" x-data="{ show: @entangle('showImageModal') }" x-show="show" x-transition>
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-black bg-opacity-50" wire:click="closeImageModal"></div>
                
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Manage Product Images</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                {{ $product->name }}
                            </p>
                        </div>
                        <flux:button wire:click="closeImageModal" variant="ghost" icon="x-mark" size="sm" />
                    </div>

                    {{-- Modal Body --}}
                    <div class="p-6 overflow-y-auto max-h-[70vh]">
                        {{-- Current Images --}}
                        @if($product->images->count() > 0)
                            <div class="mb-8">
                                <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                    <flux:icon name="photo" class="w-5 h-5 text-blue-600" />
                                    Current Images ({{ $product->images->count() }})
                                </h4>
                                
                                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                    @foreach($product->images as $image)
                                        <div class="relative bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden aspect-square group">
                                            @php
                                                $thumbnailImage = \App\Models\Image::where('folder', 'variants')
                                                    ->whereJsonContains('tags', "original-{$image->id}")
                                                    ->whereJsonContains('tags', 'thumb')
                                                    ->first();
                                                $displayUrl = $thumbnailImage ? $thumbnailImage->url : $image->url;
                                            @endphp
                                            <img src="{{ $displayUrl }}" alt="{{ $image->alt_text }}" class="w-full h-full object-cover"
                                                 title="Original: {{ $image->display_title }}">
                                            
                                            {{-- Primary Badge --}}
                                            @if($image->pivot->is_primary)
                                                <div class="absolute top-2 left-2">
                                                    <flux:badge color="yellow" size="sm">
                                                        <flux:icon name="star" class="w-3 h-3" />
                                                        Primary
                                                    </flux:badge>
                                                </div>
                                            @endif

                                            {{-- Action Buttons --}}
                                            <div class="absolute inset-0">
                                                <div class="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity bg-white bg-opacity-10 rounded backdrop-blur-sm p-1">
                                                    @if(!$image->pivot->is_primary)
                                                        <flux:button 
                                                            wire:click="setPrimaryImage({{ $image->id }})" 
                                                            size="xs" 
                                                            variant="ghost" 
                                                            icon="star"
                                                            class="bg-white bg-opacity-90 text-yellow-600 hover:bg-yellow-50"
                                                            title="Set as primary"
                                                        />
                                                    @endif
                                                    <flux:button 
                                                        wire:click="detachImage({{ $image->id }})" 
                                                        size="xs" 
                                                        variant="ghost" 
                                                        icon="x"
                                                        class="bg-white bg-opacity-90 text-red-600 hover:bg-red-50"
                                                        title="Remove image"
                                                    />
                                                </div>
                                            </div>

                                            {{-- Image Info --}}
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2">
                                                <p class="text-white text-xs truncate">{{ $image->display_title }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Available Images --}}
                        <div>
                            <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <flux:icon name="plus" class="w-5 h-5 text-green-600" />
                                Available Images ({{ count($availableImages) }})
                            </h4>

                            @if(empty($availableImages))
                                <div class="text-center py-8">
                                    <flux:icon name="photo" class="w-12 h-12 mx-auto text-gray-400 mb-3" />
                                    <p class="text-gray-500 dark:text-gray-400">No available images to attach</p>
                                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                                        All images from the library are already attached to this product.
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
                                        <div class="relative bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden aspect-square cursor-pointer group {{ in_array($image['id'], $selectedImages) ? 'ring-2 ring-blue-500 ring-offset-2' : '' }}"
                                             wire:click="toggleImageSelection({{ $image['id'] }})">
                                            <img src="{{ $image['thumb_url'] }}" alt="{{ $image['alt_text'] }}" class="w-full h-full object-cover"
                                                 title="Original: {{ $image['display_title'] }}">
                                            
                                            {{-- Selection Indicator --}}
                                            @if(in_array($image['id'], $selectedImages))
                                                <div class="absolute top-2 right-2">
                                                    <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center">
                                                        <flux:icon name="check" class="w-4 h-4 text-white" />
                                                    </div>
                                                </div>
                                            @endif

                                            {{-- Hover Overlay --}}
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                @if(!in_array($image['id'], $selectedImages))
                                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity bg-blue-600 rounded-full p-2">
                                                        <flux:icon name="plus" class="w-6 h-6 text-white" />
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- Image Info --}}
                                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2">
                                                <p class="text-white text-xs truncate">{{ $image['display_title'] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Modal Footer --}}
                    <div class="flex items-center justify-between p-6 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Click images to select, then attach to product
                        </div>
                        <div class="flex gap-3">
                            <flux:button wire:click="closeImageModal" variant="ghost">
                                Close
                            </flux:button>
                            @if(!empty($selectedImages))
                                <flux:button wire:click="attachSelectedImages" variant="primary" icon="link">
                                    Attach {{ count($selectedImages) }} Image{{ count($selectedImages) > 1 ? 's' : '' }}
                                </flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>