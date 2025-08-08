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
                title="With Variants"
                :value="$products->where('variants_count', '>', 0)->count()"
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

    {{-- Products Table --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
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
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $product->name }}</div>
                                @if($product->description)
                                    <div class="text-sm text-zinc-500">{{ Str::limit($product->description, 60) }}</div>
                                @endif
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
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $product->variants_count }}</span>
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
</x-page-template>

