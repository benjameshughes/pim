@props(['item'])

<flux:card class="relative">
    {{-- Channel Icon & Name --}}
    <div class="flex items-start justify-between mb-4">
        <div class="flex items-center space-x-3">
            <x-marketplace.channel-icon :channel="$item->account->channel" />
            <div>
                <p class="font-medium text-gray-900">{{ ucfirst($item->account->channel) }}</p>
                <p class="text-sm text-gray-500">{{ $item->account->name }}</p>
            </div>
        </div>

        {{-- Status & Linking Badges --}}
        <x-marketplace.status-badges :item="$item" />
    </div>

    {{-- Sync Information --}}
    <div class="space-y-3">
        <x-marketplace.sync-info :item="$item" />

        {{-- Shopify Color Links --}}
        <x-marketplace.color-links :item="$item" />

        {{-- Action Buttons --}}
        <x-marketplace.action-buttons :item="$item" />
    </div>

    {{-- Loading States --}}
    <x-marketplace.loading-states :account-id="$item->account->id" />
</flux:card>