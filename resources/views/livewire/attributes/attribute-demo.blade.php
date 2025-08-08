<x-page-template 
    title="Attribute System Demo"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('dashboard')],
        ['name' => 'Attributes Demo']
    ]">
    
    <x-slot:subtitle>
        Interactive demonstration of the core attribute system
    </x-slot:subtitle>

    <div class="space-y-8">
        <!-- Core Attributes Overview -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                Core Window Treatment Attributes
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($coreAttributes as $attribute)
                <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">{{ $attribute->label }}</h3>
                        <span class="text-xs bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded">{{ $attribute->category }}</span>
                    </div>
                    
                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">{{ $attribute->description }}</p>
                    
                    <div class="text-xs text-zinc-500">
                        <span class="inline-block">Type: {{ ucfirst($attribute->data_type) }}</span>
                        <span class="mx-2">•</span>
                        <span class="inline-block">Applies to: {{ ucfirst($attribute->applies_to) }}</span>
                    </div>
                    
                    @if(isset($attribute->validation_rules['options']))
                    <div class="mt-2">
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300">Sample options:</span>
                        <div class="flex flex-wrap gap-1 mt-1">
                            @foreach(array_slice($attribute->validation_rules['options'], 0, 4) as $option)
                            <span class="text-xs bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 px-2 py-1 rounded">
                                {{ ucwords(str_replace(['-', '_'], ' ', $option)) }}
                            </span>
                            @endforeach
                            @if(count($attribute->validation_rules['options']) > 4)
                            <span class="text-xs text-zinc-500">+{{ count($attribute->validation_rules['options']) - 4 }} more</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        <!-- Interactive Tester -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                Attribute Value Tester
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Category Selection -->
                <div>
                    <flux:label for="category">Category</flux:label>
                    <flux:select wire:model.live="selectedCategory" id="category">
                        @foreach($categories as $category)
                        <flux:select.option value="{{ $category }}">
                            {{ ucfirst(str_replace('_', ' ', $category)) }}
                        </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Attribute Selection -->
                <div>
                    <flux:label for="attribute">Attribute</flux:label>
                    <flux:select wire:model.live="selectedAttribute" id="attribute" :disabled="!$selectedCategory">
                        <flux:select.option value="">Choose an attribute...</flux:select.option>
                        @foreach($attributesInCategory as $attribute)
                        <flux:select.option value="{{ $attribute->key }}">
                            {{ $attribute->label }}
                        </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Value Input -->
                <div>
                    <flux:label for="value">Value</flux:label>
                    @if($currentAttribute && isset($currentAttribute->validation_rules['options']))
                        <flux:select wire:model.live="attributeValue" id="value">
                            <flux:select.option value="">Choose a value...</flux:select.option>
                            @foreach($attributeOptions as $option)
                            <flux:select.option value="{{ $option }}">
                                {{ ucwords(str_replace(['-', '_'], ' ', $option)) }}
                            </flux:select.option>
                            @endforeach
                        </flux:select>
                    @else
                        <flux:input 
                            wire:model.live="attributeValue" 
                            id="value" 
                            placeholder="Enter a value..."
                            :disabled="!$selectedAttribute"
                        />
                    @endif
                </div>
            </div>

            @if($currentAttribute)
            <div class="bg-zinc-50 dark:bg-zinc-700 rounded-lg p-4 mb-4">
                <h3 class="font-medium text-zinc-900 dark:text-zinc-100 mb-2">Attribute Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">Key:</span> {{ $currentAttribute->key }}
                    </div>
                    <div>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">Data Type:</span> {{ ucfirst($currentAttribute->data_type) }}
                    </div>
                    <div>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">Category:</span> {{ ucfirst($currentAttribute->category) }}
                    </div>
                    <div>
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">Applies To:</span> {{ ucfirst($currentAttribute->applies_to) }}
                    </div>
                </div>
                
                @if($currentAttribute->description)
                <div class="mt-2">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">Description:</span>
                    <span class="text-zinc-600 dark:text-zinc-400">{{ $currentAttribute->description }}</span>
                </div>
                @endif

                @if($currentAttribute->validation_rules)
                <div class="mt-2">
                    <span class="font-medium text-zinc-700 dark:text-zinc-300">Validation Rules:</span>
                    <code class="text-xs bg-zinc-200 dark:bg-zinc-600 px-2 py-1 rounded ml-2">
                        {{ json_encode($currentAttribute->validation_rules) }}
                    </code>
                </div>
                @endif
            </div>
            @endif

            <!-- Validation Button -->
            <div class="mb-4">
                <flux:button 
                    wire:click="validateValue" 
                    variant="primary"
                    :disabled="!$selectedAttribute || !$attributeValue"
                >
                    Validate Value
                </flux:button>
            </div>

            <!-- Validation Result -->
            @if($validationResult)
            <div class="p-4 rounded-lg {{ $validationResult['valid'] ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                <div class="flex items-center">
                    @if($validationResult['valid'])
                        <flux:icon name="check-circle" class="h-5 w-5 text-green-600 mr-2" />
                        <span class="font-medium text-green-800">Valid!</span>
                    @else
                        <flux:icon name="x-circle" class="h-5 w-5 text-red-600 mr-2" />
                        <span class="font-medium text-red-800">Invalid</span>
                    @endif
                </div>
                
                <div class="mt-2 text-sm {{ $validationResult['valid'] ? 'text-green-700' : 'text-red-700' }}">
                    {{ $validationResult['message'] }}
                </div>
                
                @if($validationResult['valid'] && $validationResult['formatted'])
                <div class="mt-2">
                    <span class="text-sm font-medium {{ $validationResult['valid'] ? 'text-green-800' : 'text-red-800' }}">
                        Formatted value:
                    </span>
                    <span class="text-sm {{ $validationResult['valid'] ? 'text-green-700' : 'text-red-700' }}">
                        "{{ $validationResult['formatted'] }}"
                    </span>
                </div>
                @endif
            </div>
            @endif
        </div>

        <!-- All Attributes by Category -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">
                All Attributes by Category
            </h2>
            
            @foreach($categories as $category)
            <div class="mb-6 last:mb-0">
                <h3 class="font-medium text-zinc-800 dark:text-zinc-200 mb-3 capitalize">
                    {{ str_replace('_', ' ', $category) }} 
                    <span class="text-sm text-zinc-500">({{ $attributesInCategory->where('category', $category)->count() }} attributes)</span>
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($categorizedAttributes->get($category, collect()) as $attribute)
                    <div class="text-sm border border-zinc-200 dark:border-zinc-700 rounded p-3">
                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $attribute->label }}</div>
                        <div class="text-zinc-600 dark:text-zinc-400">{{ $attribute->key }}</div>
                        <div class="text-xs text-zinc-500 mt-1">{{ ucfirst($attribute->data_type) }} • {{ ucfirst($attribute->applies_to) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</x-page-template>