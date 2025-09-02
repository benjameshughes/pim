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
        {{-- Enhanced Bulk Action Bar (show when items selected) --}}
        @if($showBulkActions)
            <div class="flex items-center gap-3 mr-4 p-3 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-700 shadow-sm">
                <div class="flex items-center gap-2">
                    <flux:icon name="check-circle" class="w-4 h-4 text-blue-600 dark:text-blue-400"/>
                    <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">
                        {{ count($selectedImages) }} image{{ count($selectedImages) !== 1 ? 's' : '' }} selected
                    </span>
                </div>
                <div class="flex items-center gap-2 pl-2 border-l border-blue-200 dark:border-blue-600">
                    <flux:button
                        size="sm"
                        variant="outline"
                        wire:click="bulkDelete"
                        wire:confirm="Are you sure you want to delete {{ count($selectedImages) }} images? This cannot be undone."
                        class="text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300 dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/20"
                    >
                        <flux:icon name="trash" class="w-4 h-4 mr-1"/>
                        Delete
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="outline"
                        wire:click="openBulkMoveModal"
                        class="text-blue-600 border-blue-200 hover:bg-blue-50 hover:border-blue-300 dark:text-blue-400 dark:border-blue-700 dark:hover:bg-blue-900/20"
                    >
                        <flux:icon name="folder" class="w-4 h-4 mr-1"/>
                        Move
                    </flux:button>
                    <flux:button
                        size="sm"
                        variant="outline"
                        wire:click="openBulkTagModal"
                        class="text-green-600 border-green-200 hover:bg-green-50 hover:border-green-300 dark:text-green-400 dark:border-green-700 dark:hover:bg-green-900/20"
                    >
                        <flux:icon name="tag" class="w-4 h-4 mr-1"/>
                        Tag
                    </flux:button>
                </div>
            </div>
        @endif

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
                wire:click="toggleSortDirection"
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

    {{-- Bulk Move Modal --}}
    <flux:modal wire:model="showBulkMoveModal" class="w-full max-w-md mx-auto">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Move Images to Folder</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Move {{ count($selectedImages) }} selected image{{ count($selectedImages) !== 1 ? 's' : '' }} to a new folder.
                </p>
            </div>

            <form wire:submit="executeBulkMove" class="space-y-4">
                <div>
                    <flux:input
                        label="Target Folder"
                        wire:model="bulkMoveTargetFolder"
                        placeholder="Enter folder name..."
                        required
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Only letters, numbers, hyphens, and underscores allowed.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="$set('showBulkMoveModal', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="folder"
                    >
                        Move Images
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- Bulk Tag Modal --}}
    <flux:modal wire:model="showBulkTagModal" class="w-full max-w-md mx-auto">
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Manage Tags</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Update tags for {{ count($selectedImages) }} selected image{{ count($selectedImages) !== 1 ? 's' : '' }}.
                </p>
            </div>

            <form wire:submit="executeBulkTag" class="space-y-4">
                <div>
                    <flux:select
                        label="Operation"
                        wire:model="bulkTagOperation"
                    >
                        <flux:select.option value="add">Add tags</flux:select.option>
                        <flux:select.option value="replace">Replace all tags</flux:select.option>
                        <flux:select.option value="remove">Remove tags</flux:select.option>
                    </flux:select>
                </div>

                <div>
                    <flux:input
                        label="Tags"
                        wire:model="bulkTagInput"
                        placeholder="product, hero, banner..."
                        required
                    />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Separate multiple tags with commas.
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <flux:button
                        type="button"
                        variant="ghost"
                        wire:click="$set('showBulkTagModal', false)"
                    >
                        Cancel
                    </flux:button>
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="tag"
                    >
                        Update Tags
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>