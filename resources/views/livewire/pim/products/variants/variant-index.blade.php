@if (session()->has('message'))
    <div class="mb-4 rounded-lg bg-green-100 px-6 py-4 text-green-700 dark:bg-green-900 dark:text-green-300">
        {{ session('message') }}
    </div>
@endif

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="lg">
            @if($product)
                {{ $product->name }} Variants
            @else
                Product Variants
            @endif
        </flux:heading>
        @if($product)
            <flux:button variant="primary" icon="plus" href="{{ route('products.variants.create-for-product', $product) }}" wire:navigate>
                Add Variant
            </flux:button>
        @else
            <flux:button variant="primary" icon="plus" href="{{ route('products.variants.create') }}" wire:navigate>
                Add Variant
            </flux:button>
        @endif
    </div>

    <!-- Search and Filters -->
    <div class="flex gap-4 items-center bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
        <div class="flex-1">
            <flux:input wire:model.live="search" placeholder="Search variants..." />
        </div>
        <div class="w-48">
            <flux:select wire:model.live="statusFilter">
                @foreach($statusOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if($variants->isEmpty())
        <div class="text-center py-12 bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700">
            <flux:icon name="layers" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
            <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Variants</flux:heading>
            <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                @if($product)
                    This product doesn't have any variants yet
                @else
                    No variants found matching your search criteria
                @endif
            </flux:subheading>
            @if($product)
                <flux:button variant="primary" icon="plus" href="{{ route('products.variants.create-for-product', $product) }}" wire:navigate>
                    Create First Variant
                </flux:button>
            @endif
        </div>
    @else
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Variant
                            </th>
                            @unless($product)
                                <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                    Product
                                </th>
                            @endunless
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Stock
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Pricing
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($variants as $variant)
                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            @if($variant->images && $variant->images->first())
                                                <img class="h-10 w-10 rounded-lg object-cover" 
                                                     src="{{ Storage::url($variant->images->first()->image_path) }}" 
                                                     alt="{{ $variant->sku }}">
                                            @else
                                                <div class="h-10 w-10 bg-zinc-200 dark:bg-zinc-600 rounded-lg flex items-center justify-center">
                                                    <flux:icon name="image" class="h-5 w-5 text-zinc-400" />
                                                </div>
                                            @endif
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $variant->sku }}
                                            </div>
                                            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ $variant->color }} / {{ $variant->size }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                @unless($product)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $variant->product->name }}
                                        </div>
                                        <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $variant->product->parent_sku }}
                                        </div>
                                    </td>
                                @endunless
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge variant="outline" 
                                        class="{{ $variant->status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-50 text-zinc-700 border-zinc-200' }}">
                                        {{ ucfirst($variant->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ $variant->stock_level ?? 0 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-zinc-900 dark:text-zinc-100">
                                    @if($variant->pricing && $variant->pricing->first())
                                        £{{ number_format($variant->pricing->first()->price_inc_vat, 2) }}
                                    @else
                                        <span class="text-zinc-400">No pricing</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <flux:button variant="ghost" size="sm" href="{{ route('products.variants.view', $variant) }}" wire:navigate>
                                            <flux:icon name="eye" class="h-4 w-4" />
                                        </flux:button>
                                        <flux:button variant="ghost" size="sm" href="{{ route('products.variants.edit', $variant) }}" wire:navigate>
                                            <flux:icon name="pencil" class="h-4 w-4" />
                                        </flux:button>
                                        <flux:button variant="ghost" size="sm" wire:click="confirmDelete({{ $variant->id }})">
                                            <flux:icon name="trash" class="h-4 w-4 text-red-500" />
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $variants->links() }}
            </div>
        </div>
    @endif
</div>

<!-- Delete Confirmation Modal -->
@if($showDeleteModal)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
            <div wire:click="cancelDelete" class="fixed inset-0 bg-zinc-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

            <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-zinc-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
                <div class="bg-white px-4 pb-4 pt-5 dark:bg-zinc-800 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <flux:heading size="lg" class="mb-2">Delete Variant</flux:heading>
                            <flux:text>Are you sure you want to delete this variant? This action cannot be undone and will also delete all associated barcodes and pricing.</flux:text>
                        </div>
                    </div>
                </div>
                <div class="bg-zinc-50 px-4 py-3 dark:bg-zinc-900 sm:flex sm:flex-row-reverse sm:px-6">
                    <flux:button variant="danger" wire:click="deleteVariant" class="sm:ml-3">
                        Delete
                    </flux:button>
                    <flux:button variant="ghost" wire:click="cancelDelete">
                        Cancel
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
@endif