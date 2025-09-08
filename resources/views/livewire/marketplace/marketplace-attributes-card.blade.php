<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center">
            <flux:icon name="tag" class="w-5 h-5 mr-2" />
            🏷️ Marketplace Attributes
        </h3>
        
        @if ($selectedMarketplaceId)
            <div class="flex gap-2">
                <flux:button wire:click="autoAssignAttributes" variant="ghost" size="sm" icon="sparkles">
                    Auto-assign
                </flux:button>
                <flux:button wire:click="debugReloadData" variant="ghost" size="sm" icon="arrow-path">
                    Debug Reload
                </flux:button>
            </div>
        @endif
    </div>

    {{-- Marketplace Selector --}}
    <div class="mb-4">
        <flux:select wire:model.live="selectedMarketplaceId" placeholder="Select marketplace">
            @foreach ($marketplaces as $marketplace)
                <flux:select.option value="{{ $marketplace['id'] }}">
                    {{ $marketplace['name'] }} ({{ ucfirst($marketplace['channel']) }})
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($selectedMarketplaceId)
        {{-- Readiness Overview --}}
        @if ($readinessReport)
            <div class="mb-6 p-4 rounded-lg border 
                @if(($readinessReport['status'] ?? '') === 'ready') bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700
                @elseif(($readinessReport['status'] ?? '') === 'nearly_ready') bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-700
                @elseif(($readinessReport['status'] ?? '') === 'needs_improvement') bg-orange-50 border-orange-200 dark:bg-orange-900/20 dark:border-orange-700
                @else bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700
                @endif">
                
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <flux:badge :color="$this->getReadinessColor()" size="sm">
                            {{ $readinessReport['readiness_score'] ?? 0 }}% Ready
                        </flux:badge>
                        <span class="ml-2 text-sm font-medium 
                            @if(($readinessReport['status'] ?? '') === 'ready') text-green-800 dark:text-green-200
                            @elseif(($readinessReport['status'] ?? '') === 'nearly_ready') text-yellow-800 dark:text-yellow-200
                            @elseif(($readinessReport['status'] ?? '') === 'needs_improvement') text-orange-800 dark:text-orange-200
                            @else text-red-800 dark:text-red-200
                            @endif">
                            {{ str_replace('_', ' ', ucfirst($readinessReport['status'] ?? 'unknown')) }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Completion:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $completionPercentage }}%</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Assigned:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $readinessReport['attributes']['total_assigned'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Valid:</span>
                        <span class="font-medium text-green-600 dark:text-green-400">{{ $readinessReport['attributes']['valid'] ?? 0 }}</span>
                    </div>
                    <div>
                        <span class="text-gray-600 dark:text-gray-400">Missing:</span>
                        <span class="font-medium text-red-600 dark:text-red-400">{{ $readinessReport['attributes']['required_missing'] ?? 0 }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- Missing Required Attributes Alert --}}
        @if (!empty($missingRequired))
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/20 dark:border-red-700">
                <div class="flex items-center mb-2">
                    <flux:icon name="exclamation-triangle" class="w-4 h-4 text-red-600 mr-2" />
                    <span class="text-sm font-medium text-red-800 dark:text-red-200">
                        {{ count($missingRequired) }} Required Attributes Missing
                    </span>
                </div>
                <div class="text-xs text-red-700 dark:text-red-300">
                    @foreach (array_slice($missingRequired, 0, 3) as $missing)
                        <span class="inline-block bg-red-100 dark:bg-red-800/50 rounded px-2 py-1 mr-1 mb-1">
                            {{ $missing['name'] }}
                        </span>
                    @endforeach
                    @if (count($missingRequired) > 3)
                        <span class="text-red-600 dark:text-red-400">+{{ count($missingRequired) - 3 }} more</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Add Attribute Form --}}
        @if ($showAddForm)
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/20 dark:border-blue-700">
                <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-3">Add Attribute</h4>
                
                <div class="space-y-3">
                    <flux:select wire:model.live="newAttributeKey" placeholder="Select attribute">
                        @foreach ($this->getAvailableAttributesForSelect() as $option)
                            <flux:select.option value="{{ $option['value'] }}">
                                {{ $option['label'] }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($newAttributeKey)
                        <flux:input 
                            wire:model="newAttributeValue" 
                            placeholder="Attribute value" 
                            class="w-full"
                        />
                        
                        <flux:input 
                            wire:model="newAttributeDisplayValue" 
                            placeholder="Display value (optional)" 
                            class="w-full"
                        />
                    @endif
                </div>

                <div class="flex justify-end gap-2 mt-4">
                    <flux:button wire:click="cancelAddAttribute" variant="ghost" size="sm">
                        Cancel
                    </flux:button>
                    <flux:button 
                        wire:click="addAttribute" 
                        variant="primary" 
                        size="sm"
                        :disabled="!$newAttributeKey || !$newAttributeValue"
                    >
                        Add
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- Attributes List --}}
        <div class="space-y-3">
            @if (!empty($productAttributes))
                @foreach ($productAttributes as $attribute)
                    <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-3 
                        @if($attribute['is_valid']) bg-gray-50 dark:bg-gray-700/50
                        @else bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-600
                        @endif">
                        
                        @if ($editingAttributeId === $attribute['id'])
                            {{-- Edit Mode --}}
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $attribute['name'] }}
                                        @if($attribute['is_required'])
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </span>
                                </div>
                                
                                <flux:input 
                                    wire:model="editValue" 
                                    placeholder="Attribute value"
                                    class="w-full"
                                />
                                
                                <flux:input 
                                    wire:model="editDisplayValue" 
                                    placeholder="Display value (optional)"
                                    class="w-full"
                                />

                                <div class="flex justify-end gap-2">
                                    <flux:button wire:click="cancelEdit" variant="ghost" size="sm">
                                        Cancel
                                    </flux:button>
                                    <flux:button wire:click="saveAttribute" variant="primary" size="sm">
                                        Save
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            {{-- View Mode --}}
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $attribute['name'] }}
                                        </span>
                                        
                                        @if($attribute['is_required'])
                                            <flux:badge color="red" size="sm">Required</flux:badge>
                                        @endif
                                        
                                        @if(!$attribute['is_valid'])
                                            <flux:badge color="red" size="sm">Invalid</flux:badge>
                                        @endif
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 dark:text-gray-400 break-words">
                                        {{ $attribute['display_value'] ?: $attribute['value'] }}
                                    </div>
                                    
                                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                        {{ $attribute['data_type'] }} • Added {{ $attribute['assigned_at'] }}
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-1 ml-2">
                                    <flux:button 
                                        wire:click="editAttribute({{ $attribute['id'] }})" 
                                        variant="ghost" 
                                        size="sm"
                                        icon="pencil"
                                    />
                                    <flux:button 
                                        wire:click="removeAttribute({{ $attribute['id'] }})" 
                                        wire:confirm="Are you sure you want to remove this attribute?"
                                        variant="ghost" 
                                        size="sm"
                                        icon="trash"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="text-center py-6">
                    <flux:icon name="tag" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No attributes assigned</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">Add attributes to improve marketplace readiness</p>
                </div>
            @endif
        </div>

        {{-- Add Attribute Button --}}
        @if (!$showAddForm && !empty($this->getAvailableAttributesForSelect()))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                <flux:button 
                    wire:click="showAddAttributeForm" 
                    variant="ghost" 
                    size="sm" 
                    icon="plus"
                    class="w-full"
                >
                    Add Attribute
                </flux:button>
            </div>
        @endif

    @elseif (!empty($marketplaces))
        {{-- No Marketplace Selected --}}
        <div class="text-center py-8">
            <flux:icon name="store" class="mx-auto h-8 w-8 text-gray-400 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Select a marketplace to manage attributes</p>
        </div>
    @else
        {{-- No Marketplaces Available --}}
        <div class="text-center py-8">
            <flux:icon name="exclamation-triangle" class="mx-auto h-8 w-8 text-yellow-400 mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No active marketplaces found</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Add marketplace integrations to use this feature</p>
        </div>
    @endif
</div>
