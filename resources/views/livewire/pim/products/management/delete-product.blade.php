<div>
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['name' => 'Products', 'url' => route('products.index')],
        ['name' => $product->name, 'url' => route('products.view', $product)],
        ['name' => 'Delete Product']
    ]" />

    <!-- Header -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="p-6">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-xl flex items-center justify-center">
                    <flux:icon name="trash" class="h-6 w-6 text-white" />
                </div>
                <div>
                    <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100">
                        Delete Entire Product
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        Permanently remove product and all {{ $variantCount }} variants with archiving
                    </flux:subheading>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Card -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Product Information</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <flux:field>
                        <flux:label>Product Name</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $product->name }}
                        </div>
                    </flux:field>
                </div>

                @if($product->parent_sku)
                <div>
                    <flux:field>
                        <flux:label>Parent SKU</flux:label>
                        <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $product->parent_sku }}
                        </div>
                    </flux:field>
                </div>
                @endif

                <div>
                    <flux:field>
                        <flux:label>Status</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ ucfirst($product->status) }}
                        </div>
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Total Variants</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variantCount }} variants
                        </div>
                    </flux:field>
                </div>
            </div>

            @if($product->description)
            <div class="mt-6">
                <flux:field>
                    <flux:label>Description</flux:label>
                    <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                        {{ Str::limit($product->description, 200) }}
                    </div>
                </flux:field>
            </div>
            @endif

            <!-- Variants Preview -->
            @if($variantCount > 0)
            <div class="mt-6">
                <flux:field>
                    <flux:label>Variants to be Deleted</flux:label>
                    <div class="max-h-32 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg">
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach($product->variants as $variant)
                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <div class="font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $variant->sku }}</div>
                                    @if($variant->color || $variant->width || $variant->drop)
                                        <div class="text-xs text-zinc-600 dark:text-zinc-400">
                                            {{ collect([$variant->color, $variant->width, $variant->drop])->filter()->implode(' × ') }}
                                        </div>
                                    @endif
                                </div>
                                @if($variant->barcodes()->where('is_primary', true)->exists())
                                    <div class="text-xs font-mono text-zinc-500">
                                        {{ $variant->barcodes()->where('is_primary', true)->first()->barcode }}
                                    </div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </flux:field>
            </div>
            @endif

            <!-- Critical Warning -->
            <div class="mt-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <div class="flex items-start">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-red-600 dark:text-red-400 mr-3 mt-0.5" />
                    <div>
                        <div class="text-sm font-medium text-red-800 dark:text-red-200">
                            Critical: This will delete the entire product line
                        </div>
                        <div class="text-sm text-red-700 dark:text-red-300 mt-1">
                            This action will permanently delete the product and all {{ $variantCount }} variants. 
                            Key information will be preserved in the archive and all assigned barcodes will be freed for reuse.
                            This action cannot be undone.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            <div class="flex items-center justify-between">
                <flux:button 
                    variant="outline" 
                    :href="route('products.view', $product)"
                    wire:navigate
                >
                    Cancel
                </flux:button>

                <flux:button 
                    variant="danger" 
                    icon="trash"
                    wire:click="showConfirmationModal"
                >
                    Delete Entire Product
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    @if($showConfirmation)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" wire:click="cancelDeletion">
            <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-xl max-w-lg w-full mx-4" wire:click.stop>
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-red-100 dark:bg-red-900/50 rounded-full flex items-center justify-center mr-3">
                            <flux:icon name="exclamation-triangle" class="h-5 w-5 text-red-600 dark:text-red-400" />
                        </div>
                        <flux:heading size="lg" class="text-red-900 dark:text-red-100">
                            Confirm Complete Deletion
                        </flux:heading>
                    </div>

                    <div class="mb-6">
                        <p class="text-sm text-zinc-700 dark:text-zinc-300 mb-4">
                            Are you sure you want to delete <strong>{{ $product->name }}</strong> and all {{ $variantCount }} variants?
                        </p>
                        
                        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                            <p class="text-xs text-red-700 dark:text-red-300 font-medium">
                                This will permanently delete {{ $variantCount + 1 }} database records and cannot be undone.
                            </p>
                        </div>
                        
                        <!-- Deletion Reason Form -->
                        <div class="space-y-4">
                            <flux:field>
                                <flux:label>Reason for Deletion *</flux:label>
                                <flux:select wire:model="deletionReason">
                                    <flux:select.option value="">Select a reason...</flux:select.option>
                                    @foreach($availableReasons as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('deletionReason')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>

                            <flux:field>
                                <flux:label>Additional Notes (Optional)</flux:label>
                                <flux:textarea 
                                    wire:model="deletionNotes" 
                                    placeholder="Provide additional context..."
                                    rows="3"
                                />
                                @error('deletionNotes')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            </flux:field>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <flux:button variant="outline" wire:click="cancelDeletion" class="flex-1">
                            Cancel
                        </flux:button>
                        <flux:button 
                            variant="danger" 
                            wire:click="deleteProduct" 
                            class="flex-1"
                            :disabled="empty($deletionReason)"
                        >
                            Delete Everything
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>