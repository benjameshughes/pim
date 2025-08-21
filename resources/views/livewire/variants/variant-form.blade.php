<div class="mx-auto space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex justify-between items-center gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $isEditing ? 'Edit Variant' : 'Create Variant' }}
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $isEditing ? 'Update variant details' : 'Add a new product variant' }}
            </p>
        </div>
        <flux:button wire:navigate href="{{ route('variants.index') }}" variant="ghost" icon="arrow-left">
            Back to Variants
        </flux:button>
    </div>

    {{-- 💎 VARIANT FORM --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form wire:submit.prevent="save" class="space-y-6">
            
            {{-- Product Selection --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Product</flux:label>
                    <flux:select wire:model="product_id">
                        <flux:select.option value="">Select a product...</flux:select.option>
                        @foreach ($products as $product)
                            <flux:select.option value="{{ $product->id }}">
                                {{ $product->name }} ({{ $product->parent_sku }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="product_id" />
                </flux:field>

                <flux:field>
                    <flux:label>SKU</flux:label>
                    <flux:input wire:model="sku" placeholder="e.g., WIDGET-001-RED" />
                    <flux:error name="sku" />
                </flux:field>
            </div>

            {{-- Basic Details --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Title</flux:label>
                    <flux:input wire:model="title" placeholder="Optional display title" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>Color</flux:label>
                    <div class="relative">
                        <flux:input wire:model="color" placeholder="e.g., Red, Blue, White" list="color-suggestions" />
                        <datalist id="color-suggestions">
                            @foreach ($existingColors as $existingColor)
                                <option value="{{ $existingColor }}">{{ $existingColor }}</option>
                            @endforeach
                        </datalist>
                    </div>
                    <flux:error name="color" />
                    @if($existingColors->count())
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            💡 Existing colors: {{ $existingColors->take(5)->join(', ') }}{{ $existingColors->count() > 5 ? ' +' . ($existingColors->count() - 5) . ' more' : '' }}
                        </p>
                    @endif
                </flux:field>
            </div>

            {{-- Dimensions --}}
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Dimensions</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <flux:field>
                        <flux:label>Width (cm)</flux:label>
                        <div class="relative">
                            <flux:input type="number" wire:model="width" min="1" step="0.1" list="width-suggestions" />
                            <datalist id="width-suggestions">
                                @foreach ($existingWidths as $width)
                                    <option value="{{ $width }}">{{ $width }}cm</option>
                                @endforeach
                            </datalist>
                        </div>
                        <flux:error name="width" />
                        @if($existingWidths->count())
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                💡 Common widths: {{ $existingWidths->take(7)->join('cm, ') }}cm
                            </p>
                        @endif
                    </flux:field>

                    <flux:field>
                        <flux:label>Drop (cm)</flux:label>
                        <flux:input type="number" wire:model="drop" min="1" step="0.1" placeholder="Fixed drop" />
                        <flux:error name="drop" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Max Drop (cm)</flux:label>
                        <flux:input type="number" wire:model="max_drop" min="1" step="0.1" placeholder="Maximum drop" />
                        <flux:error name="max_drop" />
                    </flux:field>
                </div>
            </div>

            {{-- Pricing & Stock --}}
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Pricing & Stock</h3>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <flux:field>
                        <flux:label>Price (£)</flux:label>
                        <flux:input type="number" wire:model="price" min="0" step="0.01" />
                        <flux:error name="price" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Stock Level</flux:label>
                        <flux:input type="number" wire:model="stock_level" min="0" />
                        <flux:error name="stock_level" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status">
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:navigate href="{{ route('variants.index') }}" variant="ghost">
                    Cancel
                </flux:button>
                
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $isEditing ? 'Update Variant' : 'Create Variant' }}
                </flux:button>
            </div>
        </form>
    </div>
</div>