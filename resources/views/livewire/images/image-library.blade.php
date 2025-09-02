<div class="space-y-4">
    {{-- Header Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- Primary Controls --}}
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
            {{-- Search --}}
            <div class="flex-1 max-w-sm">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search images..."
                    size="sm"
                >
                    <x-slot name="iconTrailing">
                        <flux:icon.magnifying-glass class="w-4 h-4 text-gray-400"/>
                    </x-slot>
                </flux:input>
            </div>
            
            {{-- Quick Stats --}}
            @php $images = $this->getImages() @endphp
            <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                <span class="font-medium">
                    {{ number_format($images->total()) }} images
                </span>
                @if($search || $selectedFolder || $selectedTag || $filterBy !== 'all')
                    <span>({{ number_format($images->count()) }} filtered)</span>
                    <flux:button
                        variant="outline"
                        size="xs"
                        wire:click="clearFilters"
                    >
                        Clear Filters
                    </flux:button>
                @endif
            </div>
        </div>
        
        {{-- Secondary Controls --}}
        <div class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50">
            {{-- Folder Filter --}}
            <div class="min-w-0">
                <flux:select wire:model.live="selectedFolder" placeholder="All folders" size="sm">
                    <flux:select.option value="">All folders</flux:select.option>
                    @foreach($this->getFolders() as $folder)
                        <flux:select.option value="{{ $folder }}">{{ ucfirst($folder) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Status Filter --}}
            <div class="min-w-0">
                <flux:select wire:model.live="filterBy" size="sm">
                    <flux:select.option value="all">All images</flux:select.option>
                    <flux:select.option value="unattached">Unlinked</flux:select.option>
                    <flux:select.option value="attached">Linked</flux:select.option>
                </flux:select>
            </div>

            {{-- Tag Filter --}}
            @php $tags = $this->getTags() @endphp
            @if(!empty($tags))
                <div class="min-w-0">
                    <flux:select wire:model.live="selectedTag" placeholder="All tags" size="sm">
                        <flux:select.option value="">All tags</flux:select.option>
                        @foreach($tags as $tag)
                            <flux:select.option value="{{ $tag }}">{{ $tag }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            {{-- Sort Controls --}}
            <div class="flex items-center gap-2 ml-auto">
                <flux:select wire:model.live="sortBy" size="sm" class="min-w-0">
                    <flux:select.option value="created_at">Upload date</flux:select.option>
                    <flux:select.option value="title">Title</flux:select.option>
                    <flux:select.option value="filename">Filename</flux:select.option>
                    <flux:select.option value="size">File size</flux:select.option>
                </flux:select>
                <flux:button
                    variant="ghost"
                    size="sm"
                    wire:click="$toggle('sortDirection')"
                    class="px-2"
                >
                    <flux:icon.arrows-up-down class="w-4 h-4"/>
                </flux:button>
            </div>
        </div>
        
        {{-- Active Filters Display --}}
        @if($search || $selectedFolder || $selectedTag || $filterBy !== 'all')
            <div class="flex flex-wrap items-center gap-2 p-3 border-t border-gray-200 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Active filters:</span>
                
                @if($search)
                    <flux:badge size="sm" color="blue">
                        Search: "{{ $search }}"
                        <button wire:click="$set('search', '')" class="ml-1 hover:text-red-600">
                            <flux:icon name="x-mark" class="w-3 h-3"/>
                        </button>
                    </flux:badge>
                @endif
                
                @if($selectedFolder)
                    <flux:badge size="sm" color="green">
                        Folder: {{ $selectedFolder }}
                        <button wire:click="$set('selectedFolder', '')" class="ml-1 hover:text-red-600">
                            <flux:icon name="x-mark" class="w-3 h-3"/>
                        </button>
                    </flux:badge>
                @endif
                
                @if($selectedTag)
                    <flux:badge size="sm" color="purple">
                        Tag: {{ $selectedTag }}
                        <button wire:click="$set('selectedTag', '')" class="ml-1 hover:text-red-600">
                            <flux:icon name="x-mark" class="w-3 h-3"/>
                        </button>
                    </flux:badge>
                @endif
                
                @if($filterBy !== 'all')
                    <flux:badge size="sm" color="orange">
                        Status: {{ ucfirst($filterBy) }}
                        <button wire:click="$set('filterBy', 'all')" class="ml-1 hover:text-red-600">
                            <flux:icon name="x-mark" class="w-3 h-3"/>
                        </button>
                    </flux:badge>
                @endif
            </div>
        @endif
    </div>


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