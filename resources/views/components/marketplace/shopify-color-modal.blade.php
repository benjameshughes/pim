@props(['showShopifyColorModal', 'linkingAccountId', 'availableAccounts', 'discoveryLoading', 'colorMappings', 'shopifyProducts'])

@if($showShopifyColorModal)
    <flux:modal wire:model="showShopifyColorModal" class="max-w-4xl">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <flux:icon name="swatch" class="w-5 h-5 inline mr-2 text-purple-600" />
                    Link Product Colors to Shopify
                </h3>
                <flux:button variant="ghost" wire:click="closeShopifyColorModal" class="p-1">
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </flux:button>
            </div>

            @if($linkingAccountId)
                @php
                    $account = $availableAccounts->firstWhere('account.id', $linkingAccountId)?->account;
                @endphp
                
                @if($account)
                    <div class="mb-6">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <span>Linking to:</span>
                            <flux:badge color="purple" size="sm">
                                {{ ucfirst($account->channel) }} - {{ $account->name }}
                            </flux:badge>
                        </div>
                    </div>

                    {{-- Discovery Status --}}
                    @if($discoveryLoading)
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <div class="flex items-center">
                                <flux:icon name="arrow-path" class="w-5 h-5 animate-spin text-blue-600 mr-2" />
                                <span class="text-blue-700">Discovering Shopify products and generating smart suggestions...</span>
                            </div>
                        </div>
                    @endif

                    {{-- Color Mapping Section --}}
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-medium text-gray-900 mb-3">Map Product Colors to Shopify Products</h4>
                            <p class="text-sm text-gray-500 mb-4">
                                Each color variant will be linked to its corresponding Shopify product ID. Leave dropdowns empty to skip colors.
                            </p>

                            {{-- Global Error Display --}}
                            @error('colorMappings')
                                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                                    <div class="flex items-center">
                                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-500 mr-2" />
                                        <span class="text-red-700 text-sm">{{ $message }}</span>
                                    </div>
                                </div>
                            @enderror

                            <div class="space-y-4">
                                @foreach($colorMappings as $color => $shopifyProductId)
                                    <x-marketplace.modals.color-mapping-row 
                                        :color="$color"
                                        :shopify-product-id="$shopifyProductId"
                                        :shopify-products="$shopifyProducts"
                                        :color-mappings="$colorMappings" />
                                @endforeach
                            </div>
                        </div>

                        {{-- Refresh Products Section --}}
                        @if(!$discoveryLoading)
                            <div class="border-t pt-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900">Product Discovery</h4>
                                        <p class="text-sm text-gray-500">
                                            @if(!empty($shopifyProducts))
                                                Found {{ count($shopifyProducts) }} Shopify products
                                            @else
                                                No products discovered yet
                                            @endif
                                        </p>
                                    </div>
                                    <flux:button 
                                        size="sm" 
                                        variant="ghost"
                                        wire:click="discoverShopifyProducts"
                                        wire:loading.attr="disabled">
                                        <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                                        {{ empty($shopifyProducts) ? 'Discover Products' : 'Refresh Products' }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                        <flux:button variant="ghost" wire:click="closeShopifyColorModal">
                            Cancel
                        </flux:button>
                        <flux:button 
                            variant="filled" 
                            color="purple"
                            wire:click="linkShopifyColors"
                            wire:loading.attr="disabled">
                            <flux:icon name="link" class="w-4 h-4 mr-2" />
                            Link Colors
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
@endif