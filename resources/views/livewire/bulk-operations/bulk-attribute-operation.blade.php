{{-- 🚀 BULK ATTRIBUTE OPERATION - Full-Page Dedicated Interface --}}
<div class="space-y-6" x-data="{ 
    isProcessing: @entangle('isProcessing'),
    progress: @entangle('processingProgress'),
    errorMessage: @entangle('errorMessage'),
    operationType: @entangle('attributeData.operation_type'),
    selectedField: @entangle('attributeData.attribute_field'),
    updatePreview: @entangle('updatePreview'),
    availableOptions: @entangle('availableOptions')
}">
    
    {{-- Breadcrumb Navigation --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <flux:button wire:click="backToBulkOperations" variant="ghost" size="sm" class="hover:text-gray-700">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
            Bulk Operations
        </flux:button>
        <span>/</span>
        <span class="text-gray-900 dark:text-white font-medium">Update Attributes</span>
    </div>

    {{-- Error Message --}}
    <div x-show="errorMessage" x-transition class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600" />
                <span x-text="errorMessage" class="text-red-800 font-medium"></span>
            </div>
            <flux:button wire:click="clearError" variant="ghost" size="sm" class="text-red-600">
                <flux:icon name="x" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>

    {{-- Processing Indicator --}}
    <div x-show="isProcessing" x-transition class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-3">
            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
            <span>Processing attributes for {{ $this->selectedCount }} {{ $this->targetDisplayName }}...</span>
            <div class="bg-gray-200 rounded-full h-3 flex-1">
                <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                     :style="`width: ${progress}%`"></div>
            </div>
            <span x-text="`${progress}%`" class="text-sm font-medium text-blue-600 min-w-12 text-right"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Update Attributes
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Bulk attribute operations for {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}
            </p>
        </div>
        
        {{-- Target Type Indicator --}}
        <div class="text-right">
            <div class="text-sm text-gray-500">Target Type</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->targetDisplayName }}</div>
        </div>
    </div>

    {{-- Main Content Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Attribute Configuration --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Attribute Configuration</flux:card.title>
                <flux:card.description>Configure what attributes to update and how</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Operation Type Selection --}}
                <flux:radio.group wire:model.live="attributeData.operation_type">
                    <flux:radio value="update_attributes">Update attributes</flux:radio>
                    <flux:radio value="clear_attributes">Clear attributes</flux:radio>
                    @if($this->targetType === 'products')
                        <flux:radio value="add_tags">Add tags</flux:radio>
                        <flux:radio value="remove_tags">Remove tags</flux:radio>
                    @endif
                </flux:radio.group>
                
                {{-- Update Attributes Options --}}
                <template x-if="operationType === 'update_attributes'">
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Update Settings</h4>
                        
                        {{-- Attribute Field Selection --}}
                        <flux:select wire:model.live="attributeData.attribute_field" label="Attribute Field">
                            <option value="">Select an attribute</option>
                            @foreach($this->availableFields as $field)
                                <option value="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</option>
                            @endforeach
                        </flux:select>

                        {{-- Value Input - Changes based on field --}}
                        <template x-if="selectedField">
                            <div>
                                @if(in_array($this->attributeData['attribute_field'] ?? '', ['status']))
                                    {{-- Dropdown for status fields --}}
                                    <flux:select wire:model.live="attributeData.attribute_value" label="New Value">
                                        <option value="">Select value</option>
                                        @foreach($this->availableOptions as $option)
                                            <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    {{-- Text input for other fields --}}
                                    <flux:input 
                                        wire:model.live="attributeData.attribute_value"
                                        label="New Value"
                                        placeholder="Enter new value..."
                                    />
                                    
                                    {{-- Show available options as suggestions --}}
                                    @if(!empty($this->availableOptions))
                                        <div class="mt-2">
                                            <div class="text-sm text-gray-600 mb-1">Suggestions:</div>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($this->availableOptions as $option)
                                                    <flux:button 
                                                        wire:click="$set('attributeData.attribute_value', '{{ $option }}')"
                                                        variant="ghost" 
                                                        size="sm"
                                                        class="text-xs"
                                                    >
                                                        {{ $option }}
                                                    </flux:button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </template>

                        {{-- Update Mode --}}
                        <template x-if="selectedField && !['status', 'category'].includes(selectedField)">
                            <flux:radio.group wire:model.live="attributeData.update_mode" label="Update Mode">
                                <flux:radio value="replace">Replace existing value</flux:radio>
                                <flux:radio value="append">Add to end of existing value</flux:radio>
                                <flux:radio value="prepend">Add to beginning of existing value</flux:radio>
                            </flux:radio.group>
                        </template>
                    </div>
                </template>

                {{-- Clear Attributes Options --}}
                <template x-if="operationType === 'clear_attributes'">
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Clear Settings</h4>
                        
                        <flux:select wire:model.live="attributeData.attribute_field" label="Attribute Field to Clear">
                            <option value="">Select an attribute</option>
                            @foreach($this->availableFields as $field)
                                <option value="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</option>
                            @endforeach
                        </flux:select>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                            <div class="text-sm text-yellow-800">
                                <strong>Warning:</strong> This will set the selected field to empty/null for all selected {{ strtolower($this->targetDisplayName) }}.
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Tags Operations --}}
                <template x-if="operationType === 'add_tags' || operationType === 'remove_tags'">
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="font-medium text-gray-900 dark:text-white" x-text="operationType === 'add_tags' ? 'Add Tags' : 'Remove Tags'"></h4>
                        
                        <flux:input 
                            wire:model.live="attributeData.attribute_value"
                            label="Tags (comma separated)"
                            placeholder="tag1, tag2, tag3"
                        />
                        
                        {{-- Show existing tags as suggestions --}}
                        @if(!empty($this->dynamicOptions['tags'] ?? []))
                            <div>
                                <div class="text-sm text-gray-600 mb-1">Existing tags:</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach(($this->dynamicOptions['tags'] ?? []) as $tag)
                                        <flux:button 
                                            wire:click="$set('attributeData.attribute_value', '{{ $this->attributeData['attribute_value'] ?? '' }}{{ $this->attributeData['attribute_value'] ? ', ' : '' }}{{ $tag }}')"
                                            variant="ghost" 
                                            size="sm"
                                            class="text-xs"
                                        >
                                            {{ $tag }}
                                        </flux:button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </template>
            </flux:card.content>
        </flux:card>

        {{-- Preview & Summary --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Preview & Summary</flux:card.title>
                <flux:card.description>Review your attribute changes before applying</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Operation Preview --}}
                <template x-if="updatePreview">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="text-sm text-blue-600 font-medium mb-2">Operation Preview</div>
                        <div class="text-sm" x-text="updatePreview"></div>
                    </div>
                </template>

                {{-- Selection Summary --}}
                <div class="border rounded-lg p-4 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white">Selection Summary</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Target:</span>
                            <div class="font-medium">{{ $this->targetDisplayName }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Count:</span>
                            <div class="font-medium">{{ $this->selectedCount }} items</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Operation:</span>
                            <div class="font-medium" x-text="operationType.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())"></div>
                        </div>
                        <div x-show="selectedField">
                            <span class="text-gray-500">Field:</span>
                            <div class="font-medium" x-text="selectedField.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())"></div>
                        </div>
                    </div>
                </div>

                {{-- Validation Warnings --}}
                <template x-if="operationType === 'clear_attributes' && selectedField">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 mt-0.5" />
                            <div>
                                <div class="font-medium text-red-800">Data Loss Warning</div>
                                <div class="text-red-700 text-sm mt-1">
                                    This will permanently clear the selected field from {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}. 
                                    This action cannot be undone.
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Important Notes --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 mt-0.5" />
                        <div class="text-sm">
                            <div class="font-medium text-yellow-800">Important Notes</div>
                            <div class="text-yellow-700 mt-1 space-y-1">
                                <div>• Changes will be applied to {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}</div>
                                <div>• Operations are processed in batches for performance</div>
                                <div>• Invalid attribute values will be skipped</div>
                                <template x-if="operationType.includes('tags')">
                                    <div>• Tags are case-sensitive and will be created if they don't exist</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card.content>
        </flux:card>
    </div>

    {{-- Action Buttons --}}
    <div class="flex justify-between items-center">
        <flux:button wire:click="backToBulkOperations" variant="ghost" ::disabled="isProcessing">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to Selection
        </flux:button>
        
        <flux:button 
            wire:click="applyBulkAttributes" 
            variant="primary"
            size="base"
            ::disabled="isProcessing || !selectedField && operationType !== 'add_tags' && operationType !== 'remove_tags'"
            x-bind:disabled="isProcessing || (!selectedField && !['add_tags', 'remove_tags'].includes(operationType)) || !$wire.attributeData.attribute_value"
        >
            <template x-if="!isProcessing">
                <div class="flex items-center gap-2">
                    <flux:icon name="tag" class="w-4 h-4" />
                    <span>Apply to {{ $this->selectedCount }} {{ $this->targetDisplayName }}</span>
                </div>
            </template>
            <template x-if="isProcessing">
                <div class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                    <span>Processing...</span>
                </div>
            </template>
        </flux:button>
    </div>
</div>