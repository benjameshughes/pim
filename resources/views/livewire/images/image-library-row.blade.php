{{-- Check if processing complete based on timestamps --}}
@if($isProcessing)
    {{-- Still processing - show skeleton row --}}
    <div class="flex items-center p-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0">
        {{-- Skeleton Checkbox --}}
        <div class="mr-3">
            <div class="w-4 h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
        </div>
        
        {{-- Skeleton Thumbnail --}}
        <div class="w-16 h-16 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
            <flux:icon name="arrow-path" class="w-6 h-6 animate-spin text-gray-400" />
        </div>
        
        {{-- Skeleton Content --}}
        <div class="flex-1 ml-4 space-y-2">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-48 animate-pulse"></div>
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-32 animate-pulse"></div>
        </div>
        
        {{-- Skeleton Actions --}}
        <div class="flex gap-2">
            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
            <div class="w-8 h-8 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
        </div>
    </div>
@else
    {{-- Processing complete - show actual image row --}}
    <div class="flex items-center p-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/50" wire:key="image-{{ $image->id }}">
        {{-- Selection Checkbox --}}
        <div class="mr-3">
            <flux:checkbox 
                value="{{ $image->id }}"
                :checked="$isSelected"
                x-on:change="$dispatch('image-selection-toggled', { imageId: {{ $image->id }}, checked: $event.target.checked })"
            />
        </div>
        
        {{-- Thumbnail --}}
        <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 flex-shrink-0">
            <a href="{{ route('images.show', $image) }}" wire:navigate>
                <img 
                    src="{{ $this->getThumbnailUrl() }}" 
                    alt="{{ $image->alt_text ?: $image->title ?: 'Image' }}"
                    class="w-full h-full object-cover hover:scale-105 transition-transform duration-200"
                    loading="lazy"
                />
            </a>
        </div>
        
        {{-- Image Info --}}
        <div class="flex-1 ml-4 min-w-0">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    {{-- Filename/Title --}}
                    <h3 class="font-medium text-gray-900 dark:text-white truncate">
                        {{ $image->title ?: $image->display_title }}
                    </h3>
                    
                    {{-- Metadata Row --}}
                    <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <span>{{ $image->width }}×{{ $image->height }}</span>
                        <span class="font-mono">{{ $image->formatted_size }}</span>
                        @if($image->folder)
                            <flux:badge size="xs" color="gray">{{ $image->folder }}</flux:badge>
                        @endif
                        @php $variantCount = $this->getVariantCount() @endphp
                        @if($variantCount > 0)
                            <flux:badge size="xs" color="blue">{{ $variantCount }} variants</flux:badge>
                        @endif
                        @if($image->isAttached())
                            <flux:badge size="xs" color="green">Linked</flux:badge>
                        @endif
                    </div>
                    
                    {{-- Tags --}}
                    @if($image->tags && count($image->tags) > 0)
                        <div class="flex flex-wrap gap-1 mt-2">
                            @foreach(array_slice($image->tags, 0, 3) as $tag)
                                <flux:badge size="xs" color="gray">{{ $tag }}</flux:badge>
                            @endforeach
                            @if(count($image->tags) > 3)
                                <span class="text-xs text-gray-400">+{{ count($image->tags) - 3 }}</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Actions --}}
        <div class="flex items-center gap-1 ml-4">
            <flux:button
                type="button"
                size="xs"
                variant="ghost"
                x-on:click="
                    navigator.clipboard.writeText('{{ $image->url }}').then(() => {
                        $dispatch('notify', { 
                            message: 'Image URL copied to clipboard! 📋', 
                            type: 'success' 
                        })
                    })
                "
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
                <flux:icon name="clipboard" class="w-4 h-4"/>
            </flux:button>
            
            <flux:button
                wire:navigate
                href="{{ route('images.show.edit', $image) }}"
                size="xs"
                variant="ghost"
                class="text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200"
            >
                <flux:icon name="pencil" class="w-4 h-4"/>
            </flux:button>
            
            <flux:button
                size="xs"
                variant="ghost"
                wire:click="deleteImage"
                wire:confirm="Are you sure you want to delete this image? This cannot be undone."
                class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200"
            >
                <flux:icon name="trash" class="w-4 h-4"/>
            </flux:button>
        </div>
    </div>
@endif