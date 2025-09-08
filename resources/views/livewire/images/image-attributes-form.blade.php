<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white flex items-center gap-2">
                <flux:icon name="tag" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                Image Attributes
            </h3>
            <div class="flex items-center gap-3">
                <flux:button wire:click="save" variant="primary" icon="check-circle">Save</flux:button>
            </div>
        </div>

        <div class="space-y-4">
            @foreach($entries as $index => $row)
                <div class="flex items-start gap-3">
                    <div class="w-64">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Key</label>
                        <input type="text" wire:model.lazy="entries.{{ $index }}.key" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="e.g., alt_text" />
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Value</label>
                        <input type="text" wire:model.lazy="entries.{{ $index }}.value" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100" placeholder="Enter value" />
                    </div>
                    <div class="pt-6">
                        <flux:button wire:click="removeRow({{ $index }})" variant="ghost" icon="trash" />
                    </div>
                </div>
            @endforeach

            <div>
                <flux:button wire:click="addRow" variant="secondary" icon="plus">Add Attribute</flux:button>
            </div>
        </div>
    </div>
</div>
