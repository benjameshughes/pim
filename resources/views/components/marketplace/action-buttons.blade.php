@props(['item'])

<div class="flex flex-wrap gap-2 pt-2 border-t">
    @if($item->isLinked)
        {{-- Update Linked Listing --}}
        <flux:button 
            size="sm" 
            variant="filled"
            color="green"
            wire:click="updateMarketplaceListing({{ $item->account->id }})"
            wire:loading.attr="disabled"
            wire:target="updateMarketplaceListing({{ $item->account->id }})">
            <flux:icon name="arrow-up" class="w-3 h-3" />
            Update
        </flux:button>

        {{-- Shopify Pricing Update Button --}}
        @if($item->account->channel === 'shopify' && $item->hasColorLinks)
            <flux:button 
                size="sm" 
                variant="filled"
                color="purple"
                wire:click="updateShopifyPricing({{ $item->account->id }})"
                wire:loading.attr="disabled"
                wire:target="updateShopifyPricing({{ $item->account->id }})">
                <flux:icon name="currency-dollar" class="w-3 h-3" />
                Update Pricing
            </flux:button>
        @endif

        {{-- Edit Links --}}
        <flux:button 
            size="sm" 
            variant="ghost"
            color="yellow"
            wire:click="showEditLinksModal({{ $item->account->id }})"
            wire:loading.attr="disabled">
            <flux:icon name="pencil" class="w-3 h-3" />
            Edit
        </flux:button>

        {{-- Unlink --}}
        <flux:button 
            size="sm" 
            variant="ghost"
            color="red"
            wire:click="unlinkFromMarketplace({{ $item->account->id }})"
            wire:confirm="Are you sure you want to unlink this product from {{ $item->account->channel }}?"
            wire:loading.attr="disabled"
            wire:target="unlinkFromMarketplace({{ $item->account->id }})">
            <flux:icon name="link-slash" class="w-3 h-3" />
            Unlink
        </flux:button>
    @else
        {{-- Create New Listing --}}
        <flux:button 
            size="sm" 
            variant="filled"
            color="blue"
            wire:click="syncToMarketplace({{ $item->account->id }})"
            wire:loading.attr="disabled"
            wire:target="syncToMarketplace({{ $item->account->id }})">
            <flux:icon name="plus" class="w-3 h-3" />
            Create
        </flux:button>

        {{-- Link Existing --}}
        @if($item->account->channel === 'shopify')
            {{-- Shopify Color Linking --}}
            <flux:button 
                size="sm" 
                variant="ghost"
                color="purple"
                wire:click="showShopifyColorLinking({{ $item->account->id }})"
                wire:loading.attr="disabled">
                <flux:icon name="swatch" class="w-3 h-3" />
                Link Colors
            </flux:button>
        @else
            {{-- Standard Link for other marketplaces --}}
            <flux:button 
                size="sm" 
                variant="ghost"
                color="blue"
                wire:click="showLinkingModal({{ $item->account->id }})"
                wire:loading.attr="disabled">
                <flux:icon name="link" class="w-3 h-3" />
                Link
            </flux:button>
        @endif
    @endif

    {{-- View External Link --}}
    @if($item->syncStatus?->external_url)
        <flux:button 
            size="sm" 
            variant="ghost"
            href="{{ $item->syncStatus->external_url }}"
            target="_blank">
            <flux:icon name="external-link" class="w-3 h-3" />
        </flux:button>
    @endif

    {{-- Manage Account --}}
    <flux:button 
        size="sm" 
        variant="ghost"
        href="{{ route('sync-accounts.edit', ['accountId' => $item->account->id]) }}">
        <flux:icon name="settings" class="w-3 h-3" />
        Manage Account
    </flux:button>
</div>
