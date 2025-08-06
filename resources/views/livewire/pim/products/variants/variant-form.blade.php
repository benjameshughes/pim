<div class="max-w-2xl">
    <div class="mb-6">
        <flux:heading size="xl">{{ $variant ? 'Edit' : 'Create' }} Variant</flux:heading>
        <flux:subheading>{{ $variant ? 'Update the variant details' : 'Add a new product variant' }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Product</flux:label>
            <select wire:model="product_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                <option value="">Select a product</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">
                        {{ $product->name }}
                    </option>
                @endforeach
            </select>
            <flux:error name="product_id" />
        </flux:field>

        <flux:input 
            wire:model="color" 
            label="Color" 
            type="text" 
            placeholder="e.g., Black, White, Grey"
            required 
        />

        <div class="grid grid-cols-2 gap-4">
            <flux:input 
                wire:model="width" 
                label="Width (cm)" 
                type="number" 
                step="0.1"
                min="0"
                placeholder="e.g., 45, 60, 90"
            />
            
            <flux:input 
                wire:model="drop" 
                label="Drop (cm)" 
                type="number" 
                step="0.1"
                min="0"
                placeholder="e.g., 45, 60, 90"
            />
        </div>

        <flux:input 
            wire:model="sku" 
            label="SKU" 
            type="text" 
            placeholder="e.g., BLK-60-VEN"
            required 
        />

        <flux:input 
            wire:model="stock_level" 
            label="Stock Level" 
            type="number" 
            min="0"
            placeholder="0"
        />

        <flux:field>
            <flux:label>Status</flux:label>
            <select wire:model="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="out_of_stock">Out of Stock</option>
            </select>
            <flux:error name="status" />
        </flux:field>

        {{-- Package Dimensions Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Package Dimensions</flux:heading>
            
            <div class="grid grid-cols-2 gap-4">
                <flux:input 
                    wire:model="package_length" 
                    label="Length (cm)" 
                    type="number" 
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                />
                
                <flux:input 
                    wire:model="package_width" 
                    label="Width (cm)" 
                    type="number" 
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                />
                
                <flux:input 
                    wire:model="package_height" 
                    label="Height (cm)" 
                    type="number" 
                    step="0.01"
                    min="0"
                    placeholder="0.00"
                />
                
                <flux:input 
                    wire:model="package_weight" 
                    label="Weight (kg)" 
                    type="number" 
                    step="0.001"
                    min="0"
                    placeholder="0.000"
                />
            </div>
        </div>

        {{-- Barcode Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Barcode</flux:heading>
            
            <div class="space-y-4">
                <!-- Barcode Type -->
                <flux:field>
                    <flux:label>Barcode Type</flux:label>
                    <select wire:model.live="barcode_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        @foreach($barcodeTypes as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <flux:error name="barcode_type" />
                </flux:field>

                <!-- Barcode Value -->
                <div class="flex gap-2">
                    <div class="flex-1">
                        <flux:input 
                            wire:model="barcode" 
                            label="Barcode Value" 
                            type="text" 
                            placeholder="Enter barcode, generate, or assign from GS1 pool"
                        />
                    </div>
                    <div class="flex items-end gap-2">
                        @php
                            $availableCount = $poolStats['by_type'][$barcode_type] ?? 0;
                            $hasAvailable = $availableCount > 0;
                        @endphp
                        
                        @if($hasAvailable)
                            <flux:button 
                                type="button" 
                                variant="primary"
                                wire:click="assignFromPool"
                                class="whitespace-nowrap text-sm"
                            >
                                Use GS1 ({{ $availableCount }})
                            </flux:button>
                        @endif
                        
                        <flux:button 
                            type="button" 
                            variant="ghost" 
                            wire:click="generateBarcode"
                            class="whitespace-nowrap"
                        >
                            Generate
                        </flux:button>
                    </div>
                </div>

                <!-- Auto-options -->
                <div class="space-y-2">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model="useGS1Pool" 
                            id="useGS1Pool"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <label for="useGS1Pool" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Auto-assign from GS1 pool if empty ({{ $poolStats['available'] }} available)
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model="generateBarcodeAutomatically" 
                            id="generateBarcodeAutomatically"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <label for="generateBarcodeAutomatically" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                            Generate random barcode automatically if empty (fallback)
                        </label>
                    </div>
                </div>

                <!-- Barcode Preview -->
                @if($barcode)
                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <flux:subheading class="mb-2">Barcode Preview</flux:subheading>
                        <div class="flex items-start gap-4">
                            <div class="bg-white p-2 rounded border">
                                @php
                                    try {
                                        $barcodeModel = new \App\Models\Barcode();
                                        $barcodeModel->barcode = $barcode;
                                        $barcodeModel->barcode_type = $barcode_type;
                                        echo $barcodeModel->generateBarcodeHtml(1, 30);
                                    } catch (Exception $e) {
                                        echo '<div class="text-red-500 text-sm">Invalid barcode format</div>';
                                    }
                                @endphp
                            </div>
                            <div class="text-sm">
                                <div><strong>Type:</strong> {{ $barcodeTypes[$barcode_type] ?? $barcode_type }}</div>
                                <div><strong>Value:</strong> {{ $barcode }}</div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Images Section --}}
        <div class="border-t pt-6">
            <flux:heading size="lg" class="mb-4">Variant Images</flux:heading>
            
            {{-- Existing Images --}}
            @if($existingImages && count($existingImages) > 0)
                <div class="mb-4">
                    <flux:subheading>Current Images</flux:subheading>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-2">
                        @foreach($existingImages as $index => $image)
                            <div class="relative group">
                                <img src="{{ Storage::url($image) }}" alt="Variant image" class="w-full h-32 object-cover rounded-lg border">
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
                {{ $variant ? 'Update' : 'Create' }} Variant
            </flux:button>
            <flux:button variant="ghost" :href="route('products.variants.index')" wire:navigate>
                Cancel
            </flux:button>
        </div>
    </form>
</div>