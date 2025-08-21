<div class="max-w-7xl mx-auto space-y-6">
    {{-- ✨ HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">🏷️ Bulk Marketplace Attributes</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Manage marketplace attributes for {{ count($selectedProducts) }} selected products
            </p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button wire:click="returnToBulkOperations" variant="ghost" icon="arrow-left">
                Back to Bulk Operations
            </flux:button>
            
            @if ($operationResults || $validationSummary || $readinessAnalysis)
                <flux:button wire:click="resetOperation" variant="ghost" icon="arrow-path">
                    Reset
                </flux:button>
            @endif
        </div>
    </div>

    {{-- 📋 SELECTED PRODUCTS SUMMARY --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Selected Products ({{ count($selectedProducts) }})</h3>
        
        @if (!empty($selectedProducts))
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach (array_slice($selectedProducts, 0, 6) as $product)
                    <div class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $product['name'] }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $product['sku'] }} • {{ $product['variants_count'] }} variants
                            </div>
                        </div>
                        <flux:badge :color="$product['status'] === 'Active' ? 'green' : 'gray'" size="sm">
                            {{ $product['status'] }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
            
            @if (count($selectedProducts) > 6)
                <div class="mt-4 text-center">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        +{{ count($selectedProducts) - 6 }} more products
                    </span>
                </div>
            @endif
        @endif
    </div>

    {{-- 🏪 MARKETPLACE SELECTION --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Marketplace Selection</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <flux:select wire:model.live="selectedMarketplaceId" placeholder="Select marketplace">
                    @foreach ($marketplaces as $marketplace)
                        <flux:select.option value="{{ $marketplace['id'] }}">
                            {{ $marketplace['name'] }} ({{ ucfirst($marketplace['channel']) }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            
            @if ($selectedMarketplaceId)
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Available attributes: <span class="font-medium text-gray-900 dark:text-white">{{ count($availableAttributes) }}</span>
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Required: <span class="font-medium text-red-600 dark:text-red-400">{{ collect($availableAttributes)->where('is_required', true)->count() }}</span>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($selectedMarketplaceId)
        {{-- 🛠️ OPERATION SELECTION --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Operation Type</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                {{-- Bulk Assign --}}
                <div class="relative">
                    <input type="radio" wire:model.live="operationType" value="assign" id="op-assign" class="sr-only peer">
                    <label for="op-assign" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:border-gray-600 dark:hover:bg-gray-700 dark:peer-checked:border-blue-400 dark:peer-checked:bg-blue-900/20">
                        <flux:icon name="tag" class="w-6 h-6 text-gray-400 peer-checked:text-blue-500 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Bulk Assign</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center">Assign specific attributes to all products</span>
                    </label>
                </div>

                {{-- Auto Assign --}}
                <div class="relative">
                    <input type="radio" wire:model.live="operationType" value="auto_assign" id="op-auto" class="sr-only peer">
                    <label for="op-auto" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:border-gray-600 dark:hover:bg-gray-700 dark:peer-checked:border-blue-400 dark:peer-checked:bg-blue-900/20">
                        <flux:icon name="sparkles" class="w-6 h-6 text-gray-400 peer-checked:text-blue-500 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Auto Assign</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center">Smart extraction from product data</span>
                    </label>
                </div>

                {{-- Validate --}}
                <div class="relative">
                    <input type="radio" wire:model.live="operationType" value="validate" id="op-validate" class="sr-only peer">
                    <label for="op-validate" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:border-gray-600 dark:hover:bg-gray-700 dark:peer-checked:border-blue-400 dark:peer-checked:bg-blue-900/20">
                        <flux:icon name="check-circle" class="w-6 h-6 text-gray-400 peer-checked:text-blue-500 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Validate</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center">Check attribute completeness</span>
                    </label>
                </div>

                {{-- Analyze --}}
                <div class="relative">
                    <input type="radio" wire:model.live="operationType" value="analyze" id="op-analyze" class="sr-only peer">
                    <label for="op-analyze" class="flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:border-gray-600 dark:hover:bg-gray-700 dark:peer-checked:border-blue-400 dark:peer-checked:bg-blue-900/20">
                        <flux:icon name="chart-bar" class="w-6 h-6 text-gray-400 peer-checked:text-blue-500 mb-2" />
                        <span class="text-sm font-medium text-gray-900 dark:text-white">Analyze</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 text-center">Marketplace readiness report</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- 📝 ATTRIBUTE ASSIGNMENT CONFIGURATION --}}
        @if ($operationType === 'assign')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Attribute Assignment</h3>
                
                {{-- Add Attribute Selector --}}
                <div class="mb-6">
                    <flux:select wire:change="addAttribute($event.target.value)" id="attribute-selector">
                        <flux:select.option value="">Add an attribute...</flux:select.option>
                        @foreach ($availableAttributes as $attribute)
                            @if (!isset($attributesToAssign[$attribute['key']]))
                                <flux:select.option value="{{ $attribute['key'] }}">
                                    {{ $attribute['name'] }} {{ $attribute['is_required'] ? '*' : '' }}
                                </flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>
                </div>

                {{-- Attributes to Assign --}}
                @if (!empty($attributesToAssign))
                    <div class="space-y-4 mb-6">
                        @foreach ($attributesToAssign as $key => $value)
                            @php $attribute = $this->getAttributeDefinition($key); @endphp
                            @if ($attribute)
                                <div class="flex items-start gap-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-700">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $attribute['name'] }}
                                            </span>
                                            @if($attribute['is_required'])
                                                <flux:badge color="red" size="sm">Required</flux:badge>
                                            @endif
                                        </div>
                                        
                                        @if (!empty($attribute['choices']))
                                            <flux:select wire:model="attributesToAssign.{{ $key }}">
                                                <flux:select.option value="">Select value...</flux:select.option>
                                                @foreach ($attribute['choices'] as $choice)
                                                    <flux:select.option value="{{ $choice }}">{{ $choice }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        @else
                                            <flux:input 
                                                wire:model="attributesToAssign.{{ $key }}" 
                                                placeholder="Enter value for {{ $attribute['name'] }}"
                                                type="{{ $attribute['data_type'] === 'integer' ? 'number' : 'text' }}"
                                            />
                                        @endif
                                        
                                        @if($attribute['description'])
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $attribute['description'] }}</p>
                                        @endif
                                    </div>
                                    
                                    <flux:button 
                                        wire:click="removeAttribute('{{ $key }}')" 
                                        variant="ghost" 
                                        size="sm" 
                                        icon="x-mark"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Assignment Options --}}
                <div class="flex items-center gap-6 mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="skipValidation" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Skip validation</span>
                    </label>
                    
                    <label class="flex items-center">
                        <input type="checkbox" wire:model="overwriteExisting" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Overwrite existing</span>
                    </label>
                </div>

                {{-- Execute Button --}}
                <flux:button 
                    wire:click="executeBulkAssign" 
                    variant="primary" 
                    icon="tag"
                    :disabled="$isProcessing || empty($attributesToAssign)"
                    class="w-full"
                >
                    @if($isProcessing)
                        Processing...
                    @else
                        Assign Attributes to {{ count($selectedProducts) }} Products
                    @endif
                </flux:button>
            </div>
        @endif

        {{-- 🤖 AUTO ASSIGN SECTION --}}
        @if ($operationType === 'auto_assign')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">🤖 Smart Auto-Assignment</h3>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 dark:bg-blue-900/20 dark:border-blue-700">
                    <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">How Auto-Assignment Works</h4>
                    <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                        <li>• Extracts attributes from existing product data (brand, color, material, etc.)</li>
                        <li>• Uses smart matching to map product fields to marketplace attributes</li>
                        <li>• Assigns confidence scores based on data quality and field matches</li>
                        <li>• Skips attributes that can't be reliably determined</li>
                    </ul>
                </div>

                <flux:button 
                    wire:click="executeAutoAssign" 
                    variant="primary" 
                    icon="sparkles"
                    :disabled="$isProcessing"
                    class="w-full"
                >
                    @if($isProcessing)
                        Auto-assigning...
                    @else
                        🤖 Auto-Assign Attributes for {{ count($selectedProducts) }} Products
                    @endif
                </flux:button>
            </div>
        @endif

        {{-- ✅ VALIDATE SECTION --}}
        @if ($operationType === 'validate')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">✅ Attribute Validation</h3>
                
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 dark:bg-green-900/20 dark:border-green-700">
                    <h4 class="text-sm font-medium text-green-900 dark:text-green-100 mb-2">Validation Checks</h4>
                    <ul class="text-sm text-green-800 dark:text-green-200 space-y-1">
                        <li>• Required attributes are assigned</li>
                        <li>• Attribute values match expected data types</li>
                        <li>• Values comply with marketplace validation rules</li>
                        <li>• Choice fields have valid selections</li>
                    </ul>
                </div>

                <flux:button 
                    wire:click="executeValidation" 
                    variant="primary" 
                    icon="check-circle"
                    :disabled="$isProcessing"
                    class="w-full"
                >
                    @if($isProcessing)
                        Validating...
                    @else
                        Validate {{ count($selectedProducts) }} Products
                    @endif
                </flux:button>
            </div>
        @endif

        {{-- 📊 ANALYZE SECTION --}}
        @if ($operationType === 'analyze')
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">📊 Marketplace Readiness Analysis</h3>
                
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6 dark:bg-purple-900/20 dark:border-purple-700">
                    <h4 class="text-sm font-medium text-purple-900 dark:text-purple-100 mb-2">Analysis Report Includes</h4>
                    <ul class="text-sm text-purple-800 dark:text-purple-200 space-y-1">
                        <li>• Marketplace readiness scores for each product</li>
                        <li>• Completion percentages and missing attributes</li>
                        <li>• Recommendations for improvement</li>
                        <li>• Quality indicators and data health metrics</li>
                    </ul>
                </div>

                <flux:button 
                    wire:click="executeReadinessAnalysis" 
                    variant="primary" 
                    icon="chart-bar"
                    :disabled="$isProcessing"
                    class="w-full"
                >
                    @if($isProcessing)
                        Analyzing...
                    @else
                        Generate Readiness Report for {{ count($selectedProducts) }} Products
                    @endif
                </flux:button>
            </div>
        @endif

        {{-- 📈 PROGRESS INDICATOR --}}
        @if ($isProcessing)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">⏳ Processing</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ $progressMessage }}</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $progressPercentage }}%</span>
                    </div>
                    
                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: {{ $progressPercentage }}%"></div>
                    </div>
                </div>
            </div>
        @endif

        {{-- 📊 RESULTS DISPLAY --}}
        @if ($operationResults || $validationSummary || $readinessAnalysis)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">📊 Results</h3>
                
                {{-- Operation Results --}}
                @if ($operationResults)
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Operation Summary</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-green-50 rounded-lg dark:bg-green-900/20">
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $operationResults['successful_products'] ?? 0 }}</div>
                                <div class="text-xs text-green-700 dark:text-green-300">Successful</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded-lg dark:bg-red-900/20">
                                <div class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $operationResults['failed_products'] ?? 0 }}</div>
                                <div class="text-xs text-red-700 dark:text-red-300">Failed</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 rounded-lg dark:bg-blue-900/20">
                                <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $operationResults['total_attributes_assigned'] ?? $operationResults['attributes_assigned'] ?? 0 }}</div>
                                <div class="text-xs text-blue-700 dark:text-blue-300">Attributes</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg dark:bg-gray-700/50">
                                <div class="text-lg font-semibold text-gray-600 dark:text-gray-400">{{ $operationResults['total_products'] ?? 0 }}</div>
                                <div class="text-xs text-gray-700 dark:text-gray-300">Total</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Validation Summary --}}
                @if ($validationSummary)
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Validation Summary</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div class="text-center p-3 bg-green-50 rounded-lg dark:bg-green-900/20">
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $validationSummary['valid_products'] }}</div>
                                <div class="text-xs text-green-700 dark:text-green-300">Valid Products</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded-lg dark:bg-red-900/20">
                                <div class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $validationSummary['invalid_products'] }}</div>
                                <div class="text-xs text-red-700 dark:text-red-300">Invalid Products</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg dark:bg-gray-700/50">
                                <div class="text-lg font-semibold text-gray-600 dark:text-gray-400">{{ $validationSummary['total_products'] }}</div>
                                <div class="text-xs text-gray-700 dark:text-gray-300">Total Products</div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Readiness Analysis --}}
                @if ($readinessAnalysis)
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Readiness Analysis</h4>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                            <div class="text-center p-3 bg-green-50 rounded-lg dark:bg-green-900/20">
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $readinessAnalysis['readiness_distribution']['ready'] }}</div>
                                <div class="text-xs text-green-700 dark:text-green-300">Ready</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg dark:bg-yellow-900/20">
                                <div class="text-lg font-semibold text-yellow-600 dark:text-yellow-400">{{ $readinessAnalysis['readiness_distribution']['nearly_ready'] }}</div>
                                <div class="text-xs text-yellow-700 dark:text-yellow-300">Nearly Ready</div>
                            </div>
                            <div class="text-center p-3 bg-orange-50 rounded-lg dark:bg-orange-900/20">
                                <div class="text-lg font-semibold text-orange-600 dark:text-orange-400">{{ $readinessAnalysis['readiness_distribution']['needs_improvement'] }}</div>
                                <div class="text-xs text-orange-700 dark:text-orange-300">Needs Work</div>
                            </div>
                            <div class="text-center p-3 bg-red-50 rounded-lg dark:bg-red-900/20">
                                <div class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $readinessAnalysis['readiness_distribution']['not_ready'] }}</div>
                                <div class="text-xs text-red-700 dark:text-red-300">Not Ready</div>
                            </div>
                            <div class="text-center p-3 bg-blue-50 rounded-lg dark:bg-blue-900/20">
                                <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $readinessAnalysis['average_completion'] }}%</div>
                                <div class="text-xs text-blue-700 dark:text-blue-300">Avg Completion</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    @endif
</div>