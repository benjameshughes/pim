<x-page-template 
    title="Products"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('dashboard')],
        ['name' => 'Products']
    ]"
    :actions="[
        [
            'type' => 'link',
            'label' => 'New Product',
            'href' => route('products.create'),
            'variant' => 'primary',
            'icon' => 'plus'
        ],
        [
            'type' => 'link', 
            'label' => 'Import Data',
            'href' => route('import.index'),
            'variant' => 'outline',
            'icon' => 'arrow-up-tray'
        ]
    ]"
>
    <x-slot:subtitle>
        Manage your product catalog and variants
    </x-slot:subtitle>

    <x-slot:stats>
        <x-stats-grid>
            <x-stats-card 
                title="Total Products"
                :value="$products->total()"
                icon="cube"
            />
            <x-stats-card 
                title="Active Products"
                :value="$products->where('status', 'active')->count()"
                icon="check-circle"
                color="green"
            />
            <x-stats-card 
                title="Draft Products"
                :value="$products->where('status', 'draft')->count()"
                icon="document"
                color="yellow"
            />
            <x-stats-card 
                title="Total Variants"
                :value="$products->sum('variants_count')"
                icon="squares-2x2"
                color="purple"
            />
        </x-stats-grid>
    </x-slot:stats>

    {{-- Reactive Search and Filters --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search products by name or SKU..."
                    icon="search"
                />
            </div>
            <div class="sm:w-48">
                <flux:select wire:model.live="statusFilter">
                    <flux:select.option value="">All Statuses</flux:select.option>
                    @foreach($statusOptions as $value => $label)
                        @if($value !== '')
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Unified Products & Variants Table --}}
    <div x-data="productTable()" class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Product</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Variants</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($products as $product)
                        {{-- Product Row --}}
                        <tr class="border-b border-zinc-100 hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    {{-- Expand/Collapse Button --}}
                                    @if($product->variants_count > 0)
                                        <button 
                                            @click="toggleProduct({{ $product->id }})"
                                            class="flex-shrink-0 p-1 hover:bg-zinc-100 dark:hover:bg-zinc-600 rounded transition-colors"
                                        >
                                            <flux:icon 
                                                name="chevron-right" 
                                                class="h-4 w-4 transition-transform duration-200"
                                                ::class="{ 'rotate-90': expandedProducts.includes({{ $product->id }}) }"
                                            />
                                        </button>
                                    @else
                                        <div class="w-6 h-6"></div>
                                    @endif
                                    
                                    {{-- Product Info --}}
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $product->name }}</div>
                                        @if($product->description)
                                            <div class="text-sm text-zinc-500">{{ Str::limit($product->description, 60) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm font-mono text-zinc-600 dark:text-zinc-400">{{ $product->parent_sku ?? '—' }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge variant="{{ $product->status === 'active' ? 'primary' : ($product->status === 'draft' ? 'neutral' : ($product->status === 'archived' ? 'danger' : 'outline')) }}">
                                    {{ ucfirst($product->status) }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($product->variants_count > 0)
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $product->variants_count }}</span>
                                        @if($product->active_variants_count > 0)
                                            <flux:badge variant="primary" size="sm">{{ $product->active_variants_count }} active</flux:badge>
                                        @endif
                                        @if($product->draft_variants_count > 0)
                                            <flux:badge variant="neutral" size="sm">{{ $product->draft_variants_count }} draft</flux:badge>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-sm text-zinc-400">No variants</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-zinc-500">{{ $product->created_at->format('M j, Y') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button href="{{ route('products.view', $product) }}" variant="ghost" size="sm" icon="eye" wire:navigate>
                                        View
                                    </flux:button>
                                    <flux:button href="{{ route('products.product.edit', $product) }}" variant="ghost" size="sm" icon="pencil" wire:navigate>
                                        Edit
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                        
                        {{-- Expandable Variants Rows --}}
                        @if($product->variants_count > 0)
                            <tr 
                                x-show="expandedProducts.includes({{ $product->id }})"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 transform -translate-y-2"
                                x-transition:enter-end="opacity-100 transform translate-y-0"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 transform translate-y-0"
                                x-transition:leave-end="opacity-0 transform -translate-y-2"
                                class="bg-zinc-50 dark:bg-zinc-900/50"
                            >
                                <td colspan="6" class="px-0 py-0">
                                    <div class="border-l-4 border-zinc-300 dark:border-zinc-600">
                                        @if(isset($expandedProducts[$product->id]))
                                            {{-- Variants Table --}}
                                            <div class="px-6 py-4">
                                                <div class="mb-3 flex items-center gap-2">
                                                    <flux:icon name="squares-2x2" class="h-4 w-4 text-zinc-500" />
                                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Product Variants</span>
                                                </div>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead class="bg-zinc-100 dark:bg-zinc-800">
                                                            <tr>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 uppercase">Variant</th>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 uppercase">Status</th>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 uppercase">Stock</th>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 uppercase">Price</th>
                                                                <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 uppercase">Data</th>
                                                                <th class="px-4 py-2 text-right text-xs font-medium text-zinc-500 uppercase">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-700">
                                                            @foreach($expandedProducts[$product->id] as $variant)
                                                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                                                                    <td class="px-4 py-3">
                                                                        <div class="flex items-center gap-3">
                                                                            @if($variant['image_url'])
                                                                                <img class="h-8 w-8 rounded object-cover" src="{{ $variant['image_url'] }}" alt="{{ $variant['sku'] }}">
                                                                            @else
                                                                                <div class="h-8 w-8 bg-zinc-200 dark:bg-zinc-600 rounded flex items-center justify-center">
                                                                                    <flux:icon name="image" class="h-4 w-4 text-zinc-400" />
                                                                                </div>
                                                                            @endif
                                                                            <div>
                                                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $variant['sku'] }}</div>
                                                                                @if($variant['color'] || $variant['size'])
                                                                                    <div class="text-xs text-zinc-500">{{ $variant['color'] }}{{ $variant['color'] && $variant['size'] ? ' / ' : '' }}{{ $variant['size'] }}</div>
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <flux:badge variant="{{ $variant['status'] === 'active' ? 'primary' : ($variant['status'] === 'draft' ? 'neutral' : 'outline') }}" size="sm">
                                                                            {{ ucfirst($variant['status']) }}
                                                                        </flux:badge>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $variant['stock_level'] ?? 0 }}</span>
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        @if($variant['retail_price'])
                                                                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">£{{ number_format($variant['retail_price'], 2) }}</span>
                                                                        @else
                                                                            <span class="text-sm text-zinc-400">No pricing</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="px-4 py-3">
                                                                        <div class="flex items-center gap-1">
                                                                            @if($variant['has_pricing'])
                                                                                <flux:icon name="currency-dollar" class="h-3 w-3 text-green-500" title="Has pricing" />
                                                                            @endif
                                                                            @if($variant['has_barcode'])
                                                                                <flux:icon name="qr-code" class="h-3 w-3 text-blue-500" title="Has barcode" />
                                                                            @endif
                                                                        </div>
                                                                    </td>
                                                                    <td class="px-4 py-3 text-right">
                                                                        <div class="flex items-center justify-end gap-1">
                                                                            <flux:button href="{{ route('products.variants.view', $variant['id']) }}" variant="ghost" size="sm" icon="eye" wire:navigate />
                                                                            <flux:button href="{{ route('products.variants.edit', $variant['id']) }}" variant="ghost" size="sm" icon="pencil" wire:navigate />
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        @else
                                            {{-- Loading State --}}
                                            <div class="px-12 py-4 text-center text-zinc-500">
                                                <flux:icon name="refresh-cw" class="inline mr-2 h-4 w-4 animate-spin" />
                                                Loading variants...
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="mx-auto h-12 w-12 text-zinc-400 mb-4">
                                    <flux:icon name="cube" class="h-12 w-12" />
                                </div>
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">No products found</h3>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">No products match your current search and filters.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Pagination --}}
        @if($products->hasPages())
            <div class="px-6 py-3 border-t border-zinc-200 dark:border-zinc-700">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    <script>
        function productTable() {
            return {
                expandedProducts: [],
                
                toggleProduct(productId) {
                    if (this.expandedProducts.includes(productId)) {
                        this.expandedProducts = this.expandedProducts.filter(id => id !== productId);
                    } else {
                        this.expandedProducts.push(productId);
                        // Trigger Livewire method to load variants
                        @this.toggleExpanded(productId);
                    }
                }
            }
        }
    </script>
</x-page-template>

