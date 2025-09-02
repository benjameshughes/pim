<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden group hover:shadow-lg transition-shadow">
    {{-- Main Image --}}
    <div class="aspect-square bg-gray-100 dark:bg-gray-700 overflow-hidden relative">
        <a href="{{ route('images.show', $image) }}" wire:navigate>
            {{-- Get thumbnail URL, fallback to original --}}
            @php 
                $thumbnailImage = \App\Models\Image::where('folder', 'variants')
                    ->whereJsonContains('tags', "original-{$image->id}")
                    ->whereJsonContains('tags', 'thumb')
                    ->first();
                $displayUrl = $thumbnailImage ? $thumbnailImage->url : $image->url;
            @endphp
            
            <img 
                src="{{ $displayUrl }}" 
                alt="{{ $image->alt_text ?: $image->title ?: 'Image' }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                loading="lazy"
            />
        </a>


        {{-- Attachment Status Badge --}}
        @if($image->isAttached())
            <div class="absolute bottom-2 left-2">
                <flux:badge size="sm" class="bg-emerald-500/90 text-white border-0">
                    <flux:icon name="link" class="w-3 h-3 mr-1"/>
                    Linked
                </flux:badge>
            </div>
        @endif
    </div>

    {{-- Image Info --}}
    <div class="p-4">
        <h3 class="font-medium text-gray-900 dark:text-white truncate mb-1">
            {{ $image->title ?: $image->display_title }}
        </h3>
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
            <span class="font-mono">{{ $image->formatted_size }}</span>
            <span>{{ $image->width }}×{{ $image->height }}</span>
        </div>
        <div class="flex items-center justify-between mt-2">
            <div class="flex items-center gap-1">
                @if($image->folder)
                    <flux:badge size="xs" color="gray">
                        <flux:icon name="folder" class="h-3 w-3" />
                        {{ $image->folder }}
                    </flux:badge>
                @endif
                
                {{-- Original Badge --}}
                <flux:badge size="xs" color="green">
                    <flux:icon name="star" class="h-3 w-3" />
                    Original
                </flux:badge>
                
                {{-- Variant Count Badge --}}
                @php $variantCount = $this->getVariantCount() @endphp
                @if($variantCount > 0)
                    <flux:badge 
                        size="xs" 
                        color="blue"
                        class="cursor-pointer"
                        wire:click="toggleVariants"
                    >
                        <flux:icon name="sparkles" class="h-3 w-3" />
                        {{ $variantCount }}
                    </flux:badge>
                @endif
            </div>
            
            {{-- Action Buttons --}}
            <div class="flex items-center gap-1">
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
                    class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <flux:icon name="clipboard" class="w-3 h-3"/>
                </flux:button>
                
                <flux:button
                    wire:navigate
                    href="{{ route('images.show.edit', $image) }}"
                    size="xs"
                    variant="ghost"
                    class="text-xs text-blue-500 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-200"
                >
                    <flux:icon name="pencil" class="w-3 h-3"/>
                </flux:button>
                
                <flux:button
                    size="xs"
                    variant="ghost"
                    wire:click="$parent.deleteImage({{ $image->id }})"
                    wire:confirm="Are you sure you want to delete this image? This cannot be undone."
                    class="text-xs text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200"
                >
                    <flux:icon name="trash" class="w-3 h-3"/>
                </flux:button>
            </div>
        </div>

        {{-- Tags --}}
        @if($image->tags && count($image->tags) > 0)
            <div class="flex flex-wrap gap-1 mt-2">
                @foreach(array_slice($image->tags, 0, 2) as $tag)
                    <flux:badge size="xs" color="gray">{{ $tag }}</flux:badge>
                @endforeach
                @if(count($image->tags) > 2)
                    <span class="text-xs text-gray-400">+{{ count($image->tags) - 2 }} more</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Variants Preview (when expanded) --}}
    @if($showVariants && $variants)
        <div class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700">
            <div class="pt-3">
                <div class="flex items-center gap-2 mb-2">
                    <flux:icon name="photo" class="h-4 w-4 text-purple-600" />
                    <span class="text-sm font-medium text-gray-900 dark:text-white">Variants</span>
                </div>
                
                <div class="grid grid-cols-3 gap-2">
                    @foreach($variants->take(6) as $variant)
                        <div class="relative group cursor-pointer">
                            <a href="{{ route('images.show', $variant) }}" wire:navigate>
                                <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded overflow-hidden">
                                    <img 
                                        src="{{ $variant->url }}" 
                                        alt="{{ $variant->getVariantType() ?: 'Variant' }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                    />
                                </div>
                                <div class="absolute inset-x-0 bottom-0 bg-black/50 text-white text-xs p-1 text-center">
                                    {{ ucfirst(str_replace('_', ' ', $variant->getVariantType() ?: 'variant')) }}
                                </div>
                            </a>
                        </div>
                    @endforeach
                    
                    {{-- Show more indicator if there are more than 6 variants --}}
                    @if($variants->count() > 6)
                        <div class="aspect-square bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center">
                            <div class="text-center">
                                <flux:icon name="ellipsis-horizontal" class="h-4 w-4 text-gray-600 dark:text-gray-400 mx-auto" />
                                <span class="text-xs text-gray-600 dark:text-gray-400">+{{ $variants->count() - 6 }} more</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Loading state for variants --}}
    @if($showVariants)
        <div wire:loading wire:target="toggleVariants" class="px-4 pb-4 border-t border-gray-200 dark:border-gray-700">
            <div class="pt-3 flex items-center gap-2 text-sm text-gray-500">
                <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" />
                Loading variants...
            </div>
        </div>
    @endif
</div>