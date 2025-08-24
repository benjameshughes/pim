{{-- Step 1: Product Info --}}
<div class="max-w-7xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 transition-all duration-500 ease-in-out transform"
         x-transition:enter="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4">
        
        <div class="flex items-center gap-3 mb-6">
            <div class="flex items-center justify-center w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                <flux:icon name="info" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Product Information</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <flux:field>
                    <flux:label>Product Name *</flux:label>
                    <flux:input 
                        wire:model.live="name" 
                        tabindex="1"
                        x-init="$nextTick(() => $el.focus())"
                    />
                    <flux:error name="name" />
                </flux:field>
            </div>
            
            <flux:field>
                <flux:label>Parent SKU *</flux:label>
                <flux:input 
                    wire:model.live="parent_sku" 
                    placeholder="e.g. 001, 123, 999" 
                    tabindex="2"
                />
                <flux:error name="parent_sku" />
                <flux:description>Must be exactly 3 digits</flux:description>
            </flux:field>
            
            <flux:field>
                <flux:label>Brand</flux:label>
                <flux:input 
                    wire:model.blur="brand" 
                    placeholder="e.g. Nike, Apple, BMW" 
                    tabindex="3"
                />
                <flux:error name="brand" />
                <flux:description>Brand will be set as an attribute on product and variants</flux:description>
            </flux:field>
            
            <flux:field>
                <flux:label>Status *</flux:label>
                <flux:select wire:model="status" tabindex="4">
                    <flux:select.option value="draft">Draft</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                    <flux:select.option value="archived">Archived</flux:select.option>
                </flux:select>
                <flux:error name="status" />
            </flux:field>
            
            <div class="md:col-span-2">
                <flux:field>
                    <flux:label>Description</flux:label>
                    <flux:textarea 
                        wire:model.blur="description" 
                        rows="3" 
                        tabindex="5"
                    />
                    <flux:error name="description" />
                </flux:field>
            </div>
        </div>
    </div>
</div>