<div class="max-w-2xl mx-auto space-y-6">
    {{-- ✨ PHOENIX HEADER --}}
    <div class="flex items-center gap-4">
        <flux:button wire:navigate href="{{ route('barcodes.index') }}" variant="ghost" icon="arrow-left">
            Back to Barcodes
        </flux:button>
        
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                {{ $isEditing ? 'Edit Barcode' : 'Add Barcode' }}
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $isEditing ? 'Update barcode details' : 'Create a new barcode for a variant' }}
            </p>
        </div>
    </div>

    {{-- 💎 BARCODE FORM --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form wire:submit.prevent="save" class="space-y-6">
            
            {{-- Product Variant Selection --}}
            <flux:field>
                <flux:label>Product Variant</flux:label>
                <flux:select wire:model="product_variant_id">
                    <flux:select.option value="">Select a variant...</flux:select.option>
                    @foreach ($variants as $variant)
                        <flux:select.option value="{{ $variant->id }}">
                            {{ $variant->product->name }} - {{ $variant->color }} {{ $variant->width }}cm ({{ $variant->sku }})
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="product_variant_id" />
            </flux:field>

            {{-- Barcode Details --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <flux:field>
                    <flux:label>Barcode</flux:label>
                    <div class="flex gap-2">
                        <flux:input wire:model="barcode_value" placeholder="e.g., 1234567890123" class="flex-1" />
                        <flux:button type="button" wire:click="generateBarcode" variant="outline" size="sm" icon="sparkles">
                            Generate
                        </flux:button>
                    </div>
                    <flux:error name="barcode_value" />
                </flux:field>

                <flux:field>
                    <flux:label>Type</flux:label>
                    <flux:select wire:model="type">
                        <flux:select.option value="caecus">Caecus</flux:select.option>
                        <flux:select.option value="system">System</flux:select.option>
                        <flux:select.option value="ean13">EAN13</flux:select.option>
                        <flux:select.option value="upc">UPC</flux:select.option>
                    </flux:select>
                    <flux:error name="type" />
                </flux:field>
            </div>

            {{-- Status --}}
            <flux:field>
                <flux:label>Status</flux:label>
                <flux:select wire:model="status">
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="inactive">Inactive</flux:select.option>
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            {{-- Form Actions --}}
            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                <flux:button wire:navigate href="{{ route('barcodes.index') }}" variant="ghost">
                    Cancel
                </flux:button>
                
                <flux:button type="submit" variant="primary" icon="qr-code">
                    {{ $isEditing ? 'Update Barcode' : 'Create Barcode' }}
                </flux:button>
            </div>
        </form>
    </div>

    {{-- 💡 HELPFUL TIPS --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <div class="flex">
            <flux:icon name="information-circle" class="w-5 h-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" />
            <div>
                <h3 class="text-sm font-medium text-blue-900 dark:text-blue-100">Barcode Guidelines</h3>
                <div class="mt-2 text-sm text-blue-700 dark:text-blue-200">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Caecus:</strong> Internal barcodes for tracking</li>
                        <li><strong>System:</strong> Automatically generated codes</li>
                        <li><strong>EAN13:</strong> 13-digit European Article Numbers</li>
                        <li><strong>UPC:</strong> 12-digit Universal Product Codes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>