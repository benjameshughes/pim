<!-- Marketplace Sync Tab Content -->
@php
    $shopifyStatus = $product->getShopifySyncStatus();
@endphp
<div class="space-y-6">
    <flux:heading size="lg">Marketplace Sync Status</flux:heading>
    <div class="space-y-6">
        <!-- Shopify Status -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-emerald-100 dark:bg-emerald-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-bag" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Shopify</span>
                </div>
                <flux:button variant="outline" size="sm">
                    <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                    Sync Now
                </flux:button>
            </div>
            <x-sync-status-badge 
                :status="$shopifyStatus['status']"
                :last-synced-at="$shopifyStatus['last_synced_at'] ? \Carbon\Carbon::parse($shopifyStatus['last_synced_at']) : null"
                marketplace="Shopify"
                size="md"
            />
            @if($shopifyStatus['total_colors'] > 0)
                <div class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $shopifyStatus['colors_synced'] }}/{{ $shopifyStatus['total_colors'] }} color variants synced
                    @if($shopifyStatus['has_failures'])
                        <span class="text-red-500 ml-2">• Has failures</span>
                    @endif
                </div>
            @endif
        </div>

        <!-- eBay Status Placeholder -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="globe" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">eBay</span>
                </div>
                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                    Coming Soon
                </flux:badge>
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                eBay marketplace integration is in development
            </div>
        </div>

        <!-- Amazon Status Placeholder -->
        <div class="border-b border-zinc-100 dark:border-zinc-800 pb-6 opacity-50">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-lg flex items-center justify-center">
                        <flux:icon name="shopping-cart" class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                    </div>
                    <span class="text-lg font-medium text-zinc-900 dark:text-zinc-100">Amazon</span>
                </div>
                <flux:badge variant="outline" class="bg-zinc-50 text-zinc-500 border-zinc-200">
                    Coming Soon
                </flux:badge>
            </div>
            <div class="text-sm text-zinc-500 dark:text-zinc-400">
                Amazon marketplace integration is planned for future release
            </div>
        </div>
    </div>
</div>