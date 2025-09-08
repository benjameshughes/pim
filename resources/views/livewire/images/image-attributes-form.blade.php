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

        @if(empty($groups))
            <p class="text-sm text-gray-600 dark:text-gray-400">No attribute definitions available.</p>
        @else
            <div class="grid grid-cols-1 gap-6">
                @foreach($groups as $groupName => $defs)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-md">
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 rounded-t-md flex items-center gap-2">
                            <flux:icon name="adjustments-horizontal" class="h-4 w-4 text-gray-500" />
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $groupName ?: 'General' }}</span>
                        </div>
                        <div class="p-4 space-y-4">
                            @foreach($defs as $def)
                                <div class="flex items-start gap-4">
                                    <div class="w-48 shrink-0">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $def['name'] }}</label>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 font-mono">{{ $def['key'] }}</div>
                                    </div>
                                    <div class="flex-1">
                                        @php $key = $def['key']; @endphp
                                        <input 
                                            type="text" 
                                            wire:model.lazy="values.{{ $key }}" 
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                            placeholder="Enter {{ strtolower($def['name']) }}" />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

