<div>
    <!-- Breadcrumb -->
    <x-breadcrumb :items="[
        ['name' => 'Products', 'url' => route('products.index')],
        ['name' => $variant->product->name, 'url' => route('products.view', $variant->product)],
        ['name' => 'Delete Variant']
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
                        Delete Product Variant
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                        Permanently remove variant {{ $variant->sku }} with archiving
                    </flux:subheading>
                </div>
            </div>
        </div>
    </div>

    <!-- Variant Details Card -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Variant Information</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <flux:field>
                        <flux:label>Product Name</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->product->name }}
                        </div>
                    </flux:field>
                </div>

                <div>
                    <flux:field>
                        <flux:label>Variant SKU</flux:label>
                        <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->sku }}
                        </div>
                    </flux:field>
                </div>

                @if($variant->color)
                <div>
                    <flux:field>
                        <flux:label>Color</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->color }}
                        </div>
                    </flux:field>
                </div>
                @endif

                @if($variant->width)
                <div>
                    <flux:field>
                        <flux:label>Width</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->width }}
                        </div>
                    </flux:field>
                </div>
                @endif

                @if($variant->drop)
                <div>
                    <flux:field>
                        <flux:label>Drop</flux:label>
                        <div class="text-sm text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->drop }}
                        </div>
                    </flux:field>
                </div>
                @endif

                @if($variant->barcodes()->where('is_primary', true)->exists())
                <div>
                    <flux:field>
                        <flux:label>Primary Barcode</flux:label>
                        <div class="text-sm font-mono text-zinc-900 dark:text-zinc-100 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                            {{ $variant->barcodes()->where('is_primary', true)->first()->barcode }}
                        </div>
                    </flux:field>
                </div>
                @endif
            </div>

            <!-- Warning -->
            <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <div class="flex items-start">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400 mr-3 mt-0.5" />
                    <div>
                        <div class="text-sm font-medium text-amber-800 dark:text-amber-200">
                            This action cannot be undone
                        </div>
                        <div class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                            The variant will be permanently deleted, but key information will be preserved in the archive. 
                            Any assigned barcodes will be freed for reuse.
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
                    :href="route('products.view', $variant->product)"
                    wire:navigate
                >
                    Cancel
                </flux:button>

                <flux:button 
                    variant="danger" 
                    icon="trash"
                    wire:click="showConfirmationModal"
                >
                    Delete Variant
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
                            Confirm Deletion
                        </flux:heading>
                    </div>

                    <div class="mb-6">
                        <p class="text-sm text-zinc-700 dark:text-zinc-300 mb-4">
                            Are you sure you want to delete variant <strong>{{ $variant->sku }}</strong>?
                        </p>
                        
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
                            wire:click="deleteVariant" 
                            class="flex-1"
                            :disabled="empty($deletionReason)"
                        >
                            Delete Forever
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>