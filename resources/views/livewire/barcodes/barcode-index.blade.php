{{-- 📊 BARCODE MANAGEMENT INDEX ✨ --}}
<div class="space-y-6">
    {{-- Header & Stats --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                📊 Barcodes
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Manage and assign barcodes to your products
            </p>
        </div>
        <div class="flex gap-3">
            <flux:button wire:click="openMarkAssignedModal" icon="check-circle" variant="ghost">
                Mark Assigned
            </flux:button>
            <flux:button href="{{ route('barcodes.import') }}" icon="arrow-up-tray" variant="ghost" wire:navigate>
                Import CSV
            </flux:button>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">Total Barcodes</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">Assigned</div>
            <div class="text-2xl font-bold text-green-600">{{ $this->stats['assigned'] }}</div>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-600 dark:text-gray-400">Unassigned</div>
            <div class="text-2xl font-bold text-orange-600">{{ $this->stats['unassigned'] }}</div>
        </div>
    </div>

    {{-- Search & Filter Bar --}}
    <div class="flex gap-4 items-center">
            <div class="flex-1 max-w-none">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search barcodes, SKUs, titles..." 
                    icon="magnifying-glass"
                />
            </div>
            <div class="flex-shrink-0">
                <flux:select wire:model.live="assignedFilter" class="w-28">
                    <option value="all">All</option>
                    <option value="assigned">Assigned</option>
                    <option value="unassigned">Unassigned</option>
                </flux:select>
            </div>
            <div class="flex-shrink-0">
                <flux:select wire:model.live="perPage" class="w-16">
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </flux:select>
            </div>
    </div>

    {{-- Main Barcodes Table --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        @if ($barcodes->count())
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th wire:click="sortBy('barcode')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Barcode
                                @if ($sortField === 'barcode')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            SKU
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Title
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Product Variant
                        </th>
                        <th wire:click="sortBy('created_at')" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-2">
                                Created
                                @if ($sortField === 'created_at')
                                    <flux:icon name="{{ $sortDirection === 'asc' ? 'chevron-up' : 'chevron-down' }}" class="w-3 h-3" />
                                @endif
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($barcodes as $barcode)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-mono font-medium text-gray-900 dark:text-white">
                                    {{ $barcode->barcode }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if ($barcode->sku)
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        {{ $barcode->sku }}
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($barcode->title)
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        {{ $barcode->title }}
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <flux:badge :color="$barcode->is_assigned ? 'green' : 'gray'" size="sm">
                                    {{ $barcode->is_assigned ? 'Assigned' : 'Unassigned' }}
                                </flux:badge>
                            </td>
                            <td class="px-6 py-4">
                                @if ($barcode->variant)
                                    <div class="text-sm">
                                        <div class="font-medium text-gray-900 dark:text-white">
                                            {{ $barcode->variant->product->name }}
                                        </div>
                                        <div class="text-gray-500 dark:text-gray-400">
                                            {{ $barcode->variant->color }} • {{ $barcode->variant->width }}cm
                                        </div>
                                    </div>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">No variant assigned</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $barcode->created_at->format('M j, Y') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="text-center py-12">
                <flux:icon name="bars-2" class="mx-auto h-8 w-8 text-gray-400" />
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No barcodes found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($search || $assignedFilter !== 'all')
                        No barcodes match your search criteria.
                    @else
                        Get started by adding your first barcode.
                    @endif
                </p>
                @if (!$search && $assignedFilter === 'all')
                    <div class="mt-6">
                        <flux:button icon="plus" variant="primary">
                            Add First Barcode
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif

        {{-- Pagination --}}
        @if ($barcodes->hasPages())
            <div class="px-6 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $barcodes->links() }}
            </div>
        @endif
    </div>

    {{-- Mark Assigned Modal --}}
    @if($showMarkAssignedModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Mark Barcodes as Assigned
                    </h3>
                </div>
                
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <flux:field label="Mark up to barcode number (optional)">
                            <flux:input 
                                wire:model="upToBarcode" 
                                placeholder="e.g., 1234567890123"
                                class="font-mono"
                            />
                        </flux:field>
                        <p class="text-sm text-gray-500 mt-1">
                            Leave empty to mark by count instead
                        </p>
                    </div>
                    
                    <div>
                        <flux:field label="Or mark first N barcodes">
                            <flux:input 
                                type="number"
                                wire:model="markCount" 
                                :disabled="!empty($upToBarcode)"
                            />
                        </flux:field>
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <flux:field>
                            <flux:checkbox wire:model.live="setEmptyTitles">
                                Set title for barcodes with empty titles
                            </flux:checkbox>
                        </flux:field>
                        
                        @if($setEmptyTitles)
                            <div class="mt-3">
                                <flux:field label="Default title">
                                    <flux:input wire:model="defaultTitle" placeholder="e.g., Assigned Barcode" />
                                </flux:field>
                            </div>
                        @endif
                    </div>
                    
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 p-3 rounded-lg">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            ⚠️ This will mark barcodes as assigned to prevent them from being assigned to new products.
                        </p>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex gap-3 justify-end">
                    <flux:button wire:click="closeMarkAssignedModal" variant="ghost">
                        Cancel
                    </flux:button>
                    <flux:button 
                        wire:click="markBarcodesAssigned" 
                        variant="primary"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove>Mark as Assigned</span>
                        <span wire:loading>Processing...</span>
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
