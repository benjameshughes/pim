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
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
        <flux:heading size="sm" class="mb-4">Upload New Images</flux:heading>
        
        <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-6">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-zinc-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="mt-4">
                    <label for="file-upload" class="cursor-pointer">
                        <span class="mt-2 block text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            Drop images here or click to upload
                        </span>
                        <input 
                            id="file-upload" 
                            wire:model="newImages" 
                            type="file" 
                            multiple 
                            accept="image/*" 
                            class="sr-only"
                        />
                    </label>
                    <p class="mt-1 text-xs text-zinc-500">PNG, JPG, GIF up to 5MB each</p>
                </div>
            </div>
        </div>

        @if($newImages)
            <div class="mt-4 flex justify-between items-center">
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ count($newImages) }} file(s) selected
                </span>
                
                <flux:button 
                    wire:click="uploadImages" 
                    variant="primary"
                    :disabled="$uploading"
                >
                    @if($uploading)
                        <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Uploading...
                    @else
                        Upload Images
                    @endif
                </flux:button>
            </div>
        @endif
    </div>

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
        @if(empty($imageData))
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-zinc-900 dark:text-zinc-100">No images found</h3>
                <p class="mt-1 text-sm text-zinc-500">Upload some images to get started.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($imageData as $image)
                    <div class="relative group">
                        <!-- Selection Checkbox (Bulk Edit Mode) -->
                        @if($bulkEditMode)
                            <div class="absolute top-2 left-2 z-10">
                                <input 
                                    type="checkbox" 
                                    wire:model.live="selectedImages" 
                                    value="{{ $image['path'] }}"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                />
                            </div>
                        @endif

                        <!-- Image -->
                        <div class="aspect-square bg-zinc-100 dark:bg-zinc-700 rounded-lg overflow-hidden">
                            @if($image['exists'])
                                <img 
                                    src="{{ $image['url'] }}" 
                                    alt="Product image"
                                    class="w-full h-full object-cover cursor-pointer hover:opacity-75 transition-opacity"
                                    onclick="window.open('{{ $image['url'] }}', '_blank')"
                                />
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="h-8 w-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </div>
                            @endif
                        </div>

                        <!-- Image Info -->
                        <div class="mt-2">
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 truncate" title="{{ basename($image['path']) }}">
                                {{ basename($image['path']) }}
                            </p>
                            
                            <!-- Usage Info -->
                            @if($image['usage']['products'] || $image['usage']['variants'])
                                <div class="flex gap-1 mt-1">
                                    @if($image['usage']['products'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300">
                                            P: {{ count($image['usage']['products']) }}
                                        </span>
                                    @endif
                                    
                                    @if($image['usage']['variants'])
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300">
                                            V: {{ count($image['usage']['variants']) }}
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
                                        wire:click="$set('selectedImages', ['{{ $image['path'] }}'])"
                                        wire:click="toggleAssignmentMode"
                                        class="bg-white text-zinc-900 px-2 py-1 rounded text-xs font-medium hover:bg-zinc-100"
                                    >
                                        Assign
                                    </button>
                                @endif
                                
                                <button 
                                    onclick="window.open('{{ $image['url'] }}', '_blank')"
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
            @if($totalPages > 1)
                <div class="mt-6 flex justify-center">
                    {{ $this->paginationView() }}
                </div>
            @endif
        @endif
    </div>
</div>
