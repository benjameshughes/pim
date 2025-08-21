{{-- 🎛️ PRODUCT FIELD MAPPER COMPONENT --}}
<div class="space-y-6">
    {{-- SYNC ACCOUNT SELECTION --}}
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                🎛️ Field Mapping Configuration
            </h3>
            <div class="flex items-center gap-2">
                @if($selectedSyncAccount)
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs rounded-full">
                        <flux:icon.circle-check class="w-3 h-3" />
                        Connected
                    </span>
                @endif
            </div>
        </div>

        {{-- Sync Account Selector --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <flux:field>
                    <flux:label>Marketplace Channel</flux:label>
                    <flux:select wire:model.live="selectedSyncAccount" placeholder="Select channel...">
                        @foreach($this->syncAccounts as $account)
                            <flux:select.option value="{{ $account->id }}">
                                {{ ucfirst($account->marketplace_type) }} - {{ $account->account_name }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            @if($selectedSyncAccount)
                <div>
                    <flux:field>
                        <flux:label>Category (Optional)</flux:label>
                        <flux:input 
                            wire:model.live="selectedCategory" 
                            placeholder="e.g., H02 or home-curtains"
                        />
                    </flux:field>
                </div>
            @endif
        </div>

        {{-- Coverage Summary --}}
        @if($selectedSyncAccount)
            @php
                $coverage = $this->getMappingCoverage();
            @endphp
            <div class="mt-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Mapping Coverage
                    </span>
                    <span class="text-sm font-bold {{ $coverage['percentage'] >= 80 ? 'text-green-600 dark:text-green-400' : ($coverage['percentage'] >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                        {{ $coverage['percentage'] }}%
                    </span>
                </div>
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                    <div 
                        class="h-2 rounded-full transition-all duration-300 {{ $coverage['percentage'] >= 80 ? 'bg-green-500' : ($coverage['percentage'] >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                        style="width: {{ $coverage['percentage'] }}%"
                    ></div>
                </div>
                <div class="flex justify-between text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                    <span>Required: {{ $coverage['mapped_required'] }}/{{ $coverage['total_required'] }}</span>
                    <span>Optional: {{ $coverage['mapped_optional'] }}/{{ $coverage['total_optional'] }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- MAIN CONTENT --}}
    @if($selectedSyncAccount)
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-xl">
            {{-- Tab Navigation --}}
            <div class="border-b border-zinc-200 dark:border-zinc-700">
                <nav class="flex">
                    <button 
                        wire:click="setActiveTab('mappings')"
                        class="px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'mappings' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                    >
                        Current Mappings ({{ $this->currentMappings->count() }})
                    </button>
                    <button 
                        wire:click="setActiveTab('requirements')"
                        class="px-6 py-4 text-sm font-medium border-b-2 transition-colors {{ $activeTab === 'requirements' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-zinc-500 dark:text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
                    >
                        @php
                            $requirements = $this->fieldRequirements;
                            $totalFields = ($requirements['required']->count() ?? 0) + ($requirements['optional']->count() ?? 0);
                        @endphp
                        Field Requirements ({{ $totalFields }})
                    </button>
                </nav>
            </div>

            <div class="p-6">
                {{-- CURRENT MAPPINGS TAB --}}
                @if($activeTab === 'mappings')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                Current Field Mappings
                            </h4>
                            <div class="flex gap-2">
                                <flux:button 
                                    wire:click="testAllMappings"
                                    variant="outline"
                                    size="sm"
                                    icon="beaker"
                                >
                                    Test All
                                </flux:button>
                                <flux:button 
                                    wire:click="validateMappings"
                                    variant="outline"
                                    size="sm"
                                    icon="check-circle"
                                >
                                    Validate
                                </flux:button>
                                <flux:button 
                                    wire:click="showAddMapping"
                                    variant="primary"
                                    size="sm"
                                    icon="plus"
                                >
                                    Add Mapping
                                </flux:button>
                            </div>
                        </div>

                        @if($this->currentMappings->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">Field</th>
                                            <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">Mapping</th>
                                            <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">Level</th>
                                            <th class="px-4 py-3 text-left font-medium text-zinc-700 dark:text-zinc-300">Type</th>
                                            <th class="px-4 py-3 text-center font-medium text-zinc-700 dark:text-zinc-300">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                        @foreach($this->currentMappings as $mapping)
                                            <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                                <td class="px-4 py-3">
                                                    <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                        {{ $mapping->channel_field_code }}
                                                    </div>
                                                    @if($mapping->notes)
                                                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                                            {{ Str::limit($mapping->notes, 50) }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    @if($mapping->mapping_type === 'pim_field')
                                                        <span class="text-blue-600 dark:text-blue-400">{{ $mapping->source_field }}</span>
                                                    @elseif($mapping->mapping_type === 'static_value')
                                                        <span class="text-green-600 dark:text-green-400">"{{ Str::limit($mapping->static_value, 30) }}"</span>
                                                    @elseif($mapping->mapping_type === 'expression')
                                                        <span class="text-purple-600 dark:text-purple-400">{{ Str::limit($mapping->mapping_expression, 30) }}</span>
                                                    @else
                                                        <span class="text-zinc-500 dark:text-zinc-400">Custom</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $mapping->mapping_level === 'global' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400' : ($mapping->mapping_level === 'product' ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400') }}">
                                                        {{ ucfirst($mapping->mapping_level) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ str_replace('_', ' ', $mapping->mapping_type) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <div class="flex items-center justify-center gap-1">
                                                        <flux:button 
                                                            wire:click="testMapping({{ $mapping->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="beaker"
                                                        >
                                                        </flux:button>
                                                        <flux:button 
                                                            wire:click="editMapping({{ $mapping->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="pencil"
                                                        >
                                                        </flux:button>
                                                        <flux:button 
                                                            wire:click="deleteMapping({{ $mapping->id }})"
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                        >
                                                        </flux:button>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-12">
                                <flux:icon.settings class="w-12 h-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-4" />
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">No mappings configured</h3>
                                <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                                    Start by adding field mappings to connect your PIM data with marketplace requirements.
                                </p>
                                <flux:button 
                                    wire:click="showAddMapping"
                                    variant="primary"
                                    icon="plus"
                                >
                                    Add First Mapping
                                </flux:button>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- FIELD REQUIREMENTS TAB --}}
                @if($activeTab === 'requirements')
                    <div class="space-y-6">
                        <h4 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            Field Requirements
                        </h4>

                        @php
                            $requirements = $this->fieldRequirements;
                            $mappedFields = $this->currentMappings->pluck('channel_field_code');
                        @endphp

                        {{-- Required Fields --}}
                        @if($requirements['required']->count() > 0)
                            <div>
                                <h5 class="text-md font-medium text-red-600 dark:text-red-400 mb-3">
                                    🔴 Required Fields ({{ $requirements['required']->count() }})
                                </h5>
                                <div class="grid gap-2">
                                    @foreach($requirements['required'] as $field)
                                        <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg {{ $mappedFields->contains($field->field_code) ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800' }}">
                                            <div class="flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $field->field_code }}
                                                </div>
                                                @if($field->description)
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {{ $field->description }}
                                                    </div>
                                                @endif
                                                @if($field->field_type)
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                                        Type: {{ $field->field_type }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if($mappedFields->contains($field->field_code))
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs rounded-full">
                                                        <flux:icon.circle-check class="w-3 h-3" />
                                                        Mapped
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs rounded-full">
                                                        <flux:icon.circle-alert class="w-3 h-3" />
                                                        Missing
                                                    </span>
                                                    <flux:button 
                                                        wire:click="showAddMapping('{{ $field->field_code }}')"
                                                        variant="outline"
                                                        size="sm"
                                                        icon="plus"
                                                    >
                                                        Map
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Optional Fields --}}
                        @if($requirements['optional']->count() > 0)
                            <div>
                                <h5 class="text-md font-medium text-blue-600 dark:text-blue-400 mb-3">
                                    🔵 Optional Fields ({{ $requirements['optional']->count() }})
                                </h5>
                                <div class="grid gap-2">
                                    @foreach($requirements['optional'] as $field)
                                        <div class="flex items-center justify-between p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg {{ $mappedFields->contains($field->field_code) ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800' : 'bg-zinc-50 dark:bg-zinc-800' }}">
                                            <div class="flex-1">
                                                <div class="font-medium text-zinc-900 dark:text-zinc-100">
                                                    {{ $field->field_code }}
                                                </div>
                                                @if($field->description)
                                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                                        {{ $field->description }}
                                                    </div>
                                                @endif
                                                @if($field->field_type)
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                                        Type: {{ $field->field_type }}
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if($mappedFields->contains($field->field_code))
                                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 text-xs rounded-full">
                                                        <flux:icon.circle-check class="w-3 h-3" />
                                                        Mapped
                                                    </span>
                                                @else
                                                    <flux:button 
                                                        wire:click="showAddMapping('{{ $field->field_code }}')"
                                                        variant="outline"
                                                        size="sm"
                                                        icon="plus"
                                                    >
                                                        Map
                                                    </flux:button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($requirements['required']->count() === 0 && $requirements['optional']->count() === 0)
                            <div class="text-center py-12">
                                <flux:icon.file-search class="w-12 h-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-4" />
                                <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">No field requirements found</h3>
                                <p class="text-zinc-600 dark:text-zinc-400 mb-4">
                                    Field requirements haven't been discovered yet for this channel. Try running field discovery.
                                </p>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <flux:icon.sliders-horizontal class="w-12 h-12 mx-auto text-zinc-400 dark:text-zinc-600 mb-4" />
            <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-100 mb-2">Select a marketplace channel</h3>
            <p class="text-zinc-600 dark:text-zinc-400">
                Choose a marketplace channel above to configure field mappings for this product.
            </p>
        </div>
    @endif

    {{-- ADD/EDIT MAPPING MODAL --}}
    <flux:modal wire:model="showAddMappingModal" class="w-full max-w-2xl">
        <form wire:submit="saveMapping">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                    {{ $editingMappingId ? 'Edit' : 'Add' }} Field Mapping
                </h3>

                <div class="space-y-4">
                    {{-- Field Code --}}
                    <flux:field>
                        <flux:label>Marketplace Field Code</flux:label>
                        <flux:input 
                            wire:model="mappingFieldCode" 
                            placeholder="e.g., product-title, brand, category"
                            required
                        />
                        <flux:error name="mappingFieldCode" />
                    </flux:field>

                    {{-- Mapping Type --}}
                    <flux:field>
                        <flux:label>Mapping Type</flux:label>
                        <flux:select wire:model.live="mappingType" required>
                            <flux:select.option value="pim_field">PIM Field</flux:select.option>
                            <flux:select.option value="static_value">Static Value</flux:select.option>
                            <flux:select.option value="expression">Expression</flux:select.option>
                            <flux:select.option value="custom">Custom Logic</flux:select.option>
                        </flux:select>
                        <flux:error name="mappingType" />
                    </flux:field>

                    {{-- Source Field (for PIM field type) --}}
                    @if($mappingType === 'pim_field')
                        <flux:field>
                            <flux:label>PIM Source Field</flux:label>
                            <flux:select wire:model="sourceField" required>
                                <flux:select.option value="">Select a field...</flux:select.option>
                                @foreach($this->availablePimFields as $fieldKey => $fieldLabel)
                                    <flux:select.option value="{{ $fieldKey }}">{{ $fieldLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="sourceField" />
                        </flux:field>
                    @endif

                    {{-- Static Value (for static value type) --}}
                    @if($mappingType === 'static_value')
                        <flux:field>
                            <flux:label>Static Value</flux:label>
                            <flux:input 
                                wire:model="staticValue" 
                                placeholder="Enter static value..."
                                required
                            />
                            <flux:error name="staticValue" />
                        </flux:field>
                    @endif

                    {{-- Expression (for expression type) --}}
                    @if($mappingType === 'expression')
                        <flux:field>
                            <flux:label>Expression</flux:label>
                            <flux:textarea 
                                wire:model="mappingExpression" 
                                placeholder="e.g., product.name + ' - ' + variant.color"
                                rows="3"
                                required
                            />
                            <flux:error name="mappingExpression" />
                        </flux:field>
                    @endif

                    {{-- Mapping Level --}}
                    <flux:field>
                        <flux:label>Mapping Level</flux:label>
                        <flux:select wire:model.live="mappingLevel" required>
                            <flux:select.option value="global">Global (All Products)</flux:select.option>
                            <flux:select.option value="product">Product-Specific</flux:select.option>
                            <flux:select.option value="variant">Variant-Specific</flux:select.option>
                        </flux:select>
                        <flux:error name="mappingLevel" />
                    </flux:field>

                    {{-- Variant Scope (for variant level) --}}
                    @if($mappingLevel === 'variant')
                        <flux:field>
                            <flux:label>Variant Scope</flux:label>
                            <flux:input 
                                wire:model="variantScope" 
                                placeholder="e.g., color:blue, size:large"
                                required
                            />
                            <flux:error name="variantScope" />
                        </flux:field>
                    @endif

                    {{-- Notes --}}
                    <flux:field>
                        <flux:label>Notes (Optional)</flux:label>
                        <flux:textarea 
                            wire:model="notes" 
                            placeholder="Add any notes about this mapping..."
                            rows="2"
                        />
                    </flux:field>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 p-6 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button 
                    type="button" 
                    wire:click="closeAddMappingModal"
                    variant="outline"
                >
                    Cancel
                </flux:button>
                <flux:button 
                    type="submit"
                    variant="primary"
                >
                    {{ $editingMappingId ? 'Update' : 'Save' }} Mapping
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- TEST RESULTS MODAL --}}
    <flux:modal wire:model="showTestModal" class="w-full max-w-4xl">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                🧪 Mapping Test Results
            </h3>

            @if(is_array($testResults) && count($testResults) > 0)
                <div class="space-y-4">
                    @foreach($testResults as $result)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                            @if(isset($result['mapping']))
                                <h4 class="font-medium text-zinc-900 dark:text-zinc-100 mb-2">
                                    {{ $result['mapping']['channel_field_code'] }}
                                </h4>
                                <div class="bg-zinc-50 dark:bg-zinc-800 rounded p-3">
                                    <pre class="text-sm text-zinc-700 dark:text-zinc-300">{{ json_encode($result['result'], JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @else
                                <div class="bg-zinc-50 dark:bg-zinc-800 rounded p-3">
                                    <pre class="text-sm text-zinc-700 dark:text-zinc-300">{{ json_encode($result, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 p-6 border-t border-zinc-200 dark:border-zinc-700">
            <flux:button 
                wire:click="closeTestModal"
                variant="outline"
            >
                Close
            </flux:button>
        </div>
    </flux:modal>
</div>
