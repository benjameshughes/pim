@props(['link', 'index', 'editingAccountId'])

<div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0 mr-4">
            <div class="flex items-center space-x-2 mb-2">
                <flux:badge 
                    :color="$link['type'] === 'marketplace_link' ? 'blue' : 'gray'" 
                    size="sm">
                    {{ $link['type'] === 'marketplace_link' ? 'MarketplaceLink' : 'SyncStatus' }}
                </flux:badge>
                
                <flux:badge 
                    :color="match($link['link_status']) {
                        'linked', 'synced' => 'green',
                        'pending' => 'yellow',
                        'failed' => 'red',
                        default => 'gray'
                    }" 
                    size="sm">
                    {{ ucfirst($link['link_status']) }}
                </flux:badge>

                @if($link['color_filter'])
                    <flux:badge color="purple" size="sm">
                        Color: {{ $link['color_filter'] }}
                    </flux:badge>
                    
                    {{-- Quick unlink for color-specific links --}}
                    <flux:button 
                        size="xs"
                        variant="ghost"
                        color="purple"
                        wire:click="unlinkShopifyColor({{ $editingAccountId }}, '{{ $link['color_filter'] }}')"
                        wire:confirm="Unlink {{ $link['color_filter'] }} color from this Shopify product?"
                        wire:loading.attr="disabled"
                        title="Unlink this color">
                        <flux:icon name="link-slash" class="w-3 h-3" />
                    </flux:button>
                @endif
            </div>
            
            <div class="space-y-2">
                <flux:field>
                    <flux:label class="text-sm">External Product ID</flux:label>
                    <div class="flex space-x-2">
                        <flux:input 
                            wire:model="existingLinks.{{ $index }}.editable_external_id"
                            placeholder="Enter external product ID"
                            class="flex-1" 
                        />
                        <flux:button 
                            size="sm"
                            variant="filled"
                            color="blue"
                            wire:click="updateLinkExternalId({{ $index }})"
                            wire:loading.attr="disabled">
                            <flux:icon name="check" class="w-4 h-4" />
                        </flux:button>
                    </div>
                </flux:field>

                @if($link['external_variant_id'])
                    <div class="text-sm text-gray-500">
                        <span class="font-medium">External Variant ID:</span>
                        <code class="ml-1 text-xs bg-gray-200 px-1 py-0.5 rounded">{{ $link['external_variant_id'] }}</code>
                    </div>
                @endif

                @if($link['linked_at'])
                    <div class="text-sm text-gray-500">
                        <span class="font-medium">Linked:</span>
                        {{ \Carbon\Carbon::parse($link['linked_at'])->format('M j, Y g:i A') }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Remove Link Button --}}
        <div class="flex-shrink-0">
            <flux:button 
                size="sm"
                variant="ghost"
                color="red"
                wire:click="removeLinkById({{ $index }})"
                wire:confirm="Are you sure you want to remove this link?"
                wire:loading.attr="disabled">
                <flux:icon name="trash" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>
</div>