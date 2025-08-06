<div class="max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ $product ? 'Edit' : 'Create' }} Product</flux:heading>
        <flux:subheading>{{ $product ? 'Update the product details' : 'Add a new product' }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input 
            wire:model="name" 
            label="Name" 
            type="text" 
            placeholder="e.g., Black 60cm"
            required 
        />

        <flux:input 
            wire:model="slug" 
            label="Slug (URL-friendly name)" 
            type="text" 
            placeholder="Auto-generated from name"
        />

        <flux:textarea 
            wire:model="description" 
            label="Description" 
            placeholder="Describe this product..."
            rows="4"
        />

        <flux:field>
            <flux:label>Status</flux:label>
            <select wire:model="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="discontinued">Discontinued</option>
            </select>
            <flux:error name="status" />
        </flux:field>

        {{-- Product Features Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Product Features</flux:heading>
            
            <div class="space-y-4">
                @for($i = 1; $i <= 5; $i++)
                    <flux:textarea 
                        wire:model="product_features_{{ $i }}" 
                        label="Feature {{ $i }}" 
                        placeholder="Enter product feature {{ $i }}..."
                        rows="2"
                    />
                @endfor
            </div>
        </div>

        {{-- Product Details Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Product Details</flux:heading>
            
            <div class="space-y-4">
                @for($i = 1; $i <= 5; $i++)
                    <flux:textarea 
                        wire:model="product_details_{{ $i }}" 
                        label="Detail {{ $i }}" 
                        placeholder="Enter product detail {{ $i }}..."
                        rows="3"
                    />
                @endfor
            </div>
        </div>

        {{-- Images Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Product Images</flux:heading>
            
            {{-- Image Type Selection --}}
            <div class="mb-4">
                <flux:field>
                    <flux:label>Image Type</flux:label>
                    <flux:select wire:model="imageType">
                        <flux:select.option value="main">Main Image</flux:select.option>
                        <flux:select.option value="gallery">Gallery Image</flux:select.option>
                        <flux:select.option value="swatch">Swatch Image</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>
            
            {{-- Existing Images --}}
            @if($existingImages && count($existingImages) > 0)
                <div class="mb-4">
                    <flux:subheading>Current Images</flux:subheading>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                        @foreach($existingImages as $index => $image)
                            <div class="relative group">
                                <img src="{{ Storage::url($image['path']) }}" alt="Product image" class="w-full h-32 object-cover rounded-lg border">
                                <div class="absolute top-2 left-2">
                                    <flux:badge variant="outline" class="text-xs bg-white/90">
                                        {{ ucfirst($image['type']) }}
                                    </flux:badge>
                                </div>
                                <button 
                                    type="button"
                                    wire:click="removeExistingImage({{ $index }})"
                                    class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    ×
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            {{-- New Image Upload --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Add New Images</flux:label>
                    <input 
                        type="file" 
                        wire:model="newImages" 
                        multiple 
                        accept="image/*"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                    >
                    <flux:error name="newImages.*" />
                    <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                        You can select multiple images. Maximum 2MB per image.
                    </div>
                </flux:field>
                
                {{-- New Images Preview --}}
                @if($newImages && count($newImages) > 0)
                    <div>
                        <flux:subheading>New Images Preview</flux:subheading>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                            @foreach($newImages as $index => $image)
                                <div class="relative group">
                                    <img src="{{ $image->temporaryUrl() }}" alt="New image preview" class="w-full h-32 object-cover rounded-lg border">
                                    <button 
                                        type="button"
                                        wire:click="removeNewImage({{ $index }})"
                                        class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        ×
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                {{-- Upload Progress --}}
                <div wire:loading wire:target="newImages" class="text-blue-600">
                    Uploading images...
                </div>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <flux:button type="submit" variant="primary">
                {{ $product ? 'Update' : 'Create' }} Product
            </flux:button>
            <flux:button variant="ghost" :href="route('products.index')" wire:navigate>
                Cancel
            </flux:button>
        </div>
    </form>
</div>