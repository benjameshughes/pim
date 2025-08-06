<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Pricing Management</flux:heading>
        <flux:subheading>Manage retail prices, costs, VAT, channel fees, and profit margins</flux:subheading>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Total Pricing Entries</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['profitable']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Profitable Items</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-red-600">{{ number_format($stats['unprofitable']) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Unprofitable Items</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-purple-600">{{ $stats['average_margin'] }}%</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Average Profit Margin</div>
        </div>
    </div>

    <!-- Marketplace Statistics -->
    @if(count($marketplaceStats) > 0)
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
        <flux:heading size="lg" class="mb-4">Marketplace Overview</flux:heading>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($marketplaceStats as $code => $stats)
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <flux:heading size="sm">{{ $stats['name'] }}</flux:heading>
                    <flux:badge variant="outline" class="text-xs">{{ $code }}</flux:badge>
                </div>
                <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-1">
                    <div>{{ number_format($stats['variants']) }} products synced</div>
                    <div>{{ number_format($stats['synced_recently']) }} synced today</div>
                    @if(isset($stats['failed_syncs']) && $stats['failed_syncs'] > 0)
                        <div class="text-red-500">{{ number_format($stats['failed_syncs']) }} failed</div>
                    @endif
                </div>
                
                @if($code === 'shopify')
                    <div class="mt-3">
                        @php
                            $shopifyStatus = $stats['variants'] > 0 ? 'synced' : 'not_synced';
                            if ($stats['failed_syncs'] > 0) $shopifyStatus = 'failed';
                        @endphp
                        <x-sync-status-badge 
                            :status="$shopifyStatus"
                            marketplace=""
                            size="sm"
                        />
                    </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Filters and Bulk Actions -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
            <!-- Search -->
            <flux:field>
                <flux:label>Search</flux:label>
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by product, SKU, or channel..."
                />
            </flux:field>

            <!-- Channel Filter -->
            <flux:field>
                <flux:label>Sales Channel</flux:label>
                <flux:select wire:model.live="channelFilter">
                    <option value="">All Channels</option>
                    @foreach($salesChannels as $channel)
                        <option value="{{ $channel->slug }}">{{ $channel->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            <!-- Profitability Filter -->
            <flux:field>
                <flux:label>Profitability</flux:label>
                <flux:select wire:model.live="profitabilityFilter">
                    <option value="">All Items</option>
                    <option value="profitable">Profitable Only</option>
                    <option value="unprofitable">Unprofitable Only</option>
                </flux:select>
            </flux:field>

            <!-- Bulk Actions Toggle -->
            <div class="flex items-end gap-2">
                <flux:button 
                    wire:click="toggleBulkEdit"
                    variant="{{ $bulkEditMode ? 'danger' : 'primary' }}"
                >
                    {{ $bulkEditMode ? 'Cancel Bulk Edit' : 'Bulk Edit' }}
                </flux:button>
                <flux:button 
                    wire:click="toggleMarketplacePricing"
                    variant="{{ $showMarketplacePricing ? 'danger' : 'outline' }}"
                    icon="globe"
                >
                    {{ $showMarketplacePricing ? 'Cancel Marketplace' : 'Marketplace Sync' }}
                </flux:button>
            </div>
        </div>

        <!-- Bulk Edit Panel -->
        @if($bulkEditMode)
            <div class="border-t pt-4 bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <flux:heading size="sm" class="mb-4">Bulk Edit Selected Items</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <flux:field>
                        <flux:label>VAT Percentage (%)</flux:label>
                        <flux:input 
                            wire:model="bulkVatPercentage"
                            type="number"
                            step="0.01"
                            placeholder="e.g., 20.00"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>Channel Fee (%)</flux:label>
                        <flux:input 
                            wire:model="bulkChannelFeePercentage"
                            type="number"
                            step="0.01"
                            placeholder="e.g., 15.00"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label>Shipping Cost (£)</flux:label>
                        <flux:input 
                            wire:model="bulkShippingCost"
                            type="number"
                            step="0.01"
                            placeholder="e.g., 3.50"
                        />
                    </flux:field>

                    <div class="flex items-end gap-2">
                        <flux:button 
                            wire:click="bulkUpdateFields"
                            variant="primary"
                            size="sm"
                        >
                            Update Fields
                        </flux:button>
                        <flux:button 
                            wire:click="bulkRecalculate"
                            variant="ghost"
                            size="sm"
                        >
                            Recalculate
                        </flux:button>
                    </div>
                </div>

                <div class="flex gap-2 text-sm">
                    <flux:button wire:click="selectAllPricing" variant="ghost" size="sm">
                        Select All ({{ count($selectedPricing) }})
                    </flux:button>
                    <flux:button wire:click="deselectAllPricing" variant="ghost" size="sm">
                        Deselect All
                    </flux:button>
                </div>
            </div>
        @endif

        <!-- Marketplace Pricing Panel -->
        @if($showMarketplacePricing)
            <div class="border-t pt-4 bg-blue-50 dark:bg-blue-950/20 rounded-lg p-4">
                <flux:heading size="sm" class="mb-4">Sync Pricing to Marketplace</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <flux:field>
                        <flux:label>Target Marketplace</flux:label>
                        <flux:select wire:model="selectedMarketplace">
                            <flux:select.option value="">Select marketplace...</flux:select.option>
                            @foreach($marketplaces as $marketplace)
                                <flux:select.option value="{{ $marketplace->code }}">{{ $marketplace->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Price Adjustment (%)</flux:label>
                        <flux:input 
                            wire:model="priceAdjustmentPercentage"
                            type="number"
                            step="0.1"
                            placeholder="e.g., 10 for +10%, -5 for -5%"
                        />
                        <flux:description>Adjust prices by percentage (leave empty for no adjustment)</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Currency</flux:label>
                        <flux:select wire:model="currencyFilter">
                            <flux:select.option value="GBP">GBP (£)</flux:select.option>
                            <flux:select.option value="USD">USD ($)</flux:select.option>
                            <flux:select.option value="EUR">EUR (€)</flux:select.option>
                        </flux:select>
                    </flux:field>

                    <div class="flex items-end">
                        <flux:button 
                            wire:click="syncMarketplacePricing"
                            variant="primary"
                            icon="globe"
                        >
                            Sync to Marketplace
                        </flux:button>
                    </div>
                </div>

                <div class="flex gap-2 text-sm">
                    <flux:button wire:click="selectAllPricing" variant="ghost" size="sm">
                        Select All ({{ count($selectedPricing) }})
                    </flux:button>
                    <flux:button wire:click="deselectAllPricing" variant="ghost" size="sm">
                        Deselect All
                    </flux:button>
                </div>
            </div>
        @endif
    </div>

    <!-- Pricing Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-zinc-50 dark:bg-zinc-900">
                    <tr>
                        @if($bulkEditMode)
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" class="rounded">
                            </th>
                        @endif
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Product & Channel
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Pricing
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Costs & Fees
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Profit
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($pricing as $priceEntry)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            @if($bulkEditMode)
                                <td class="px-4 py-4">
                                    <input 
                                        type="checkbox" 
                                        wire:model="selectedPricing" 
                                        value="{{ $priceEntry->id }}"
                                        class="rounded"
                                    >
                                </td>
                            @endif
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $priceEntry->productVariant->product->name }}
                                    </div>
                                    <div class="text-sm text-zinc-500 dark:text-zinc-400">
                                        SKU: {{ $priceEntry->productVariant->sku }}
                                    </div>
                                    <div class="text-xs text-zinc-400">
                                        {{ $priceEntry->salesChannel->name ?? $priceEntry->marketplace }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm space-y-1">
                                    <div class="font-medium">Retail: £{{ number_format($priceEntry->retail_price, 2) }}</div>
                                    @if($priceEntry->cost_price)
                                        <div class="text-zinc-600 dark:text-zinc-400">Cost: £{{ number_format($priceEntry->cost_price, 2) }}</div>
                                    @endif
                                    <div class="text-zinc-500 text-xs">
                                        VAT: {{ $priceEntry->vat_percentage }}% 
                                        @if($priceEntry->vat_inclusive)
                                            (inc)
                                        @else
                                            (exc)
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm space-y-1">
                                    @if($priceEntry->channel_fee_amount > 0)
                                        <div>Fee: £{{ number_format($priceEntry->channel_fee_amount, 2) }} ({{ $priceEntry->channel_fee_percentage }}%)</div>
                                    @endif
                                    @if($priceEntry->shipping_cost > 0)
                                        <div>Shipping: £{{ number_format($priceEntry->shipping_cost, 2) }}</div>
                                    @endif
                                    @if($priceEntry->total_cost > 0)
                                        <div class="font-medium">Total: £{{ number_format($priceEntry->total_cost, 2) }}</div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    @if($priceEntry->isProfitable())
                                        <div class="font-medium text-green-600">
                                            £{{ number_format($priceEntry->profit_amount, 2) }}
                                        </div>
                                        <div class="text-green-500 text-xs">
                                            {{ $priceEntry->getFormattedProfitMargin() }}
                                        </div>
                                    @else
                                        <div class="font-medium text-red-600">
                                            £{{ number_format($priceEntry->profit_amount, 2) }}
                                        </div>
                                        <div class="text-red-500 text-xs">
                                            {{ $priceEntry->getFormattedProfitMargin() }}
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <flux:button 
                                        size="sm" 
                                        variant="ghost"
                                        wire:click="recalculatePricing({{ $priceEntry->id }})"
                                    >
                                        Recalculate
                                    </flux:button>
                                    
                                    <flux:button 
                                        size="sm" 
                                        variant="danger"
                                        wire:click="deletePricing({{ $priceEntry->id }})"
                                        wire:confirm="Are you sure you want to delete this pricing entry?"
                                    >
                                        Delete
                                    </flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $bulkEditMode ? '6' : '5' }}" class="px-6 py-12 text-center">
                                <div class="text-zinc-500 dark:text-zinc-400">
                                    <div class="mb-2">No pricing entries found</div>
                                    <div class="text-sm">Add pricing to your product variants to get started</div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($pricing->hasPages())
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $pricing->links() }}
            </div>
        @endif
    </div>
</div>
