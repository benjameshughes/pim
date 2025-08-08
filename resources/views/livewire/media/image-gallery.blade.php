<div class="space-y-4">
    <!-- Header with controls -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="lg">
                @if($modelType && $modelId)
                    {{ ucfirst($modelType) }} Images
                @else
                    Unassigned Images
                @endif
            </flux:heading>
            <flux:subheading class="text-zinc-500">
                {{ $stats['total'] }} {{ $stats['total'] === 1 ? 'image' : 'images' }}
                @if($bulkMode && $stats['selected'] > 0)
                    • {{ $stats['selected'] }} selected
                @endif
            </flux:subheading>
        </div>

        <div class="flex items-center space-x-3">
            <!-- Search -->
            <div class="relative">
                <flux:input 
                    wire:model.live.debounce="search" 
                    placeholder="Search images..."
                    class="pl-9">
                </flux:input>
                <flux:icon name="magnifying-glass" class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-zinc-400" />
            </div>

            <!-- Layout toggle -->
            <flux:button variant="ghost" size="sm" wire:click="toggleLayout">
                <flux:icon name="{{ $layout === 'grid' ? 'list-bullet' : 'squares-2x2' }}" class="h-4 w-4" />
            </flux:button>

            <!-- Bulk mode toggle -->
            <flux:button 
                variant="{{ $bulkMode ? 'primary' : 'ghost' }}" 
                size="sm" 
                wire:click="toggleBulkMode">
                <flux:icon name="check-circle" class="h-4 w-4 mr-2" />
                Bulk
            </flux:button>

            <!-- Upload toggle -->
            @if($showUploader)
                <flux:button variant="primary" size="sm" wire:click="toggleUploader">
                    <flux:icon name="plus" class="h-4 w-4 mr-2" />
                    Upload
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Filters -->
    @if(!empty($filters) || $stats['pending'] > 0 || $stats['failed'] > 0)
        <div class="flex items-center space-x-2">
            <flux:subheading class="text-zinc-500">Filters:</flux:subheading>
            
            @if($stats['pending'] > 0)
                <flux:badge 
                    color="{{ in_array('pending', $filters) ? 'blue' : 'zinc' }}"
                    class="cursor-pointer"
                    wire:click="{{ in_array('pending', $filters) ? 'removeFilter(\'pending\')' : 'addFilter(\'pending\')' }}">
                    Pending ({{ $stats['pending'] }})
                </flux:badge>
            @endif
            
            @if($stats['failed'] > 0)
                <flux:badge 
                    color="{{ in_array('failed', $filters) ? 'red' : 'zinc' }}"
                    class="cursor-pointer"
                    wire:click="{{ in_array('failed', $filters) ? 'removeFilter(\'failed\')' : 'addFilter(\'failed\')' }}">
                    Failed ({{ $stats['failed'] }})
                </flux:badge>
            @endif

            @if(!empty($filters))
                <flux:button variant="ghost" size="xs" wire:click="$set('filters', [])">
                    Clear filters
                </flux:button>
            @endif
        </div>
    @endif

    <!-- Bulk actions bar -->
    @if($bulkMode && !empty($selectedImages))
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <flux:subheading class="text-blue-900 dark:text-blue-100">
                        {{ count($selectedImages) }} images selected
                    </flux:subheading>
                    
                    <div class="flex space-x-2">
                        <flux:button variant="ghost" size="sm" wire:click="selectAll">
                            Select All
                        </flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="deselectAll">
                            Deselect All
                        </flux:button>
                    </div>
                </div>
                
                <div class="flex space-x-2">
                    @if($allowDelete)
                        <flux:button 
                            variant="danger" 
                            size="sm" 
                            wire:click="bulkDelete"
                            wire:confirm="Are you sure you want to delete the selected images? This action cannot be undone.">
                            <flux:icon name="trash" class="h-4 w-4 mr-2" />
                            Delete
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Upload area (if enabled) -->
    @if($showUploader)
        <div class="border-t border-zinc-200 dark:border-zinc-700 pt-4">
            @livewire('media.image-uploader', [
                'modelType' => $modelType,
                'modelId' => $modelId,
                'multiple' => true
            ])
        </div>
    @endif

    <!-- Images display -->
    @if($images->isEmpty())
        <div class="text-center py-12">
            <flux:icon name="photo" class="h-12 w-12 mx-auto text-zinc-400 mb-4" />
            <flux:heading size="lg" class="text-zinc-500 mb-2">No images found</flux:heading>
            <flux:subheading class="text-zinc-400">
                @if($search)
                    No images match your search criteria
                @elseif(!empty($filters))
                    No images match the selected filters
                @else
                    Upload some images to get started
                @endif
            </flux:subheading>
            
            @if($search || !empty($filters))
                <flux:button variant="ghost" class="mt-4" wire:click="$set('search', ''); $set('filters', [])">
                    Clear filters
                </flux:button>
            @endif
        </div>
    @else
        @if($layout === 'grid')
            <!-- Grid layout -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($images as $image)
                    <div class="group relative bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden hover:shadow-lg transition-all">
                        <!-- Selection checkbox (bulk mode) -->
                        @if($bulkMode)
                            <div class="absolute top-2 left-2 z-10">
                                <flux:checkbox 
                                    wire:model="selectedImages" 
                                    value="{{ $image->id }}"
                                    class="bg-white/80 backdrop-blur-sm" />
                            </div>
                        @endif

                        <!-- Processing status indicator -->
                        @if($image->processing_status !== \App\Models\ProductImage::PROCESSING_COMPLETED)
                            <div class="absolute top-2 right-2 z-10">
                                @if($image->processing_status === \App\Models\ProductImage::PROCESSING_PENDING)
                                    <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                @elseif($image->processing_status === \App\Models\ProductImage::PROCESSING_IN_PROGRESS)
                                    <flux:badge color="blue" size="sm">Processing</flux:badge>
                                @elseif($image->processing_status === \App\Models\ProductImage::PROCESSING_FAILED)
                                    <flux:badge color="red" size="sm">Failed</flux:badge>
                                @endif
                            </div>
                        @endif

                        <!-- Image -->
                        <div class="aspect-square">
                            @if($image->thumbnail_path)
                                <img 
                                    src="{{ Storage::url($image->thumbnail_path) }}" 
                                    alt="{{ $image->alt_text ?: $image->original_filename }}"
                                    class="w-full h-full object-cover">
                            @elseif($image->image_path)
                                <img 
                                    src="{{ Storage::url($image->image_path) }}" 
                                    alt="{{ $image->alt_text ?: $image->original_filename }}"
                                    class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full bg-zinc-200 dark:bg-zinc-600 flex items-center justify-center">
                                    <flux:icon name="photo" class="h-8 w-8 text-zinc-400" />
                                </div>
                            @endif
                        </div>

                        <!-- Image info overlay -->
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/80 to-transparent p-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <p class="text-white text-xs font-medium truncate">
                                {{ $image->original_filename }}
                            </p>
                            @if($image->product)
                                <p class="text-white/80 text-xs truncate">
                                    {{ $image->product->name }}
                                </p>
                            @elseif($image->variant)
                                <p class="text-white/80 text-xs truncate">
                                    {{ $image->variant->sku }}
                                </p>
                            @endif
                        </div>

                        <!-- Action buttons -->
                        @if(!$bulkMode)
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center space-x-2">
                                @if($allowDelete)
                                    <flux:button 
                                        variant="danger" 
                                        size="sm"
                                        wire:click="deleteImage({{ $image->id }})"
                                        wire:confirm="Are you sure you want to delete this image?">
                                        <flux:icon name="trash" class="h-4 w-4" />
                                    </flux:button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <!-- List layout -->
            <div class="space-y-3">
                @foreach($images as $image)
                    <div class="bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div class="flex items-center space-x-4">
                            <!-- Selection checkbox (bulk mode) -->
                            @if($bulkMode)
                                <flux:checkbox 
                                    wire:model="selectedImages" 
                                    value="{{ $image->id }}" />
                            @endif

                            <!-- Thumbnail -->
                            <div class="flex-shrink-0">
                                @if($image->thumbnail_path)
                                    <img 
                                        src="{{ Storage::url($image->thumbnail_path) }}" 
                                        alt="{{ $image->alt_text ?: $image->original_filename }}"
                                        class="h-16 w-16 rounded-lg object-cover">
                                @else
                                    <div class="h-16 w-16 bg-zinc-200 dark:bg-zinc-600 rounded-lg flex items-center justify-center">
                                        <flux:icon name="photo" class="h-6 w-6 text-zinc-400" />
                                    </div>
                                @endif
                            </div>

                            <!-- Image details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                            {{ $image->original_filename }}
                                        </p>
                                        <div class="flex items-center space-x-4 mt-1">
                                            <p class="text-xs text-zinc-500">
                                                {{ $image->image_type ? ucfirst($image->image_type) : 'No type' }}
                                            </p>
                                            <p class="text-xs text-zinc-500">
                                                {{ $image->created_at->format('M j, Y') }}
                                            </p>
                                            @if($image->file_size)
                                                <p class="text-xs text-zinc-500">
                                                    {{ number_format($image->file_size / 1024, 1) }} KB
                                                </p>
                                            @endif
                                        </div>
                                        @if($image->product)
                                            <p class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                                Product: {{ $image->product->name }}
                                            </p>
                                        @elseif($image->variant)
                                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                Variant: {{ $image->variant->sku }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <!-- Processing status -->
                                        @if($image->processing_status !== \App\Models\ProductImage::PROCESSING_COMPLETED)
                                            @if($image->processing_status === \App\Models\ProductImage::PROCESSING_PENDING)
                                                <flux:badge color="yellow" size="sm">Pending</flux:badge>
                                            @elseif($image->processing_status === \App\Models\ProductImage::PROCESSING_IN_PROGRESS)
                                                <flux:badge color="blue" size="sm">Processing</flux:badge>
                                            @elseif($image->processing_status === \App\Models\ProductImage::PROCESSING_FAILED)
                                                <flux:badge color="red" size="sm">Failed</flux:badge>
                                            @endif
                                        @endif

                                        <!-- Actions -->
                                        @if(!$bulkMode && $allowDelete)
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm"
                                                wire:click="deleteImage({{ $image->id }})"
                                                wire:confirm="Are you sure you want to delete this image?">
                                                <flux:icon name="trash" class="h-4 w-4" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>