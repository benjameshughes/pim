<div class="max-w-4xl mx-auto p-6">
    {{-- 🎯 WIZARD HEADER --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Add New Marketplace Integration</h1>
        <p class="text-gray-600">Connect your store to a new marketplace in just a few simple steps.</p>
        
        {{-- Progress Bar --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">{{ $this->stepTitle }}</span>
                <span class="text-sm text-gray-500">Step {{ $currentStep }} of {{ self::TOTAL_STEPS }}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                     style="width: {{ $this->progressPercentage }}%"></div>
            </div>
        </div>
    </div>

    {{-- 🏪 STEP 1: MARKETPLACE SELECTION --}}
    @if($currentStep === 1)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-4">Choose Your Marketplace</flux:heading>
            <p class="text-gray-600 mb-6">Select the marketplace you want to integrate with your product catalog.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($availableMarketplaces as $marketplace)
                    <button wire:click="selectMarketplace('{{ $marketplace['type'] }}')"
                            class="p-4 border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition-all duration-200 text-left group">
                        <div class="flex items-start space-x-4">
                            {{-- Marketplace Logo --}}
                            <div class="flex-shrink-0">
                                @if($marketplace['logo_url'])
                                    <img src="{{ $marketplace['logo_url'] }}" alt="{{ $marketplace['name'] }}" class="w-12 h-12 object-contain">
                                @else
                                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                                        <flux:icon.store class="w-6 h-6 text-gray-500" />
                                    </div>
                                @endif
                            </div>
                            
                            {{-- Marketplace Info --}}
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-blue-600">
                                    {{ $marketplace['name'] }}
                                </h3>
                                <p class="text-gray-600 text-sm mt-1">{{ $marketplace['description'] }}</p>
                                
                                @if($marketplace['has_operators'])
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-2">
                                        Multiple Operators Available
                                    </span>
                                @endif
                            </div>

                            {{-- Arrow Icon --}}
                            <div class="flex-shrink-0">
                                <flux:icon.chevron-right class="w-5 h-5 text-gray-400 group-hover:text-blue-500" />
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        </flux:card>
    @endif

    {{-- ⚙️ STEP 2: CONFIGURATION STEP --}}
    @if($currentStep === 2)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-4">Configure Integration</flux:heading>
            <p class="text-gray-600 mb-6">Enter your credentials and configuration settings.</p>

            <div class="space-y-6">
                {{-- Display Name --}}
                <div>
                    <flux:label for="displayName">Integration Name</flux:label>
                    <flux:input 
                        id="displayName"
                        wire:model="displayName" 
                        placeholder="Enter a name for this integration"
                        class="mt-1"
                    />
                    @error('displayName') <flux:error class="mt-1">{{ $message }}</flux:error> @enderror
                </div>

                {{-- Dynamic Credentials Fields --}}
                @foreach($this->requiredFields as $field)
                    <div>
                        <flux:label for="credentials.{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</flux:label>
                        
                        @if(str_contains($field, 'password') || str_contains($field, 'secret') || str_contains($field, 'key'))
                            <flux:input 
                                type="password"
                                id="credentials.{{ $field }}"
                                wire:model.live="credentials.{{ $field }}" 
                                placeholder="Enter {{ str_replace('_', ' ', $field) }}"
                                class="mt-1"
                            />
                        @elseif(str_contains($field, 'url'))
                            <flux:input 
                                type="url"
                                id="credentials.{{ $field }}"
                                wire:model.live="credentials.{{ $field }}" 
                                placeholder="https://example.com"
                                class="mt-1"
                            />
                        @elseif($field === 'environment')
                            <flux:select id="credentials.{{ $field }}" wire:model.live="credentials.{{ $field }}" class="mt-1">
                                <flux:select.option value="">Select environment</flux:select.option>
                                <flux:select.option value="SANDBOX">Sandbox (Testing)</flux:select.option>
                                <flux:select.option value="PRODUCTION">Production (Live)</flux:select.option>
                            </flux:select>
                        @else
                            <flux:input 
                                id="credentials.{{ $field }}"
                                wire:model.live="credentials.{{ $field }}" 
                                placeholder="Enter {{ str_replace('_', ' ', $field) }}"
                                class="mt-1"
                            />
                        @endif
                        
                        @error("credentials.{$field}") 
                            <flux:error class="mt-1">{{ $message }}</flux:error> 
                        @enderror
                    </div>
                @endforeach

                {{-- 🔍 MIRAKL: Fetch Store Info Button --}}
                @if($selectedMarketplace === 'mirakl')
                    <div class="border-t pt-6 mt-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-1">
                                <h4 class="text-sm font-medium text-gray-900 mb-1">Auto-Fetch Store Information</h4>
                                <p class="text-sm text-gray-600">
                                    Click below to automatically retrieve your shop name and shop ID from the Mirakl API.
                                    This information is required for API calls.
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
                                <flux:button 
                                    wire:click="fetchMiraklStoreInfo" 
                                    :disabled="!$this->canFetchMiraklInfo"
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
                            </div>
                        </div>
                        
                        @if(!$this->canFetchMiraklInfo && !$isLoading)
                            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <div class="flex items-center">
                                    <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-500 mr-2" />
                                    <div class="text-sm text-amber-700">
                                        Please enter your Base URL and API Key above before fetching store information.
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Navigation --}}
            <div class="mt-6 flex justify-between">
                <flux:button variant="ghost" wire:click="previousStep">
                    <flux:icon.chevron-left class="w-4 h-4 mr-2" />
                    Back
                </flux:button>
                
                <flux:button wire:click="updateConfiguration" variant="primary">
                    Continue
                    <flux:icon.chevron-right class="w-4 h-4 ml-2" />
                </flux:button>
            </div>
        </flux:card>
    @endif

    {{-- 🧪 STEP 3: CONNECTION TEST --}}
    @if($currentStep === 3)
        <flux:card class="p-6">
            <flux:heading size="lg" class="mb-4">Test Connection</flux:heading>
            <p class="text-gray-600 mb-6">Verify that your credentials work correctly by testing the connection.</p>

            {{-- Configuration Summary --}}
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h4 class="font-medium text-gray-900 mb-2">Configuration Summary</h4>
                <div class="space-y-1 text-sm">
                    <div><span class="text-gray-600">Marketplace:</span> <span class="font-medium">{{ $selectedMarketplace }}</span></div>
                    <div><span class="text-gray-600">Integration Name:</span> <span class="font-medium">{{ $displayName }}</span></div>
                    @if($selectedOperator)
                        <div><span class="text-gray-600">Operator:</span> <span class="font-medium">{{ $selectedOperator }}</span></div>
                    @endif
                </div>
            </div>

            {{-- Connection Test Button --}}
            @if(!$connectionTestResult)
                <flux:button 
                    wire:click="testConnection"
                    variant="primary"
                    :loading="$isLoading"
                    class="w-full mb-4"
                >
                    <flux:icon.wifi class="w-4 h-4 mr-2" />
                    Test Connection
                </flux:button>
            @endif

            {{-- Connection Test Results --}}
            @if($connectionTestResult)
                <div class="mb-6">
                    @if($connectionTestResult['success'])
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <flux:icon.check-circle class="w-5 h-5 text-green-600 mr-2" />
                                <h4 class="font-medium text-green-900">Connection Successful!</h4>
                            </div>
                            <p class="text-green-700 text-sm mt-1">{{ $connectionTestResult['message'] }}</p>
                            
                            @if($connectionTestResult['details'])
                                <div class="mt-3 text-sm">
                                    @foreach($connectionTestResult['details'] as $key => $value)
                                        @if(is_string($value))
                                            <div class="text-green-600">
                                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span> {{ $value }}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <flux:icon.x-circle class="w-5 h-5 text-red-600 mr-2" />
                                <h4 class="font-medium text-red-900">Connection Failed</h4>
                            </div>
                            <p class="text-red-700 text-sm mt-1">{{ $connectionTestResult['message'] }}</p>
                            
                            @if($connectionTestResult['recommendations'])
                                <div class="mt-3">
                                    <p class="text-sm font-medium text-red-900">Recommendations:</p>
                                    <ul class="list-disc list-inside text-sm text-red-700 mt-1">
                                        @foreach($connectionTestResult['recommendations'] as $recommendation)
                                            <li>{{ $recommendation }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                        
                        {{-- Retry Button --}}
                        <flux:button 
                            wire:click="testConnection"
                            variant="primary"
                            :loading="$isLoading"
                            class="w-full mt-4"
                        >
                            <flux:icon.arrow-path class="w-4 h-4 mr-2" />
                            Retry Connection Test
                        </flux:button>
                    @endif
                </div>
            @endif

            {{-- Navigation --}}
            <div class="flex justify-between">
                <flux:button variant="ghost" wire:click="previousStep">
                    <flux:icon.chevron-left class="w-4 h-4 mr-2" />
                    Back
                </flux:button>
                
                @if($connectionTestPassed)
                    <flux:button 
                        wire:click="completeWizard"
                        variant="primary"
                        :loading="$isLoading"
                    >
                        <flux:icon.check class="w-4 h-4 mr-2" />
                        Create Integration
                    </flux:button>
                @endif
            </div>
        </flux:card>
    @endif

    {{-- 🔄 RESET WIZARD --}}
    <div class="mt-6 text-center">
        <flux:button variant="ghost" wire:click="resetWizard" class="text-gray-500 hover:text-gray-700">
            <flux:icon.arrow-path class="w-4 h-4 mr-2" />
            Start Over
        </flux:button>
    </div>
</div>