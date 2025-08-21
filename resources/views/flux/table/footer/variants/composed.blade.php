{{-- ✨ COMPOSED FOOTER - Uses specialized sub-components ✨ --}}
@props([
    'pagination' => null,       // flux:table.pagination props or paginator object
    'perPage' => null,          // flux:table.per-page props  
    'actions' => null,          // flux:table.actions props
    'summary' => true,          // Show results summary
    'bulkActions' => true,      // Show bulk actions toolbar
    'layout' => 'split',        // split, stacked, inline
])

<div x-data="{
    hasPagination: @js(!empty($pagination)),
    hasPerPage: @js(!empty($perPage)),
    hasActions: @js(!empty($actions)),
    showSummary: @js($summary),
    showBulkActions: @js($bulkActions)
}">

    {{-- ✨ BULK ACTIONS TOOLBAR ✨ --}}
    <template x-if="showBulkActions && selectable && showBulkToolbar">
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
                                       x-bind:icon="bulkAction.icon"
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
    
    {{-- ✨ FOOTER CONTENT ✨ --}}
    <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
        
        @if($layout === 'split')
            {{-- Split Layout: Summary | Pagination | Actions --}}
            <div class="flex items-center justify-between px-6 py-4 flex-wrap gap-4">
                
                {{-- Left Side: Summary + Per Page --}}
                <div class="flex items-center gap-6">
                    {{-- Results Summary --}}
                    @if($summary)
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
                    @endif
                    
                    {{-- Per Page --}}
                    @if($perPage)
                        @if(is_array($perPage))
                            <flux:table.per-page 
                                :options="$perPage['options'] ?? [10, 25, 50, 100]"
                                :current="$perPage['current'] ?? 15"
                                :label="$perPage['label'] ?? 'Show'"
                                :suffix="$perPage['suffix'] ?? 'per page'"
                                :size="$perPage['size'] ?? 'sm'"
                            />
                        @else
                            {{ $perPage }}
                        @endif
                    @endif
                </div>
                
                {{-- Center: Pagination --}}
                @if($pagination)
                    <div class="flex-1 flex justify-center">
                        @if(is_array($pagination))
                            <flux:table.pagination 
                                :paginator="$pagination['paginator'] ?? null"
                                :show-info="$pagination['showInfo'] ?? false"
                                :show-pages="$pagination['showPages'] ?? true"
                                :show-prev-next="$pagination['showPrevNext'] ?? true"
                                :show-first-last="$pagination['showFirstLast'] ?? false"
                                :max-pages="$pagination['maxPages'] ?? 7"
                                :size="$pagination['size'] ?? 'normal'"
                                :align="$pagination['align'] ?? 'center'"
                            />
                        @else
                            {{ $pagination }}
                        @endif
                    </div>
                @endif
                
                {{-- Right Side: Actions --}}
                @if($actions)
                    <div class="flex items-center gap-3">
                        @if(is_array($actions))
                            <flux:table.actions 
                                :secondary="$actions['secondary'] ?? []"
                                :size="$actions['size'] ?? 'sm'"
                                :align="$actions['align'] ?? 'right'"
                            />
                        @else
                            {{ $actions }}
                        @endif
                    </div>
                @endif
                
            </div>
            
        @elseif($layout === 'stacked')
            {{-- Stacked Layout: Everything in separate rows --}}
            <div class="px-6 py-4 space-y-4">
                
                {{-- Summary Row --}}
                @if($summary)
                    <div class="text-sm text-gray-700 dark:text-gray-300 text-center">
                        <span>Showing </span>
                        <span class="font-medium" x-text="filteredData.length"></span>
                        <span> of </span>
                        <span class="font-medium" x-text="data.length"></span>
                        <span> results</span>
                    </div>
                @endif
                
                {{-- Controls Row --}}
                <div class="flex justify-between items-center">
                    {{-- Per Page --}}
                    @if($perPage)
                        <div>
                            @if(is_array($perPage))
                                <flux:table.per-page 
                                    :options="$perPage['options'] ?? [10, 25, 50]"
                                    :current="$perPage['current'] ?? 15"
                                    :size="$perPage['size'] ?? 'sm'"
                                />
                            @else
                                {{ $perPage }}
                            @endif
                        </div>
                    @else
                        <div></div>
                    @endif
                    
                    {{-- Actions --}}
                    @if($actions)
                        <div>
                            @if(is_array($actions))
                                <flux:table.actions 
                                    :secondary="$actions['secondary'] ?? []"
                                    :size="$actions['size'] ?? 'sm'"
                                />
                            @else
                                {{ $actions }}
                            @endif
                        </div>
                    @endif
                </div>
                
                {{-- Pagination Row --}}
                @if($pagination)
                    <div class="flex justify-center">
                        @if(is_array($pagination))
                            <flux:table.pagination 
                                :paginator="$pagination['paginator'] ?? null"
                                :show-pages="$pagination['showPages'] ?? true"
                                :show-prev-next="$pagination['showPrevNext'] ?? true"
                                :max-pages="$pagination['maxPages'] ?? 7"
                                :size="$pagination['size'] ?? 'normal'"
                            />
                        @else
                            {{ $pagination }}
                        @endif
                    </div>
                @endif
                
            </div>
            
        @else
            {{-- Custom Layout --}}
            <div class="px-6 py-4">
                {{ $slot }}
            </div>
        @endif
        
    </div>
    
</div>