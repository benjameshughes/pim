<div class="bg-white dark:bg-gray-800 rounded-lg p-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold">Edit Image: {{ $image->filename }}</h1>
    </div>
    
    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
        <flux:button 
            @click="confirmAction({
                title: 'Delete Image',
                message: 'This will permanently delete the image and cannot be undone.\\n\\nAny product attachments will also be removed.',
                confirmText: 'Yes, Delete It',
                cancelText: 'Cancel',
                variant: 'danger',
                onConfirm: () => $wire.deleteImage()
            })"
            variant="danger"
        >Delete Image</flux:button>
        
        <div class="flex items-center gap-3">
            <flux:button wire:click="cancel" variant="ghost">Cancel</flux:button>
        </div>
    </div>
</div>