<div>
    <x-breadcrumb :items="[
        ['name' => 'Operations'],
        ['name' => 'Bulk Operations'],
        ['name' => 'Bulk Attributes']
    ]" />

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Bulk Operations - Attributes</flux:heading>
        <flux:subheading>Manage attributes across multiple products and variants</flux:subheading>
    </div>

    <!-- Tab Navigation -->
    <x-route-tabs :tabs="$tabs" class="mb-6">
        <div class="p-6">
            <!-- Flash Messages -->
            @if (session()->has('message'))
                <div class="mb-6 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                    <div class="flex">
                        <flux:icon name="check-circle" class="w-5 h-5 text-emerald-600 mr-2" />
                        <div class="text-sm text-emerald-700 dark:text-emerald-300">
                            {{ session('message') }}
                        </div>
                    </div>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="flex">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 mr-2" />
                        <div class="text-sm text-red-700 dark:text-red-300">
                            {{ session('error') }}
                        </div>
                    </div>
                </div>
            @endif
            @if($selectedVariantsCount === 0)
                <!-- No Selection State -->
                <div class="text-center py-12">
                    <flux:icon name="tag" class="w-16 h-16 text-zinc-400 mx-auto mb-4" />
                    <flux:heading size="lg" class="text-zinc-600 dark:text-zinc-400 mb-2">No Variants Selected</flux:heading>
                    <flux:subheading class="text-zinc-500 dark:text-zinc-500 mb-4">
                        Select variants from the Overview tab to manage their attributes
                    </flux:subheading>
                    <flux:button wire:navigate href="{{ route('operations.bulk.overview') }}" variant="primary">
                        <flux:icon name="chart-bar" class="w-4 h-4 mr-2" />
                        Go to Overview
                    </flux:button>
                </div>
            @else
                <!-- Selected Variants Summary -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">
                                {{ $selectedVariantsCount }} variants selected
                            </h3>
                            <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                Apply attributes to all selected variants
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Add New Attributes -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <flux:heading size="lg" class="mb-4">Add New Attribute</flux:heading>
                            
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <flux:field>
                                        <flux:label>Attribute Key</flux:label>
                                        <flux:input 
                                            wire:model.live="bulkAttributeKey"
                                            placeholder="e.g., material, brand, weight"
                                        />
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Attribute Value</flux:label>
                                        <flux:input 
                                            wire:model.live="bulkAttributeValue"
                                            placeholder="e.g., Cotton, Nike, 500g"
                                        />
                                    </flux:field>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <flux:field>
                                        <flux:label>Apply To</flux:label>
                                        <flux:select wire:model.live="bulkAttributeType">
                                            <flux:select.option value="product">Product Level</flux:select.option>
                                            <flux:select.option value="variant">Variant Level</flux:select.option>
                                        </flux:select>
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Data Type</flux:label>
                                        <flux:select wire:model.live="bulkAttributeDataType">
                                            <flux:select.option value="string">Text</flux:select.option>
                                            <flux:select.option value="number">Number</flux:select.option>
                                            <flux:select.option value="boolean">Yes/No</flux:select.option>
                                            <flux:select.option value="json">JSON</flux:select.option>
                                        </flux:select>
                                    </flux:field>
                                    
                                    <flux:field>
                                        <flux:label>Category</flux:label>
                                        <flux:select wire:model.live="bulkAttributeCategory">
                                            <flux:select.option value="general">General</flux:select.option>
                                            <flux:select.option value="specifications">Specifications</flux:select.option>
                                            <flux:select.option value="marketing">Marketing</flux:select.option>
                                            <flux:select.option value="compliance">Compliance</flux:select.option>
                                        </flux:select>
                                    </flux:field>
                                </div>

                                <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                    <flux:button 
                                        wire:click="applyBulkAttribute"
                                        variant="primary"
                                        :disabled="!$this->bulkAttributeKey || !$this->bulkAttributeValue"
                                    >
                                        <flux:icon name="plus" class="w-4 h-4 mr-2" />
                                        Apply to {{ $selectedVariantsCount }} Variants
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Update Existing Attributes -->
                    <div class="space-y-6">
                        <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                            <flux:heading size="lg" class="mb-4">Update Existing Attributes</flux:heading>
                            
                            @if(empty($existingAttributes['product']) && empty($existingAttributes['variant']))
                                <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                                    <flux:icon name="tag" class="w-12 h-12 mx-auto mb-3 opacity-50" />
                                    <p class="text-sm">No existing attributes found on selected variants</p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    <flux:field>
                                        <flux:label>Select Attribute to Update</flux:label>
                                        <flux:select wire:model.live="selectedExistingAttribute">
                                            <flux:select.option value="">Choose an attribute...</flux:select.option>
                                            
                                            @if(!empty($existingAttributes['product']))
                                                <optgroup label="Product Attributes">
                                                    @foreach($existingAttributes['product'] as $attr)
                                                        <flux:select.option value="product:{{ $attr['key'] }}">
                                                            {{ $attr['key'] }} ({{ $attr['summary'] }})
                                                        </flux:select.option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                            
                                            @if(!empty($existingAttributes['variant']))
                                                <optgroup label="Variant Attributes">
                                                    @foreach($existingAttributes['variant'] as $attr)
                                                        <flux:select.option value="variant:{{ $attr['key'] }}">
                                                            {{ $attr['key'] }} ({{ $attr['summary'] }})
                                                        </flux:select.option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>New Value</flux:label>
                                        <flux:input 
                                            wire:model.live="updateAttributeValue"
                                            placeholder="Enter new value for all selected variants"
                                        />
                                    </flux:field>

                                    <div class="pt-4 border-t border-zinc-200 dark:border-zinc-700">
                                        <flux:button 
                                            wire:click="updateExistingAttribute"
                                            variant="outline"
                                            :disabled="!$this->selectedExistingAttribute || !$this->updateAttributeValue"
                                        >
                                            <flux:icon name="pencil" class="w-4 h-4 mr-2" />
                                            Update Attribute
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Existing Attributes Overview -->
                @if(!empty($existingAttributes['product']) || !empty($existingAttributes['variant']))
                    <div class="mt-8">
                        <flux:heading size="lg" class="mb-4">Current Attributes on Selected Variants</flux:heading>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Product Attributes -->
                            @if(!empty($existingAttributes['product']))
                                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                                    <div class="flex items-center mb-4">
                                        <flux:icon name="cube" class="w-5 h-5 mr-2 text-blue-600" />
                                        <flux:heading size="md">Product Attributes</flux:heading>
                                    </div>
                                    <div class="space-y-3">
                                        @foreach($existingAttributes['product'] as $attr)
                                            <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-700">
                                                <div>
                                                    <div class="font-medium text-sm">{{ $attr['key'] }}</div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $attr['category'] }} • {{ $attr['data_type'] }}
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm {{ $attr['is_consistent'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                                        {{ $attr['summary'] }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $attr['count'] }} items
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Variant Attributes -->
                            @if(!empty($existingAttributes['variant']))
                                <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                                    <div class="flex items-center mb-4">
                                        <flux:icon name="squares-plus" class="w-5 h-5 mr-2 text-purple-600" />
                                        <flux:heading size="md">Variant Attributes</flux:heading>
                                    </div>
                                    <div class="space-y-3">
                                        @foreach($existingAttributes['variant'] as $attr)
                                            <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-700">
                                                <div>
                                                    <div class="font-medium text-sm">{{ $attr['key'] }}</div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $attr['category'] }} • {{ $attr['data_type'] }}
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm {{ $attr['is_consistent'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                                        {{ $attr['summary'] }}
                                                    </div>
                                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                                        {{ $attr['count'] }} items
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </x-route-tabs>
</div>