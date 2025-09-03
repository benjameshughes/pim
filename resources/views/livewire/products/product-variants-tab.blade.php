{{-- Variants Table --}}
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                Product Variants ({{ $product->variants->count() }})
            </h3>
            <flux:button wire:navigate href="{{ route('variants.create') }}?product={{ $product->id }}" variant="primary" size="sm" icon="plus">
                Add Variant
            </flux:button>
        </div>
    </div>

    @if ($product->variants->count())
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Variant
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Dimensions
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Price
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Stock
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Barcodes
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($product->variants as $variant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-4 h-4 rounded-full border border-gray-200 dark:border-gray-600 shadow-sm
                                        @if(strtolower($variant->color) === 'black') bg-gray-900
                                        @elseif(strtolower($variant->color) === 'white') bg-white
                                        @elseif(strtolower($variant->color) === 'red') bg-red-500
                                        @elseif(strtolower($variant->color) === 'blue') bg-blue-500
                                        @elseif(strtolower($variant->color) === 'green') bg-green-500
                                        @elseif(str_contains(strtolower($variant->color), 'grey') || str_contains(strtolower($variant->color), 'gray')) bg-gray-500
                                        @elseif(str_contains(strtolower($variant->color), 'orange')) bg-orange-500
                                        @elseif(str_contains(strtolower($variant->color), 'yellow') || str_contains(strtolower($variant->color), 'lemon')) bg-yellow-500
                                        @elseif(str_contains(strtolower($variant->color), 'purple') || str_contains(strtolower($variant->color), 'lavender')) bg-purple-500
                                        @elseif(str_contains(strtolower($variant->color), 'pink')) bg-pink-500
                                        @elseif(str_contains(strtolower($variant->color), 'brown') || str_contains(strtolower($variant->color), 'cappuccino')) bg-amber-700
                                        @elseif(str_contains(strtolower($variant->color), 'navy')) bg-blue-900
                                        @elseif(str_contains(strtolower($variant->color), 'natural')) bg-amber-200
                                        @elseif(str_contains(strtolower($variant->color), 'lime')) bg-lime-500
                                        @elseif(str_contains(strtolower($variant->color), 'aubergine')) bg-purple-900
                                        @elseif(str_contains(strtolower($variant->color), 'ochre')) bg-yellow-700
                                        @else bg-gradient-to-br from-orange-400 to-red-500
                                        @endif" 
                                        title="{{ $variant->color }}">
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $variant->color }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                            {{ $variant->sku }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $variant->width }}cm
                                    @if ($variant->drop)
                                        × {{ $variant->drop }}cm
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    £{{ number_format($variant->getRetailPrice(), 2) }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    {{ $variant->stock_level }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    @if($variant->barcode)
                                        <flux:badge color="green" size="sm">
                                            {{ $variant->barcode->barcode }}
                                        </flux:badge>
                                    @else
                                        <flux:badge color="gray" size="sm">
                                            None
                                        </flux:badge>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <flux:button wire:navigate href="{{ route('variants.show', $variant) }}" variant="ghost" size="sm">
                                        View
                                    </flux:button>
                                    <flux:button wire:navigate href="{{ route('variants.edit', $variant) }}" variant="ghost" size="sm">
                                        Edit
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <flux:icon name="squares-plus" class="mx-auto h-8 w-8 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No variants</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating your first variant</p>
            <div class="mt-6">
                <flux:button wire:navigate href="{{ route('variants.create') }}?product={{ $product->id }}" variant="primary" icon="plus">
                    Add Variant
                </flux:button>
            </div>
        </div>
    @endif
</div>