<div>
    <div class="max-w-4xl mx-auto p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white font-mono">
                    {{ $barcode->barcode }}
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    {{ $barcode->type ?? 'Unknown Type' }} Barcode
                </p>
            </div>
            
            <div class="flex space-x-3">
                <flux:button
                    href="{{ route('barcodes.edit', $barcode) }}"
                    icon="pencil"
                    variant="outline"
                    size="sm"
                >
                    Edit
                </flux:button>
                
                <flux:button
                    wire:click="deleteBarcode"
                    wire:confirm="Are you sure you want to delete this barcode?"
                    icon="trash-2"
                    variant="danger"
                    size="sm"
                >
                    Delete
                </flux:button>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Main Info -->
            <div class="space-y-6">
                <!-- Barcode Details -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6 flex items-center">
                        <flux:icon name="scan-barcode" class="mr-2" />
                        Barcode Details
                    </h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barcode</label>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-2xl font-mono text-center text-gray-900 dark:text-white">
                                    {{ $barcode->barcode }}
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <p class="text-gray-900 dark:text-white">{{ $barcode->type ?? 'Not specified' }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Created</label>
                            <p class="text-gray-900 dark:text-white">{{ $barcode->created_at->format('M j, Y g:i A') }}</p>
                        </div>
                        
                        @if($barcode->updated_at->ne($barcode->created_at))
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Updated</label>
                            <p class="text-gray-900 dark:text-white">{{ $barcode->updated_at->format('M j, Y g:i A') }}</p>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Visual Representation -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <flux:icon name="image" class="mr-2" />
                        Visual Barcode
                    </h3>
                    
                    <div class="text-center">
                        <div class="inline-block bg-white p-6 rounded-lg border">
                            <!-- Simplified barcode visual - you could integrate a real barcode generator here -->
                            <div class="flex space-x-1 justify-center mb-2">
                                @for($i = 0; $i < 20; $i++)
                                    <div class="w-1 {{ $i % 3 === 0 ? 'bg-black h-12' : ($i % 2 === 0 ? 'bg-black h-10' : 'bg-black h-8') }}"></div>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-600 font-mono">{{ $barcode->barcode }}</p>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Simplified visual representation</p>
                    </div>
                </div>
            </div>

            <!-- Associations -->
            <div class="space-y-6">
                <!-- Product Association -->
                @if($barcode->product)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <flux:icon name="package" class="mr-2" />
                        Associated Product
                    </h3>
                    
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $barcode->product->name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">SKU: {{ $barcode->product->parent_sku }}</p>
                        </div>
                        <a href="{{ route('products.show', $barcode->product) }}" 
                           class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            View Product
                        </a>
                    </div>
                </div>
                @endif

                <!-- Variant Association -->
                @if($barcode->productVariant)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                        <flux:icon name="box" class="mr-2" />
                        Associated Variant
                    </h3>
                    
                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $barcode->productVariant->sku }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $barcode->productVariant->color ?? 'No Color' }} • {{ $barcode->productVariant->width ? $barcode->productVariant->width . 'cm' : 'No Width' }}
                            </p>
                        </div>
                        <a href="{{ route('variants.show', $barcode->productVariant) }}" 
                           class="text-blue-600 hover:text-blue-700 dark:text-blue-400">
                            View Variant
                        </a>
                    </div>
                </div>
                @endif

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    
                    <div class="space-y-2">
                        <flux:button
                            href="{{ route('barcodes.edit', $barcode) }}"
                            icon="pencil"
                            variant="outline"
                            size="sm"
                            class="w-full justify-start"
                        >
                            Edit Barcode
                        </flux:button>
                        
                        <flux:button
                            href="{{ route('barcodes.index') }}"
                            icon="arrow-left"
                            variant="ghost"
                            size="sm"
                            class="w-full justify-start"
                        >
                            Back to Barcodes
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>