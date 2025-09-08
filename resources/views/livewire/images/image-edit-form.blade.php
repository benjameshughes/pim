<form wire:submit="save" class="space-y-6">
    {{-- Basic Information --}}
    <div class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
            <flux:icon name="info" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            Basic Information
        </h3>
        
        <flux:field>
            <flux:label>Title</flux:label>
            <flux:input 
                wire:model.live="title" 
                placeholder="Enter a descriptive title for this image"
            />
            <flux:error name="title" />
            <flux:description>Optional display title for the image</flux:description>
        </flux:field>
        
        <flux:field>
            <flux:label>Alt Text</flux:label>
            <flux:input 
                wire:model.live="alt_text" 
                placeholder="Describe the image for screen readers"
            />
            <flux:error name="alt_text" />
            <flux:description>Important for accessibility and SEO</flux:description>
        </flux:field>
        
        <flux:field>
            <flux:label>Description</flux:label>
            <flux:textarea 
                wire:model.live="description" 
                rows="3"
                placeholder="Optional longer description of this image"
            />
            <flux:error name="description" />
            <flux:description>Detailed description for cataloging purposes</flux:description>
        </flux:field>
    </div>

    {{-- Organization --}}
    <div class="space-y-4 pt-6 border-t border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
            <flux:icon name="folder" class="h-5 w-5 text-green-600 dark:text-green-400" />
            Organization
        </h3>
        
        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label>Folder</flux:label>
                @if(!$creatingNewFolder)
                    <flux:button size="xs" variant="outline" icon="plus" wire:click="startCreateFolder">
                        New Folder
                    </flux:button>
                @endif
            </div>

            @if($creatingNewFolder)
                <div class="flex items-center gap-2 mt-2">
                    <flux:input 
                        wire:model.live="newFolderName" 
                        placeholder="e.g., product, lifestyle, hero"
                        class="flex-1"
                    />
                    <flux:button size="sm" variant="primary" wire:click="confirmCreateFolder" icon="check">
                        Add
                    </flux:button>
                    <flux:button size="sm" variant="ghost" wire:click="cancelCreateFolder">
                        Cancel
                    </flux:button>
                </div>
                <flux:error name="newFolderName" />
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Only letters, numbers, hyphens, and underscores allowed.</p>
            @else
                @if(count($this->folders) > 0)
                    <flux:select wire:model="folder" class="mt-2">
                        <flux:select.option value="uncategorized">Uncategorized</flux:select.option>
                        @foreach($this->folders as $folderOption)
                            <flux:select.option value="{{ $folderOption }}">{{ ucfirst($folderOption) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:input wire:model="folder" placeholder="Enter folder name" />
                @endif
            @endif
            <flux:error name="folder" />
            <flux:description>Organize images into folders for easier management</flux:description>
        </flux:field>
        
        <flux:field>
            <div class="flex items-center justify-between">
                <flux:label>Tags</flux:label>
                <div class="flex items-center gap-2">
                    <flux:button size="xs" variant="outline" icon="sparkles" wire:click="cleanTags">Clean</flux:button>
                </div>
            </div>
            <div 
                x-data="{
                    open: false,
                    q: @entangle('tagInput'),
                    available: @js($this->availableTags),
                    filtered() {
                        if (!this.q) return this.available;
                        const s = this.q.toLowerCase();
                        return this.available.filter(t => t.toLowerCase().includes(s));
                    }
                }" 
                class="w-full"
                @click.away="open = false"
            >
                <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-300 dark:border-gray-600 p-2 dark:bg-gray-900">
                    {{-- Chips --}}
                    <template x-for="tag in @entangle('tagTokens')" :key="tag">
                        <span class="inline-flex items-center gap-1 text-xs bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 px-2 py-1 rounded">
                            <span x-text="tag"></span>
                            <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-300" @click="$wire.removeTagToken(tag)">
                                <flux:icon name="x" class="w-3 h-3" />
                            </button>
                        </span>
                    </template>

                    {{-- Input --}}
                    <input 
                        type="text" 
                        x-model="q"
                        @focus="open = true"
                        @keydown.enter.prevent="$wire.addTagToken()"
                        @keydown.,.prevent="$wire.addTagToken()"
                        class="flex-1 bg-transparent focus:outline-none text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400"
                        placeholder="Type to add or pick..."
                    />
                </div>

                {{-- Suggestions --}}
                <div 
                    x-show="open && filtered().length > 0"
                    x-transition
                    class="mt-2 max-h-48 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow"
                >
                    <template x-for="tag in filtered()" :key="tag">
                        <button type="button" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                @click.prevent="$wire.addTagToken(tag); open = false;">
                            <span x-text="tag"></span>
                        </button>
                    </template>
                </div>
            </div>
            <flux:error name="tagInput" />
            <flux:description>Select existing tags or type to add new ones</flux:description>
        </flux:field>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3"></div>
        
        <div class="flex items-center gap-3">
            <flux:button 
                wire:navigate 
                href="{{ route('images.show', $image) }}" 
                variant="ghost"
                :disabled="$isSaving"
            >
                Cancel
            </flux:button>
            <flux:button 
                type="submit" 
                variant="primary"
                :loading="$isSaving"
            >
                Save Changes
            </flux:button>
        </div>
    </div>
</form>
