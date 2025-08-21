@props(['showLinkingModal', 'linkingAccountId', 'availableAccounts', 'externalProductId'])

@if($showLinkingModal)
    <flux:modal wire:model="showLinkingModal" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Link to Existing Listing</h3>
                <flux:button variant="ghost" wire:click="closeLinkingModal" class="p-1">
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </flux:button>
            </div>

            @if($linkingAccountId)
                @php
                    $account = $availableAccounts->firstWhere('account.id', $linkingAccountId)?->account;
                @endphp
                
                @if($account)
                    <div class="mb-4">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <span>Linking to:</span>
                            <flux:badge color="blue" size="sm">
                                {{ ucfirst($account->channel) }} - {{ $account->name }}
                            </flux:badge>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <flux:field>
                                <flux:label>
                                    @switch($account->channel)
                                        @case('shopify')
                                            Shopify Product ID
                                            @break
                                        @case('ebay')
                                            eBay Item ID
                                            @break
                                        @case('amazon')
                                            Amazon ASIN
                                            @break
                                        @default
                                            External Product ID
                                    @endswitch
                                </flux:label>
                                <flux:input 
                                    wire:model="externalProductId"
                                    placeholder="Enter the {{ $account->channel }} product ID"
                                    class="w-full" 
                                />
                                @error('externalProductId') 
                                    <flux:error>{{ $message }}</flux:error> 
                                @enderror
                            </flux:field>
                        </div>

                        <div class="text-xs text-gray-500">
                            @switch($account->channel)
                                @case('shopify')
                                    Find this in your Shopify admin: Products > [Product] > URL shows the ID
                                    @break
                                @case('ebay')
                                    This is your eBay item number found in the listing URL
                                    @break
                                @case('amazon')
                                    The 10-character Amazon Standard Identification Number (ASIN)
                                    @break
                                @default
                                    The unique identifier for this product in {{ $account->channel }}
                            @endswitch
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <flux:button variant="ghost" wire:click="closeLinkingModal">
                            Cancel
                        </flux:button>
                        <flux:button 
                            variant="filled" 
                            color="blue"
                            wire:click="linkToMarketplace"
                            wire:loading.attr="disabled">
                            <flux:icon name="link" class="w-4 h-4 mr-2" />
                            Link Product
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
@endif