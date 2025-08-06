<div>
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['name' => 'Products', 'url' => route('products.index')],
        ['name' => 'Deleted Products Archive']
    ]" />

    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="p-6">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 bg-gradient-to-br from-zinc-500 to-zinc-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="archive-box" class="h-6 w-6 text-white" />
                </div>
                <div>
                    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">
                        Deleted Products Archive
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        View and search deleted products with their archived information
                    </flux:subheading>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-100">{{ number_format($stats['total_deleted']) }}</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">Total Deleted</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['unique_products']) }}</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">Products</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($stats['barcodes_freed']) }}</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">Barcodes Freed</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-purple-600">{{ count($stats['deletion_reasons']) }}</div>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">Reasons Used</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    type="search" 
                    placeholder="Search by product name, SKU, barcode, color..."
                    class="w-full md:w-96"
                />
                <flux:select wire:model.live="reasonFilter" class="w-full md:w-48">
                    <flux:select.option value="">All Reasons</flux:select.option>
                    @foreach($availableReasons as $reason => $label)
                        <flux:select.option value="{{ $reason }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if($search || $reasonFilter)
                    <flux:button variant="ghost" wire:click="clearFilters" size="sm" icon="x">
                        Clear Filters
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        @if($deletedVariants->isNotEmpty())
            <div class="overflow-hidden rounded-xl">
                <table class="w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-6 py-3 text-left">
                                <button wire:click="sortBy('product_name')" class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 flex items-center gap-1">
                                    Product Name
                                    @if($sortBy === 'product_name')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left">
                                <button wire:click="sortBy('variant_sku')" class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 flex items-center gap-1">
                                    SKU
                                    @if($sortBy === 'variant_sku')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Details
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Barcode
                            </th>
                            <th class="px-6 py-3 text-left">
                                <button wire:click="sortBy('deletion_reason')" class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 flex items-center gap-1">
                                    Reason
                                    @if($sortBy === 'deletion_reason')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left">
                                <button wire:click="sortBy('deleted_at')" class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200 flex items-center gap-1">
                                    Deleted
                                    @if($sortBy === 'deleted_at')
                                        <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                    @endif
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($deletedVariants as $variant)
                            <tr wire:key="deleted-{{ $variant->id }}">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $variant->product_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100">
                                        {{ $variant->variant_sku }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        @if($variant->color || $variant->size)
                                            {{ $variant->color }} {{ $variant->size }}
                                        @else
                                            No attributes
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @if($variant->primary_barcode)
                                        <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $variant->primary_barcode }}
                                        </div>
                                    @else
                                        <span class="text-sm text-zinc-400">No barcode</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <flux:badge variant="outline" class="bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800">
                                        {{ $availableReasons[$variant->deletion_reason] ?? $variant->deletion_reason }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $variant->deleted_at->format('M j, Y g:i A') }}
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $deletedVariants->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <flux:icon name="archive-box" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Deleted Products Found</flux:heading>
                <p class="text-sm text-zinc-500 dark:text-zinc-500 mb-4">
                    No products have been deleted yet or try adjusting your search filters.
                </p>
            </div>
        @endif
    </div>
</div>