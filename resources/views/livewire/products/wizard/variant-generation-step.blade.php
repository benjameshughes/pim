<div class="p-4 space-y-4">
    {{-- Step Header --}}
    <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/20">
                <flux:icon name="cube" class="w-4 h-4 text-purple-600 dark:text-purple-400" />
            </div>
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Product Variants
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Define colors, sizes, and dimensions to automatically generate product variants.
                </p>
            </div>
        </div>
    </div>

    {{-- Configuration Section --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- SKU Configuration --}}
        <div class="space-y-4">
            <flux:field>
                <flux:label>Parent SKU for Variants</flux:label>
                <flux:input
                    wire:model.live="parent_sku"
                    wire:change="generateVariants"
                    placeholder="e.g. BLIND-001"
                />
                <flux:description>Base SKU that will be used to generate variant SKUs</flux:description>
            </flux:field>
            
            <flux:field>
                <flux:checkbox 
                    wire:model.live="enable_sku_grouping"
                    wire:change="generateVariants"
                    label="Enable SKU grouping (PARENT-001, PARENT-002, etc.)"
                />
            </flux:field>
        </div>

        {{-- Variant Count Display --}}
        <div class="flex items-center justify-center bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-6">
            <div class="text-center">
                <div class="flex items-center justify-center w-12 h-12 bg-blue-100 dark:bg-blue-900/50 rounded-full mb-3">
                    <flux:icon name="cube-transparent" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                    {{ $total_variants }}
                </div>
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    Variants Generated
                </div>
            </div>
        </div>
    </div>

    {{-- Colors Section --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center gap-2 mb-4">
            <flux:icon name="swatch" class="w-5 h-5 text-pink-500" />
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Colors</h3>
        </div>
        
        {{-- Add Color --}}
        <div class="flex gap-2 mb-4">
            <flux:input 
                wire:model="new_color"
                wire:keydown.enter="addColor"
                placeholder="Enter color name..."
                class="flex-1"
            />
            <flux:button 
                wire:click="addColor"
                variant="primary"
                size="sm"
                icon="plus"
            >
                Add
            </flux:button>
        </div>

        {{-- Color List --}}
        <div class="flex flex-wrap gap-2">
            @foreach($colors as $color)
                <flux:badge 
                    color="pink"
                    size="sm"
                    class="group"
                >
                    <span>{{ $color }}</span>
                    <button 
                        wire:click="removeColor('{{ $color }}')"
                        class="ml-1 opacity-60 hover:opacity-100 transition-opacity"
                    >
                        <flux:icon name="x-mark" class="w-3 h-3" />
                    </button>
                </flux:badge>
            @endforeach
        </div>
    </div>

    {{-- Dimensions Section --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Widths --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon name="arrows-right-left" class="w-5 h-5 text-green-500" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Widths (cm)</h3>
            </div>
            
            <div class="flex gap-2 mb-4">
                <flux:input 
                    type="number"
                    wire:model="new_width"
                    wire:keydown.enter="addWidth"
                    placeholder="Width in cm..."
                    min="1"
                    class="flex-1"
                />
                <flux:button 
                    wire:click="addWidth"
                    variant="primary"
                    size="sm"
                    icon="plus"
                    color="green"
                >
                    Add
                </flux:button>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($widths as $width)
                    <flux:badge 
                        color="green"
                        size="sm"
                        class="group"
                    >
                        <span>{{ $width }}cm</span>
                        <button 
                            wire:click="removeWidth({{ $width }})"
                            class="ml-1 opacity-60 hover:opacity-100 transition-opacity"
                        >
                            <flux:icon name="x-mark" class="w-3 h-3" />
                        </button>
                    </flux:badge>
                @endforeach
            </div>
        </div>

        {{-- Drops --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-2 mb-4">
                <flux:icon name="arrows-up-down" class="w-5 h-5 text-purple-500" />
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Drops (cm)</h3>
            </div>
            
            <div class="flex gap-2 mb-4">
                <flux:input 
                    type="number"
                    wire:model="new_drop"
                    wire:keydown.enter="addDrop"
                    placeholder="Drop in cm..."
                    min="1"
                    class="flex-1"
                />
                <flux:button 
                    wire:click="addDrop"
                    variant="primary"
                    size="sm"
                    icon="plus"
                    color="purple"
                >
                    Add
                </flux:button>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach($drops as $drop)
                    <flux:badge 
                        color="purple"
                        size="sm"
                        class="group"
                    >
                        <span>{{ $drop }}cm</span>
                        <button 
                            wire:click="removeDrop({{ $drop }})"
                            class="ml-1 opacity-60 hover:opacity-100 transition-opacity"
                        >
                            <flux:icon name="x-mark" class="w-3 h-3" />
                        </button>
                    </flux:badge>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Generated Variants Preview --}}
    @if(count($generated_variants) > 0)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50">
                    <flux:icon name="clipboard-document-list" class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Generated Variants ({{ count($generated_variants) }})
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Preview of all variants that will be created
                    </p>
                </div>
            </div>
            
            <div class="max-h-64 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($generated_variants as $index => $variant)
                        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-3 shadow-sm">
                            <div class="font-mono text-sm font-medium text-gray-900 dark:text-white mb-2">
                                {{ $variant['sku'] }}
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs">
                                @if($variant['color'])
                                    <flux:badge size="xs" color="pink">
                                        {{ $variant['color'] }}
                                    </flux:badge>
                                @endif
                                @if($variant['width'])
                                    <flux:badge size="xs" color="green">
                                        {{ $variant['width'] }}cm W
                                    </flux:badge>
                                @endif
                                @if($variant['drop'])
                                    <flux:badge size="xs" color="purple">
                                        {{ $variant['drop'] }}cm D
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Quick Add Buttons --}}
    <div class="bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center gap-2 mb-4">
            <flux:icon name="bolt" class="w-5 h-5 text-yellow-500" />
            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Quick Add</h4>
            <flux:badge size="sm" color="yellow">Time Saver</flux:badge>
        </div>
        <div class="space-y-4">
            {{-- Preset Buttons --}}
            <div>
                <flux:label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
                    Presets
                </flux:label>
                <div class="flex flex-wrap gap-2">
                    <flux:button 
                        wire:click="loadPreset('roller_blinds')"
                        size="xs"
                        variant="outline"
                        icon="scroll"
                    >
                        Roller Blinds
                    </flux:button>
                    <flux:button 
                        wire:click="loadPreset('venetian_blinds')"
                        size="xs"
                        variant="outline"
                        icon="menu"
                    >
                        Venetian Blinds
                    </flux:button>
                </div>
            </div>
            {{-- Popular Colors --}}
            <div>
                <flux:label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
                    Popular Colors
                </flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach(['Black', 'White', 'Grey', 'Blue', 'Red'] as $color)
                        <flux:button 
                            wire:click="quickAddColor('{{ $color }}')"
                            size="xs"
                            variant="outline"
                        >
                            {{ $color }}
                        </flux:button>
                    @endforeach
                </div>
            </div>
            {{-- Standard Widths --}}
            <div>
                <flux:label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
                    Standard Widths (cm)
                </flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach([60, 90, 120, 150, 180, 210, 240] as $width)
                        <flux:button 
                            wire:click="quickAddWidth({{ $width }})"
                            size="xs"
                            variant="outline"
                        >
                            {{ $width }}cm
                        </flux:button>
                    @endforeach
                </div>
            </div>
            {{-- Standard Drops --}}
            <div>
                <flux:label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
                    Standard Drops (cm)
                </flux:label>
                <div class="flex flex-wrap gap-2">
                    @foreach([140, 160, 210] as $drop)
                        <flux:button 
                            wire:click="quickAddDrop({{ $drop }})"
                            size="xs"
                            variant="outline"
                        >
                            {{ $drop }}cm
                        </flux:button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</div>