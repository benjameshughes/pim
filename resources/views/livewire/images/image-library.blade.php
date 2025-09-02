<div class="space-y-6">
    {{-- Filters and Search --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-6 shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search images..."
                >
                    <x-slot name="iconTrailing">
                        <flux:icon.magnifying-glass class="w-5 h-5 text-gray-400"/>
                    </x-slot>
                </flux:input>
            </div>

            {{-- Folder Filter --}}
            <div>
                <flux:select wire:model.live="selectedFolder" placeholder="All folders">
                    <flux:select.option value="">All folders</flux:select.option>
                    @foreach($this->getFolders() as $folder)
                        <flux:select.option value="{{ $folder }}">{{ ucfirst($folder) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Status Filter --}}
            <div>
                <flux:select wire:model.live="filterBy" placeholder="All images">
                    <flux:select.option value="all">All images</flux:select.option>
                    <flux:select.option value="unattached">Unlinked</flux:select.option>
                    <flux:select.option value="attached">Linked to products</flux:select.option>
                </flux:select>
            </div>

            {{-- Sort --}}
            <div class="flex space-x-2">
                <flux:select wire:model.live="sortBy" class="flex-1">
                    <flux:select.option value="created_at">Upload date</flux:select.option>
                    <flux:select.option value="title">Title</flux:select.option>
                    <flux:select.option value="filename">Filename</flux:select.option>
                    <flux:select.option value="size">File size</flux:select.option>
                </flux:select>
                <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="$toggle('sortDirection')"
                        class="px-3"
                >
                    <flux:icon.arrows-up-down class="w-4 h-4"/>
                </flux:button>
            </div>
        </div>

        {{-- Tag Filter --}}
        @php $tags = $this->getTags() @endphp
        @if(!empty($tags))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Filter by tags:
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($tags as $tag)
                        <flux:badge
                                variant="{{ $selectedTag === $tag ? 'primary' : 'outline' }}"
                                class="cursor-pointer"
                                wire:click="$set('selectedTag', '{{ $selectedTag === $tag ? '' : $tag }}')"
                        >
                            {{ $tag }}
                        </flux:badge>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Simple image count instead of bulk selection --}}
    @php $images = $this->getImages() @endphp
    @if($images->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ $images->count() }} of {{ $images->total() }} images
            </div>
        </div>
    @endif

    {{-- Images List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        @forelse($images as $image)
            <div wire:key="image-{{ $image->id }}" class="flex items-center p-4 border-b border-gray-200 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                {{-- Check if processing complete based on timestamps --}}
                @if($image->created_at == $image->updated_at)
                    {{-- Still processing - show skeleton row --}}
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
                @else
                    {{-- Processing complete - show actual image row --}}
                    {{-- Thumbnail --}}
                    <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-700 flex-shrink-0">
                        @php 
                            $thumbnailImage = \App\Models\Image::where('folder', 'variants')
                                ->whereJsonContains('tags', "original-{$image->id}")
                                ->whereJsonContains('tags', 'thumb')
                                ->first();
                            $displayUrl = $thumbnailImage ? $thumbnailImage->url : $image->url;
                        @endphp
                        
                        <a href="{{ route('images.show', $image) }}" wire:navigate>
                            <img 
                                src="{{ $displayUrl }}" 
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
                                    @php 
                                        $variantCount = \App\Models\Image::where('folder', 'variants')
                                            ->whereJsonContains('tags', "original-{$image->id}")
                                            ->count();
                                    @endphp
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
                            wire:click="$parent.deleteImage({{ $image->id }})"
                            wire:confirm="Are you sure you want to delete this image? This cannot be undone."
                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200"
                        >
                            <flux:icon name="trash" class="w-4 h-4"/>
                        </flux:button>
                    </div>
                @endif
            </div>
        @empty
            <div class="p-16 text-center">
                <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-6 mb-6 inline-flex">
                    <flux:icon.photo class="w-12 h-12 text-gray-400 dark:text-gray-500"/>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No images found</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    @if($search || $selectedFolder || $selectedTag)
                        No images match your current filters. Try adjusting your search terms or filters.
                    @else
                        Your media library is empty. Upload some images to get started.
                    @endif
                </p>
                
                @if($search || $selectedFolder || $selectedTag)
                    <div class="flex items-center justify-center space-x-3">
                        <flux:button variant="outline" wire:click="clearFilters">
                            Clear Filters
                        </flux:button>
                        <flux:button variant="primary" icon="upload" wire:click="openUploadModal">
                            Upload Images
                        </flux:button>
                    </div>
                @else
                    <flux:button variant="primary" icon="upload" wire:click="openUploadModal" class="px-8 py-3">
                        Upload Your First Images
                    </flux:button>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($images->hasPages())
        <div class="flex justify-center pt-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-4">
                {{ $images->links() }}
            </div>
        </div>
    @endif

    {{-- Upload Modal --}}
    <flux:modal wire:model="showUploadModal" class="w-full max-w-7xl mx-auto">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Upload Images</h2>
            </div>

            {{-- Upload Progress removed - using simple toast notifications instead --}}

            <form wire:submit="uploadImages" class="space-y-4">
                {{-- File Upload --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Select Images (max 10)
                    </label>
                    <input
                        type="file"
                        wire:model="newImages"
                        multiple
                        accept="image/*"
                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    @error('newImages.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                {{-- Metadata --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input
                            label="Title (optional)"
                            wire:model="uploadMetadata.title"
                            placeholder="Enter image title..."
                        />
                    </div>

                    <div>
                        <flux:input
                            label="Alt Text (optional)"
                            wire:model="uploadMetadata.alt_text"
                            placeholder="Describe the image..."
                        />
                    </div>
                </div>

                <div>
                    <flux:textarea
                        label="Description (optional)"
                        wire:model="uploadMetadata.description"
                        placeholder="Enter image description..."
                        rows="3"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input
                            label="Folder"
                            wire:model="uploadMetadata.folder"
                            placeholder="uncategorized"
                        />
                    </div>

                    <div>
                        <flux:input
                            label="Tags (comma-separated)"
                            wire:model="uploadMetadata.tags"
                            placeholder="product, hero, banner..."
                        />
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end pt-4">
                    <flux:button
                        icon="upload"
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75"
                    >
                        <span wire:loading.remove>Upload Images</span>
                        <span wire:loading>
                            <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin"/>
                            Uploading...
                        </span>
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>


    {{-- Delete confirmation modal and bulk actions removed - using simple inline delete --}}
</div>