@props([
    'pagination' => null,
    'summary' => true,
    'bulkActions' => true,
])

<div x-data="{
    showSummary: @js($summary),
    showBulkActions: @js($bulkActions),
    pagination: @js($pagination)
}">
    {{-- ✨ BULK ACTIONS TOOLBAR ✨ --}}
    <template x-if="showBulkActions && selectable && showBulkActions">
        <div x-show="showBulkToolbar" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="absolute top-0 left-0 right-0 bg-gradient-to-r from-blue-600 to-purple-600 text-white px-4 py-3 flex items-center justify-between shadow-lg z-10">
            <div class="flex items-center gap-4">
                <span class="text-sm font-medium" x-text="`${selectedRows.length} ${selectedRows.length === 1 ? 'item' : 'items'} selected`"></span>
                
                {{-- Bulk Actions --}}
                <template x-if="bulkActions && bulkActions.length > 0">
                    <div class="flex gap-2">
                        <template x-for="bulkAction in bulkActions" :key="bulkAction.key">
                            <flux:button size="sm" 
                                       variant="outline"
                                       class="border-white/20 text-white hover:bg-white/10"
                                       :icon="bulkAction.icon"
                                       x-on:click="$dispatch('bulk-action', { action: bulkAction.key, selected: selectedRows })">
                                <span x-text="bulkAction.label"></span>
                            </flux:button>
                        </template>
                    </div>
                </template>
            </div>
            
            <button x-on:click="selectedRows = []; showBulkToolbar = false;" 
                    class="text-white/80 hover:text-white transition-colors">
                <flux:icon name="x-mark" class="w-5 h-5" />
            </button>
        </div>
    </template>
    
    {{-- Legacy Selection Bar (fallback) --}}
    <template x-if="selectable && !showBulkActions">
        <div x-show="selectedRows.length > 0" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="absolute top-0 left-0 right-0 bg-blue-600 text-white px-4 py-2 flex items-center justify-between">
            <span x-text="`${selectedRows.length} row${selectedRows.length > 1 ? 's' : ''} selected`"></span>
            <button x-on:click="selectedRows = []" class="text-blue-200 hover:text-white">
                <flux:icon name="x-mark" class="w-4 h-4" />
            </button>
        </div>
    </template>

    {{-- ✨ FOOTER CONTENT ✨ --}}
    <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
        
        {{-- Summary & Pagination Row --}}
        <div class="flex items-center justify-between px-6 py-4">
            
            {{-- Results Summary --}}
            <template x-if="showSummary">
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <span>Showing </span>
                    <span class="font-medium" x-text="filteredData.length"></span>
                    <span> of </span>
                    <span class="font-medium" x-text="data.length"></span>
                    <span> results</span>
                    <template x-if="searchQuery">
                        <span> for "<span class="font-medium" x-text="searchQuery"></span>"</span>
                    </template>
                </div>
            </template>
            
            {{-- Pagination --}}
            @if($pagination)
                <div class="flex items-center space-x-2">
                    {{ $pagination }}
                </div>
            @endif
            
            {{-- Custom Footer Actions --}}
            @if($slot->isNotEmpty())
                <div class="flex items-center gap-3">
                    {{ $slot }}
                </div>
            @endif
        </div>
        
        {{-- Additional Footer Content --}}
        @if($slot->isNotEmpty())
            <div class="px-6 pb-4">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>