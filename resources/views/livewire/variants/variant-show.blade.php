<div class="space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                💎 {{ $variant->sku }}
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                <a href="{{ route('products.show', $variant->product) }}" class="hover:text-blue-600 dark:hover:text-blue-400">
                    {{ $variant->product->name }}
                </a>
                @if($variant->color || $variant->width)
                    • {{ $variant->color ?? 'No Color' }}
                    @if($variant->width)
                        • {{ $variant->width }}cm
                        @if($variant->drop) x {{ $variant->drop }}cm @endif
                    @endif
                @endif
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button wire:navigate href="{{ route('variants.edit', $variant) }}" variant="primary" icon="pencil">
                Edit
            </flux:button>
            
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal" />
                
                <flux:menu>
                    <flux:menu.item wire:click="duplicateVariant" icon="document-duplicate">
                        Duplicate Variant
                    </flux:menu.item>
                    <flux:menu.item wire:navigate href="{{ route('products.show', $variant->product) }}" icon="arrow-left">
                        Back to Product
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item wire:click="deleteVariant" wire:confirm="Are you sure you want to delete this variant?" icon="trash" variant="danger">
                        Delete Variant
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 📦 VARIANT INFO --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Basic Info Card --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Variant Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">SKU</label>
                        <p class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ $variant->sku }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Barcode</label>
                        <p class="mt-1 text-sm font-mono {{ $variant->barcode ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400 italic' }}">
                            {{ $variant->barcode ? $variant->barcode->barcode : 'No barcode assigned' }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Product Family</label>
                        <div class="mt-1">
                            <a href="{{ route('products.show', $variant->product) }}" 
                               class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 hover:underline">
                                {{ $variant->product->name }}
                            </a>
                        </div>
                    </div>
                    
                    @if($variant->status)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <div class="mt-1">
                            <flux:badge :color="$variant->status === 'active' ? 'green' : 'gray'" size="sm">
                                {{ ucfirst($variant->status) }}
                            </flux:badge>
                        </div>
                    </div>
                    @endif
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Created</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $variant->created_at->format('M j, Y') }}</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Last Updated</label>
                        <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $variant->updated_at->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            {{-- 🎨 COLOR & DIMENSIONS SHOWCASE --}}
            @if($variant->color || $variant->width)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Visual Specifications</h3>
                
                <div class="flex flex-wrap items-center gap-4">
                    @if($variant->color)
                        <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            @php
                                $colorClass = match(strtolower($variant->color)) {
                                    'black' => 'bg-gray-900',
                                    'white' => 'bg-white border-gray-300',
                                    'red' => 'bg-red-500',
                                    'blue' => 'bg-blue-500',
                                    'green' => 'bg-green-500',
                                    default => str_contains(strtolower($variant->color), 'grey') || str_contains(strtolower($variant->color), 'gray') ? 'bg-gray-500' :
                                              (str_contains(strtolower($variant->color), 'orange') ? 'bg-orange-500' :
                                              (str_contains(strtolower($variant->color), 'yellow') || str_contains(strtolower($variant->color), 'lemon') ? 'bg-yellow-500' :
                                              (str_contains(strtolower($variant->color), 'purple') || str_contains(strtolower($variant->color), 'lavender') ? 'bg-purple-500' :
                                              (str_contains(strtolower($variant->color), 'pink') ? 'bg-pink-500' :
                                              (str_contains(strtolower($variant->color), 'brown') || str_contains(strtolower($variant->color), 'cappuccino') ? 'bg-amber-700' :
                                              (str_contains(strtolower($variant->color), 'navy') ? 'bg-blue-900' :
                                              (str_contains(strtolower($variant->color), 'natural') ? 'bg-amber-200' :
                                              (str_contains(strtolower($variant->color), 'lime') ? 'bg-lime-500' :
                                              (str_contains(strtolower($variant->color), 'aubergine') ? 'bg-purple-900' :
                                              (str_contains(strtolower($variant->color), 'ochre') ? 'bg-yellow-700' : 'bg-gradient-to-br from-orange-400 to-red-500'))))))))))
                                };
                            @endphp
                            <div class="w-8 h-8 rounded-full border-2 border-white shadow-sm {{ $colorClass }}"
                                title="{{ $variant->color }}">
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $variant->color }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Color</div>
                            </div>
                        </div>
                    @endif
                    
                    @if($variant->width)
                        <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <flux:icon name="arrows-pointing-out" class="w-6 h-6 text-gray-500 dark:text-gray-400" />
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $variant->width }}cm
                                    @if($variant->drop) x {{ $variant->drop }}cm @endif
                                    @if($variant->max_drop && !$variant->drop) (up to {{ $variant->max_drop }}cm drop) @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Dimensions</div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- 🔢 BARCODE --}}
            @if($variant->barcode)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Barcode</h3>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <flux:icon name="qr-code" class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <div>
                                <p class="font-mono text-sm font-medium text-gray-900 dark:text-white">{{ $variant->barcode->barcode }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $variant->barcode->type ?? 'Standard' }}</p>
                            </div>
                        </div>
                        @if(Route::has('barcodes.show'))
                            <flux:button href="{{ route('barcodes.show', $variant->barcode) }}" size="xs" variant="ghost" icon="eye">
                                View
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- 🖼️ VARIANT SIDEBAR --}}
        <div class="space-y-6">
            {{-- Quick Stats --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Quick Stats</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Stock Level</span>
                        <span class="text-sm font-medium {{ ($variant->stock_level ?? 0) > 10 ? 'text-green-600' : (($variant->stock_level ?? 0) > 0 ? 'text-yellow-600' : 'text-red-600') }}">
                            {{ $variant->stock_level ?? 0 }}
                        </span>
                    </div>
                    
                    @php $retailPrice = $variant->getRetailPrice(); @endphp
                    @if($retailPrice > 0)
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Retail Price</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">£{{ number_format($retailPrice, 2) }}</span>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Barcode</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $variant->barcode ? '1' : '0' }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Status</span>
                        <flux:badge :color="$variant->status === 'active' ? 'green' : 'gray'" size="xs">
                            {{ ucfirst($variant->status ?? 'unknown') }}
                        </flux:badge>
                    </div>
                    
                    @if($variant->parcel_weight || $variant->parcel_length)
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Package</span>
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            @if($variant->parcel_weight){{ number_format($variant->parcel_weight, 1) }}kg @endif
                            @if($variant->parcel_length && $variant->parcel_width && $variant->parcel_depth)
                                <br><span class="text-xs text-gray-500">{{ $variant->parcel_length }}x{{ $variant->parcel_width }}x{{ $variant->parcel_depth }}cm</span>
                            @endif
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Pricing Details --}}
            @if($variant->pricingRecords->count() > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Channel Pricing</h3>
                
                <div class="space-y-3">
                    @php 
                        $retailChannel = $variant->pricingRecords->where('salesChannel.code', 'retail')->first();
                        $otherChannels = $variant->pricingRecords->where('salesChannel.code', '!=', 'retail');
                    @endphp
                    
                    {{-- Retail Price (highlighted as primary) --}}
                    @if($retailChannel)
                    <div class="flex justify-between items-center p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg border border-indigo-200 dark:border-indigo-800">
                        <div>
                            <span class="text-sm font-medium text-indigo-900 dark:text-indigo-300">{{ $retailChannel->salesChannel->name ?? 'Retail' }}</span>
                            <p class="text-xs text-indigo-600 dark:text-indigo-400">Base retail price</p>
                        </div>
                        <span class="font-semibold text-indigo-900 dark:text-indigo-300">
                            £{{ number_format($retailChannel->price, 2) }}
                        </span>
                    </div>
                    @endif
                    
                    {{-- Other Channel Prices --}}
                    @foreach($otherChannels as $pricing)
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $pricing->salesChannel->name ?? 'Channel' }}</span>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ ucfirst($pricing->price_type ?? 'marketplace') }} price</p>
                        </div>
                        <span class="font-medium text-gray-900 dark:text-white">
                            £{{ number_format($pricing->price, 2) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Related Variants --}}
            @if($variant->product->variants->count() > 1)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Other Variants ({{ $variant->product->variants->count() - 1 }})</h3>
                
                <div class="space-y-2">
                    @foreach($variant->product->variants->take(3) as $otherVariant)
                        @if($otherVariant->id !== $variant->id)
                        <a href="{{ route('variants.show', $otherVariant) }}" 
                           class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            @if($otherVariant->color)
                            @php
                                $otherColorClass = match(strtolower($otherVariant->color)) {
                                    'black' => 'bg-gray-900',
                                    'white' => 'bg-white',
                                    'red' => 'bg-red-500',
                                    'blue' => 'bg-blue-500',
                                    'green' => 'bg-green-500',
                                    default => 'bg-gray-500'
                                };
                            @endphp
                            <div class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-600 {{ $otherColorClass }}">
                            </div>
                            @endif
                            
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-mono text-gray-900 dark:text-white truncate">{{ $otherVariant->sku }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $otherVariant->color }} • {{ $otherVariant->width }}cm</p>
                            </div>
                        </a>
                        @endif
                    @endforeach
                    
                    @if($variant->product->variants->count() > 4)
                    <a href="{{ route('products.show', $variant->product) }}" 
                       class="block text-center text-xs text-blue-600 hover:text-blue-700 dark:text-blue-400 py-2">
                        View all {{ $variant->product->variants->count() }} variants
                    </a>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
</div>