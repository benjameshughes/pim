<div class="space-y-6">
    <flux:heading size="xl">Export to Shopify</flux:heading>
    <flux:subheading>Export your products to Shopify CSV format with color grouping</flux:subheading>

    @if (session()->has('message'))
        <flux:badge color="green" size="sm">{{ session('message') }}</flux:badge>
    @endif

    @if (session()->has('error'))
        <flux:badge color="red" size="sm">{{ session('error') }}</flux:badge>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Filters & Selection -->
        <div class="lg:col-span-1 space-y-4">
            <x-card>
                <x-slot:header>
                    <flux:heading size="lg">Filters</flux:heading>
                </x-slot:header>
                
                <div class="space-y-4">
                    <!-- Categories Filter -->
                    <div>
                        <flux:field>
                            <flux:label>Filter by Categories</flux:label>
                            <div class="space-y-2 max-h-48 overflow-y-auto">
                                @foreach($this->categories as $category)
                                    <flux:checkbox 
                                        wire:model.live="selectedCategories" 
                                        value="{{ $category->id }}"
                                        label="{{ $category->name }}"
                                    />
                                    @foreach($category->children as $child)
                                        <div class="ml-6">
                                            <flux:checkbox 
                                                wire:model.live="selectedCategories" 
                                                value="{{ $child->id }}"
                                                label="{{ $child->name }}"
                                            />
                                        </div>
                                    @endforeach
                                @endforeach
                            </div>
                        </flux:field>
                    </div>

                    <!-- Include Inactive -->
                    <flux:checkbox 
                        wire:model.live="includeInactive" 
                        label="Include inactive products"
                    />
                </div>
            </x-card>

            <!-- Export Summary -->
            <x-card>
                <x-slot:header>
                    <flux:heading size="lg">Export Summary</flux:heading>
                </x-slot:header>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Selected Products:</span>
                        <span class="font-medium">{{ $this->selectedProductsCount }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Estimated Shopify Products:</span>
                        <span class="font-medium">{{ $this->estimatedShopifyProducts }}</span>
                    </div>
                    @if($this->estimatedShopifyProducts > $this->selectedProductsCount)
                        <div class="text-xs text-zinc-600 mt-2">
                            More Shopify products due to color splitting
                        </div>
                    @endif
                </div>
            </x-card>

            <!-- Export Actions -->
            <div class="space-y-3">
                <flux:button 
                    wire:click="selectAllProducts" 
                    variant="outline" 
                    size="sm" 
                    class="w-full"
                >
                    Select All
                </flux:button>
                
                <flux:button 
                    wire:click="deselectAllProducts" 
                    variant="outline" 
                    size="sm" 
                    class="w-full"
                >
                    Deselect All
                </flux:button>
                
                <flux:button 
                    wire:click="previewExport" 
                    variant="outline" 
                    size="sm" 
                    class="w-full"
                    :disabled="empty($selectedProducts)"
                >
                    Preview Export
                </flux:button>
            </div>
        </div>

        <!-- Products List -->
        <div class="lg:col-span-2">
            <x-card>
                <x-slot:header>
                    <flux:heading size="lg">Products ({{ $this->products->count() }})</flux:heading>
                </x-slot:header>
                
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @forelse($this->products as $product)
                        <div class="flex items-center justify-between p-3 border border-zinc-200 rounded">
                            <div class="flex items-center space-x-3">
                                <flux:checkbox 
                                    wire:click="toggleProduct({{ $product->id }})"
                                    :checked="in_array($product->id, $selectedProducts)"
                                />
                                <div>
                                    <div class="font-medium">{{ $product->name }}</div>
                                    <div class="text-sm text-zinc-600">
                                        SKU: {{ $product->parent_sku }} | 
                                        {{ $product->variants->count() }} variants
                                        @if($product->variants->pluck('color')->filter()->unique()->count() > 1)
                                            | {{ $product->variants->pluck('color')->filter()->unique()->count() }} colors
                                        @endif
                                    </div>
                                    @if($product->categories->count() > 0)
                                        <div class="text-xs text-zinc-500">
                                            {{ $product->categories->pluck('name')->join(', ') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <flux:badge 
                                :color="$product->status === 'active' ? 'green' : 'zinc'" 
                                size="sm"
                            >
                                {{ ucfirst($product->status) }}
                            </flux:badge>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500">
                            No products found matching your filters.
                        </div>
                    @endforelse
                </div>
            </x-card>
        </div>
    </div>

    <!-- Preview Section -->
    @if(session()->has('preview'))
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Export Preview</flux:heading>
                <flux:subheading>Sample of how your first selected product will appear in Shopify</flux:subheading>
            </x-slot:header>
            
            <div class="bg-zinc-50 p-4 rounded text-sm">
                <pre class="whitespace-pre-wrap">{{ json_encode(session('preview'), JSON_PRETTY_PRINT) }}</pre>
            </div>
        </x-card>
    @endif

    <!-- Export Button & Results -->
    <div class="flex flex-col items-center space-y-4">
        <flux:button 
            wire:click="exportToShopify" 
            variant="primary"
            class="px-8 py-3 text-lg"
            :disabled="empty($selectedProducts) || $isProcessing"
            :loading="$isProcessing"
        >
            @if($isProcessing)
                Generating Export...
            @else
                Export {{ $this->selectedProductsCount }} Products to Shopify
            @endif
        </flux:button>

        @if($lastExportInfo)
            <x-card class="w-full max-w-md">
                <x-slot:header>
                    <flux:heading size="lg">Export Complete</flux:heading>
                </x-slot:header>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Filename:</span>
                        <span class="font-medium">{{ $lastExportInfo['filename'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Products Exported:</span>
                        <span class="font-medium">{{ $lastExportInfo['products_count'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Shopify Products:</span>
                        <span class="font-medium">{{ $lastExportInfo['shopify_products_count'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>File Size:</span>
                        <span class="font-medium">{{ number_format($lastExportInfo['size'] / 1024, 1) }} KB</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Created:</span>
                        <span class="font-medium">{{ $lastExportInfo['created_at'] }}</span>
                    </div>
                </div>
                
                <div class="mt-4">
                    <flux:button 
                        wire:click="downloadExport" 
                        variant="outline" 
                        size="sm" 
                        class="w-full"
                    >
                        Download CSV File
                    </flux:button>
                </div>
            </x-card>
        @endif
    </div>

    <!-- Help Section -->
    <x-card>
        <x-slot:header>
            <flux:heading size="lg">How Color Grouping Works</flux:heading>
        </x-slot:header>
        
        <div class="prose prose-sm max-w-none">
            <p>When exporting to Shopify, your products will be split by color to create separate listings:</p>
            <ul>
                <li><strong>Original Product:</strong> "Blackout Roller Shade" with White, Black, Grey variants</li>
                <li><strong>Shopify Products:</strong> 
                    <ul>
                        <li>"White Blackout Roller Shade" (with all White variants)</li>
                        <li>"Black Blackout Roller Shade" (with all Black variants)</li>
                        <li>"Grey Blackout Roller Shade" (with all Grey variants)</li>
                    </ul>
                </li>
            </ul>
            <p>Each Shopify product will contain only variants of that specific color, with size options for each variant.</p>
        </div>
    </x-card>
</div>