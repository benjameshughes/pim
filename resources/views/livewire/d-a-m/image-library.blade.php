<div class="space-y-6">
    {{-- Header Upload Button (positioned to align with page header) --}}
    <div class="flex justify-end -mt-16 mb-10">
        <flux:button
                variant="primary"
                icon="upload"
                wire:click="openUploadModal"
        >
            Upload Images
        </flux:button>
    </div>

    {{-- Filters and Search --}}
    <div class="space-y-4">
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
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
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
        <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
            <div class="flex items-center space-x-3">
                <flux:checkbox
                        wire:model.live="selectAll"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    @if($this->allSelected())
                        All {{ $this->images->count() }} images selected
                    @elseif($this->someSelected())
                        {{ count($selectedImages) }} of {{ $this->images->count() }} images selected
                    @else
                        Select all ({{ $this->images->count() }} images)
                    @endif
                </span>
            </div>

            <div class="text-sm text-gray-500">
                Showing {{ $this->images->count() }} of {{ $this->images->total() }} images
            </div>
        </div>
    @endif

    {{-- Images Grid --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
        @forelse($this->images as $image)
            <div
                    class="group relative bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-lg transition-shadow duration-200 overflow-hidden"
                    wire:key="image-{{ $image->id }}"
            >
                {{-- Selection Checkbox --}}
                <div class="absolute top-2 left-2 z-10">
                    <flux:checkbox
                            wire:model.live="selectedImages"
                            value="{{ $image->id }}"
                            class="bg-white bg-opacity-80 rounded shadow"
                    />
                </div>

                {{-- Image --}}
                <div class="aspect-square bg-gray-100 dark:bg-gray-700">
                    <img
                            src="{{ $image->url }}"
                            alt="{{ $image->alt_text ?? $image->display_title }}"
                            class="w-full h-full object-cover cursor-pointer"
                            wire:click="editImage({{ $image->id }})"
                    />
                </div>

                {{-- Image Info --}}
                <div class="p-3">
                    <div class="space-y-1">
                        {{-- Title --}}
                        <h4 class="font-medium text-sm text-gray-900 dark:text-white truncate">
                            {{ $image->display_title }}
                        </h4>

                        {{-- Metadata --}}
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span>{{ $image->formatted_size }}</span>
                            <span>{{ $image->folder }}</span>
                        </div>

                        <div class="flex justify-between gap-2">
                            {{-- Attachment Status --}}
                            @if($image->isAttached())
                                <flux:badge variant="success" size="sm">
                                    <flux:icon.link class="w-3 h-3 mr-1"/>
                                    Linked
                                </flux:badge>
                            @else
                                <flux:badge variant="outline" size="sm">
                                    <flux:icon.photo class="w-3 h-3 mr-1"/>
                                    Unlinked
                                </flux:badge>
                            @endif

                            <flux:button
                                    type="button"
                                    size="xs"
                                    @click="navigator.clipboard.writeText('{{ $image->url }}'); $wire.copyUrl('{{ $image->url }}')"
                            >
                                Copy Link
                            </flux:button>
                        </div>

                        {{-- Tags --}}
                        @if(!empty($image->tags))
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach(array_slice($image->tags, 0, 2) as $tag)
                                    <flux:badge variant="outline" size="xs">{{ $tag }}</flux:badge>
                                @endforeach
                                @if(count($image->tags) > 2)
                                    <span class="text-xs text-gray-400">+{{ count($image->tags) - 2 }}</span>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Action Buttons (shown on hover) --}}
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-1">
                    <flux:button
                            variant="outline"
                            size="sm"
                            wire:click="editImage({{ $image->id }})"
                            class="bg-white bg-opacity-90 text-xs"
                    >
                        <flux:icon.pencil class="w-3 h-3"/>
                    </flux:button>

                    <flux:button
                            variant="danger"
                            size="sm"
                            wire:click="deleteImage({{ $image->id }})"
                            wire:confirm="Are you sure you want to delete this image?"
                            class="bg-white bg-opacity-90 text-xs"
                    >
                        <flux:icon.trash class="w-3 h-3"/>
                    </flux:button>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <div class="text-center py-12">
                    <flux:icon.photo class="w-16 h-16 text-gray-300 mx-auto mb-4"/>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No images found</h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        @if($search || $selectedFolder || !empty($selectedTags))
                            Try adjusting your filters or search terms.
                        @else
                            Get started by uploading your first images.
                        @endif
                    </p>
                    <flux:button variant="primary" icon="upload" wire:click="openUploadModal">
                        Upload Images
                    </flux:button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($this->images->hasPages())
        <div class="flex justify-center">
            {{ $this->images->links() }}
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

    {{-- Edit Modal --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-7xl mx-auto">
        @if($editingImage)
            <div class="p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Edit Image</h2>
                </div>

                {{-- Image Preview --}}
                <div class="mb-6">
                    <img
                            src="{{ $editingImage->url }}"
                            alt="{{ $editingImage->display_title }}"
                            class="w-full h-48 object-cover rounded-lg"
                    />
                </div>

                <form wire:submit="saveImageChanges" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <flux:input
                                    label="Title"
                                    wire:model="editMetadata.title"
                                    placeholder="Enter image title..."
                            />
                        </div>

                        <div>
                            <flux:input
                                    label="Alt Text"
                                    wire:model="editMetadata.alt_text"
                                    placeholder="Describe the image..."
                            />
                        </div>
                    </div>

                    <div>
                        <flux:textarea
                                label="Description"
                                wire:model="editMetadata.description"
                                placeholder="Enter image description..."
                                rows="3"
                        />
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <flux:input
                                    label="Folder"
                                    wire:model="editMetadata.folder"
                                    placeholder="uncategorized"
                            />
                        </div>

                        <div>
                            <flux:input
                                    label="Tags (comma-separated)"
                                    wire:model="editMetadata.tags"
                                    placeholder="product, hero, banner..."
                            />
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex justify-end pt-4">
                        <flux:button type="submit" variant="primary">
                            <flux:icon.check class="w-4 h-4 mr-2"/>
                            Save Changes
                        </flux:button>
                    </div>
                </form>
            </div>
        @endif
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