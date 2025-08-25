<div class="bg-white dark:bg-gray-800 rounded-lg p-8">
    {{-- Product/Variant Attachment --}}
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
            <flux:icon name="link" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
            Product Attachment
        </h3>

        {{-- Current Attachments Display --}}
        @if($this->getCurrentAttachments())
            <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-sm font-medium text-purple-800 dark:text-purple-200">
                        Currently attached to:
                    </p>
                    <flux:button
                        @click="confirmAction({
                            title: 'Detach Image',
                            message: 'This will remove the image from all products and variants.\\n\\nThe image itself will remain in the library.',
                            confirmText: 'Yes, Detach All',
                            cancelText: 'Cancel',
                            variant: 'warning',
                            onConfirm: () => $wire.detachFromAll()
                        })"
                        variant="danger"
                        size="sm"
                    >
                        <flux:icon name="unlink" class="h-4 w-4" />
                        Detach All
                    </flux:button>
                </div>
                
                <div class="space-y-2">
                    @foreach($this->getCurrentAttachments() as $attachment)
                        <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-purple-100 dark:border-purple-800">
                            <div class="flex items-center gap-3">
                                @if($attachment['type'] === 'product')
                                    <flux:badge variant="primary" size="sm">
                                        <flux:icon name="package" class="h-3 w-3" />
                                        Product
                                    </flux:badge>
                                @else
                                    <flux:badge variant="success" size="sm">
                                        <flux:icon name="box" class="h-3 w-3" />
                                        Variant
                                    </flux:badge>
                                @endif
                                
                                <div>
                                    <p class="font-medium text-gray-900 dark:text-white text-sm">
                                        {{ $attachment['name'] }}
                                    </p>
                                    <p class="text-xs font-mono text-gray-500">
                                        SKU: {{ $attachment['sku'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Attach New Product/Variant --}}
        <div class="space-y-3">
            <flux:field>
                <flux:label>Search and attach to product or variant</flux:label>
                <livewire:components.product-variant-combobox 
                    placeholder="Search products and variants to attach..."
                    :allow-products="true"
                    :allow-variants="true"
                    :max-results="8"
                />
                <flux:description>
                    Type to search by product name, SKU, or variant details. Select an item to attach this image.
                </flux:description>
            </flux:field>

            @if($attachmentType && $attachmentId)
                <flux:button 
                    wire:click="attachToItem"
                    variant="primary"
                    size="sm"
                >
                    <flux:icon name="link" class="h-4 w-4" />
                    Attach Image
                </flux:button>
            @endif
        </div>
    </div>
</div>