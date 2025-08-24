<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-900">Add New Sync Account</h1>
            <p class="mt-1 text-sm text-gray-600">Connect your store to a marketplace or sales channel</p>
        </div>

        <form wire:submit="testConnection" class="p-6 space-y-6">
            <!-- Channel Selection -->
            <div>
                <flux:field>
                    <flux:label>Integration Type</flux:label>
                    <flux:select wire:model.live="channel" placeholder="Choose an integration...">
                        @foreach($this->getChannelOptions() as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="channel" />
                </flux:field>
            </div>

            @if($channel)
                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Display Name</flux:label>
                        <flux:input wire:model.blur="display_name" placeholder="e.g., My Shopify Store" />
                        <flux:error name="display_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Marketplace Identifier</flux:label>
                        <flux:input wire:model.blur="marketplace_subtype" placeholder="Store identifier" />
                        <flux:error name="marketplace_subtype" />
                    </flux:field>
                </div>

                <!-- Channel-specific fields -->
                @if($channel === 'shopify')
                    <div class="bg-blue-50 rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-blue-900 flex items-center">
                            <flux:icon name="storefront" class="w-5 h-5 mr-2" />
                            Shopify Configuration
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Shop Domain</flux:label>
                                <flux:input 
                                    wire:model.blur="shop_domain" 
                                    placeholder="your-store.myshopify.com"
                                    type="url"
                                />
                                <flux:error name="shop_domain" />
                                <flux:description>Your Shopify store domain (e.g., example.myshopify.com)</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>API Version</flux:label>
                                <flux:input wire:model="api_version" placeholder="2025-07" />
                                <flux:error name="api_version" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Access Token</flux:label>
                            <flux:input 
                                wire:model="access_token" 
                                type="password"
                                placeholder="shpat_..."
                            />
                            <flux:error name="access_token" />
                            <flux:description>Your Shopify Admin API access token</flux:description>
                        </flux:field>
                    </div>
                @endif

                @if($channel === 'ebay')
                    <div class="bg-yellow-50 rounded-lg p-6 space-y-4">
                        <h3 class="text-lg font-medium text-yellow-900 flex items-center">
                            <flux:icon name="tag" class="w-5 h-5 mr-2" />
                            eBay Configuration
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:field>
                                <flux:label>Client ID (App ID)</flux:label>
                                <flux:input wire:model="client_id" placeholder="YourAppI-d..." />
                                <flux:error name="client_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Client Secret</flux:label>
                                <flux:input wire:model="client_secret" type="password" placeholder="XXXXXXXX" />
                                <flux:error name="client_secret" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Developer ID</flux:label>
                                <flux:input wire:model="dev_id" placeholder="your-dev-id" />
                                <flux:error name="dev_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Environment</flux:label>
                                <flux:select wire:model="environment">
                                    <flux:select.option value="SANDBOX">Sandbox (Testing)</flux:select.option>
                                    <flux:select.option value="PRODUCTION">Production (Live)</flux:select.option>
                                </flux:select>
                                <flux:error name="environment" />
                            </flux:field>
                        </div>
                    </div>
                @endif

                <!-- Test Results -->
                @if($hasTestedConnection)
                    <div class="bg-gray-50 rounded-lg p-6">
                        @if($connectionSuccessful)
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <flux:icon name="check-circle" class="w-6 h-6 text-green-500" />
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-green-900">Connection Successful!</h4>
                                    
                                    @if(!empty($testResults['shop_info']))
                                        <div class="mt-3 space-y-2">
                                            <p class="text-sm text-gray-700">
                                                <strong>Store:</strong> {{ $testResults['shop_info']['name'] ?? 'N/A' }}
                                            </p>
                                            @if(!empty($testResults['shop_info']['domain']))
                                                <p class="text-sm text-gray-700">
                                                    <strong>Domain:</strong> {{ $testResults['shop_info']['domain'] }}
                                                </p>
                                            @endif
                                            @if(!empty($testResults['shop_info']['plan_name']))
                                                <p class="text-sm text-gray-700">
                                                    <strong>Plan:</strong> {{ $testResults['shop_info']['plan_name'] }}
                                                </p>
                                            @endif
                                            @if(!empty($testResults['shop_info']['currency']))
                                                <p class="text-sm text-gray-700">
                                                    <strong>Currency:</strong> {{ $testResults['shop_info']['currency'] }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    @if(!empty($testResults['message']))
                                        <p class="mt-2 text-sm text-gray-600">{{ $testResults['message'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0">
                                    <flux:icon name="x-circle" class="w-6 h-6 text-red-500" />
                                </div>
                                <div class="flex-1">
                                    <h4 class="text-lg font-medium text-red-900">Connection Failed</h4>
                                    <p class="mt-1 text-sm text-red-700">{{ $testError }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                    <a href="{{ route('sync-accounts.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                        ← Back to Sync Accounts
                    </a>
                    
                    <div class="flex items-center space-x-3">
                        @if(!$hasTestedConnection || !$connectionSuccessful)
                            <flux:button 
                                type="submit" 
                                variant="primary"
                                :loading="$isLoading"
                            >
                                <flux:icon name="wifi" class="w-4 h-4 mr-2" />
                                Test Connection
                            </flux:button>
                        @endif

                        @if($hasTestedConnection && !$connectionSuccessful)
                            <flux:button 
                                type="submit" 
                                variant="outline"
                                :loading="$isLoading"
                            >
                                <flux:icon name="refresh-cw" class="w-4 h-4 mr-2" />
                                Retry Test
                            </flux:button>
                        @endif

                        @if($hasTestedConnection && $connectionSuccessful)
                            <flux:button 
                                wire:click="create" 
                                variant="primary"
                                :loading="$isLoading"
                            >
                                <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                Create Sync Account
                            </flux:button>
                        @endif
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <flux:icon name="link" class="w-12 h-12 mx-auto text-gray-400 mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Choose an Integration</h3>
                    <p class="text-gray-600">Select an integration type above to get started</p>
                </div>
            @endif
        </form>
    </div>
</div>

@script
<script>
    // Auto-focus first input when channel changes
    $wire.on('channel-changed', () => {
        setTimeout(() => {
            const firstInput = document.querySelector('input:not([type="hidden"])');
            if (firstInput) firstInput.focus();
        }, 100);
    });
</script>
@endscript