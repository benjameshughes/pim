<div class="space-y-6">
    {{-- 💰 PRICING HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Channel Pricing</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Manage pricing across different sales channels for {{ $variants->count() }} variants
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            {{-- Filters Toggle --}}
            <flux:button 
                wire:click="$toggle('showOnlyOverrides')" 
                variant="{{ $showOnlyOverrides ? 'primary' : 'ghost' }}"
                size="sm"
                icon="funnel">
                {{ $showOnlyOverrides ? 'All Variants' : 'Overrides Only' }}
            </flux:button>
            
            {{-- Bulk Actions Dropdown --}}
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal" size="sm" />
                <flux:menu>
                    <flux:menu.item wire:click="openBulkMarkupModal" icon="plus">Apply Markup</flux:menu.item>
                    <flux:menu.item wire:click="openBulkDiscountModal" icon="minus">Apply Discount</flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item icon="arrow-path">Sync All Prices</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- 📊 CHANNEL SUMMARY CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach($channelSummary as $channelCode => $summary)
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $summary['channel']->name }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['overrides_count'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-500">
                            {{ $summary['percentage_with_overrides'] }}% with overrides
                        </p>
                    </div>
                    <div class="h-8 w-8 rounded-full flex items-center justify-center" 
                         style="background-color: {{ match($channelCode) {
                             'shopify' => '#96bf48',
                             'ebay' => '#e53238',
                             'amazon' => '#ff9900',
                             'direct' => '#3b82f6',
                             default => '#6b7280'
                         } }}20">
                        <flux:icon name="{{ match($channelCode) {
                            'shopify' => 'shopping-bag',
                            'ebay' => 'store',
                            'amazon' => 'shopping-cart',
                            'direct' => 'home',
                            default => 'pound-sterling'
                        } }}" class="w-4 h-4" style="color: {{ match($channelCode) {
                             'shopify' => '#96bf48',
                             'ebay' => '#e53238',
                             'amazon' => '#ff9900',
                             'direct' => '#3b82f6',
                             default => '#6b7280'
                         } }}" />
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- 🔍 SEARCH & FILTERS --}}
    <div class="flex items-center gap-4">
        <div class="flex-1">
            <flux:input 
                wire:model.live="searchVariants"
                placeholder="Search variants by SKU or color..."
                icon="magnifying-glass"
                clearable />
        </div>
        
        <flux:select wire:model.live="filterChannel" placeholder="All Channels">
            <flux:select.option value="">All Channels</flux:select.option>
            @foreach($channels as $channel)
                <flux:select.option value="{{ $channel->code }}">{{ $channel->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- 📋 PRICING TABLE --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Variant
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Retail Price
                        </th>
                        @foreach($channels as $channel)
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                <div class="flex items-center justify-center gap-2">
                                    <flux:icon name="{{ match($channel->code) {
                                        'shopify' => 'shopping-bag',
                                        'ebay' => 'store', 
                                        'amazon' => 'shopping-cart',
                                        'direct' => 'home',
                                        default => 'pound-sterling'
                                    } }}" class="w-4 h-4" />
                                    <span>{{ $channel->name }}</span>
                                </div>
                            </th>
                        @endforeach
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($pricingData as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            {{-- Variant Info --}}
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white font-mono">
                                        {{ $row['variant']->sku }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ $row['variant']->color }} • {{ $row['variant']->width }}cm
                                    </div>
                                </div>
                            </td>
                            
                            {{-- Retail Price --}}
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <button 
                                    wire:click="openBasePriceModal({{ $row['variant']->id }})"
                                    class="group inline-flex items-center gap-1 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                    <span class="font-mono text-gray-900 dark:text-white">
                                        £{{ number_format($row['default_price'], 2) }}
                                    </span>
                                    <flux:icon name="pencil" class="w-3 h-3 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" />
                                </button>
                            </td>
                            
                            {{-- Channel Prices --}}
                            @foreach($channels as $channel)
                                @php
                                    $channelData = $row['channels'][$channel->code];
                                    $hasOverride = $channelData['has_override'];
                                    $price = $channelData['price'];
                                    $markup = $channelData['markup_percentage'];
                                @endphp
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <button 
                                        wire:click="openPriceModal({{ $row['variant']->id }}, '{{ $channel->code }}')"
                                        class="group inline-flex flex-col items-center gap-1 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                        
                                        <span class="font-mono text-sm {{ $hasOverride ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-gray-900 dark:text-white' }}">
                                            £{{ number_format($price, 2) }}
                                        </span>
                                        
                                        @if($hasOverride)
                                            <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                {{ $markup > 0 ? '+' : '' }}{{ $markup }}%
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                                default
                                            </span>
                                        @endif
                                    </button>
                                </td>
                            @endforeach
                            
                            {{-- Actions --}}
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                @if($row['has_any_override'])
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            @foreach($channels as $channel)
                                                @if($row['channels'][$channel->code]['has_override'])
                                                    <flux:menu.item 
                                                        wire:click="removeChannelOverride({{ $row['variant']->id }}, '{{ $channel->code }}')"
                                                        icon="x-mark">
                                                        Remove {{ $channel->name }} override
                                                    </flux:menu.item>
                                                @endif
                                            @endforeach
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500 text-sm">No overrides</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($channels) + 3 }}" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <flux:icon name="pound-sterling" class="w-8 h-8 mx-auto mb-2 opacity-40" />
                                <p>No variants match your filters</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 💰 CHANNEL PRICE MODAL --}}
    <flux:modal wire:model="showPriceModal">
        <form wire:submit="savePriceModal">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Set Channel Price</h3>
                        @if($selectedVariant)
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $selectedVariant->sku }} • {{ $selectedVariant->color }} • {{ $selectedVariant->width }}cm
                            </p>
                        @endif
                    </div>
                    @if($selectedChannel && $channels->firstWhere('code', $selectedChannel))
                        @php $channel = $channels->firstWhere('code', $selectedChannel) @endphp
                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 dark:bg-gray-700">
                            <flux:icon name="{{ match($selectedChannel) {
                                'shopify' => 'shopping-bag',
                                'ebay' => 'store',
                                'amazon' => 'shopping-cart', 
                                'direct' => 'home',
                                default => 'pound-sterling'
                            } }}" class="w-4 h-4" />
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $channel->name }}</span>
                        </div>
                    @endif
                </div>

                @if($selectedVariant)
                    <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Default Price:</span>
                            <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">
                                £{{ number_format($selectedVariant->getRetailPrice(), 2) }}
                            </span>
                        </div>
                    </div>
                @endif

                <div class="mb-6">
                    <flux:input
                        wire:model="modalPrice"
                        label="Channel Price"
                        placeholder="Enter price or leave empty to use default"
                        type="number"
                        step="0.01"
                        min="0">
                        <x-slot name="prefix">£</x-slot>
                    </flux:input>
                    
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Leave empty to remove channel override and use default price
                    </div>
                </div>

                @if($modalPrice && $selectedVariant)
                    <div class="mb-6 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        @php
                            $retailPrice = $selectedVariant->getRetailPrice();
                            $markup = $retailPrice > 0 ? (($modalPrice - $retailPrice) / $retailPrice) * 100 : 0;
                        @endphp
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-blue-700 dark:text-blue-300">Markup/Discount:</span>
                            <span class="font-semibold {{ $markup > 0 ? 'text-green-600' : ($markup < 0 ? 'text-red-600' : 'text-gray-600') }}">
                                {{ $markup > 0 ? '+' : '' }}{{ number_format($markup, 1) }}%
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:click="closePriceModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $modalPrice ? 'Set Price' : 'Remove Override' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- 📈 BULK MARKUP MODAL --}}
    <flux:modal wire:model="showBulkMarkupModal">
        <form wire:submit="saveBulkMarkup" class="w-full">
            <div class="p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Apply Markup</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Apply markup to {{ $this->getFilteredVariants()->count() }} variants
                    </p>
                </div>

                <div class="space-y-6">
                    <flux:select wire:model="markupChannel" label="Channel" placeholder="Select channel">
                        @foreach($channels as $channel)
                            <flux:select.option value="{{ $channel->code }}">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="{{ match($channel->code) {
                                        'shopify' => 'shopping-bag',
                                        'ebay' => 'building-storefront',
                                        'amazon' => 'shopping-cart',
                                        'direct' => 'home',
                                        default => 'currency-pound'
                                    } }}" class="w-4 h-4" />
                                    {{ $channel->name }}
                                </div>
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Pricing Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input type="radio" wire:model="markupPriceType" value="percentage" class="sr-only peer" />
                                <div class="w-full p-4 text-sm font-medium text-center border-2 border-gray-200 rounded-lg cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:peer-checked:bg-blue-900/20 dark:peer-checked:text-blue-400 dark:peer-checked:border-blue-500">
                                    <flux:icon name="percent" class="w-5 h-5 mx-auto mb-1" />
                                    <div>Percentage Markup</div>
                                </div>
                            </label>
                            <label class="relative">
                                <input type="radio" wire:model="markupPriceType" value="fixed" class="sr-only peer" />
                                <div class="w-full p-4 text-sm font-medium text-center border-2 border-gray-200 rounded-lg cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-checked:text-blue-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:peer-checked:bg-blue-900/20 dark:peer-checked:text-blue-400 dark:peer-checked:border-blue-500">
                                    <flux:icon name="currency-pound" class="w-5 h-5 mx-auto mb-1" />
                                    <div>Fixed Value</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    @if($markupPriceType === 'percentage')
                        <flux:input
                            wire:model="markupPercentage"
                            label="Markup Percentage"
                            placeholder="e.g. 10 for 10% markup"
                            type="number"
                            step="0.1"
                            min="0"
                            max="1000">
                            <x-slot name="suffix">%</x-slot>
                        </flux:input>

                        @if($markupPercentage)
                            <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                                <div class="text-sm text-green-700 dark:text-green-300">
                                    <strong>Preview:</strong> A £100 product will become £{{ number_format(100 * (1 + ($markupPercentage / 100)), 2) }}
                                </div>
                            </div>
                        @endif
                    @else
                        <flux:input
                            wire:model="markupFixedPrice"
                            label="Value"
                            placeholder="e.g. 25.99"
                            type="number"
                            step="0.01"
                            min="0">
                            <x-slot name="prefix">£</x-slot>
                        </flux:input>

                        @if($markupFixedPrice)
                            <div class="p-3 rounded-lg bg-green-50 dark:bg-green-900/20">
                                <div class="text-sm text-green-700 dark:text-green-300">
                                    <strong>Preview:</strong> All variants will be set to £{{ number_format($markupFixedPrice, 2) }}
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:click="closeBulkMarkupModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="plus" class="min-w-32">
                    @if($markupPriceType === 'percentage')
                        @if($markupPercentage)
                            Apply {{ $markupPercentage }}% Markup
                        @else
                            Apply Markup
                        @endif
                    @else
                        @if($markupFixedPrice)
                            Set Value £{{ $markupFixedPrice }}
                        @else
                            Set Value
                        @endif
                    @endif
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- 📉 BULK DISCOUNT MODAL --}}
    <flux:modal wire:model="showBulkDiscountModal">
        <form wire:submit="saveBulkDiscount" class="w-full">
            <div class="p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Apply Discount</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Apply discount to {{ $this->getFilteredVariants()->count() }} variants
                    </p>
                </div>

                <div class="space-y-6">
                    <flux:select wire:model="discountChannel" label="Channel" placeholder="Select channel">
                        @foreach($channels as $channel)
                            <flux:select.option value="{{ $channel->code }}">
                                <div class="flex items-center gap-2">
                                    <flux:icon name="{{ match($channel->code) {
                                        'shopify' => 'shopping-bag',
                                        'ebay' => 'building-storefront',
                                        'amazon' => 'shopping-cart',
                                        'direct' => 'home',
                                        default => 'currency-pound'
                                    } }}" class="w-4 h-4" />
                                    {{ $channel->name }}
                                </div>
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Pricing Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input type="radio" wire:model="discountPriceType" value="percentage" class="sr-only peer" />
                                <div class="w-full p-4 text-sm font-medium text-center border-2 border-gray-200 rounded-lg cursor-pointer peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:peer-checked:bg-red-900/20 dark:peer-checked:text-red-400 dark:peer-checked:border-red-500">
                                    <flux:icon name="percent" class="w-5 h-5 mx-auto mb-1" />
                                    <div>Percentage Discount</div>
                                </div>
                            </label>
                            <label class="relative">
                                <input type="radio" wire:model="discountPriceType" value="fixed" class="sr-only peer" />
                                <div class="w-full p-4 text-sm font-medium text-center border-2 border-gray-200 rounded-lg cursor-pointer peer-checked:border-red-500 peer-checked:bg-red-50 peer-checked:text-red-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:peer-checked:bg-red-900/20 dark:peer-checked:text-red-400 dark:peer-checked:border-red-500">
                                    <flux:icon name="currency-pound" class="w-5 h-5 mx-auto mb-1" />
                                    <div>Fixed Value</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    @if($discountPriceType === 'percentage')
                        <flux:input
                            wire:model="discountPercentage"
                            label="Discount Percentage"
                            placeholder="e.g. 10 for 10% discount"
                            type="number"
                            step="0.1"
                            min="0"
                            max="100">
                            <x-slot name="suffix">%</x-slot>
                        </flux:input>

                        @if($discountPercentage)
                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                                <div class="text-sm text-red-700 dark:text-red-300">
                                    <strong>Preview:</strong> A £100 product will become £{{ number_format(100 * (1 - ($discountPercentage / 100)), 2) }}
                                </div>
                            </div>
                        @endif
                    @else
                        <flux:input
                            wire:model="discountFixedPrice"
                            label="Value"
                            placeholder="e.g. 19.99"
                            type="number"
                            step="0.01"
                            min="0">
                            <x-slot name="prefix">£</x-slot>
                        </flux:input>

                        @if($discountFixedPrice)
                            <div class="p-3 rounded-lg bg-red-50 dark:bg-red-900/20">
                                <div class="text-sm text-red-700 dark:text-red-300">
                                    <strong>Preview:</strong> All variants will be set to £{{ number_format($discountFixedPrice, 2) }}
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:click="closeBulkDiscountModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="minus" class="min-w-32">
                    @if($discountPriceType === 'percentage')
                        @if($discountPercentage)
                            Apply {{ $discountPercentage }}% Discount
                        @else
                            Apply Discount
                        @endif
                    @else
                        @if($discountFixedPrice)
                            Set Value £{{ $discountFixedPrice }}
                        @else
                            Set Value
                        @endif
                    @endif
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- 💰 BASE PRICE MODAL --}}
    <flux:modal wire:model="showBasePriceModal">
        <form wire:submit="saveBasePriceModal">
            <div class="p-6">
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Set Retail Price</h3>
                    @if($selectedBaseVariant)
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $selectedBaseVariant->sku }} • {{ $selectedBaseVariant->color }} • {{ $selectedBaseVariant->width }}cm
                        </p>
                    @endif
                </div>

                @if($selectedBaseVariant)
                    <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Current Retail Price:</span>
                            <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white">
                                £{{ number_format($selectedBaseVariant->getRetailPrice(), 2) }}
                            </span>
                        </div>
                    </div>
                @endif

                <div class="mb-6">
                    <flux:input
                        wire:model="basePriceValue"
                        label="New Retail Price"
                        placeholder="e.g. 25.99"
                        type="number"
                        step="0.01"
                        min="0">
                        <x-slot name="prefix">£</x-slot>
                    </flux:input>
                    
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        This is the base price used for all channels unless overridden
                    </div>
                </div>

                @if($basePriceValue && $selectedBaseVariant && $basePriceValue != $selectedBaseVariant->getRetailPrice())
                    <div class="mb-6 p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-blue-700 dark:text-blue-300">Price Change:</span>
                            <span class="font-semibold">
                                £{{ number_format($selectedBaseVariant->getRetailPrice(), 2) }} → £{{ number_format($basePriceValue, 2) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 px-6 py-4 bg-gray-50 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:click="closeBasePriceModal" variant="ghost">Cancel</flux:button>
                <flux:button type="submit" variant="primary" icon="currency-pound">
                    Update Retail Price
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
