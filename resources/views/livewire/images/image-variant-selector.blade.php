<div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <flux:icon name="photo" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            <h3 class="font-medium text-gray-900 dark:text-white">Image Variants</h3>
            <flux:badge size="sm" color="gray">{{ count($imageFamily) }}</flux:badge>
        </div>
        
        @if(count($variants) === 0 && $originalImage)
            <flux:button 
                wire:click="generateVariants" 
                size="sm" 
                variant="outline"
                icon="sparkles"
            >
                Generate Variants
            </flux:button>
        @endif
    </div>

    {{-- Variant Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
        @foreach($imageFamily as $image)
            @php
                $isActive = $image['id'] === $currentImage->id;
                $isVariant = in_array('variant', $image['tags'] ?? []);
                $variantType = $isVariant ? collect($image['tags'])->intersect(['thumb', 'small', 'medium', 'large'])->first() : 'original';
                $processingStatus = $this->processingStatuses[$image['id']] ?? null;
            @endphp
            
            <div 
                wire:click="selectImage({{ $image['id'] }})"
                class="relative group cursor-pointer rounded-lg overflow-hidden border-2 transition-all duration-200
                       {{ $isActive 
                          ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-800' 
                          : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}"
            >
                {{-- Image Thumbnail --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <img 
                        src="{{ $image['url'] }}" 
                        alt="{{ $image['alt_text'] ?: $image['title'] ?: 'Image thumbnail' }}"
                        class="w-full h-full object-cover transition-transform group-hover:scale-105"
                        loading="lazy"
                    />
                </div>
                
                {{-- Processing Status Overlay --}}
                @if($processingStatus)
                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                        <div class="text-center text-white">
                            <flux:icon 
                                name="{{ $processingStatus['icon'] }}" 
                                class="h-6 w-6 mx-auto mb-1 
                                       {{ $processingStatus['status']->value === 'processing' ? 'animate-spin' : '' }}"
                            />
                            <p class="text-xs font-medium">{{ $processingStatus['label'] }}</p>
                        </div>
                    </div>
                @endif
                
                {{-- Active Indicator --}}
                @if($isActive)
                    <div class="absolute top-2 right-2">
                        <div class="bg-blue-500 text-white rounded-full p-1">
                            <flux:icon name="check" class="h-3 w-3" />
                        </div>
                    </div>
                @endif
                
                {{-- Image Info --}}
                <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent p-2">
                    <div class="text-white text-xs">
                        {{-- Variant Type Badge --}}
                        <div class="flex items-center justify-between mb-1">
                            <flux:badge 
                                size="xs" 
                                :color="$isVariant ? 'blue' : 'green'"
                            >
                                {{ ucfirst($variantType) }}
                            </flux:badge>
                            
                            {{-- Dimensions --}}
                            @if($image['width'] && $image['height'])
                                <span class="text-xs opacity-90">
                                    {{ $image['width'] }}×{{ $image['height'] }}
                                </span>
                            @endif
                        </div>
                        
                        {{-- File Size --}}
                        @if($image['size'])
                            <p class="text-xs opacity-75 truncate">
                                {{ $this->formatFileSize($image['size']) }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- No Variants Message --}}
    @if(count($variants) === 0 && $originalImage)
        <div class="text-center py-4 text-gray-500 dark:text-gray-400 text-sm border-t border-gray-200 dark:border-gray-700 mt-4">
            <flux:icon name="photo" class="h-8 w-8 mx-auto mb-2 opacity-50" />
            <p>No variants generated yet</p>
            <p class="text-xs mt-1">Click "Generate Variants" to create thumbnails</p>
        </div>
    @endif
</div>

@script
<script>
    // Helper function for file size formatting (could be moved to a trait or helper)
    window.formatFileSize = function(bytes) {
        if (bytes >= 1024 * 1024) {
            return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return bytes + ' B';
    };
</script>
@endscript