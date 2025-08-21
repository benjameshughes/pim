@props(['showEditLinksModal', 'editingAccountId', 'availableAccounts', 'existingLinks', 'newExternalId'])

@if($showEditLinksModal)
    <flux:modal wire:model="showEditLinksModal" class="max-w-3xl">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    <flux:icon name="pencil" class="w-5 h-5 inline mr-2 text-yellow-600" />
                    Edit Marketplace Links
                </h3>
                <flux:button variant="ghost" wire:click="closeEditLinksModal" class="p-1">
                    <flux:icon name="x-mark" class="w-5 h-5" />
                </flux:button>
            </div>

            @if($editingAccountId)
                @php
                    $account = $availableAccounts->firstWhere('account.id', $editingAccountId)?->account;
                @endphp
                
                @if($account)
                    <div class="mb-6">
                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                            <span>Managing links for:</span>
                            <flux:badge color="yellow" size="sm">
                                {{ ucfirst($account->channel) }} - {{ $account->name }}
                            </flux:badge>
                        </div>
                    </div>

                    {{-- Existing Links Section --}}
                    <div class="space-y-6">
                        @if(!empty($existingLinks))
                            <div>
                                <h4 class="font-medium text-gray-900 mb-4">Existing Links</h4>
                                <div class="space-y-3">
                                    @foreach($existingLinks as $index => $link)
                                        <x-marketplace.modals.link-editor 
                                            :link="$link" 
                                            :index="$index" 
                                            :editing-account-id="$editingAccountId" />
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 border-2 border-dashed border-gray-300 rounded-lg">
                                <flux:icon name="link-slash" class="w-8 h-8 text-gray-400 mx-auto mb-2" />
                                <p class="text-gray-500">No existing links found</p>
                            </div>
                        @endif

                        {{-- Add New Link Section --}}
                        <div class="border-t pt-6">
                            <h4 class="font-medium text-gray-900 mb-4">Add New Link</h4>
                            <div class="space-y-4">
                                <flux:field>
                                    <flux:label>
                                        @switch($account->channel)
                                            @case('shopify')
                                                New Shopify Product ID
                                                @break
                                            @case('ebay')
                                                New eBay Item ID
                                                @break
                                            @case('amazon')
                                                New Amazon ASIN
                                                @break
                                            @default
                                                New External Product ID
                                        @endswitch
                                    </flux:label>
                                    <div class="flex space-x-3">
                                        <flux:input 
                                            wire:model="newExternalId"
                                            placeholder="Enter the {{ $account->channel }} product ID"
                                            class="flex-1" 
                                        />
                                        <flux:button 
                                            variant="filled"
                                            color="green"
                                            wire:click="addNewLink"
                                            wire:loading.attr="disabled"
                                            {{ empty($newExternalId) ? 'disabled' : '' }}>
                                            <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                            Add Link
                                        </flux:button>
                                    </div>
                                    @error('newExternalId') 
                                        <flux:error>{{ $message }}</flux:error> 
                                    @enderror
                                </flux:field>

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
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex justify-between items-center mt-6 pt-4 border-t">
                        <div class="text-sm text-gray-500">
                            {{ count($existingLinks) }} existing {{ Str::plural('link', count($existingLinks)) }}
                        </div>
                        <div class="flex space-x-3">
                            <flux:button 
                                variant="ghost" 
                                wire:click="closeEditLinksModal"
                                wire:loading.attr="disabled">
                                Close
                            </flux:button>
                            <flux:button 
                                variant="filled" 
                                color="blue"
                                wire:click="closeEditLinksModalAndRefresh"
                                wire:loading.attr="disabled">
                                <flux:icon name="check" class="w-4 h-4 mr-2" />
                                Save & Refresh
                            </flux:button>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>
@endif