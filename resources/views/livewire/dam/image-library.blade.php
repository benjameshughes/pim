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
                    <flux:select.option value="mine">My uploads</flux:select.option>
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
        @if(!empty($this->availableTags))
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Filter by tags:
                </label>
                <div class="flex flex-wrap gap-2">
                    @foreach($this->availableTags as $tag)
                        <flux:badge
                                variant="{{ in_array($tag, $selectedTags) ? 'primary' : 'outline' }}"
                                class="cursor-pointer"
                                wire:click="$set('selectedTags', {{ json_encode(
        in_array($tag, $selectedTags)
            ? array_values(array_filter($selectedTags, fn($t) => $t !== $tag))
            : array_merge($selectedTags, [$tag])
    ) }})"
                        >
                            {{ $tag }}
                        </flux:badge>

                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Bulk Selection Controls --}}
    @if($this->images->count() > 0)
        <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex items-center space-x-3">
                <flux:checkbox
                        wire:model.live="selectAll"
                />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    @if($this->allSelected())
                        All {{ $this->images->count() }} images selected
                    @elseif($this->someSelected())
                        {{ count($selectedImages) }} of {{ $this->images->count() }} images selected
                    @else
                        Select all ({{ $this->images->count() }} images)
                    @endif
                </span>
            </div>

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
                {{-- Selection Checkbox --}}
                <div class="absolute top-3 left-3 z-20">
                    <flux:checkbox
                            wire:model.live="selectedImages"
                            value="{{ $image->id }}"
                            class="bg-white/90 dark:bg-gray-800/90 rounded shadow-sm"
                    />
                </div>

                {{-- Image --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
                    <a href="{{ route('dam.images.show', $image) }}" class="block w-full h-full" wire:navigate>
                        <img
                                src="{{ $image->url }}"
                                alt="{{ $image->alt_text ?? $image->display_title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                        />
                    </a>
                    
                    {{-- Overlay with attachment status --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200"></div>
                    
                    {{-- Action Buttons --}}
                    <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-2">
                        <flux:button
                                variant="outline"
                                size="sm"
                                href="{{ route('dam.images.show.edit', $image) }}"
                                wire:navigate
                                class="bg-white/90 hover:bg-white text-gray-700 border-white/20 shadow-sm"
                        >
                            <flux:icon.pencil class="w-3 h-3"/>
                        </flux:button>

                        <flux:button
                                variant="danger"
                                size="sm"
                                @click="confirmAction({
                                    title: 'Delete Image',
                                    message: 'This will permanently delete the image and cannot be undone.\\n\\nAny product attachments will also be removed.',
                                    confirmText: 'Yes, Delete It',
                                    cancelText: 'Cancel',
                                    variant: 'danger',
                                    onConfirm: () => $wire.deleteImage({{ $image->id }})
                                })"
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
                            {{ $image->original_filename }}
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
                                    @click="navigator.clipboard.writeText('{{ $image->url }}'); $wire.copyUrl('{{ $image->url }}')"
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
                        @if($search || $selectedFolder || !empty($selectedTags))
                            No images match your current filters. Try adjusting your search terms or filters.
                        @else
                            Your media library is empty. Upload some images to get started.
                        @endif
                    </p>
                    
                    @if($search || $selectedFolder || !empty($selectedTags))
                        <div class="flex items-center space-x-3">
                            <flux:button variant="outline" wire:click="$set('search', ''); $set('selectedFolder', ''); $set('selectedTags', [])">
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

            {{-- Upload Progress --}}
            @if($isUploading)
                <div class="mb-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-700/50">
                    {{-- Progress Header --}}
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <flux:icon.arrow-path class="w-5 h-5 text-blue-600 animate-spin"/>
                            <span class="font-medium text-blue-800 dark:text-blue-200">
                                Uploading {{ $uploadedCount }} of {{ $totalToUpload }} images
                            </span>
                        </div>
                        <span class="text-sm text-blue-600 dark:text-blue-400">
                            {{ $totalToUpload > 0 ? round(($uploadedCount / $totalToUpload) * 100) : 0 }}%
                        </span>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2 mb-4">
                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: {{ $totalToUpload > 0 ? ($uploadedCount / $totalToUpload) * 100 : 0 }}%"></div>
                    </div>

                    {{-- Currently Uploading Files --}}
                    @if(!empty($uploadingFiles))
                        <div class="space-y-1">
                            @foreach($uploadingFiles as $filename => $status)
                                <div class="flex items-center space-x-2 text-sm">
                                    <flux:icon.arrow-up class="w-4 h-4 text-blue-600 animate-pulse"/>
                                    <span class="text-blue-700 dark:text-blue-300">{{ $filename }}</span>
                                    <span class="text-blue-600 dark:text-blue-400">{{ ucfirst($status) }}...</span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Completed Files --}}
                    @if(!empty($uploadResults))
                        <div class="mt-3 max-h-32 overflow-y-auto space-y-1">
                            @foreach($uploadResults as $filename => $result)
                                <div class="flex items-center space-x-2 text-sm">
                                    @if($result === 'success')
                                        <flux:icon.check-circle class="w-4 h-4 text-green-600"/>
                                        <span class="text-green-700 dark:text-green-300">{{ $filename }}</span>
                                        <span class="text-green-600 dark:text-green-400">Uploaded</span>
                                    @else
                                        <flux:icon.x-circle class="w-4 h-4 text-red-600"/>
                                        <span class="text-red-700 dark:text-red-300">{{ $filename }}</span>
                                        <span class="text-red-600 dark:text-red-400">Failed</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

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


    {{-- Delete Confirmation Modal --}}
    <flux:modal wire:model="showDeleteConfirmModal" class="w-full max-w-7xl mx-auto">
        @if($showDeleteConfirmModal && !empty($pendingDeleteAction))
            <div class="p-6">
                <div class="flex items-center mb-6">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/20 rounded-full flex items-center justify-center mr-4">
                        <flux:icon.exclamation-triangle class="w-6 h-6 text-red-600"/>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            Confirm Deletion
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            This action cannot be undone
                        </p>
                    </div>
                </div>

                {{-- Confirmation Message --}}
                <div class="mb-6">
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 rounded-lg p-4">
                        <p class="text-red-800 dark:text-red-200">
                            Are you sure you want to delete
                            <span class="font-semibold">{{ count($pendingDeleteAction['items'] ?? []) }}</span>
                            {{ count($pendingDeleteAction['items'] ?? []) === 1 ? 'image' : 'images' }}?
                        </p>
                        <p class="text-sm text-red-700 dark:text-red-300 mt-2">
                            The selected images will be permanently removed from your DAM library and any products
                            they're linked to.
                        </p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex justify-end">
                    <flux:button
                            variant="danger"
                            wire:click="confirmBulkDelete"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50"
                    >
                        <flux:icon.trash class="w-4 h-4 mr-2"/>
                        <span wire:loading.remove wire:target="confirmBulkDelete">Delete Images</span>
                        <span wire:loading wire:target="confirmBulkDelete">Deleting...</span>
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Floating Action Bar --}}
    <livewire:components.floating-action-bar :selected-items="$selectedImages" wire:key="floating-bar-{{ count($selectedImages) }}"/>
</div>