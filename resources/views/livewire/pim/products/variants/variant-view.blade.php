<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <!-- Breadcrumb -->
            <x-breadcrumb :items="[
                ['name' => 'Products', 'url' => route('products.index')],
                ['name' => 'Variant Details']
            ]" class="mb-4" />
            
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <!-- Title -->
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 bg-zinc-100 dark:bg-zinc-700 rounded-xl flex items-center justify-center">
                            <flux:icon name="layers" class="h-6 w-6 text-zinc-500 dark:text-zinc-400" />
                        </div>
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                {{ $variant->product->name }}
                            </flux:heading>
                            <div class="flex items-center gap-2 mt-1">
                                @if($variant->color)
                                    <span class="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 px-2 py-1 rounded-full border border-purple-200 dark:border-purple-800">
                                        <flux:icon name="palette" class="w-3 h-3 mr-1" />
                                        {{ $variant->color }}
                                    </span>
                                @endif
                                @if($variant->dimensions)
                                    <span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-2 py-1 rounded-full border border-blue-200 dark:border-blue-800">
                                        <flux:icon name="ruler" class="w-3 h-3 mr-1" />
                                        {{ $variant->dimensions }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <flux:subheading class="text-zinc-600 dark:text-zinc-400 font-mono bg-zinc-100 dark:bg-zinc-800 px-3 py-1 rounded-lg text-sm">
                            SKU: {{ $variant->sku }}
                        </flux:subheading>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->getStatusBadgeClass() }}">
                            {{ ucfirst($variant->status) }}
                        </span>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <flux:button variant="outline" icon="copy" wire:click="duplicateVariant">
                        Duplicate
                    </flux:button>
                    
                    <flux:button variant="primary" icon="pencil" wire:click="editVariant">
                        Edit
                    </flux:button>
                    
                    <flux:dropdown>
                        <flux:button variant="outline" icon="ellipsis-vertical" />
                        
                        <flux:menu>
                            <flux:menu.item wire:click="deleteVariant" variant="danger">
                                <flux:icon name="trash-2" class="w-4 h-4" />
                                Delete Variant
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            @if($this->activeTab === 'details')
                <!-- Basic Information Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Basic Information</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-1">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Product</flux:label>
                            <div class="flex items-center gap-2">
                                <flux:icon name="package" class="h-4 w-4 text-zinc-400" />
                                <a href="#" class="text-zinc-900 dark:text-zinc-100 hover:text-zinc-600 dark:hover:text-zinc-300 font-medium transition-colors duration-200">
                                    {{ $variant->product->name }}
                                </a>
                            </div>
                        </div>
                        
                        <div class="space-y-1">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Status</flux:label>
                            <div>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $this->getStatusBadgeClass() }}">
                                    <flux:icon name="circle-check" class="mr-1 h-3 w-3" />
                                    {{ ucfirst($variant->status) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="space-y-1">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Color</flux:label>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 rounded-full bg-purple-200 border border-purple-300"></div>
                                <span class="text-zinc-900 dark:text-zinc-100 font-medium">{{ $variant->color ?: 'Not specified' }}</span>
                            </div>
                        </div>
                        
                        <div class="space-y-1">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Dimensions</flux:label>
                            <div class="flex items-center gap-2">
                                <flux:icon name="ruler" class="h-4 w-4 text-zinc-400" />
                                <span class="text-zinc-900 dark:text-zinc-100 font-medium">
                                    {{ $variant->dimensions ?: 'Not specified' }}
                                </span>
                            </div>
                            @if($variant->width || $variant->drop)
                                <div class="text-xs text-zinc-500 dark:text-zinc-400 ml-6">
                                    @if($variant->width)
                                        Width: {{ $variant->width }}cm
                                    @endif
                                    @if($variant->width && $variant->drop) • @endif
                                    @if($variant->drop)
                                        Drop: {{ $variant->drop }}cm
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Package Dimensions -->
                    @if($variant->package_length || $variant->package_width || $variant->package_height || $variant->package_weight)
                        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-6">
                            <flux:heading size="md" class="mb-4">Package Dimensions</flux:heading>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                                @if($variant->package_length)
                                <div class="text-center">
                                    <flux:icon name="ruler" class="h-6 w-6 text-zinc-400 mx-auto mb-2" />
                                    <flux:label class="text-xs font-medium text-zinc-700 dark:text-zinc-300 block mb-1">Length</flux:label>
                                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($variant->package_length, 1) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">cm</div>
                                </div>
                                @endif
                                
                                @if($variant->package_width)
                                <div class="text-center">
                                    <flux:icon name="ruler" class="h-6 w-6 text-zinc-400 mx-auto mb-2" />
                                    <flux:label class="text-xs font-medium text-zinc-700 dark:text-zinc-300 block mb-1">Width</flux:label>
                                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($variant->package_width, 1) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">cm</div>
                                </div>
                                @endif
                                
                                @if($variant->package_height)
                                <div class="text-center">
                                    <flux:icon name="ruler" class="h-6 w-6 text-zinc-400 mx-auto mb-2" />
                                    <flux:label class="text-xs font-medium text-zinc-700 dark:text-zinc-300 block mb-1">Height</flux:label>
                                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($variant->package_height, 1) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">cm</div>
                                </div>
                                @endif
                                
                                @if($variant->package_weight)
                                <div class="text-center">
                                    <flux:icon name="weight" class="h-6 w-6 text-zinc-400 mx-auto mb-2" />
                                    <flux:label class="text-xs font-medium text-zinc-700 dark:text-zinc-300 block mb-1">Weight</flux:label>
                                    <div class="text-lg font-bold text-zinc-900 dark:text-zinc-100">
                                        {{ number_format($variant->package_weight, 2) }}
                                    </div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">kg</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

            @elseif($this->activeTab === 'inventory')
                <!-- Stock & Inventory Tab -->
                <div class="space-y-6">
                    <flux:heading size="lg" class="mb-4">Stock & Inventory</flux:heading>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Stock Level</flux:label>
                            <div class="flex items-baseline gap-2">
                                <span class="text-4xl font-bold {{ $this->getStockLevelClass() }}">
                                    {{ number_format($variant->stock_level ?? 0) }}
                                </span>
                                <span class="text-sm text-zinc-500 dark:text-zinc-400 font-medium">units</span>
                            </div>
                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 mt-3">
                                @php $stockPercentage = min(100, ($variant->stock_level ?? 0) * 2) @endphp
                                <div class="h-3 rounded-full {{ ($variant->stock_level ?? 0) > 10 ? 'bg-emerald-500' : (($variant->stock_level ?? 0) > 0 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $stockPercentage }}%"></div>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <flux:label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Stock Status</flux:label>
                            <div>
                                @if(($variant->stock_level ?? 0) === 0)
                                    <span class="inline-flex items-center px-4 py-3 rounded-xl text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300 border border-red-200 dark:border-red-800">
                                        <flux:icon name="triangle-alert" class="w-5 h-5 mr-2" />
                                        Out of Stock
                                    </span>
                                @elseif(($variant->stock_level ?? 0) <= 10)
                                    <span class="inline-flex items-center px-4 py-3 rounded-xl text-sm font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                        <flux:icon name="circle-alert" class="w-5 h-5 mr-2" />
                                        Low Stock
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-4 py-3 rounded-xl text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        <flux:icon name="circle-check" class="w-5 h-5 mr-2" />
                                        In Stock
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>


            @elseif($this->activeTab === 'images')
                <!-- Images Tab -->
                @if($variant->images && count($variant->images) > 0)
                    <div class="space-y-6">
                        <flux:heading size="lg">
                            Variant Images
                            <span class="ml-2 text-sm text-zinc-500 dark:text-zinc-400 font-normal">{{ count($variant->images) }} {{ count($variant->images) === 1 ? 'image' : 'images' }}</span>
                        </flux:heading>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                            @foreach($variant->images as $image)
                            <div class="relative group rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-700 aspect-square border border-zinc-200 dark:border-zinc-600 hover:border-zinc-300 dark:hover:border-zinc-500 transition-all duration-200">
                                <img src="{{ asset('storage/' . $image) }}" 
                                     alt="Variant image" 
                                     class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex items-end justify-center p-4">
                                    <flux:button variant="ghost" size="sm" class="bg-white/90 dark:bg-zinc-800/90 backdrop-blur-sm text-zinc-900 dark:text-zinc-100 hover:bg-white dark:hover:bg-zinc-800">
                                        <flux:icon name="eye" class="w-4 h-4 mr-1" />
                                        View
                                    </flux:button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon name="image" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Images</flux:heading>
                        <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                            Upload variant images to showcase this specific product variant
                        </flux:subheading>
                        <flux:button variant="primary" wire:click="editVariant">
                            <flux:icon name="upload" class="w-4 h-4 mr-2" />
                            Upload Images
                        </flux:button>
                    </div>
                @endif

            @elseif($this->activeTab === 'attributes')
                <!-- Attributes Tab -->
                <div class="space-y-8">
                    <!-- Product Attributes -->
                    <div>
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="cube" class="h-5 w-5" />
                            Product Attributes
                        </flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">These attributes apply to all variants of {{ $variant->product->name }}</p>
                        
                        @if($variant->product->attributes->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->product->attributes as $attr)
                                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ ucwords(str_replace('_', ' ', $attr->attribute_key)) }}
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ $attr->data_type }}
                                        </span>
                                        @if($attr->category)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                {{ ucfirst($attr->category) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $attr->attribute_value }}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <flux:icon name="cube" class="w-8 h-8 text-zinc-400 dark:text-zinc-500 mx-auto mb-3" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No product attributes set</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Product attributes can be added in the edit form</p>
                            </div>
                        @endif
                    </div>

                    <!-- Variant Attributes -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="swatch" class="h-5 w-5" />
                            Variant-Specific Attributes
                        </flux:heading>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-4">These attributes are specific to this {{ $variant->color ? $variant->color . ' ' : '' }}{{ $variant->dimensions ? $variant->dimensions . ' ' : '' }}variant</p>
                        
                        @if($variant->attributes->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->attributes as $attr)
                                <div class="border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ ucwords(str_replace('_', ' ', $attr->attribute_key)) }}
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ $attr->data_type }}
                                        </span>
                                        @if($attr->category)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                {{ ucfirst($attr->category) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $attr->attribute_value }}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <flux:icon name="swatch" class="w-8 h-8 text-zinc-400 dark:text-zinc-500 mx-auto mb-3" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No variant-specific attributes set</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Variant attributes can be added in the edit form</p>
                            </div>
                        @endif
                    </div>
                </div>
                
            @elseif($this->activeTab === 'data')
                <!-- Data & Pricing Tab -->
                <div class="space-y-8">
                    <!-- Barcodes Section -->
                    <div>
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="qr-code" class="h-5 w-5" />
                            Barcodes
                        </flux:heading>
                        
                        @if($variant->barcodes->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->barcodes as $barcode)
                                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-mono text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $barcode->barcode }}
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $barcode->barcode_type }}</span>
                                            @if($barcode->is_primary)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                    Primary
                                                </span>
                                            @endif
                                        </div> 
                                    </div>
                                    
                                    <flux:button variant="ghost" size="sm" class="ml-3">
                                        <flux:icon name="qr-code" class="w-5 h-5" />
                                    </flux:button>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="qr-code" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No barcodes assigned</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Barcodes will appear here when assigned</p>
                            </div>
                        @endif
                    </div>

                    <!-- Pricing Section -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="dollar-sign" class="h-5 w-5" />
                            Pricing
                        </flux:heading>
                        
                        @if($variant->pricing->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->pricing as $price)
                                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-sm text-zinc-900 dark:text-zinc-100">
                                            {{ $price->salesChannel->name ?? 'Default' }}
                                        </div>
                                        @if($price->cost_price)
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                Cost: £{{ number_format($price->cost_price, 2) }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                            £{{ number_format($price->retail_price, 2) }}
                                        </div>
                                        @if($price->cost_price && $price->retail_price > $price->cost_price)
                                            @php
                                                $margin = (($price->retail_price - $price->cost_price) / $price->retail_price) * 100;
                                            @endphp
                                            <div class="text-xs text-green-600 dark:text-green-400">
                                                {{ number_format($margin, 1) }}% margin
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="dollar-sign" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No pricing set</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Pricing information will appear here</p>
                            </div>
                        @endif
                    </div>

                    <!-- Marketplace Variants Section -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="globe" class="h-5 w-5" />
                            Marketplace Variants
                        </flux:heading>
                        
                        @if($variant->marketplaceVariants->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->marketplaceVariants as $mv)
                                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $mv->marketplace->name }}
                                            </div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                {{ $mv->marketplace->platform }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                {{ ucfirst($mv->status) }}
                                            </span>
                                        </div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ $mv->title }}
                                        </div>
                                        @if($mv->price_override)
                                            <div class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">
                                                Price Override: £{{ number_format($mv->price_override, 2) }}
                                            </div>
                                        @endif
                                        @if($mv->last_synced_at)
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                Last synced: {{ $mv->last_synced_at->diffForHumans() }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <flux:button variant="ghost" size="sm" class="ml-3">
                                        <flux:icon name="external-link" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="globe" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No marketplace variants configured</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Configure marketplace-specific titles and pricing in the edit form</p>
                            </div>
                        @endif
                    </div>

                    <!-- Marketplace Identifiers Section -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="hashtag" class="h-5 w-5" />
                            Marketplace Identifiers
                        </flux:heading>
                        
                        @if($variant->marketplaceBarcodes->count() > 0)
                            <div class="space-y-4">
                                @foreach($variant->marketplaceBarcodes as $mb)
                                <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-3 mb-2">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $mb->marketplace->name }}
                                            </div>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300">
                                                {{ strtoupper($mb->identifier_type) }}
                                            </span>
                                            @if($mb->is_active)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                    Active
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-sm text-zinc-600 dark:text-zinc-400 font-mono">
                                            {{ $mb->identifier_value }}
                                        </div>
                                    </div>
                                    
                                    <flux:button variant="ghost" size="sm" class="ml-3">
                                        <flux:icon name="clipboard-copy" class="w-4 h-4" />
                                    </flux:button>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon name="hashtag" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No marketplace identifiers set</p>
                                <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">Add ASINs, Item IDs, and other marketplace identifiers in the edit form</p>
                            </div>
                        @endif
                    </div>

                    <!-- Quick Actions Section -->
                    <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                        <flux:heading size="lg" class="mb-4 flex items-center gap-2">
                            <flux:icon name="zap" class="h-5 w-5" />
                            Quick Actions
                        </flux:heading>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <flux:button variant="ghost" class="justify-start p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors duration-200 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <flux:icon name="chart-bar" class="w-5 h-5 mr-3 text-blue-500" />
                                View Analytics
                            </flux:button>
                            
                            <flux:button variant="ghost" class="justify-start p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors duration-200 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <flux:icon name="file-text" class="w-5 h-5 mr-3 text-emerald-500" />
                                Generate Report
                            </flux:button>
                            
                            <flux:button variant="ghost" class="justify-start p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors duration-200 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <flux:icon name="refresh-ccw" class="w-5 h-5 mr-3 text-amber-500" />
                                Sync Inventory
                            </flux:button>
                            
                            <flux:button variant="ghost" class="justify-start p-4 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition-colors duration-200 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                                <flux:icon name="share" class="w-5 h-5 mr-3 text-purple-500" />
                                Export Data
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-route-tabs>
</div>