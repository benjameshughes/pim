<flux:card class="p-6">
    <flux:heading size="lg" class="mb-4">Configure Integration</flux:heading>
    <p class="text-gray-600 mb-6">Enter your credentials and configuration settings.</p>

    <div class="space-y-6">
        <div>
            <flux:label for="displayName">Integration Name</flux:label>
            <flux:input id="displayName" wire:model.live="displayName" placeholder="Enter a name for this integration" class="mt-1" />
            @error('displayName') <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
        </div>

        @foreach($requiredFields as $field)
            <div>
                <flux:label for="credentials.{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</flux:label>

                @php $isSecret = str_contains($field, 'password') || str_contains($field, 'secret') || str_contains($field, 'key'); @endphp

                @if($isSecret)
                    <flux:input type="password" id="credentials.{{ $field }}" wire:model.live="credentials.{{ $field }}" placeholder="Enter {{ str_replace('_', ' ', $field) }}" class="mt-1" />
                @elseif(str_contains($field, 'url'))
                    <flux:input type="url" id="credentials.{{ $field }}" wire:model.live="credentials.{{ $field }}" placeholder="https://example.com" class="mt-1" />
                @else
                    <flux:input id="credentials.{{ $field }}" wire:model.live="credentials.{{ $field }}" placeholder="Enter {{ str_replace('_', ' ', $field) }}" class="mt-1" />
                @endif

                @error("credentials.{$field}") <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
            </div>
        @endforeach

        {{-- Fetch Store Info helper --}}
        <div class="border-t pt-6 mt-6">
            <div class="flex items-start space-x-4">
                <div class="flex-1">
                    <h4 class="text-sm font-medium text-gray-900 mb-1">Auto-Fetch Store Information</h4>
                    <p class="text-sm text-gray-600">
                        Retrieve your store information from the {{ ucfirst($selectedMarketplace) }} API. This helps complete your configuration.
                    </p>

                    @if(!empty($credentials['shop_name']) && !empty($credentials['shop_id']))
                        <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                            <div class="flex items-center">
                                <flux:icon.check-circle class="w-5 h-5 text-green-500 mr-2" />
                                <div class="text-sm">
                                    <div class="font-medium text-green-900">Store information fetched successfully!</div>
                                    <div class="text-green-700 mt-1">
                                        Shop: <strong>{{ $credentials['shop_name'] }}</strong> (ID: {{ $credentials['shop_id'] }})
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex-shrink-0">
                    @if($selectedMarketplace === 'mirakl')
                        <flux:button 
                            wire:click="fetchMiraklStoreInfo"
                            variant="outline"
                            size="sm"
                        >
                            @if($isLoading)
                                <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                                Fetching...
                            @else
                                <flux:icon.arrow-down-tray class="w-4 h-4 mr-2" />
                                Fetch Store Info
                            @endif
                        </flux:button>
                    @elseif($selectedMarketplace === 'shopify')
                        <flux:button 
                            wire:click="fetchShopifyStoreInfo"
                            variant="outline"
                            size="sm"
                        >
                            @if($isLoading)
                                <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                                Fetching...
                            @else
                                <flux:icon.arrow-down-tray class="w-4 h-4 mr-2" />
                                Fetch Store Info
                            @endif
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between">
        <flux:button variant="ghost" wire:click="back">
            <flux:icon.chevron-left class="w-4 h-4 mr-2" />
            Back
        </flux:button>

        <flux:button 
            wire:click="$parent.completeWizard"
            variant="primary"
            :disabled="!$storeInfoFetched"
            title="Fetch store info to enable"
        >
            <flux:icon.check class="w-4 h-4 mr-2" />
            Create Integration
        </flux:button>
    </div>
</flux:card>
