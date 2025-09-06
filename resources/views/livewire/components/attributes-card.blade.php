<div class="bg-white rounded-lg shadow-sm border border-gray-200">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon.tag class="w-5 h-5 text-gray-500" />
                <h3 class="text-lg font-semibold text-gray-900">
                    Attributes
                    @if($modelType === 'variant' && $showInheritance)
                        <span class="text-sm font-normal text-gray-500">with inheritance</span>
                    @endif
                </h3>
            </div>
            
            {{-- Actions --}}
            <div class="flex items-center gap-2">
                @if($modelType === 'variant' && $showInheritance)
                    <flux:button size="sm" variant="ghost" wire:click="refreshInheritance" :loading="$wire.busy">
                        <flux:icon.arrow-path class="w-4 h-4" />
                        Refresh Inheritance
                    </flux:button>
                @endif
                
                @if($allowEditing)
                    <flux:dropdown>
                        <flux:button size="sm" variant="primary">
                            <flux:icon.plus class="w-4 h-4" />
                            Add Attribute
                        </flux:button>
                        
                        <flux:menu class="w-56">
                            @foreach($availableDefinitions as $available)
                                <flux:menu.item wire:click="addAttribute('{{ $available['definition']->key }}')">
                                    <div class="flex items-center justify-between w-full">
                                        <span>{{ $available['definition']->name }}</span>
                                        @if($available['definition']->is_required_for_products || $available['definition']->is_required_for_variants)
                                            <flux:badge size="sm" color="red">Required</flux:badge>
                                        @endif
                                    </div>
                                </flux:menu.item>
                            @endforeach
                            
                            @if(empty($availableDefinitions))
                                <flux:menu.item disabled>
                                    <span class="text-gray-500">All attributes are set</span>
                                </flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        </div>
        
        {{-- Inheritance Summary for Variants --}}
        @if($modelType === 'variant' && $showInheritance && $inheritanceSummary)
            <div class="mt-3 p-3 bg-blue-50 rounded-md">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium text-blue-900">Inheritance Summary</span>
                    <div class="flex items-center gap-4 text-blue-700">
                        <span>{{ $inheritanceSummary['inherited'] }} inherited</span>
                        <span>{{ $inheritanceSummary['overridden'] }} overridden</span>
                        <span>{{ $inheritanceSummary['explicit'] }} explicit</span>
                        @if($inheritanceSummary['inheritable_available'] > 0)
                            <span class="text-amber-600">{{ $inheritanceSummary['inheritable_available'] }} available</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Attributes List --}}
    <div class="px-6 py-4">
        @if(empty($attributes))
            <div class="text-center py-8 text-gray-500">
                <flux:icon.tag class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                <p>No attributes defined</p>
                @if($allowEditing)
                    <p class="text-sm mt-1">Click "Add Attribute" to get started</p>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @foreach($attributes as $attr)
                    @php
                        $definition = $attr['definition'];
                        $currentValue = $attr['current_value'];
                        $inheritanceInfo = $attr['inheritance_info'];
                        $isEditing = $attr['is_editing'];
                        $key = $definition->key;
                    @endphp
                    
                    <div class="border border-gray-200 rounded-lg p-4 {{ $isEditing ? 'ring-2 ring-blue-500' : '' }}">
                        {{-- Attribute Header --}}
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                @if($definition->icon)
                                    <flux:icon :name="$definition->icon" class="w-4 h-4 text-gray-500" />
                                @endif
                                <span class="font-medium text-gray-900">{{ $definition->name }}</span>
                                
                                {{-- Required Badge --}}
                                @if($definition->is_required_for_products || $definition->is_required_for_variants)
                                    <flux:badge size="sm" color="red">Required</flux:badge>
                                @endif
                                
                                {{-- System Badge --}}
                                @if($definition->is_system_attribute)
                                    <flux:badge size="sm" color="gray">System</flux:badge>
                                @endif
                            </div>
                            
                            {{-- Actions --}}
                            @if($allowEditing && !$isEditing)
                                <div class="flex items-center gap-1">
                                    <flux:button size="sm" variant="ghost" wire:click="editAttribute('{{ $key }}')">
                                        <flux:icon.pencil class="w-3 h-3" />
                                    </flux:button>
                                    
                                    {{-- Inheritance Actions for Variants --}}
                                    @if($modelType === 'variant' && $inheritanceInfo)
                                        @if($inheritanceInfo['has_parent_value'] && !$currentValue)
                                            <flux:button size="sm" variant="ghost" wire:click="inheritAttribute('{{ $key }}')" title="Inherit from product">
                                                <flux:icon.arrow-down class="w-3 h-3 text-blue-500" />
                                            </flux:button>
                                        @endif
                                        
                                        @if($currentValue && ($currentValue['is_override'] ?? false))
                                            <flux:button size="sm" variant="ghost" wire:click="clearOverride('{{ $key }}')" title="Clear override">
                                                <flux:icon.x-mark class="w-3 h-3 text-red-500" />
                                            </flux:button>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                        
                        {{-- Description --}}
                        @if($definition->description)
                            <p class="text-sm text-gray-500 mb-3">{{ $definition->description }}</p>
                        @endif
                        
                        {{-- Current Value or Edit Form --}}
                        @if($isEditing)
                            <div class="space-y-3">
                                {{-- Edit Form --}}
                                <div>
                                    @php $editData = $editingAttribute[$key]; @endphp
                                    
                                    @switch($editData['ui_config']['type'])
                                        @case('select')
                                            <flux:select wire:model="editingAttribute.{{ $key }}.value">
                                                <option value="">-- Select {{ $definition->name }} --</option>
                                                @foreach($editData['ui_config']['options'] as $optionValue => $optionLabel)
                                                    <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
                                                @endforeach
                                            </flux:select>
                                            @break
                                            
                                        @case('checkbox')
                                            <flux:checkbox wire:model="editingAttribute.{{ $key }}.value">
                                                {{ $editData['ui_config']['label'] }}
                                            </flux:checkbox>
                                            @break
                                            
                                        @case('textarea')
                                            <flux:textarea wire:model="editingAttribute.{{ $key }}.value" 
                                                          placeholder="Enter {{ $definition->name }}" 
                                                          rows="6" />
                                            @if($definition->data_type === 'json')
                                                <div class="text-xs text-gray-500 mt-1">
                                                    💡 Enter valid JSON format, e.g.: {"key": "value"}
                                                </div>
                                            @endif
                                            @break
                                            
                                        @case('number')
                                            <flux:input type="number" wire:model="editingAttribute.{{ $key }}.value" 
                                                       placeholder="Enter {{ $definition->name }}" />
                                            @break
                                            
                                        @case('url')
                                            <flux:input type="url" wire:model="editingAttribute.{{ $key }}.value" 
                                                       placeholder="https://example.com" />
                                            @break
                                            
                                        @case('date')
                                            <flux:input type="date" wire:model="editingAttribute.{{ $key }}.value" />
                                            @break
                                            
                                        @default
                                            <flux:input type="text" wire:model="editingAttribute.{{ $key }}.value" 
                                                       placeholder="Enter {{ $definition->name }}" />
                                    @endswitch
                                </div>
                                
                                {{-- Edit Actions --}}
                                <div class="flex items-center justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="cancelEditAttribute('{{ $key }}')">
                                        Cancel
                                    </flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="saveAttribute('{{ $key }}')" 
                                                wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="saveAttribute">Save</span>
                                        <span wire:loading wire:target="saveAttribute">Saving...</span>
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            {{-- Display Current Value --}}
                            <div class="space-y-2">
                                @if($currentValue)
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="font-mono text-sm text-gray-900">
                                                {{ format_attribute_value($currentValue['display_value'] ?: $currentValue['value']) }}
                                            </div>
                                            
                                            {{-- Value Metadata --}}
                                            <div class="flex items-center gap-3 mt-1 text-xs text-gray-500">
                                                <span>{{ ucfirst($currentValue['source'] ?? 'manual') }}</span>
                                                
                                                @if($modelType === 'variant' && isset($currentValue['is_inherited']) && $currentValue['is_inherited'])
                                                    <flux:badge size="xs" color="blue">Inherited</flux:badge>
                                                @endif
                                                
                                                @if($modelType === 'variant' && isset($currentValue['is_override']) && $currentValue['is_override'])
                                                    <flux:badge size="xs" color="amber">Override</flux:badge>
                                                @endif
                                            </div>
                                        </div>
                                        
                                        {{-- Inheritance Info for Variants --}}
                                        @if($modelType === 'variant' && $inheritanceInfo)
                                            <div class="ml-4 text-right text-xs text-gray-500">
                                                @if($inheritanceInfo['has_parent_value'])
                                                    <div>Parent: <span class="font-mono">{{ format_attribute_value($inheritanceInfo['parent_display_value']) }}</span></div>
                                                    <div class="text-blue-600">{{ ucfirst($inheritanceInfo['inheritance_strategy']) }}</div>
                                                @else
                                                    <div class="text-gray-400">No parent value</div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    {{-- No Value Set --}}
                                    <div class="flex items-center justify-between text-gray-500">
                                        <span class="text-sm">No value set</span>
                                        
                                        {{-- Show Available Parent Value --}}
                                        @if($modelType === 'variant' && $inheritanceInfo && $inheritanceInfo['has_parent_value'])
                                            <div class="text-right text-xs">
                                                <div>Available from parent:</div>
                                                <div class="font-mono text-blue-600">{{ format_attribute_value($inheritanceInfo['parent_display_value']) }}</div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    
    {{-- Footer Stats --}}
    @if(!empty($attributes))
        <div class="px-6 py-3 border-t border-gray-200 bg-gray-50 text-xs text-gray-500">
            <div class="flex items-center justify-between">
                <span>{{ count($attributes) }} attribute definitions</span>
                <span>{{ count(array_filter($attributes, fn($attr) => $attr['current_value'])) }} have values</span>
            </div>
        </div>
    @endif
</div>