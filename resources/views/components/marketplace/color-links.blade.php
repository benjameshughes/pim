@props(['item'])

@if($item->account->channel === 'shopify' && $item->hasColorLinks)
    <div class="text-sm">
        <div class="flex items-center justify-between">
            <span class="text-purple-600 font-medium">Color Links:</span>
            <flux:button 
                size="xs"
                variant="ghost"
                color="purple"
                wire:click="showEditLinksModal({{ $item->account->id }})"
                wire:loading.attr="disabled"
                title="Edit color links">
                <flux:icon name="pencil" class="w-3 h-3" />
            </flux:button>
        </div>
        <div class="flex flex-wrap gap-1 mt-1">
            @foreach($item->colorLinks as $colorLink)
                <div class="inline-flex items-center">
                    <flux:badge color="purple" size="xs">
                        {{ $colorLink['color'] }}
                    </flux:badge>
                    <flux:button 
                        size="xs"
                        variant="ghost"
                        color="red"
                        wire:click="unlinkShopifyColor({{ $item->account->id }}, '{{ $colorLink['color'] }}')"
                        wire:confirm="Unlink {{ $colorLink['color'] }} from Shopify?"
                        wire:loading.attr="disabled"
                        class="ml-1"
                        title="Unlink {{ $colorLink['color'] }}">
                        <flux:icon name="x-mark" class="w-2 h-2" />
                    </flux:button>
                </div>
            @endforeach
        </div>
    </div>
@endif