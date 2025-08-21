<div class="space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Shopify Sync</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Monitor and manage Shopify synchronization</p>
        </div>
        
        <div class="flex items-center space-x-3">
            <flux:button wire:click="testConnection" variant="ghost" size="sm">
                Test Connection
            </flux:button>
            <flux:button wire:click="syncAll" variant="primary" icon="arrow-path">
                Sync All Products
            </flux:button>
        </div>
    </div>

    {{-- 📊 STATS CARDS --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="cube" class="w-8 h-8 text-blue-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total Items</div>
                </div>
            </div>
        </div>

        {{-- Synced --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="check-circle" class="w-8 h-8 text-green-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['synced'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Synced</div>
                </div>
            </div>
        </div>

        {{-- Pending --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="clock" class="w-8 h-8 text-yellow-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['pending'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Pending</div>
                </div>
            </div>
        </div>

        {{-- Failed --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center">
                <flux:icon name="x-circle" class="w-8 h-8 text-red-500" />
                <div class="ml-4">
                    <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['failed'] }}</div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Failed</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 🔍 FILTER --}}
    <div class="flex justify-start">
        <flux:select wire:model.live="status" class="w-48">
            <flux:select.option value="all">All Status</flux:select.option>
            <flux:select.option value="synced">Synced</flux:select.option>
            <flux:select.option value="pending">Pending</flux:select.option>
            <flux:select.option value="failed">Failed</flux:select.option>
        </flux:select>
    </div>

    {{-- 💎 SYNC RECORDS TABLE --}}
    @if ($syncRecords->count())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Item
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Shopify ID
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Last Synced
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Error
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($syncRecords as $record)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                {{-- Item --}}
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $record->product->name }}
                                        </div>
                                        @if ($record->productVariant)
                                            <div class="flex items-center gap-2 mt-1">
                                                <div class="w-3 h-3 rounded-full bg-gray-300 dark:bg-gray-600 border border-gray-200 dark:border-gray-600" 
                                                     title="{{ $record->productVariant->color }}"></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $record->productVariant->color }} {{ $record->productVariant->width }}cm
                                                </div>
                                            </div>
                                        @endif
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                                            {{ $record->product->parent_sku }}
                                        </div>
                                    </div>
                                </td>

                                {{-- Shopify ID --}}
                                <td class="px-6 py-4">
                                    <div class="text-sm font-mono text-gray-900 dark:text-white">
                                        @if ($record->shopify_product_id)
                                            {{ $record->shopify_product_id }}
                                            @if ($record->shopify_variant_id)
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    V: {{ $record->shopify_variant_id }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="px-6 py-4">
                                    <flux:badge 
                                        :color="match($record->sync_status) {
                                            'synced' => 'green',
                                            'pending' => 'yellow',
                                            'failed' => 'red',
                                            default => 'gray'
                                        }"
                                        size="sm"
                                    >
                                        {{ ucfirst($record->sync_status) }}
                                    </flux:badge>
                                </td>

                                {{-- Last Synced --}}
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        @if ($record->last_synced_at)
                                            {{ $record->last_synced_at->diffForHumans() }}
                                        @else
                                            <span class="text-gray-400">Never</span>
                                        @endif
                                    </div>
                                </td>

                                {{-- Error --}}
                                <td class="px-6 py-4">
                                    @if ($record->error_message)
                                        <div class="text-sm text-red-600 dark:text-red-400 max-w-xs truncate" 
                                             title="{{ $record->error_message }}">
                                            {{ $record->error_message }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-6 py-4 text-right">
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        
                                        <flux:menu>
                                            @if ($record->shopify_url)
                                                <flux:menu.item href="{{ $record->shopify_url }}" target="_blank" icon="external-link">
                                                    View in Shopify
                                                </flux:menu.item>
                                            @endif
                                            <flux:menu.item icon="arrow-path">
                                                Retry Sync
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item icon="trash" variant="danger">
                                                Reset Sync
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Showing {{ $syncRecords->firstItem() }} to {{ $syncRecords->lastItem() }} of {{ $syncRecords->total() }} records
            </div>
            {{ $syncRecords->links() }}
        </div>
    @else
        {{-- Empty State --}}
        <div class="text-center py-12">
            <flux:icon name="cloud-arrow-up" class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No sync records found</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                @if ($status !== 'all')
                    No records with "{{ $status }}" status
                @else
                    Start by syncing your products to Shopify
                @endif
            </p>
            @if ($status === 'all')
                <div class="mt-6">
                    <flux:button wire:click="syncAll" variant="primary" icon="arrow-path">
                        Start Sync
                    </flux:button>
                </div>
            @endif
        </div>
    @endif
</div>