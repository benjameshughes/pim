{{-- Step 2: Variants --}}
<div class="max-w-7xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 transition-all duration-500 ease-in-out transform"
         x-transition:enter="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4">
         
        <div class="flex items-center gap-3 mb-6">
            <div class="flex items-center justify-center w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                <flux:icon name="grid-3x3" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Product Variants</h2>
        </div>
        
        @error('variants')
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-red-600 text-sm">{{ $message }}</p>
            </div>
        @enderror
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Colors --}}
            <div>
                <h3 class="font-medium mb-2">Colors</h3>
                <div class="flex gap-2 mb-2">
                    <flux:input 
                        wire:model="new_color" 
                        wire:keydown.enter="addColor" 
                        placeholder="Add color" 
                        class="flex-1" 
                        tabindex="1"
                        x-init="$nextTick(() => $el.focus())"
                    />
                    <flux:button wire:click="addColor" size="sm" tabindex="4">Add</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($colors as $index => $color)
                        <div 
                            x-data="{ editing: false, value: '{{ $color }}' }"
                            class="relative group"
                        >
                            <!-- Display Mode -->
                            <div x-show="!editing" class="flex items-center">
                                <flux:badge 
                                    class="cursor-pointer hover:bg-gray-100 pr-8"
                                    @click="editing = true"
                                >
                                    {{ $color }}
                                </flux:badge>
                                <button 
                                    wire:click="removeColor({{ $index }})"
                                    class="absolute right-1 text-gray-400 hover:text-red-500 text-xs"
                                    title="Remove color"
                                    tabindex="{{ 20 + $index }}"
                                >
                                    ✕
                                </button>
                            </div>
                            
                            <!-- Edit Mode -->
                            <div x-show="editing" class="flex items-center gap-1">
                                <input 
                                    x-model="value"
                                    @keydown.enter="$wire.updateColor({{ $index }}, value); editing = false"
                                    @keydown.escape="editing = false; value = '{{ $color }}'"
                                    @blur="$wire.updateColor({{ $index }}, value); editing = false"
                                    class="px-2 py-1 text-sm border rounded w-20"
                                    x-init="$nextTick(() => editing && $el.focus())"
                                >
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            {{-- Widths --}}
            <div>
                <h3 class="font-medium mb-2">Widths (cm)</h3>
                <div class="flex gap-2 mb-2">
                    <flux:input 
                        wire:model="new_width" 
                        wire:keydown.enter="addWidth" 
                        type="number" 
                        placeholder="Width" 
                        class="flex-1" 
                        tabindex="2"
                    />
                    <flux:button wire:click="addWidth" size="sm" tabindex="5">Add</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($widths as $index => $width)
                        <div 
                            x-data="{ editing: false, value: '{{ $width }}' }"
                            class="relative group"
                        >
                            <!-- Display Mode -->
                            <div x-show="!editing" class="flex items-center">
                                <flux:badge 
                                    class="cursor-pointer hover:bg-gray-100 pr-8"
                                    @click="editing = true"
                                >
                                    {{ $width }}cm
                                </flux:badge>
                                <button 
                                    wire:click="removeWidth({{ $index }})"
                                    class="absolute right-1 text-gray-400 hover:text-red-500 text-xs"
                                    title="Remove width"
                                    tabindex="{{ 30 + $index }}"
                                >
                                    ✕
                                </button>
                            </div>
                            
                            <!-- Edit Mode -->
                            <div x-show="editing" class="flex items-center gap-1">
                                <input 
                                    x-model="value"
                                    type="number"
                                    @keydown.enter="$wire.updateWidth({{ $index }}, value); editing = false"
                                    @keydown.escape="editing = false; value = '{{ $width }}'"
                                    @blur="$wire.updateWidth({{ $index }}, value); editing = false"
                                    class="px-2 py-1 text-sm border rounded w-16"
                                    x-init="$nextTick(() => editing && $el.focus())"
                                >
                                <span class="text-xs text-gray-500">cm</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            
            {{-- Drops --}}
            <div>
                <h3 class="font-medium mb-2">Drops (cm)</h3>
                <div class="flex gap-2 mb-2">
                    <flux:input 
                        wire:model="new_drop" 
                        wire:keydown.enter="addDrop" 
                        type="number" 
                        placeholder="Drop" 
                        class="flex-1" 
                        tabindex="3"
                    />
                    <flux:button wire:click="addDrop" size="sm" tabindex="6">Add</flux:button>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($drops as $index => $drop)
                        <div 
                            x-data="{ editing: false, value: '{{ $drop }}' }"
                            class="relative group"
                        >
                            <!-- Display Mode -->
                            <div x-show="!editing" class="flex items-center">
                                <flux:badge 
                                    class="cursor-pointer hover:bg-gray-100 pr-8"
                                    @click="editing = true"
                                >
                                    {{ $drop }}cm
                                </flux:badge>
                                <button 
                                    wire:click="removeDrop({{ $index }})"
                                    class="absolute right-1 text-gray-400 hover:text-red-500 text-xs"
                                    title="Remove drop"
                                    tabindex="{{ 40 + $index }}"
                                >
                                    ✕
                                </button>
                            </div>
                            
                            <!-- Edit Mode -->
                            <div x-show="editing" class="flex items-center gap-1">
                                <input 
                                    x-model="value"
                                    type="number"
                                    @keydown.enter="$wire.updateDrop({{ $index }}, value); editing = false"
                                    @keydown.escape="editing = false; value = '{{ $drop }}'"
                                    @blur="$wire.updateDrop({{ $index }}, value); editing = false"
                                    class="px-2 py-1 text-sm border rounded w-16"
                                    x-init="$nextTick(() => editing && $el.focus())"
                                >
                                <span class="text-xs text-gray-500">cm</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        
        {{-- Generated Variants Preview --}}
        @if(count($generated_variants) > 0)
            <div class="mt-6 p-6 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-800">
                <div class="flex items-center gap-2 mb-4">
                    <flux:icon name="grid-3x3" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                    <h4 class="font-semibold text-purple-900 dark:text-purple-100">Generated Variants ({{ count($generated_variants) }})</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 max-h-40 overflow-y-auto">
                    @foreach($generated_variants as $variant)
                        <div class="bg-white dark:bg-gray-700 rounded-lg border border-purple-200 dark:border-purple-700 p-3 hover:shadow-sm transition-shadow duration-200">
                            <div class="font-mono font-semibold text-purple-700 dark:text-purple-300">{{ $variant['sku'] }}</div>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @if($variant['color'])
                                    <span class="inline-flex items-center px-2 py-1 bg-purple-100 dark:bg-purple-800 text-purple-700 dark:text-purple-200 text-xs rounded-full">
                                        <flux:icon name="palette" class="h-3 w-3 mr-1" />
                                        {{ $variant['color'] }}
                                    </span>
                                @endif
                                @if($variant['width'])
                                    <span class="inline-flex items-center px-2 py-1 bg-blue-100 dark:bg-blue-800 text-blue-700 dark:text-blue-200 text-xs rounded-full">
                                        <flux:icon name="move-horizontal" class="h-3 w-3 mr-1" />
                                        {{ $variant['width'] }}cm
                                    </span>
                                @endif
                                @if($variant['drop'])
                                    <span class="inline-flex items-center px-2 py-1 bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-200 text-xs rounded-full">
                                        <flux:icon name="move-vertical" class="h-3 w-3 mr-1" />
                                        {{ $variant['drop'] }}cm
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>