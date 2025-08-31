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
            <flux:label>Folder</flux:label>
            @if(count($this->folders) > 0)
                <flux:select wire:model="folder">
                    <flux:select.option value="uncategorized">Uncategorized</flux:select.option>
                    @foreach($this->folders as $folderOption)
                        <flux:select.option value="{{ $folderOption }}">{{ ucfirst($folderOption) }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:input wire:model="folder" placeholder="Enter folder name" />
            @endif
            <flux:error name="folder" />
            <flux:description>Organize images into folders for easier management</flux:description>
        </flux:field>
        
        <flux:field>
            <flux:label>Tags</flux:label>
            <flux:input 
                wire:model.live="tagsString" 
                placeholder="product, hero, banner, lifestyle (comma-separated)"
            />
            <flux:error name="tagsString" />
            <flux:description>Add comma-separated tags to help categorize and find this image</flux:description>
        </flux:field>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <flux:button 
                type="button"
                wire:click="resetForm" 
                variant="ghost"
                :disabled="$isSaving"
            >
                Reset Form
            </flux:button>
            
            <flux:button 
                type="button"
                wire:click="reprocessImage" 
                variant="outline"
                icon="sparkles"
                :disabled="$isSaving"
            >
                Reprocess & Generate Variants
            </flux:button>
        </div>
        
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