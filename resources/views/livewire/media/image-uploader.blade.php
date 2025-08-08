<div class="space-y-4">
    <!-- Upload Area -->
    <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-xl p-6 text-center hover:border-zinc-400 transition-colors
                {{ count($files) > 0 ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20' : '' }}">
        
        <!-- File Input -->
        <input type="file" 
               wire:model="files" 
               class="hidden" 
               id="image-upload-{{ $this->getId() }}"
               {{ $multiple ? 'multiple' : '' }}
               accept="{{ implode(',', array_map(fn($type) => 'image/' . $type, $acceptTypes)) }}">
        
        <!-- Upload Interface -->
        <div class="space-y-3">
            <div class="flex justify-center">
                <flux:icon name="cloud-arrow-up" class="h-12 w-12 text-zinc-400" />
            </div>
            
            <div>
                <label for="image-upload-{{ $this->getId() }}" class="cursor-pointer">
                    <flux:button variant="primary" as="span">
                        <flux:icon name="plus" class="h-4 w-4 mr-2" />
                        Choose Images
                    </flux:button>
                </label>
            </div>
            
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                or drag and drop images here
            </p>
            
            <p class="text-xs text-zinc-400">
                Max {{ $maxSize }}, {{ implode(', ', array_map('strtoupper', $acceptTypes)) }} only
                @if($multiple) • Multiple files allowed @endif
            </p>
        </div>
    </div>

    <!-- File Preview -->
    @if(count($files) > 0)
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:subheading>Selected Files ({{ count($files) }})</flux:subheading>
                <flux:button variant="ghost" size="sm" wire:click="clearFiles">
                    Clear All
                </flux:button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($files as $index => $file)
                    <div class="relative bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                        <div class="flex items-center space-x-3">
                            <!-- File preview -->
                            <div class="flex-shrink-0">
                                @if($file->isValid() && str_starts_with($file->getMimeType(), 'image/'))
                                    <img src="{{ $file->temporaryUrl() }}" 
                                         alt="Preview" 
                                         class="h-12 w-12 object-cover rounded-lg">
                                @else
                                    <div class="h-12 w-12 bg-zinc-200 dark:bg-zinc-600 rounded-lg flex items-center justify-center">
                                        <flux:icon name="document" class="h-6 w-6 text-zinc-400" />
                                    </div>
                                @endif
                            </div>
                            
                            <!-- File info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $file->getClientOriginalName() }}
                                </p>
                                <p class="text-xs text-zinc-500">
                                    {{ number_format($file->getSize() / 1024, 1) }} KB
                                </p>
                            </div>
                            
                            <!-- Remove button -->
                            <flux:button variant="ghost" size="sm" wire:click="removeFile({{ $index }})">
                                <flux:icon name="x-mark" class="h-4 w-4" />
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Configuration Options -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
        <div>
            <flux:field>
                <flux:label>Image Type</flux:label>
                <flux:select wire:model="imageType">
                    <flux:select.option value="main">Main</flux:select.option>
                    <flux:select.option value="detail">Detail</flux:select.option>
                    <flux:select.option value="lifestyle">Lifestyle</flux:select.option>
                    <flux:select.option value="swatch">Swatch</flux:select.option>
                </flux:select>
            </flux:field>
        </div>
        
        <div class="flex items-center">
            <flux:checkbox wire:model="createThumbnails" />
            <flux:label class="ml-2">Create Thumbnails</flux:label>
        </div>
        
        <div class="flex items-center">
            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                Storage: {{ ucfirst($storageDisk) }}
            </span>
        </div>
    </div>

    <!-- Upload Button -->
    <div class="flex items-center justify-between">
        <div>
            @if($errorMessage)
                <div class="text-sm text-red-600 dark:text-red-400">
                    {{ $errorMessage }}
                </div>
            @endif
            
            @if(!empty($uploadResults))
                <div class="text-sm text-green-600 dark:text-green-400">
                    {{ $uploadResults['message'] }}
                </div>
            @endif
        </div>
        
        <flux:button 
            variant="primary" 
            wire:click="upload"
            :disabled="count($files) === 0 || $isUploading">
            
            @if($isUploading)
                <div class="flex items-center">
                    <div class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full mr-2"></div>
                    Uploading...
                </div>
            @else
                <flux:icon name="cloud-arrow-up" class="h-4 w-4 mr-2" />
                Upload {{ count($files) > 0 ? '(' . count($files) . ')' : '' }}
            @endif
        </flux:button>
    </div>
</div>