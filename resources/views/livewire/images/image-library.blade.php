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
                    @foreach($this->folders as $folder)
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
        @if(!empty($this->tags))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Filter by tags:
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->tags as $tag)
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
    @if($this->images->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ $this->images->count() }} of {{ $this->images->total() }} images
            </div>
        </div>
    @endif

    {{-- Images Grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
        @forelse($this->images as $image)
            <div
                    class="group relative bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600"
                    wire:key="image-{{ $image->id }}"
            >
                {{-- Image --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
                        <img
                                src="{{ $image->url }}"
                                alt="{{ $image->alt_text ?? $image->display_title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                        />
                    
                    {{-- Overlay with attachment status --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200"></div>
                    
                    {{-- Simple Delete Button --}}
                    <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <flux:button
                                variant="danger"
                                size="sm"
                                wire:click="deleteImage({{ $image->id }})"
                                wire:confirm="Are you sure you want to delete this image? This cannot be undone."
                                class="bg-red-500/90 hover:bg-red-600 text-white border-red-500/20 shadow-sm"
                        >
                            <flux:icon.trash class="w-3 h-3"/>
                        </flux:button>
                    </div>

                    {{-- Attachment Status Badge --}}
                    <div class="absolute bottom-3 left-3">
                        @if($image->isAttached())
                            <flux:badge variant="success" size="sm" class="bg-emerald-500/90 text-white border-0">
                                <flux:icon.link class="w-3 h-3 mr-1"/>
                                Linked
                            </flux:badge>
                        @endif
                    </div>
                </div>

                {{-- Image Info --}}
                <div class="p-4">
                    <div class="space-y-2">
                        {{-- Title --}}
                        <h4 class="font-medium text-sm text-gray-900 dark:text-white truncate">
                            {{ $image->display_title }}
                        </h4>

                        {{-- Metadata --}}
                        <div class="flex items-center justify-end text-xs text-gray-500 dark:text-gray-400">
                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-full">{{ $image->folder }}</span>
                        </div>

                        {{-- Tags --}}
                        @if(!empty($image->tags))
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($image->tags, 0, 2) as $tag)
                                    <flux:badge variant="outline" size="xs" class="text-xs">{{ $tag }}</flux:badge>
                                @endforeach
                                @if(count($image->tags) > 2)
                                    <span class="text-xs text-gray-400 self-center">+{{ count($image->tags) - 2 }} more</span>
                                @endif
                            </div>
                        @endif
                        
                        {{-- Quick Actions --}}
                        <div class="flex items-center gap-2 pt-1">
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
                                <flux:icon.clipboard class="w-3 h-3 mr-1"/>
                                Copy URL
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="flex flex-col items-center justify-center py-16 px-6">
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-6 mb-6">
                        <flux:icon.photo class="w-12 h-12 text-gray-400 dark:text-gray-500"/>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No images found</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-md text-center">
                        @if($search || $selectedFolder || $selectedTag)
                            No images match your current filters. Try adjusting your search terms or filters.
                        @else
                            Your media library is empty. Upload some images to get started.
                        @endif
                    </p>
                    
                    @if($search || $selectedFolder || $selectedTag)
                        <div class="flex items-center space-x-3">
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
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($this->images->hasPages())
        <div class="flex justify-center pt-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-4">
                {{ $this->images->links() }}
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