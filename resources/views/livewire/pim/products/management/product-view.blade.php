<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => $product->name]
            ]" class="mb-4" />
            
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-4 mb-3">
                        @if($product->productImages->where('image_type', 'main')->first())
                            <img src="{{ Storage::url($product->productImages->where('image_type', 'main')->first()->image_path) }}" 
                                 alt="{{ $product->name }}" 
                                 class="w-16 h-16 object-cover rounded-xl border border-zinc-200 dark:border-zinc-700">
                        @else
                            <div class="w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                <flux:icon name="package" class="h-8 w-8 text-white" />
                            </div>
                        @endif
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                {{ $product->name }}
                            </flux:heading>
                            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                {{ $product->slug }}
                            </flux:subheading>
                            <div class="flex items-center gap-2 mt-2">
                                @if($product->status === 'active')
                                    <flux:badge variant="outline" class="bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800">
                                        <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                        Active
                                    </flux:badge>
                                @elseif($product->status === 'inactive')
                                    <flux:badge variant="outline" class="bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600">
                                        <flux:icon name="circle-pause" class="mr-1 h-3 w-3" />
                                        Inactive
                                    </flux:badge>
                                @else
                                    <flux:badge variant="outline" class="bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                        <flux:icon name="circle-x" class="mr-1 h-3 w-3" />
                                        Discontinued
                                    </flux:badge>
                                @endif
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $product->variants->count() }} variants
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-2">
                    <flux:button variant="outline" icon="pencil" :href="route('products.product.edit', $product)" wire:navigate>
                        Edit Product
                    </flux:button>
                    <flux:button variant="primary" icon="plus" :href="route('products.variants.create') . '?product=' . $product->id" wire:navigate>
                        Add Variant
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6" wire:cloak>
            @if($this->activeTab === 'overview')
                <!-- Overview Tab Content -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Description -->
                        @if($product->description)
                            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                                <flux:heading size="lg" class="mb-4">Description</flux:heading>
                                <div class="prose prose-sm dark:prose-invert max-w-none">
                                    {{ $product->description }}
                                </div>
                            </div>
                        @endif

                        <!-- Product Features -->
                        @if(collect([$product->product_features_1, $product->product_features_2, $product->product_features_3, $product->product_features_4, $product->product_features_5])->filter()->isNotEmpty())
                            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                                <flux:heading size="lg" class="mb-4">Features</flux:heading>
                                <div class="space-y-3">
                                    @foreach(['product_features_1', 'product_features_2', 'product_features_3', 'product_features_4', 'product_features_5'] as $feature)
                                        @if($product->$feature)
                                            <div class="flex items-start gap-3">
                                                <flux:icon name="check" class="h-4 w-4 text-emerald-500 mt-0.5 flex-shrink-0" />
                                                <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $product->$feature }}</span>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Product Details -->
                        @if(collect([$product->product_details_1, $product->product_details_2, $product->product_details_3, $product->product_details_4, $product->product_details_5])->filter()->isNotEmpty())
                            <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                                <flux:heading size="lg" class="mb-4">Details</flux:heading>
                                <div class="space-y-3">
                                    @foreach(['product_details_1', 'product_details_2', 'product_details_3', 'product_details_4', 'product_details_5'] as $detail)
                                        @if($product->$detail)
                                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ $product->$detail }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="space-y-6">

                        <!-- Quick Stats -->
                        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                            <flux:heading size="lg" class="mb-4">Quick Stats</flux:heading>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Total Variants</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Total Stock</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->sum('stock_level') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Active Variants</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->variants->where('status', 'active')->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Images</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->productImages->count() }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Created</span>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $product->created_at->format('M j, Y') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($this->activeTab === 'variants')
                <!-- Variants Tab Content -->
                <livewire:pim.products.variants.variant-index :product="$product" wire:key="product-variants-{{ $product->id }}" />

            @elseif($this->activeTab === 'images')
                <!-- Images Tab Content -->
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <flux:heading size="lg">Product Images</flux:heading>
                        <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                            {{ $product->productImages->count() }} images
                        </flux:subheading>
                    </div>
                    
                    <!-- Main Images Section -->
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="border-b border-zinc-200 dark:border-zinc-700 p-4">
                            <div class="flex gap-2 flex-wrap">
                                <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                    Main Images ({{ $product->productImages->where('image_type', 'main')->count() }})
                                </flux:badge>
                                <flux:badge variant="outline">
                                    Detail Images ({{ $product->productImages->where('image_type', 'detail')->count() }})
                                </flux:badge>
                                <flux:badge variant="outline">
                                    Lifestyle Images ({{ $product->productImages->where('image_type', 'lifestyle')->count() }})
                                </flux:badge>
                                <flux:badge variant="outline">
                                    Swatch Images ({{ $product->productImages->where('image_type', 'swatch')->count() }})
                                </flux:badge>
                            </div>
                        </div>
                        
                        <!-- Main Images Uploader -->
                        <div class="p-6">
                            <livewire:components.image-uploader 
                                :model-type="'product'"
                                :model-id="$product->id"
                                :image-type="'main'"
                                :multiple="true"
                                :max-files="10"
                                :max-size="10240"
                                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                                :process-immediately="true"
                                :show-preview="true"
                                :allow-reorder="true"
                                :show-existing-images="true"
                                upload-text="Upload main product images"
                                wire:key="product-main-images-{{ $product->id }}"
                            />
                        </div>
                    </div>
                    
                    <!-- Detail Images Section -->
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="p-6">
                            <livewire:components.image-uploader 
                                :model-type="'product'"
                                :model-id="$product->id"
                                :image-type="'detail'"
                                :multiple="true"
                                :max-files="8"
                                :max-size="10240"
                                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                                :process-immediately="true"
                                :show-preview="true"
                                :allow-reorder="true"
                                :show-existing-images="true"
                                upload-text="Upload detailed product images"
                                wire:key="product-detail-images-{{ $product->id }}"
                            />
                        </div>
                    </div>
                    
                    <!-- Lifestyle Images Section -->
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="p-6">
                            <livewire:components.image-uploader 
                                :model-type="'product'"
                                :model-id="$product->id"
                                :image-type="'lifestyle'"
                                :multiple="true"
                                :max-files="6"
                                :max-size="10240"
                                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                                :process-immediately="true"
                                :show-preview="true"
                                :allow-reorder="true"
                                :show-existing-images="true"
                                upload-text="Upload lifestyle product images"
                                wire:key="product-lifestyle-images-{{ $product->id }}"
                            />
                        </div>
                    </div>
                </div>

            @elseif($this->activeTab === 'attributes')
                <!-- Attributes Tab Content -->

                <!-- Product Attributes -->
                @if($product->attributes->isNotEmpty())
                    <div class="space-y-6">
                        <flux:heading size="lg">Product Attributes</flux:heading>
                        <div class="space-y-4">
                            @foreach($product->attributes as $attribute)
                                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $attribute->attributeDefinition->label }}
                                        </div>
                                        @if($attribute->attributeDefinition->description)
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $attribute->attributeDefinition->description }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $attribute->value }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon name="tag" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Attributes</flux:heading>
                        <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                            Add product attributes to provide more detailed information about your products
                        </flux:subheading>
                        <flux:button variant="primary" :href="route('products.product.edit', $product)" wire:navigate>
                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                            Add Attributes
                        </flux:button>
                    </div>
                @endif

            @elseif($this->activeTab === 'sync')
                <!-- Marketplace Sync Tab Content -->
                @php
                    $shopifyStatus = $product->getShopifySyncStatus();
                @endphp
                <div class="space-y-6">
                    <flux:heading size="lg">Marketplace Sync Status</flux:heading>
                    <div class="space-y-6">
                        <!-- Shopify Status -->
                        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900 rounded-lg flex items-center justify-center">
                                        <flux:icon name="shopping-bag" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Shopify</span>
                                </div>
                                <flux:button variant="outline" size="sm">
                                    <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                                    Sync Now
                                </flux:button>
                            </div>
                            <x-sync-status-badge 
                                :status="$shopifyStatus['status']"
                                :last-synced-at="$shopifyStatus['last_synced_at'] ? \Carbon\Carbon::parse($shopifyStatus['last_synced_at']) : null"
                                marketplace="Shopify"
                                size="md"
                            />
                            @if($shopifyStatus['total_colors'] > 0)
                                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $shopifyStatus['colors_synced'] }}/{{ $shopifyStatus['total_colors'] }} color variants synced
                                    @if($shopifyStatus['has_failures'])
                                        <span class="text-red-500 ml-2">• Has failures</span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- eBay Status Placeholder -->
                        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                        <flux:icon name="globe" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">eBay</span>
                                </div>
                                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                                    Coming Soon
                                </flux:badge>
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                eBay marketplace integration is in development
                            </div>
                        </div>

                        <!-- Amazon Status Placeholder -->
                        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                                        <flux:icon name="shopping-cart" class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                                    </div>
                                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Amazon</span>
                                </div>
                                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                                    Coming Soon
                                </flux:badge>
                            </div>
                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                Amazon marketplace integration is planned for future release
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-route-tabs>
</div>