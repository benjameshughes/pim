<div class="space-y-6" xmlns:flux="http://www.w3.org/1999/html">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Marketplace Identifiers</h1>
            <p class="text-gray-600">Manage marketplace account details and identifier configurations</p>
        </div>
        
        <div class="flex items-center space-x-3">
            {{-- Add New Integration Button --}}
            <flux:button 
                href="/marketplace/add-integration" 
                variant="primary"
                size="sm"
            >
                <flux:icon.plus class="w-4 h-4 mr-2" />
                Add New Integration
            </flux:button>
            
            <flux:badge color="green" size="sm">
                {{ $this->stats['total_accounts'] }} Active Accounts
            </flux:badge>
            <flux:badge color="blue" size="sm">
                {{ $this->stats['configured_accounts'] }} Configured
            </flux:badge>
            @if ($this->stats['pending_setup'] > 0)
                <flux:badge color="amber" size="sm">
                    {{ $this->stats['pending_setup'] }} Need Setup
                </flux:badge>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon.store class="h-8 w-8 text-blue-600" />
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $this->stats['total_accounts'] }}</div>
                    <div class="text-sm text-gray-600">Total Accounts</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon.sparkles class="h-8 w-8 text-green-600" />
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $this->stats['configured_accounts'] }}</div>
                    <div class="text-sm text-gray-600">Configured</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon.sliders-horizontal class="h-8 w-8 text-amber-600" />
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $this->stats['pending_setup'] }}</div>
                    <div class="text-sm text-gray-600">Pending Setup</div>
                </div>
            </div>
        </flux:card>

        <flux:card class="p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <flux:icon.grid-3x3 class="h-8 w-8 text-purple-600" />
                </div>
                <div class="ml-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $this->stats['channels'] }}</div>
                    <div class="text-sm text-gray-600">Channels</div>
                </div>
            </div>
        </flux:card>
    </div>

    {{-- Main Content --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Accounts List --}}
        <div class="lg:col-span-1">
            <flux:card>
                <flux:card.header>
                    <flux:heading>Marketplace Accounts</flux:heading>
                </flux:card.header>

                <div class="divide-y divide-gray-200">
                    @forelse ($this->accounts as $account)
                        <div class="p-4 hover:bg-gray-50 cursor-pointer transition-colors"
                             wire:click="selectAccount({{ $account->id }})"
                             class="{{ $selectedAccount === $account->id ? 'bg-blue-50 border-l-4 border-blue-500' : '' }}">
                            
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-2">
                                        <flux:badge :color="match($account->channel) {
                                            'shopify' => 'green',
                                            'ebay' => 'red', 
                                            'amazon' => 'orange',
                                            'mirakl' => 'purple',
                                            default => 'gray'
                                        }" size="sm">
                                            {{ ucfirst($account->channel) }}
                                        </flux:badge>
                                        
                                        @if ($account->isIdentifierSetupComplete())
                                            <flux:icon.sparkles class="h-4 w-4 text-green-500" />
                                        @else
                                            <flux:icon.sliders-horizontal class="h-4 w-4 text-amber-500" />
                                        @endif
                                    </div>
                                    
                                    <div class="mt-1">
                                        <h3 class="text-sm font-medium text-gray-900">
                                            {{ $account->display_name }}
                                        </h3>
                                        
                                        @php
                                            $details = $account->getMarketplaceDetails();
                                            $marketplaceName = $details['shop_name'] ?? $details['name'] ?? null;
                                        @endphp
                                        @if ($marketplaceName)
                                            <p class="text-xs text-gray-600">
                                                {{ $marketplaceName }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex-shrink-0">
                                    @if ($account->isIdentifierSetupComplete())
                                        <flux:badge color="green" size="xs">Ready</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="xs">Setup</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <flux:icon.store class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-4 text-sm font-medium text-gray-900">No accounts found</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                Create marketplace accounts to manage identifiers.
                            </p>
                        </div>
                    @endforelse
                </div>
            </flux:card>
        </div>

        {{-- Account Details --}}
        <div class="lg:col-span-2">
            @if ($selectedAccount && $this->selectedAccountData)
                @php
                    $data = $this->selectedAccountData;
                    $account = $data['account'];
                    $displayInfo = $data['display_info'];
                    $identifiers = $data['identifiers'];
                    $details = $data['details'];
                    $identifierTypes = $data['identifier_types'];
                @endphp

                <flux:card>
                    <flux:card.header>
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:heading>{{ $displayInfo['account_name'] }}</flux:heading>
                                <p class="text-sm text-gray-600">{{ ucfirst($displayInfo['channel']) }} Account</p>
                            </div>
                            
                            <div class="flex items-center space-x-3">
                                {{-- Delete Button --}}
                                <flux:button size="sm" wire:click="deleteAccount('{{$account->id}}')">Delete Account</flux:button>
                                {{-- Mirakl Operator Test Button --}}
                                @if ($account->channel === 'mirakl' && $displayInfo['setup_complete'])
                                    <flux:button icon="cpu" color="purple" size="sm"
                                                wire:click="testMiraklOperator({{ $account->id }})"
                                                wire:loading.attr="disabled">
                                        Test Connection
                                    </flux:button>
                                @endif
                                
                                @if ($displayInfo['setup_complete'])
                                    <flux:button color="blue" size="sm" icon="waves"
                                                wire:click="refreshIdentifiers({{ $account->id }})">
                                        Refresh
                                    </flux:button>
                                @else
                                    <flux:button color="green" size="sm" 
                                                wire:click="getAccountInfo({{ $account->id }})">
                                        <flux:icon.information-circle class="h-4 w-4" />
                                        Get Account Info
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    </flux:card.header>

                    @if ($displayInfo['setup_complete'])
                        <div class="space-y-6 p-6">
                            {{-- Marketplace Details --}}
                            @if (!empty($details))
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Marketplace Details</h3>
                                    
                                    {{-- Mirakl Operator Specific Details --}}
                                    @if ($account->channel === 'mirakl' && !empty($details['operator_details']))
                                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                                            <div class="flex items-center mb-3">
                                                <flux:icon.store class="h-5 w-5 text-purple-600 mr-2" />
                                                <h4 class="text-lg font-medium text-purple-900">{{ $details['operator_details']['operator_name'] }}</h4>
                                            </div>
                                            
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div>
                                                    <label class="text-sm font-medium text-purple-700">Operator Type</label>
                                                    <p class="text-sm text-purple-900">{{ ucfirst($details['operator_details']['operator_type']) }}</p>
                                                </div>
                                                
                                                <div>
                                                    <label class="text-sm font-medium text-purple-700">Platform</label>
                                                    <p class="text-sm text-purple-900">{{ $details['operator_details']['platform'] }}</p>
                                                </div>
                                                
                                                <div>
                                                    <label class="text-sm font-medium text-purple-700">Currency</label>
                                                    <p class="text-sm text-purple-900">{{ $details['operator_details']['currency'] }}</p>
                                                </div>
                                                
                                                @if (!empty($details['operator_details']['categories']))
                                                    <div>
                                                        <label class="text-sm font-medium text-purple-700">Categories</label>
                                                        <p class="text-sm text-purple-900">{{ $details['operator_details']['categories'] }} Available</p>
                                                    </div>
                                                @endif
                                            </div>
                                            
                                            {{-- Operator Requirements --}}
                                            @if (!empty($details['special_requirements']))
                                                <div class="mt-4">
                                                    <label class="text-sm font-medium text-purple-700">Special Requirements</label>
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        @foreach ($details['special_requirements'] as $requirement)
                                                            <flux:badge color="purple" size="sm">{{ $requirement }}</flux:badge>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    {{-- Standard Marketplace Details --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @php
                                            $marketplaceName = $details['shop_name'] ?? $details['name'] ?? $details['operator_details']['operator_name'] ?? null;
                                        @endphp
                                        @if ($marketplaceName)
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Name</label>
                                                <p class="text-sm text-gray-900">{{ $marketplaceName }}</p>
                                            </div>
                                        @endif

                                        @if (!empty($details['shop_domain'] ?? $details['domain']))
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Domain</label>
                                                <p class="text-sm text-gray-900">{{ $details['shop_domain'] ?? $details['domain'] }}</p>
                                            </div>
                                        @endif

                                        @if (!empty($details['plan']))
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Plan</label>
                                                <p class="text-sm text-gray-900">{{ $details['plan'] }}</p>
                                            </div>
                                        @endif

                                        @if (!empty($details['currency']) && $account->channel !== 'mirakl')
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Currency</label>
                                                <p class="text-sm text-gray-900">{{ $details['currency'] }}</p>
                                            </div>
                                        @endif

                                        @if (!empty($details['country']))
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Country</label>
                                                <p class="text-sm text-gray-900">{{ $details['country'] }}</p>
                                            </div>
                                        @endif

                                        @if (isset($details['products_count']))
                                            <div>
                                                <label class="text-sm font-medium text-gray-700">Products</label>
                                                <p class="text-sm text-gray-900">{{ number_format($details['products_count']) }}</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Mirakl Validation Rules --}}
                            @if ($account->channel === 'mirakl' && !empty($details['validation_rules']))
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Validation Rules</h3>
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                                        <div class="flex items-center mb-3">
                                            <flux:icon.alert-circle class="h-5 w-5 text-amber-600 mr-2" />
                                            <h4 class="text-sm font-medium text-amber-900">Operator-Specific Requirements</h4>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach ($details['validation_rules'] as $field => $rules)
                                                <div class="text-sm">
                                                    <span class="font-medium text-amber-900">{{ ucfirst(str_replace('_', ' ', $field)) }}:</span>
                                                    <span class="text-amber-800">{{ implode(', ', (array) $rules) }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Identifier Types --}}
                            @if (!empty($identifierTypes) && is_array($identifierTypes))
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Available Identifier Types</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        @foreach ($identifierTypes as $type => $description)
                                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                                <h4 class="text-sm font-medium text-gray-900">{{ $type }}</h4>
                                                <p class="text-xs text-gray-600 mt-1">
                                                    @if (is_string($description))
                                                        {{ $description }}
                                                    @elseif (is_array($description))
                                                        {{ implode(', ', $description) }}
                                                    @else
                                                        {{ json_encode($description) }}
                                                    @endif
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Setup Info --}}
                            @if (!empty($displayInfo['setup_date']))
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="text-sm font-medium text-gray-900">Setup Information</h3>
                                    <p class="text-xs text-gray-600 mt-1">
                                        Identifiers configured on {{ \Carbon\Carbon::parse($displayInfo['setup_date'])->format('M j, Y \a\t g:i A') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-8 text-center">
                            <flux:icon.sliders-horizontal class="mx-auto h-12 w-12 text-amber-400" />
                            <h3 class="mt-4 text-lg font-medium text-gray-900">Identifiers Not Configured</h3>
                            <p class="mt-2 text-sm text-gray-500">
                                Fetch and store marketplace account details for this integration.
                            </p>
                            <div class="mt-6">
                                <flux:button color="green" 
                                            wire:click="getAccountInfo({{ $account->id }})">
                                    <flux:icon.information-circle class="h-4 w-4" />
                                    Get Account Info
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </flux:card>
            @else
                <flux:card class="p-8 text-center">
                    <flux:icon.store class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-4 text-lg font-medium text-gray-900">Select an Account</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Choose a marketplace account from the left to view and manage its identifiers.
                    </p>
                </flux:card>
            @endif
        </div>
    </div>
</div>
