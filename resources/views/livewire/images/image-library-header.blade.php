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
        {{-- Bulk Actions (show when items selected) --}}
        @if($showBulkActions)
            <div class="flex items-center gap-2 mr-4 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                <span class="text-sm font-medium text-blue-700 dark:text-blue-300">
                    {{ count($selectedImages) }} selected
                </span>
                <div class="flex gap-1">
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="bulkDelete"
                        wire:confirm="Are you sure you want to delete {{ count($selectedImages) }} images? This cannot be undone."
                        class="text-red-600 hover:text-red-700"
                    >
                        <flux:icon name="trash" class="w-3 h-3"/>
                    </flux:button>
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="bulkMove"
                        class="text-blue-600 hover:text-blue-700"
                    >
                        <flux:icon name="folder" class="w-3 h-3"/>
                    </flux:button>
                    <flux:button
                        size="xs"
                        variant="ghost"
                        wire:click="bulkTag"
                        class="text-green-600 hover:text-green-700"
                    >
                        <flux:icon name="tag" class="w-3 h-3"/>
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
</div>