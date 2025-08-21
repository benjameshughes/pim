<div>
    {{-- Image Selector Modal --}}
    <flux:modal wire:model="show" class="w-full max-w-7xl mx-auto">
        @if($show && $targetModel)
            <div class="p-6">
                <div class="mb-6">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                        🔗 Link Images to {{ ucfirst($targetType) }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Select {{ $allowMultiple ? "up to {$maxSelection} images" : 'one image' }} from your DAM library
                    </p>
                </div>

                {{-- Selection Status --}}
                @if($this->selectionInfo['hasSelection'])
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <flux:icon.check-circle class="w-5 h-5 text-blue-600 mr-2" />
                                <span class="text-sm text-blue-800 dark:text-blue-200">
                                    {{ $this->selectionInfo['count'] }} of {{ $this->selectionInfo['max'] }} images selected
                                </span>
                            </div>
                            <div class="flex space-x-2">
                                <flux:button 
                                    variant="outline" 
                                    size="sm"
                                    wire:click="$set('selectedImageIds', [])"
                                >
                                    Clear Selection
                                </flux:button>
                                <flux:button 
                                    variant="primary" 
                                    size="sm"
                                    wire:click="confirmSelection"
                                >
                                    <flux:icon.link class="w-4 h-4 mr-1" />
                                    Link Images
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Search and Filters --}}
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {{-- Search --}}
                        <div>
                            <flux:input
                                wire:model.live.debounce.300ms="search"
                                placeholder="Search images..."
                                size="sm"
                            >
                                <x-slot name="iconTrailing">
                                    <flux:icon.magnifying-glass class="w-4 h-4 text-gray-400" />
                                </x-slot>
                            </flux:input>
                        </div>

                        {{-- Folder Filter --}}
                        <div>
                            <flux:select wire:model.live="selectedFolder" placeholder="All folders" size="sm">
                                <flux:select.option value="">All folders</flux:select.option>
                                @foreach($this->folders as $folder)
                                    <flux:select.option value="{{ $folder }}">{{ ucfirst($folder) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        {{-- Status Filter --}}
                        <div>
                            <flux:select wire:model.live="filterBy" size="sm">
                                <flux:select.option value="unattached">Unlinked images</flux:select.option>
                                <flux:select.option value="all">All images</flux:select.option>
                                <flux:select.option value="attached">Linked images</flux:select.option>
                                <flux:select.option value="mine">My uploads</flux:select.option>
                            </flux:select>
                        </div>
                    </div>

                    {{-- Tag Filter --}}
                    @if(!empty($this->availableTags))
                        <div class="mt-4">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Filter by tags:
                            </label>
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($this->availableTags, 0, 10) as $tag)
                                    <flux:badge
                                        variant="{{ in_array($tag, $selectedTags) ? 'primary' : 'outline' }}"
                                        size="xs"
                                        class="cursor-pointer"
                                        wire:click="
                                            @if(in_array($tag, $selectedTags))
                                                $set('selectedTags', {{ json_encode(array_values(array_filter($selectedTags, fn($t) => $t !== $tag))) }})
                                            @else
                                                $set('selectedTags', {{ json_encode(array_merge($selectedTags, [$tag])) }})
                                            @endif
                                        "
                                    >
                                        {{ $tag }}
                                    </flux:badge>
                                @endforeach
                                @if(count($this->availableTags) > 10)
                                    <span class="text-xs text-gray-400">+{{ count($this->availableTags) - 10 }} more</span>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Images Grid --}}
                <div class="mb-6" style="max-height: 60vh; overflow-y: auto;">
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3">
                        @forelse($this->images as $image)
                            <div 
                                class="group relative bg-white dark:bg-gray-800 rounded-lg shadow hover:shadow-md transition-all duration-200 overflow-hidden cursor-pointer {{ in_array($image->id, $selectedImageIds) ? 'ring-2 ring-blue-500' : '' }}"
                                wire:key="selector-image-{{ $image->id }}"
                                wire:click="toggleImageSelection({{ $image->id }})"
                            >
                                {{-- Selection Overlay --}}
                                @if(in_array($image->id, $selectedImageIds))
                                    <div class="absolute inset-0 bg-blue-500 bg-opacity-20 z-10 flex items-center justify-center">
                                        <div class="bg-blue-500 rounded-full p-1">
                                            <flux:icon.check class="w-4 h-4 text-white" />
                                        </div>
                                    </div>
                                @endif

                                {{-- Selection Number --}}
                                @if(in_array($image->id, $selectedImageIds))
                                    <div class="absolute top-1 left-1 z-20 bg-blue-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                        {{ array_search($image->id, $selectedImageIds) + 1 }}
                                    </div>
                                @endif

                                {{-- Cannot Select Overlay --}}
                                @if(!$this->selectionInfo['canSelectMore'] && !in_array($image->id, $selectedImageIds))
                                    <div class="absolute inset-0 bg-gray-500 bg-opacity-50 z-10 flex items-center justify-center">
                                        <span class="text-white text-xs font-medium">Max reached</span>
                                    </div>
                                @endif

                                {{-- Image --}}
                                <div class="aspect-square bg-gray-100 dark:bg-gray-700">
                                    <img 
                                        src="{{ $image->url }}" 
                                        alt="{{ $image->alt_text ?? $image->display_title }}"
                                        class="w-full h-full object-cover"
                                    />
                                </div>

                                {{-- Image Info --}}
                                <div class="p-2">
                                    <h4 class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                        {{ $image->display_title }}
                                    </h4>
                                    <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                                        <span>{{ $image->formatted_size }}</span>
                                        @if(!empty($image->tags))
                                            <flux:badge variant="outline" size="xs">
                                                {{ count($image->tags) }} tag{{ count($image->tags) !== 1 ? 's' : '' }}
                                            </flux:badge>
                                        @endif
                                    </div>

                                    {{-- Attachment Status --}}
                                    @if($image->isAttached())
                                        <flux:badge variant="success" size="xs" class="mt-1">
                                            <flux:icon.link class="w-2 h-2 mr-1" />
                                            Linked
                                        </flux:badge>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="col-span-full">
                                <div class="text-center py-8">
                                    <flux:icon.photo class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">No images found</h3>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-3">
                                        @if($search || $selectedFolder || !empty($selectedTags))
                                            Try adjusting your filters or search terms.
                                        @else
                                            No images available to link.
                                        @endif
                                    </p>
                                    <flux:button variant="outline" size="sm" wire:click="closeSelector">
                                        <flux:icon.plus class="w-3 h-3 mr-1" />
                                        Upload Images Instead
                                    </flux:button>
                                </div>
                            </div>
                        @endforelse
                    </div>

                    {{-- Pagination --}}
                    @if($this->images->hasPages())
                        <div class="flex justify-center mt-4">
                            {{ $this->images->links() }}
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex justify-between items-center pt-4 border-t">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Showing {{ $this->images->count() }} of {{ $this->images->total() }} images
                    </div>

                    <div class="flex justify-end">
                        @if($this->selectionInfo['hasSelection'])
                            <flux:button variant="primary" wire:click="confirmSelection">
                                <flux:icon.link class="w-4 h-4 mr-2" />
                                Link {{ $this->selectionInfo['count'] }} Image{{ $this->selectionInfo['count'] !== 1 ? 's' : '' }}
                            </flux:button>
                        @else
                            <flux:button variant="primary" disabled>
                                <flux:icon.link class="w-4 h-4 mr-2" />
                                Select Images to Link
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>
</div>