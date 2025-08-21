{{-- 🎯✨ FLOATING ACTION BAR - COMPACT PILL DESIGN ✨🎯 --}}
<div 
    class="fixed bottom-12 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 ease-in-out {{ $visible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0 pointer-events-none' }}"
    x-data="{ visible: @entangle('visible') }"
    x-show="visible"
    x-transition:enter="transform transition ease-in-out duration-300"
    x-transition:enter-start="translate-y-8 opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transform transition ease-in-out duration-300"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-8 opacity-0"
>
    {{-- Main Floating Bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-full shadow-lg border border-gray-200 dark:border-gray-700 backdrop-blur-lg">
        {{-- Compact Action Pills --}}
        <div class="flex items-center space-x-1 px-4 py-3">
            {{-- Selection Count Badge --}}
            <div class="flex items-center space-x-2 mr-3">
                <div class="w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center">
                    <span class="text-xs font-bold text-white">{{ $this->selectedCount }}</span>
                </div>
                <span class="text-sm font-medium text-gray-900 dark:text-white whitespace-nowrap">
                    {{ $this->selectedCount === 1 ? '1 selected' : $this->selectedCount . ' selected' }}
                </span>
            </div>
            
            {{-- Action Buttons --}}
            @foreach($quickActions as $action => $config)
                <flux:button
                    variant="{{ $bulkAction['type'] === $action ? 'primary' : 'ghost' }}"
                    size="sm"
                    wire:click="setQuickAction('{{ $action }}')"
                    class="rounded-full px-3 py-1.5 {{ $config['color'] === 'red' ? 'hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20' : '' }}"
                    title="{{ $config['label'] }}"
                >
                    <flux:icon name="{{ $config['icon'] }}" class="w-4 h-4" />
                </flux:button>
            @endforeach

            {{-- Expand Button (only show if there are menu actions) --}}
            @if($this->hasMenuActions)
                <flux:button 
                    variant="ghost" 
                    size="sm" 
                    wire:click="toggleExpanded"
                    class="rounded-full px-2 py-1.5"
                    title="More options"
                >
                    <flux:icon name="{{ $expanded ? 'chevron-up' : 'ellipsis-horizontal' }}" class="w-4 h-4" />
                </flux:button>
            @endif

            {{-- Clear Button --}}
            <flux:button 
                variant="ghost" 
                size="sm" 
                wire:click="clearSelection"
                class="rounded-full px-2 py-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                title="Clear selection"
            >
                <flux:icon.x-mark class="w-4 h-4" />
            </flux:button>
        </div>

        {{-- Floating Menu (appears above the bar) --}}
        @if($expanded)
            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 min-w-80">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 backdrop-blur-lg p-4">
                    <div class="space-y-3">
                        {{-- Menu Actions (if no specific action selected) --}}
                        @if(empty($bulkAction['type']))
                            <div class="grid grid-cols-1 gap-1">
                                @foreach($menuActions as $action => $config)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="setQuickAction('{{ $action }}')"
                                        class="justify-start text-left px-3 py-2 rounded-lg {{ $config['color'] === 'red' ? 'hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-900/20' : '' }}"
                                        icon="{{ $config['icon'] }}"
                                    >
                                        {{ $config['label'] }}
                                    </flux:button>
                                @endforeach
                            </div>
                        @else
                            {{-- Action Title & Execute Button --}}
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $this->actionLabel }}
                                </h3>
                                
                                <flux:button 
                                    variant="primary" 
                                    size="sm"
                                    wire:click="executeAction"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="opacity-50"
                                    class="rounded-full"
                                >
                                    <flux:icon.bolt class="w-3 h-3 mr-1" />
                                    Apply
                                </flux:button>
                            </div>
                        @endif

                        {{-- Action Forms --}}
                        @if($bulkAction['type'] === 'move_folder')
                            <flux:input
                                wire:model="bulkAction.folder"
                                placeholder="Enter folder name..."
                                size="sm"
                            >
                                <x-slot name="iconLeading">
                                    <flux:icon.folder class="w-4 h-4 text-gray-400" />
                                </x-slot>
                            </flux:input>
                        @endif

                        @if($bulkAction['type'] === 'add_tags')
                            <flux:input
                                wire:model="bulkAction.tags_to_add"
                                placeholder="tag1, tag2, tag3..."
                                size="sm"
                            >
                                <x-slot name="iconLeading">
                                    <flux:icon.tag class="w-4 h-4 text-gray-400" />
                                </x-slot>
                            </flux:input>
                        @endif

                        @if($bulkAction['type'] === 'remove_tags')
                            <flux:input
                                wire:model="bulkAction.tags_to_remove"
                                placeholder="tag1, tag2, tag3..."
                                size="sm"
                            >
                                <x-slot name="iconLeading">
                                    <flux:icon.minus class="w-4 h-4 text-gray-400" />
                                </x-slot>
                            </flux:input>
                        @endif

                        @if($bulkAction['type'] === 'delete')
                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/50 rounded-lg p-3 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <flux:icon.exclamation-triangle class="w-4 h-4 text-red-600" />
                                    <p class="text-sm text-red-800 dark:text-red-200">
                                        Delete {{ $this->selectedCount }} {{ $this->selectedCount === 1 ? 'image' : 'images' }}?
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Menu Arrow Pointer --}}
                    <div class="absolute top-full left-1/2 transform -translate-x-1/2">
                        <div class="w-3 h-3 bg-white dark:bg-gray-800 border-r border-b border-gray-200 dark:border-gray-700 transform rotate-45 -mt-1.5"></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Loading Overlay --}}
    <div 
        wire:loading 
        wire:target="executeAction"
        class="absolute inset-0 bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm rounded-full flex items-center justify-center"
    >
        <flux:icon.arrow-path class="w-5 h-5 animate-spin text-blue-600" />
    </div>
</div>