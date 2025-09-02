<div class="space-y-4">
    {{-- Header Component --}}
    <livewire:images.image-library-header 
        :search="$search" 
        :selectedFolder="$selectedFolder" 
        :selectedTag="$selectedTag" 
        :filterBy="$filterBy" 
        :sortBy="$sortBy" 
        :sortDirection="$sortDirection" 
    />


    {{-- Images List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        {{-- List Header with Select All --}}
        @if(!$this->getImages()->isEmpty())
            <div class="flex items-center p-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50" 
                 x-data="{ 
                    selectAll: @entangle('selectAll'), 
                    selectedImages: @entangle('selectedImages')
                 }">
                <div class="flex items-center gap-3">
                    <input 
                        type="checkbox" 
                        x-model="selectAll"
                        x-on:change="
                            if (selectAll) {
                                // Get all visible image IDs
                                let checkboxes = document.querySelectorAll('[data-image-checkbox]');
                                selectedImages = Array.from(checkboxes).map(cb => parseInt(cb.dataset.imageId));
                                checkboxes.forEach(cb => cb.checked = true);
                            } else {
                                selectedImages = [];
                                document.querySelectorAll('[data-image-checkbox]').forEach(cb => cb.checked = false);
                            }
                        "
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700"
                    >
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Select All
                    </span>
                </div>
                @if(!empty($selectedImages))
                    <div class="ml-auto text-sm text-gray-500 dark:text-gray-400">
                        {{ count($selectedImages) }} of {{ $this->getImages()->count() }} selected
                    </div>
                @endif
            </div>
        @endif
        @forelse($this->getImages() as $image)
            <livewire:images.image-library-row 
                wire:key="image-row-{{ $image->id }}" 
                :image="$image" 
                :selectedImages="$selectedImages" 
            />
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
    @if($this->getImages()->hasPages())
        <div class="flex justify-center pt-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-4">
                {{ $this->getImages()->links() }}
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