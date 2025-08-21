{{-- 💎 ENHANCED VARIANTS TABLE --}}
<div class="space-y-6">
    {{-- Header & Search --}}
    <div class="flex items-center justify-between">
        <h3 class="text-2xl font-bold text-gray-900 dark:text-white">Product Variants</h3>
        <flux:button href="{{ route('variants.create') }}" icon="plus" variant="primary">
            Create Variant
        </flux:button>
    </div>

    {{-- Search & Filters --}}
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <flux:input 
                    wire:model.live="search" 
                    placeholder="Search variants, products, or SKUs..." 
                    icon="magnifying-glass"
                />
            </div>
            <div>
                <flux:select wire:model.live="status">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="color">
                    <option value="all">All Colors</option>
                    @foreach($colors as $colorOption)
                        <option value="{{ $colorOption }}">{{ $colorOption }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
    
    <div class="overflow-x-auto">
        <table class="w-full table-fixed">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">SKU</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Color</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stock</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($variants as $variant)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            <div class="flex items-center">
                                <span class="font-mono">{{ $variant->sku }}</span>
                                @if($variant->external_sku && $variant->external_sku !== $variant->sku)
                                    <span class="ml-2 text-xs text-gray-400">({{ $variant->external_sku }})</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            {{ $variant->product->name ?? 'N/A' }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ $variant->color ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            @if($variant->width)
                                {{ $variant->width }}cm
                                @if($variant->drop)
                                    × {{ $variant->drop }}cm
                                @endif
                            @else
                                N/A
                            @endif
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            {{ $variant->price ? '£' . number_format($variant->price, 2) : 'N/A' }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                            {{ $variant->stock_level ?? 0 }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $variant->status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                {{ ucfirst($variant->status ?? 'unknown') }}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <flux:button href="{{ route('variants.show', $variant) }}" size="sm" variant="outline">View</flux:button>
                                <flux:button href="{{ route('variants.edit', $variant) }}" size="sm" variant="ghost">Edit</flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                            <div class="flex flex-col items-center">
                                <span class="text-4xl mb-2">💎</span>
                                <p class="text-lg font-medium">No variants found</p>
                                <p class="text-sm">Create some stunning product variations!</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    {{-- Pagination --}}
    @if($variants->hasPages())
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $variants->links() }}
        </div>
    @endif
</div>

{{-- Stats Summary --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                <flux:icon name="squares-plus" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Variants</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $variants->total() }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                <flux:icon name="check-circle" class="w-6 h-6 text-green-600 dark:text-green-400" />
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $variants->where('status', 'active')->count() }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                <flux:icon name="swatch" class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Colors</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $colors->count() }}</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow">
        <div class="flex items-center">
            <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                <flux:icon name="currency-pound" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Avg Price</p>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">£{{ number_format($variants->avg('price') ?? 0, 2) }}</p>
            </div>
        </div>
    </div>
</div>
</div>
