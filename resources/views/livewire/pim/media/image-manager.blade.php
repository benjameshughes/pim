<div class="space-y-6">
    <!-- Header & Stats -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <flux:heading size="lg">Image Manager</flux:heading>
                <flux:subheading>Manage product and variant images</flux:subheading>
            </div>
            
        </div>
        
        <div class="flex justify-between items-center mb-6">
            <div></div>
            
            <div class="flex gap-3">
                <flux:button 
                    type="button" 
                    wire:click="toggleBulkEdit" 
                    variant="{{ $bulkEditMode ? 'primary' : 'outline' }}"
                >
                    {{ $bulkEditMode ? 'Exit Bulk Mode' : 'Bulk Edit' }}
                </flux:button>
                
                <flux:button 
                    type="button" 
                    wire:click="toggleAssignmentMode" 
                    variant="{{ $assignmentMode ? 'primary' : 'outline' }}"
                >
                    {{ $assignmentMode ? 'Exit Assignment' : 'Assign Images' }}
                </flux:button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['total_images'] }}</div>
                <div class="text-sm text-blue-700 dark:text-blue-300">Total Images</div>
            </div>
            
            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $stats['assigned_to_products'] }}</div>
                <div class="text-sm text-green-700 dark:text-green-300">Products with Images</div>
            </div>
            
            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">{{ $stats['assigned_to_variants'] }}</div>
                <div class="text-sm text-purple-700 dark:text-purple-300">Variants with Images</div>
            </div>
            
            @if($bulkEditMode)
                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ $stats['selected_count'] }}</div>
                    <div class="text-sm text-orange-700 dark:text-orange-300">Selected Images</div>
                </div>
            @endif
        </div>

        <!-- Search & Filters -->
        <div class="flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <flux:input 
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search images by filename..."
                    class="w-full"
                />
            </div>
            
            <flux:select wire:model.live="filterType" class="md:w-48">
                <option value="">All Images</option>
                <option value="product">Product Images</option>
                <option value="variant">Variant Images</option>
                <option value="unassigned">Unassigned</option>
            </flux:select>
        </div>
    </div>

    <!-- Upload Section -->
    @if($showImageUploader)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center">
                    <flux:heading size="sm">Upload New Images</flux:heading>
                    <flux:button 
                        variant="ghost" 
                        size="sm" 
                        icon="x" 
                        wire:click="toggleImageUploader"
                        class="text-zinc-500 hover:text-zinc-700"
                    >
                        Hide
                    </flux:button>
                </div>
                <flux:subheading class="text-zinc-600 dark:text-zinc-400 mt-1">
                    Upload unassigned images that can be assigned to products or variants later
                </flux:subheading>
            </div>
            
            <livewire:components.simple-image-uploader
                :model-type="null"
                :model-id="null"
                :image-type="$defaultImageType"
                :multiple="true"
                :max-files="20"
                :max-size="10240"
                :accept-types="['jpg', 'jpeg', 'png', 'webp']"
                :process-immediately="true"
                :show-preview="true"
                :allow-reorder="false"
                :show-existing-images="false"
                upload-text="Drag & drop images here or click to browse"
                wire:key="image-manager-uploader"
            />
        </div>
    @else
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <flux:heading size="sm">Upload New Images</flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400 mt-1">
                        Add unassigned images to your library
                    </flux:subheading>
                </div>
                <flux:button 
                    variant="primary" 
                    icon="plus" 
                    wire:click="toggleImageUploader"
                >
                    Show Uploader
                </flux:button>
            </div>
        </div>
    @endif

    <!-- Assignment Panel -->
    @if($assignmentMode && !empty($selectedImages))
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-700 p-6">
            <flux:heading size="sm" class="mb-4">Assign {{ count($selectedImages) }} Selected Images</flux:heading>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Product Assignment -->
                <div>
                    <flux:field>
                        <flux:label>Assign to Product</flux:label>
                        <flux:select wire:model.live="selectedProductId">
                            <option value="">Select Product...</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    
                    @if($selectedProductId)
                        <flux:button 
                            wire:click="assignImagesToProduct" 
                            variant="primary" 
                            class="mt-3 w-full"
                        >
                            Assign to Product
                        </flux:button>
                    @endif
                </div>

                <!-- Variant Assignment -->
                <div>
                    <flux:field>
                        <flux:label>Assign to Variant</flux:label>
                        <flux:select wire:model.live="selectedVariantId">
                            <option value="">Select Variant...</option>
                            @foreach($variants as $variant)
                                <option value="{{ $variant->id }}">
                                    {{ $variant->product->name }} - {{ $variant->sku }}
                                </option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    
                    @if($selectedVariantId)
                        <flux:button 
                            wire:click="assignImagesToVariant" 
                            variant="primary" 
                            class="mt-3 w-full"
                        >
                            Assign to Variant
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Bulk Actions -->
    @if($bulkEditMode)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex justify-between items-center">
                <div class="flex gap-3">
                    <flux:button 
                        wire:click="selectAllImages" 
                        variant="outline" 
                        size="sm"
                    >
                        Select All
                    </flux:button>
                    
                    <flux:button 
                        wire:click="deselectAllImages" 
                        variant="outline" 
                        size="sm"
                    >
                        Deselect All
                    </flux:button>
                </div>

                @if(!empty($selectedImages))
                    <flux:button 
                        wire:click="deleteImages" 
                        variant="danger"
                        wire:confirm="Are you sure you want to delete the selected images? This will also remove them from all products and variants."
                    >
                        Delete Selected ({{ count($selectedImages) }})
                    </flux:button>
                @endif
            </div>
        </div>
    @endif

    <!-- Images Grid -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        @if($images->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No images found</h3>
                <p class="mt-1 text-sm text-zinc-500">Upload some images to get started.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($images as $image)
                    <div class="relative group">
                        <!-- Selection Checkbox (Bulk Edit Mode) -->
                        @if($bulkEditMode)
                            <div class="absolute top-2 left-2 z-10">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedImages" 
                                    value="{{ $image->id }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                />
                            </div>
                        @endif

                        <!-- Image -->
                        <div class="aspect-square bg-zinc-100 dark:bg-zinc-700 rounded-lg overflow-hidden relative">
                            <img 
                                src="{{ $image->getVariantUrl('small') }}" 
                                alt="{{ $image->alt_text }}"
                                class="w-full h-full object-cover cursor-pointer hover:opacity-75 transition-opacity"
{{-- onclick="window.open('{{ $image->getVariantUrl('large') }}', '_blank')" --}}
                                loading="lazy"
                            />
                            
                            <!-- Processing Status Overlay -->
                            @if(!$image->isProcessed())
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    @if($image->isPending())
                                        <div class="text-center text-white">
                                            <flux:icon name="clock" class="w-4 h-4 mx-auto mb-1" />
                                            <p class="text-xs">Pending</p>
                                        </div>
                                    @elseif($image->isProcessing())
                                        <div class="text-center text-white">
                                            <div class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent mx-auto mb-1"></div>
                                            <p class="text-xs">Processing</p>
                                        </div>
                                    @elseif($image->isFailed())
                                        <div class="text-center text-red-400">
                                            <flux:icon name="triangle-alert" class="w-4 h-4 mx-auto mb-1" />
                                            <p class="text-xs">Failed</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Image Info -->
                        <div class="mt-2">
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ $image->original_filename }}">
                                {{ $image->original_filename }}
                            </p>
                            
                            <div class="flex items-center justify-between text-xs text-zinc-500 mt-1">
                                <span>{{ ucfirst($image->image_type) }}</span>
                                <span>{{ number_format($image->file_size / 1024, 1) }}KB</span>
                            </div>
                            
                            <!-- Usage Info -->
                            @if($image->product_id || $image->variant_id)
                                <div class="flex gap-1 mt-1">
                                    @if($image->product_id)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                            Product
                                        </span>
                                    @endif
                                    
                                    @if($image->variant_id)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                                            Variant
                                        </span>
                                    @endif
                                </div>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/50 dark:text-gray-300 mt-1">
                                    Unassigned
                                </span>
                            @endif
                        </div>

                        <!-- Hover Actions -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-all duration-200 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100">
                            <div class="flex gap-2">
                                @if(!$bulkEditMode)
                                    <button 
                                        wire:click="selectImageForAssignment({{ $image->id }})"
                                        class="bg-white text-zinc-900 px-2 py-1 rounded text-xs font-medium hover:bg-zinc-100"
                                    >
                                        Assign
                                    </button>
                                @endif
                                
                                <button 
    {{-- onclick="window.open('{{ $image->getVariantUrl('large') }}', '_blank')" --}}
                                    class="bg-white text-zinc-900 px-2 py-1 rounded text-xs font-medium hover:bg-zinc-100"
                                >
                                    View
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                {{ $images->links() }}
            </div>
        @endif
    </div>
</div>