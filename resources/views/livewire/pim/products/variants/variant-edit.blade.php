<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <!-- Breadcrumb -->
                    <nav class="flex mb-4" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-3">
                            <li class="inline-flex items-center">
                                <a href="{{ route('products.index') }}" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                                    <flux:icon.home class="w-4 h-4" />
                                </a>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <flux:icon.chevron-right class="w-4 h-4 text-zinc-400" />
                                    <a href="{{ route('products.variants.index') }}" class="ml-1 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">Variants</a>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center">
                                    <flux:icon.chevron-right class="w-4 h-4 text-zinc-400" />
                                    <span class="ml-1 text-sm font-medium text-zinc-500 dark:text-zinc-400">
                                        {{ $isEditing ? 'Edit ' . $variant->sku : 'Create Variant' }}
                                    </span>
                                </div>
                            </li>
                        </ol>
                    </nav>
                    
                    <!-- Title -->
                    <flux:heading size="xl" class="font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ $isEditing ? 'Edit Variant' : 'Create New Variant' }}
                    </flux:heading>
                    
                    @if($isEditing)
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        {{ $variant->product->name ?? '' }} - {{ $sku }}
                    </flux:subheading>
                    @endif
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <flux:button variant="outline" wire:click="cancel">
                        Cancel
                    </flux:button>
                    
                    <flux:button variant="primary" wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $isEditing ? 'Update' : 'Create' }} Variant</span>
                        <span wire:loading>Saving...</span>
                    </flux:button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="flex space-x-8 px-6" aria-label="Tabs">
                <button wire:click="setActiveTab('basic')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'basic' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.identification class="w-4 h-4 inline mr-2" />
                    Basic Information
                </button>
                
                <button wire:click="setActiveTab('inventory')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'inventory' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.cube class="w-4 h-4 inline mr-2" />
                    Inventory & Package
                </button>
                
                <button wire:click="setActiveTab('barcodes')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'barcodes' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.qr-code class="w-4 h-4 inline mr-2" />
                    Barcodes
                    @if(count($barcodes) > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                            {{ count($barcodes) }}
                        </span>
                    @endif
                </button>
                
                <button wire:click="setActiveTab('pricing')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'pricing' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.currency-pound class="w-4 h-4 inline mr-2" />
                    Pricing
                    @if(count($pricing) > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                            {{ count($pricing) }}
                        </span>
                    @endif
                </button>
                
                <button wire:click="setActiveTab('marketplace_variants')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'marketplace_variants' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.globe-alt class="w-4 h-4 inline mr-2" />
                    Marketplace Variants
                    @if(count($marketplaceVariants) > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300">
                            {{ count($marketplaceVariants) }}
                        </span>
                    @endif
                </button>
                
                <button wire:click="setActiveTab('marketplace_barcodes')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'marketplace_barcodes' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.hashtag class="w-4 h-4 inline mr-2" />
                    Marketplace IDs
                    @if(count($marketplaceBarcodes) > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300">
                            {{ count($marketplaceBarcodes) }}
                        </span>
                    @endif
                </button>
                
                <button wire:click="setActiveTab('attributes')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'attributes' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.tag class="w-4 h-4 inline mr-2" />
                    Attributes
                    @if(count($productAttributes) + count($variantAttributes) > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                            {{ count($productAttributes) + count($variantAttributes) }}
                        </span>
                    @endif
                </button>
                
                <button wire:click="setActiveTab('images')" 
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors {{ $activeTab === 'images' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-300' }}">
                    <flux:icon.photo class="w-4 h-4 inline mr-2" />
                    Images
                    @if($variant && $variant->images && $variant->images->count() > 0)
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                            {{ $variant->images->count() }}
                        </span>
                    @endif
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Basic Information Tab -->
            @if($activeTab === 'basic')
            <div class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:field>
                            <flux:label for="product_id">Product *</flux:label>
                            <flux:select wire:model="product_id" name="product_id" placeholder="Select Product">
                                @foreach($products as $product)
                                    <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="product_id" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="status">Status *</flux:label>
                            <flux:select wire:model="status" name="status">
                                <flux:select.option value="active">Active</flux:select.option>
                                <flux:select.option value="inactive">Inactive</flux:select.option>
                                <flux:select.option value="out_of_stock">Out of Stock</flux:select.option>
                            </flux:select>
                            <flux:error name="status" />
                        </flux:field>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <flux:field>
                            <flux:label for="sku">SKU *</flux:label>
                            <flux:input wire:model="sku" name="sku" placeholder="e.g., VAR-001-RED-L" />
                            <flux:error name="sku" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="color">Color *</flux:label>
                            <flux:input wire:model="color" name="color" placeholder="e.g., Red, Blue, Black" />
                            <flux:error name="color" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="size">Size *</flux:label>
                            <flux:input wire:model="size" name="size" placeholder="e.g., Small, Medium, Large" />
                            <flux:error name="size" />
                        </flux:field>
                    </div>
                </div>
            </div>
            @endif

            <!-- Inventory & Package Tab -->
            @if($activeTab === 'inventory')
            <div class="space-y-8">
                <!-- Stock Information -->
                <div>
                    <flux:heading size="lg" class="mb-4">Stock Information</flux:heading>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <flux:field>
                                <flux:label for="stock_level">Stock Level</flux:label>
                                <flux:input type="number" wire:model="stock_level" name="stock_level" min="0" placeholder="0" />
                                <flux:error name="stock_level" />
                                <flux:description>Current inventory count for this variant</flux:description>
                            </flux:field>
                        </div>
                    </div>
                </div>
                
                <!-- Package Dimensions -->
                <div>
                    <flux:heading size="lg" class="mb-4">Package Dimensions</flux:heading>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                        <div>
                            <flux:field>
                                <flux:label for="package_length">Length (cm)</flux:label>
                                <flux:input type="number" step="0.01" wire:model="package_length" name="package_length" min="0" placeholder="0.00" />
                                <flux:error name="package_length" />
                            </flux:field>
                        </div>
                        
                        <div>
                            <flux:field>
                                <flux:label for="package_width">Width (cm)</flux:label>
                                <flux:input type="number" step="0.01" wire:model="package_width" name="package_width" min="0" placeholder="0.00" />
                                <flux:error name="package_width" />
                            </flux:field>
                        </div>
                        
                        <div>
                            <flux:field>
                                <flux:label for="package_height">Height (cm)</flux:label>
                                <flux:input type="number" step="0.01" wire:model="package_height" name="package_height" min="0" placeholder="0.00" />
                                <flux:error name="package_height" />
                            </flux:field>
                        </div>
                        
                        <div>
                            <flux:field>
                                <flux:label for="package_weight">Weight (kg)</flux:label>
                                <flux:input type="number" step="0.001" wire:model="package_weight" name="package_weight" min="0" placeholder="0.000" />
                                <flux:error name="package_weight" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Barcodes Tab -->
            @if($activeTab === 'barcodes')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Barcode Management</flux:heading>
                    <flux:button variant="primary" wire:click="$set('showBarcodeModal', true)">
                        <flux:icon.plus class="w-4 h-4" />
                        Add Barcode
                    </flux:button>
                </div>
                
                @if(count($barcodes) > 0)
                    <div class="space-y-3">
                        @foreach($barcodes as $index => $barcode)
                        <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $barcode['barcode'] }}
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                        {{ $barcode['barcode_type'] }}
                                    </span>
                                    @if($barcode['is_primary'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                            Primary
                                        </span>
                                    @endif
                                    @if(isset($barcode['is_valid']) && !$barcode['is_valid'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300">
                                            Invalid
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                @if(!$barcode['is_primary'])
                                    <flux:button variant="ghost" size="sm" wire:click="setPrimaryBarcode({{ $index }})">
                                        Set Primary
                                    </flux:button>
                                @endif
                                
                                <flux:button variant="ghost" size="sm" wire:click="removeBarcode({{ $index }})">
                                    <flux:icon.trash class="w-4 h-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon.qr-code class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No barcodes added yet</p>
                        <flux:button variant="primary" size="sm" class="mt-3" wire:click="$set('showBarcodeModal', true)">
                            Add Your First Barcode
                        </flux:button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Pricing Tab -->
            @if($activeTab === 'pricing')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Pricing Management</flux:heading>
                    <flux:button variant="primary" wire:click="$set('showPricingModal', true)">
                        <flux:icon.plus class="w-4 h-4" />
                        Add Pricing
                    </flux:button>
                </div>
                
                @if(count($pricing) > 0)
                    <div class="space-y-3">
                        @foreach($pricing as $index => $price)
                        <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $price['channel_name'] }}
                                        </div>
                                        @if($price['cost_price'])
                                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                Cost: £{{ number_format($price['cost_price'], 2) }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="text-right">
                                        <div class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                            £{{ number_format($price['retail_price'], 2) }}
                                        </div>
                                        @if($price['cost_price'] && $price['retail_price'] > $price['cost_price'])
                                            @php
                                                $margin = (($price['retail_price'] - $price['cost_price']) / $price['retail_price']) * 100;
                                            @endphp
                                            <div class="text-xs text-green-600 dark:text-green-400">
                                                {{ number_format($margin, 1) }}% margin
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <div class="ml-4">
                                <flux:button variant="ghost" size="sm" wire:click="removePricing({{ $index }})">
                                    <flux:icon.trash class="w-4 h-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon.currency-pound class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No pricing set yet</p>
                        <flux:button variant="primary" size="sm" class="mt-3" wire:click="$set('showPricingModal', true)">
                            Add Your First Price
                        </flux:button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Images Tab -->
            @if($activeTab === 'images')
            <div class="space-y-6">
                <div class="flex justify-between items-center">
                    <flux:heading size="lg">Variant Images</flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        @if($variant && $variant->images)
                            {{ $variant->images->count() }} images
                        @else
                            0 images
                        @endif
                    </flux:subheading>
                </div>
                
                <!-- Main Images Section -->
                <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="border-b border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex gap-2 flex-wrap">
                            <flux:badge variant="outline" class="bg-blue-50 text-blue-700 border-blue-200">
                                @if($variant && $variant->images)
                                    Main Images ({{ $variant->images->where('image_type', 'main')->count() }})
                                @else
                                    Main Images (0)
                                @endif
                            </flux:badge>
                            <flux:badge variant="outline">
                                @if($variant && $variant->images)
                                    Swatch Images ({{ $variant->images->where('image_type', 'swatch')->count() }})
                                @else
                                    Swatch Images (0)
                                @endif
                            </flux:badge>
                        </div>
                    </div>
                    
                    <!-- Main Images Uploader -->
                    <div class="p-6">
                        @if($variant && $variant->id)
                            <livewire:components.image-uploader 
                                :model-type="'variant'"
                                :model-id="$variant->id"
                                :image-type="'main'"
                                :multiple="true"
                                :max-files="6"
                                :max-size="10240"
                                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                                :process-immediately="true"
                                :show-preview="true"
                                :allow-reorder="true"
                                :show-existing-images="true"
                                upload-text="Upload main variant images"
                                wire:key="variant-main-images-{{ $variant->id }}"
                            />
                        @else
                            <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                <flux:icon name="image" class="w-12 h-12 mx-auto mb-3 text-zinc-400" />
                                <p>Save the variant first to upload images</p>
                            </div>
                        @endif
                    </div>
                </div>
                
                <!-- Swatch Images Section -->
                @if($variant && $variant->id)
                    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="p-6">
                            <livewire:components.image-uploader 
                                :model-type="'variant'"
                                :model-id="$variant->id"
                                :image-type="'swatch'"
                                :multiple="true"
                                :max-files="3"
                                :max-size="10240"
                                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                                :process-immediately="true"
                                :show-preview="true"
                                :allow-reorder="true"
                                :show-existing-images="true"
                                upload-text="Upload swatch images (color/material samples)"
                                wire:key="variant-swatch-images-{{ $variant->id }}"
                            />
                        </div>
                    </div>
                @endif
            </div>
            @endif

            <!-- Marketplace Variants Tab -->
            @if($activeTab === 'marketplace_variants')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Marketplace Variants</flux:heading>
                    <flux:button variant="primary" wire:click="$set('showMarketplaceVariantModal', true)">
                        <flux:icon.plus class="w-4 h-4" />
                        Add Marketplace Variant
                    </flux:button>
                </div>
                
                @if(count($marketplaceVariants) > 0)
                    <div class="space-y-3">
                        @foreach($marketplaceVariants as $index => $mv)
                        <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $mv['marketplace_name'] }}
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                        {{ $mv['status'] }}
                                    </span>
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    {{ $mv['title'] }}
                                </div>
                                @if($mv['price_override'])
                                    <div class="text-xs text-green-600 dark:text-green-400 mt-1">
                                        Price Override: £{{ number_format($mv['price_override'], 2) }}
                                    </div>
                                @endif
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="removeMarketplaceVariant({{ $index }})">
                                    <flux:icon.trash class="w-4 h-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon.globe-alt class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No marketplace variants added yet</p>
                        <flux:button variant="primary" size="sm" class="mt-3" wire:click="$set('showMarketplaceVariantModal', true)">
                            Add Your First Marketplace Variant
                        </flux:button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Marketplace Barcodes Tab -->
            @if($activeTab === 'marketplace_barcodes')
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">Marketplace Identifiers</flux:heading>
                    <flux:button variant="primary" wire:click="$set('showMarketplaceBarcodeModal', true)">
                        <flux:icon.plus class="w-4 h-4" />
                        Add Marketplace ID
                    </flux:button>
                </div>
                
                @if(count($marketplaceBarcodes) > 0)
                    <div class="space-y-3">
                        @foreach($marketplaceBarcodes as $index => $mb)
                        <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $mb['marketplace_name'] }}
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-300">
                                        {{ strtoupper($mb['identifier_type']) }}
                                    </span>
                                    @if($mb['is_active'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                            Active
                                        </span>
                                    @endif
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1 font-mono">
                                    {{ $mb['identifier_value'] }}
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <flux:button variant="ghost" size="sm" wire:click="removeMarketplaceBarcode({{ $index }})">
                                    <flux:icon.trash class="w-4 h-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon.hashtag class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-4" />
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">No marketplace identifiers added yet</p>
                        <flux:button variant="primary" size="sm" class="mt-3" wire:click="$set('showMarketplaceBarcodeModal', true)">
                            Add Your First Marketplace ID
                        </flux:button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Attributes Tab -->
            @if($activeTab === 'attributes')
            <div class="space-y-8">
                <!-- Product Attributes -->
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Product Attributes</flux:heading>
                        <flux:button variant="outline" wire:click="$set('showProductAttributeModal', true)">
                            <flux:icon.plus class="w-4 h-4" />
                            Add Product Attribute
                        </flux:button>
                    </div>
                    
                    @if(count($productAttributes) > 0)
                        <div class="space-y-3">
                            @foreach($productAttributes as $index => $attr)
                            <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $attr['attribute_key'] }}
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ $attr['data_type'] }}
                                        </span>
                                        @if($attr['category'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $attr['category'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                        {{ $attr['attribute_value'] }}
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <flux:button variant="ghost" size="sm" wire:click="removeProductAttribute({{ $index }})">
                                        <flux:icon.trash class="w-4 h-4 text-red-500" />
                                    </flux:button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon.tag class="w-8 h-8 text-zinc-400 dark:text-zinc-500 mx-auto mb-3" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No product attributes added yet</p>
                        </div>
                    @endif
                </div>

                <!-- Variant Attributes -->
                <div class="border-t border-zinc-200 dark:border-zinc-700 pt-8">
                    <div class="flex items-center justify-between mb-4">
                        <flux:heading size="lg">Variant Attributes</flux:heading>
                        <flux:button variant="outline" wire:click="$set('showVariantAttributeModal', true)">
                            <flux:icon.plus class="w-4 h-4" />
                            Add Variant Attribute
                        </flux:button>
                    </div>
                    
                    @if(count($variantAttributes) > 0)
                        <div class="space-y-3">
                            @foreach($variantAttributes as $index => $attr)
                            <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-700 rounded-lg border border-zinc-200 dark:border-zinc-600">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-3">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $attr['attribute_key'] }}
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300">
                                            {{ $attr['data_type'] }}
                                        </span>
                                        @if($attr['category'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                                {{ $attr['category'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                        {{ $attr['attribute_value'] }}
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <flux:button variant="ghost" size="sm" wire:click="removeVariantAttribute({{ $index }})">
                                        <flux:icon.trash class="w-4 h-4 text-red-500" />
                                    </flux:button>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <flux:icon.tag class="w-8 h-8 text-zinc-400 dark:text-zinc-500 mx-auto mb-3" />
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">No variant attributes added yet</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Barcode Modal -->
    <flux:modal wire:model="showBarcodeModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Barcode</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newBarcode">Barcode *</flux:label>
                        <flux:input wire:model="newBarcode" name="newBarcode" placeholder="Enter barcode number" />
                        <flux:error name="newBarcode" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newBarcodeType">Barcode Type</flux:label>
                        <flux:select wire:model="newBarcodeType" name="newBarcodeType">
                            @foreach($barcodeTypes as $key => $label)
                                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="newBarcodeType" />
                    </flux:field>
                </div>
                
                <div class="flex gap-3">
                    <flux:button variant="outline" wire:click="generateBarcode" class="flex-1">
                        <flux:icon.sparkles class="w-4 h-4" />
                        Generate Random
                    </flux:button>
                    
                    @if($poolStats['available'] > 0)
                    <flux:button variant="outline" wire:click="assignFromPool" class="flex-1">
                        <flux:icon.rectangle-stack class="w-4 h-4" />
                        From Pool ({{ $poolStats['available'] }} available)
                    </flux:button>
                    @endif
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showBarcodeModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addBarcode">
                    Add Barcode
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Pricing Modal -->
    <flux:modal wire:model="showPricingModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Pricing</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newPricing.sales_channel_id">Sales Channel</flux:label>
                        <flux:select wire:model="newPricing.sales_channel_id" name="newPricing.sales_channel_id" placeholder="Default Channel">
                            @foreach($salesChannels as $channel)
                                <flux:select.option value="{{ $channel->id }}">{{ $channel->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>Leave empty for default pricing</flux:description>
                    </flux:field>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:field>
                            <flux:label for="newPricing.retail_price">Retail Price *</flux:label>
                            <flux:input type="number" step="0.01" wire:model="newPricing.retail_price" name="newPricing.retail_price" placeholder="0.00" />
                            <flux:error name="newPricing.retail_price" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="newPricing.cost_price">Cost Price</flux:label>
                            <flux:input type="number" step="0.01" wire:model="newPricing.cost_price" name="newPricing.cost_price" placeholder="0.00" />
                            <flux:error name="newPricing.cost_price" />
                        </flux:field>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showPricingModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addPricing">
                    Add Pricing
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Marketplace Variant Modal -->
    <flux:modal wire:model="showMarketplaceVariantModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Marketplace Variant</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceVariant.marketplace_id">Marketplace *</flux:label>
                        <flux:select wire:model="newMarketplaceVariant.marketplace_id" name="newMarketplaceVariant.marketplace_id" placeholder="Select Marketplace">
                            @foreach($marketplaces as $marketplace)
                                <flux:select.option value="{{ $marketplace->id }}">{{ $marketplace->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="newMarketplaceVariant.marketplace_id" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceVariant.title">Title *</flux:label>
                        <flux:input wire:model="newMarketplaceVariant.title" name="newMarketplaceVariant.title" placeholder="Marketplace-specific title" />
                        <flux:error name="newMarketplaceVariant.title" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceVariant.description">Description</flux:label>
                        <flux:textarea wire:model="newMarketplaceVariant.description" name="newMarketplaceVariant.description" placeholder="Marketplace-specific description" rows="3" />
                        <flux:error name="newMarketplaceVariant.description" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceVariant.price_override">Price Override</flux:label>
                        <flux:input type="number" step="0.01" wire:model="newMarketplaceVariant.price_override" name="newMarketplaceVariant.price_override" placeholder="0.00" />
                        <flux:error name="newMarketplaceVariant.price_override" />
                        <flux:description>Leave empty to use base pricing</flux:description>
                    </flux:field>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showMarketplaceVariantModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addMarketplaceVariant">
                    Add Marketplace Variant
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Marketplace Barcode Modal -->
    <flux:modal wire:model="showMarketplaceBarcodeModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Marketplace Identifier</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceBarcode.marketplace_id">Marketplace *</flux:label>
                        <flux:select wire:model="newMarketplaceBarcode.marketplace_id" name="newMarketplaceBarcode.marketplace_id" placeholder="Select Marketplace">
                            @foreach($marketplaces as $marketplace)
                                <flux:select.option value="{{ $marketplace->id }}">{{ $marketplace->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="newMarketplaceBarcode.marketplace_id" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceBarcode.identifier_type">Identifier Type *</flux:label>
                        <flux:select wire:model="newMarketplaceBarcode.identifier_type" name="newMarketplaceBarcode.identifier_type">
                            @foreach($identifierTypes as $key => $label)
                                <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="newMarketplaceBarcode.identifier_type" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newMarketplaceBarcode.identifier_value">Identifier Value *</flux:label>
                        <flux:input wire:model="newMarketplaceBarcode.identifier_value" name="newMarketplaceBarcode.identifier_value" placeholder="e.g., B08XYZ123, 123456789012" />
                        <flux:error name="newMarketplaceBarcode.identifier_value" />
                    </flux:field>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showMarketplaceBarcodeModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addMarketplaceBarcode">
                    Add Marketplace Identifier
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Product Attribute Modal -->
    <flux:modal wire:model="showProductAttributeModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Product Attribute</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newProductAttribute.attribute_key">Attribute Key *</flux:label>
                        <flux:input wire:model="newProductAttribute.attribute_key" name="newProductAttribute.attribute_key" placeholder="e.g., max_drop, material_type" />
                        <flux:error name="newProductAttribute.attribute_key" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newProductAttribute.attribute_value">Value *</flux:label>
                        <flux:input wire:model="newProductAttribute.attribute_value" name="newProductAttribute.attribute_value" placeholder="e.g., 160cm, polyester" />
                        <flux:error name="newProductAttribute.attribute_value" />
                    </flux:field>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:field>
                            <flux:label for="newProductAttribute.data_type">Data Type *</flux:label>
                            <flux:select wire:model="newProductAttribute.data_type" name="newProductAttribute.data_type">
                                @foreach($dataTypes as $key => $label)
                                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newProductAttribute.data_type" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="newProductAttribute.category">Category</flux:label>
                            <flux:select wire:model="newProductAttribute.category" name="newProductAttribute.category" placeholder="Select Category">
                                @foreach($categories as $key => $label)
                                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newProductAttribute.category" />
                        </flux:field>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showProductAttributeModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addProductAttribute">
                    Add Product Attribute
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Variant Attribute Modal -->
    <flux:modal wire:model="showVariantAttributeModal" class="md:w-2xl">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Add Variant Attribute</flux:heading>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label for="newVariantAttribute.attribute_key">Attribute Key *</flux:label>
                        <flux:input wire:model="newVariantAttribute.attribute_key" name="newVariantAttribute.attribute_key" placeholder="e.g., fabric_width_difference, opacity_level" />
                        <flux:error name="newVariantAttribute.attribute_key" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label for="newVariantAttribute.attribute_value">Value *</flux:label>
                        <flux:input wire:model="newVariantAttribute.attribute_value" name="newVariantAttribute.attribute_value" placeholder="e.g., 4cm, 100%" />
                        <flux:error name="newVariantAttribute.attribute_value" />
                    </flux:field>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:field>
                            <flux:label for="newVariantAttribute.data_type">Data Type *</flux:label>
                            <flux:select wire:model="newVariantAttribute.data_type" name="newVariantAttribute.data_type">
                                @foreach($dataTypes as $key => $label)
                                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newVariantAttribute.data_type" />
                        </flux:field>
                    </div>
                    
                    <div>
                        <flux:field>
                            <flux:label for="newVariantAttribute.category">Category</flux:label>
                            <flux:select wire:model="newVariantAttribute.category" name="newVariantAttribute.category" placeholder="Select Category">
                                @foreach($categories as $key => $label)
                                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="newVariantAttribute.category" />
                        </flux:field>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <flux:button variant="outline" wire:click="$set('showVariantAttributeModal', false)">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="addVariantAttribute">
                    Add Variant Attribute
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>