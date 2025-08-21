<div class="space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $isEditing ? 'Edit Product' : 'Create Product' }}
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $isEditing ? 'Update product information' : 'Add a new product to your catalog' }}
            </p>
        </div>
        
        <flux:button wire:navigate href="{{ route('products.index') }}" variant="ghost" icon="arrow-left">
            Back to Products
        </flux:button>
    </div>

    {{-- 📋 PRODUCT FORM --}}
    <div class="bg-white dark:bg-gray-800">
        <form wire:submit="save" class="p-6 space-y-6">
            {{-- Basic Information --}}
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Basic Information</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Product Name --}}
                    <div>
                        <flux:input 
                            wire:model.blur="name"
                            label="Product Name"
                            placeholder="e.g., Blackout Roller Blind"
                            required
                        />
                        @error('name') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>

                    {{-- Parent SKU --}}
                    <div>
                        <flux:input 
                            wire:model.blur="parent_sku"
                            label="Parent SKU"
                            placeholder="e.g., 026"
                            required
                        />
                        @error('parent_sku') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <flux:textarea 
                        wire:model.blur="description"
                        label="Description"
                        placeholder="Describe your product..."
                        rows="3"
                    />
                    @error('description') 
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </div>

                {{-- Status & Image --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Status --}}
                    <div>
                        <flux:select wire:model="status" label="Status">
                            <flux:select.option value="active">Active</flux:select.option>
                            <flux:select.option value="inactive">Inactive</flux:select.option>
                        </flux:select>
                        @error('status') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>

                    {{-- Image URL --}}
                    <div>
                        <flux:input 
                            wire:model.blur="image_url"
                            label="Image URL (optional)"
                            placeholder="https://example.com/image.jpg"
                            type="url"
                        />
                        @error('image_url') 
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </div>
                </div>

                {{-- Image Preview --}}
                @if ($image_url)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Image Preview
                        </label>
                        <div class="w-32 h-32 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <img src="{{ $image_url }}" alt="Preview" class="w-full h-full object-cover" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            <div class="w-full h-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center" style="display: none;">
                                <flux:icon name="photo" class="w-8 h-8 text-gray-400" />
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:navigate href="{{ route('products.index') }}" variant="ghost">
                    Cancel
                </flux:button>
                
                <flux:button type="submit" variant="primary">
                    {{ $isEditing ? 'Update Product' : 'Create Product' }}
                </flux:button>
            </div>
        </form>
    </div>

</div>
