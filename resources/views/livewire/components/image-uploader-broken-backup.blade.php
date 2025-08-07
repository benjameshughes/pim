<div class="space-y-6">
    <!-- Header with Configuration Info -->
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <flux:heading size="md" class="text-zinc-900 dark:text-zinc-50">
                {{ ucfirst($imageType) }} Images
            </flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                @if($modelType && $modelId)
                    @if($modelType === 'product')
                        Product images
                    @elseif($modelType === 'variant')
                        Variant images
                    @endif
                @else
                    Unassigned images
                @endif
                • Max {{ $maxFiles }} files • Max {{ number_format($maxSize / 1024, 1) }}MB each
            </flux:subheading>
        </div>
        
        <div class="flex items-center gap-3">
            @if($showExistingImages && $existingImages->count() > 0)
                <flux:button 
                    variant="ghost" 
                    size="sm"
                    icon="{{ $viewMode === 'grid' ? 'list' : 'grid' }}"
                    wire:click="toggleViewMode"
                    class="text-zinc-500 hover:text-zinc-700"
                >
                    {{ $viewMode === 'grid' ? 'List' : 'Grid' }}
                </flux:button>
            @endif
            
            @if(!empty($files) || !$showUploadArea)
                <flux:button 
                    variant="outline" 
                    size="sm"
                    icon="{{ $showUploadArea ? 'chevron-up' : 'chevron-down' }}"
                    wire:click="toggleUploadArea"
                >
                    {{ $showUploadArea ? 'Hide' : 'Show' }} Upload
                </flux:button>
            @endif
        </div>
    </div>

    <!-- Upload Area -->
    @if($showUploadArea)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <!-- Drag & Drop Zone -->
            <div 
                x-data="{ 
                    isDragOver: @entangle('isDragOver').live,
                    handleDrop(event) {
                        event.preventDefault();
                        this.isDragOver = false;
                        const files = Array.from(event.dataTransfer.files);
                        if (files.length > 0) {
                            const fileInput = this.$el.querySelector('input[type=file]');
                            if (fileInput) {
                                fileInput.files = event.dataTransfer.files;
                                fileInput.dispatchEvent(new Event('input', { bubbles: true }));
                            }
                        }
                    }
                }"
                @dragover.prevent="isDragOver = true"
                @dragleave.prevent="isDragOver = false"
                @drop.prevent="handleDrop($event)"
                class="relative p-8 border-2 border-dashed transition-all duration-200 {{ $isDragOver ? 'border-blue-400 bg-blue-50 dark:bg-blue-950/20' : 'border-zinc-300 dark:border-zinc-600' }} {{ $isUploading ? 'pointer-events-none opacity-60' : 'hover:border-blue-400 hover:bg-blue-50/30 dark:hover:bg-blue-950/10' }}"
            >
                <div class="text-center space-y-4">
                    @if($isUploading)
                        <div class="flex flex-col items-center space-y-3">
                            <div class="animate-spin rounded-full h-8 w-8 border-2 border-blue-600 border-t-transparent"></div>
                            <flux:subheading class="text-blue-600 dark:text-blue-400">
                                Uploading images...
                            </flux:subheading>
                        </div>
                    @else
                        <flux:icon name="cloud-upload" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto" />
                        <div class="space-y-2">
                            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50">
                                {{ $uploadText }}
                            </flux:heading>
                            <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                                Supports {{ implode(', ', array_map('strtoupper', $acceptTypes)) }} files up to {{ number_format($maxSize / 1024, 1) }}MB
                            </flux:subheading>
                        </div>
                        
                        <input 
                            type="file" 
                            wire:model="files"
                            {{ $multiple ? 'multiple' : '' }}
                            accept="{{ $acceptTypesString }}"
                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                        >
                    @endif
                </div>
            </div>

            <!-- File Preview Area -->
            @if(!empty($files) || !empty($uploadProgress))
                <div class="border-t border-zinc-200 dark:border-zinc-700 p-6 bg-zinc-50/50 dark:bg-zinc-800/20">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50">
                                Selected Files ({{ count($files) }})
                            </flux:heading>
                            
                            <div class="flex items-center gap-2">
                                @if(!$isUploading && !empty($files))
                                    <flux:button 
                                        variant="ghost" 
                                        size="sm"
                                        icon="x"
                                        wire:click="clearFiles"
                                        class="text-red-600 hover:text-red-700"
                                    >
                                        Clear All
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            @foreach($files as $index => $file)
                                <div class="relative group" wire:key="file-{{ $index }}">
                                    <div class="aspect-square rounded-lg overflow-hidden border-2 {{ isset($validationErrors['file_' . $index]) ? 'border-red-300 dark:border-red-700' : 'border-zinc-200 dark:border-zinc-700' }}">
                                        @if($showPreview && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                            <img 
                                                src="{{ $file->temporaryUrl() }}" 
                                                alt="Preview"
                                                class="w-full h-full object-cover"
                                            >
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-zinc-100 dark:bg-zinc-800">
                                                <flux:icon name="image" class="w-8 h-8 text-zinc-400" />
                                            </div>
                                        @endif
                                    </div>

                                    <!-- File Info -->
                                    <div class="mt-2 space-y-1">
                                        <p class="text-xs font-medium text-zinc-900 dark:text-zinc-50 truncate" title="{{ $file->getClientOriginalName() }}">
                                            {{ Str::limit($file->getClientOriginalName(), 20) }}
                                        </p>
                                        <p class="text-xs text-zinc-500">
                                            {{ number_format($file->getSize() / 1024, 1) }}KB
                                        </p>
                                        
                                        <!-- Progress Bar -->
                                        @if(isset($uploadProgress['file_' . $index]))
                                            @php $progress = $uploadProgress['file_' . $index] @endphp
                                            <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-1.5">
                                                <div 
                                                    class="h-1.5 rounded-full transition-all duration-300 {{ $progress['status'] === 'uploaded' ? 'bg-green-500' : ($progress['status'] === 'failed' ? 'bg-red-500' : 'bg-blue-500') }}"
                                                    style="width: {{ $progress['progress'] ?? 0 }}%"
                                                ></div>
                                            </div>
                                        @endif
                                    </div>

                                    <!-- Validation Errors -->
                                    @if(isset($validationErrors['file_' . $index]))
                                        <div class="mt-1 space-y-1">
                                            @foreach($validationErrors['file_' . $index] as $error)
                                                <p class="text-xs text-red-600 dark:text-red-400">{{ $error }}</p>
                                            @endforeach
                                        </div>
                                    @endif

                                    <!-- Remove Button -->
                                    @if(!$isUploading)
                                        <button 
                                            wire:click="removeFile({{ $index }})"
                                            class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center transition-colors duration-150 opacity-0 group-hover:opacity-100"
                                        >
                                            <flux:icon name="x" class="w-3 h-3" />
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <!-- Global Validation Errors -->
                        @if($hasErrors && !empty($validationErrors))
                            <div class="p-3 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-lg">
                                <div class="flex items-start space-x-2">
                                    <flux:icon name="triangle-alert" class="w-4 h-4 text-red-600 dark:text-red-400 mt-0.5 shrink-0" />
                                    <div class="space-y-1">
                                        <p class="text-sm font-medium text-red-700 dark:text-red-300">Please fix these issues:</p>
                                        @foreach(collect($validationErrors)->flatten() as $error)
                                            <p class="text-xs text-red-600 dark:text-red-400">• {{ $error }}</p>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Upload Button -->
                        <div class="flex items-center justify-between pt-2">
                            <div class="text-xs text-zinc-500">
                                @if($processImmediately)
                                    Images will be processed automatically after upload
                                @else
                                    Images will be stored and can be processed later
                                @endif
                            </div>
                            
                            <flux:button 
                                wire:click="upload"
                                wire:loading.attr="disabled"
                                wire:loading.class="animate-pulse opacity-60"
                                {{ !$canUpload ? 'disabled' : '' }}
                                variant="primary"
                                icon="{{ $isUploading ? 'loading' : 'upload' }}"
                                class="{{ $isUploading ? 'animate-pulse' : '' }}"
                            >
                                <span wire:loading.remove wire:target="upload">
                                    Upload {{ count($files) }} Files
                                </span>
                                <span wire:loading wire:target="upload" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Uploading...
                                </span>
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Existing Images -->
    @if($showExistingImages)
        @if($existingImages->count() > 0)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="p-6 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50">
                            Existing Images ({{ $existingImages->count() }})
                        </flux:heading>
                        
                        @if($allowReorder && $existingImages->count() > 1)
                            <flux:subheading class="text-zinc-500 dark:text-zinc-400 text-xs">
                                Drag to reorder
                            </flux:subheading>
                        @endif
                    </div>
                </div>

                <div class="p-6">
                    @if($viewMode === 'grid')
                        <div
                            x-data="{
                                sortable: null,
                                init() {
                                    if ({{ $allowReorder ? 'true' : 'false' }} && this.$refs.container) {
                                        this.sortable = new Sortable(this.$refs.container, {
                                            animation: 150,
                                            ghostClass: 'opacity-30',
                                            onEnd: (event) => {
                                                const orderedIds = Array.from(this.$refs.container.children)
                                                    .map(el => parseInt(el.dataset.imageId));
                                                @this.call('updateImageSortOrder', orderedIds);
                                            }
                                        });
                                    }
                                }
                            }"
                            x-ref="container"
                            class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4"
                        >
                            @foreach($existingImages as $image)
                                <div 
                                    data-image-id="{{ $image->id }}"
                                    class="relative group {{ $allowReorder ? 'cursor-move' : '' }}"
                                    wire:key="existing-{{ $image->id }}"
                                >
                                    <div class="aspect-square rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-700 relative">
                                        <img 
                                            src="{{ $image->getVariantUrl('small') }}" 
                                            alt="{{ $image->alt_text }}"
                                            class="w-full h-full object-cover"
                                            loading="lazy"
                                        >

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

                                        <!-- Action Buttons -->
                                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                            <flux:button 
                                                size="sm"
                                                variant="ghost"
                                                icon="trash-2"
                                                wire:click="deleteExistingImage({{ $image->id }})"
                                                wire:confirm="Are you sure you want to delete this image?"
                                                class="bg-red-500/80 hover:bg-red-600 text-white w-6 h-6 p-0"
                                            />
                                        </div>

                                        <!-- Sort Order Indicator -->
                                        @if($allowReorder)
                                            <div class="absolute top-2 left-2 bg-black/70 text-white text-xs rounded px-1.5 py-0.5">
                                                {{ $image->sort_order ?: $loop->iteration }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-2 space-y-1">
                                        <p class="text-xs font-medium text-zinc-900 dark:text-zinc-50 truncate">
                                            {{ $image->original_filename }}
                                        </p>
                                        <div class="flex items-center justify-between text-xs text-zinc-500">
                                            <span>{{ number_format($image->file_size / 1024, 1) }}KB</span>
                                            <span class="px-2 py-0.5 bg-zinc-100 dark:bg-zinc-800 rounded text-xs">
                                                {{ $image->processing_status }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- List View -->
                        <div class="space-y-2">
                            @foreach($existingImages as $image)
                                <div class="flex items-center gap-4 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg" wire:key="existing-list-{{ $image->id }}">
                                    <img 
                                        src="{{ $image->getVariantUrl('thumbnail') }}" 
                                        alt="{{ $image->alt_text }}"
                                        class="w-12 h-12 object-cover rounded"
                                    >
                                    
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-zinc-900 dark:text-zinc-50 truncate">
                                            {{ $image->original_filename }}
                                        </p>
                                        <p class="text-sm text-zinc-500">
                                            {{ number_format($image->file_size / 1024, 1) }}KB • {{ $image->processing_status }}
                                        </p>
                                    </div>

                                    <flux:button 
                                        size="sm"
                                        variant="ghost"
                                        icon="trash-2"
                                        wire:click="deleteExistingImage({{ $image->id }})"
                                        wire:confirm="Are you sure you want to delete this image?"
                                        class="text-red-600 hover:text-red-700"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="text-center py-8 bg-zinc-50 dark:bg-zinc-800/20 rounded-xl border-2 border-dashed border-zinc-300 dark:border-zinc-600">
                <flux:icon name="image" class="w-12 h-12 text-zinc-400 dark:text-zinc-500 mx-auto mb-3" />
                <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-400 mb-1">
                    No {{ $imageType }} images yet
                </flux:heading>
                <flux:subheading class="text-zinc-500 dark:text-zinc-400">
                    Upload some images to get started
                </flux:subheading>
            </div>
        @endif
    @endif

    <!-- Required Scripts -->
    @once
        @push('scripts')
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        @endpush
    @endonce
</div>