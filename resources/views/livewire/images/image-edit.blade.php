<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Image Preview Column --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 sticky top-6">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Preview</h3>
                </div>
                
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden mb-4">
                    <img 
                        src="{{ $image->url }}" 
                        alt="{{ $image->alt_text ?: $image->title ?: 'Image preview' }}"
                        class="w-full h-full object-cover"
                    />
                </div>
                
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <div class="flex justify-between items-start gap-2">
                        <span class="flex-shrink-0">File:</span>
                        <span class="font-medium text-right break-all">{{ $image->filename }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Size:</span>
                        <span class="font-medium">{{ $image->file_size_human }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Dimensions:</span>
                        <span class="font-medium">{{ $image->width }}×{{ $image->height }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Created:</span>
                        <span class="font-medium">{{ $image->created_at?->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Form Column --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-8">
                <form wire:submit="save" class="space-y-6">
                    {{-- Basic Information --}}
                    <div class="space-y-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                            <flux:icon name="info" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                            Basic Information
                        </h3>
                        
                        <flux:field>
                            <flux:label>Title</flux:label>
                            <flux:input 
                                wire:model.live="title" 
                                placeholder="Enter a descriptive title for this image"
                            />
                            <flux:error name="title" />
                        </flux:field>
                        
                        <flux:field>
                            <flux:label>Alt Text</flux:label>
                            <flux:input 
                                wire:model.live="alt_text" 
                                placeholder="Describe the image for screen readers"
                            />
                            <flux:error name="alt_text" />
                            <flux:description>Important for accessibility and SEO</flux:description>
                        </flux:field>
                        
                        <flux:field>
                            <flux:label>Description</flux:label>
                            <flux:textarea 
                                wire:model.live="description" 
                                rows="3"
                                placeholder="Optional longer description of this image"
                            />
                            <flux:error name="description" />
                        </flux:field>
                    </div>

                    {{-- Organization --}}
                    <div class="space-y-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                            <flux:icon name="folder" class="h-5 w-5 text-green-600 dark:text-green-400" />
                            Organization
                        </h3>
                        
                        <flux:field>
                            <flux:label>Folder</flux:label>
                            @if(count($this->folders) > 0)
                                <flux:select wire:model="folder">
                                    <flux:select.option value="uncategorized">Uncategorized</flux:select.option>
                                    @foreach($this->folders as $folderOption)
                                        <flux:select.option value="{{ $folderOption }}">{{ ucfirst($folderOption) }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input wire:model="folder" placeholder="Enter folder name" />
                            @endif
                            <flux:error name="folder" />
                            <flux:description>Organize images into folders for easier management</flux:description>
                        </flux:field>
                        
                        <flux:field>
                            <flux:label>Tags</flux:label>
                            <flux:input 
                                wire:model.live="tagsString" 
                                placeholder="product, hero, banner, lifestyle (comma-separated)"
                            />
                            <flux:error name="tagsString" />
                            <flux:description>Add comma-separated tags to help categorize and find this image</flux:description>
                        </flux:field>
                    </div>

                    {{-- Product/Variant Attachment --}}
                    <div class="space-y-4 pt-6 border-t border-gray-200 dark:border-gray-700">
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

                    {{-- Actions --}}
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                        <flux:button wire:click="deleteImage" variant="danger">Delete Image</flux:button>
                        
                        <div class="flex items-center gap-3">
                            <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
                            <flux:button type="submit" variant="primary">Save Changes</flux:button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>