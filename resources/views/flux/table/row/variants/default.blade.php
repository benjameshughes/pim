@props([
    'actionRoute' => null,    // Base route for actions (e.g., 'variants' for variants.show, variants.edit)
])

<div class="overflow-x-auto">
    <table class="w-full">
        <tbody x-data="{
            actionRoute: @js($actionRoute),
            rowClasses: '',
            cellPadding: '',
            animationConfig: {}
        }" x-init="
            // Get styles from parent table config
            if (typeof $el.closest('[data-table-config]') !== 'undefined') {
                let parentData = Alpine.$data($el.closest('[data-table-config]'));
                rowClasses = parentData.rowClasses;
                cellPadding = parentData.cellPadding;
                animationConfig = parentData.animationConfig;
            }
        " class="divide-y divide-gray-200 dark:divide-gray-700">
            
            {{-- LOADING STATE --}}
            <template x-if="loading">
                <tr>
                    <td :colspan="columns.length + (selectable ? 1 : 0)" 
                        :class="cellPadding + ' text-center'">
                        <div class="flex items-center justify-center gap-3 py-8">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            <span class="text-gray-500 dark:text-gray-400">Loading...</span>
                        </div>
                    </td>
                </tr>
            </template>
            
            {{-- EMPTY STATE --}}
            <template x-if="!loading && filteredData.length === 0">
                <tr>
                    <td :colspan="columns.length + (selectable ? 1 : 0)" 
                        :class="cellPadding + ' text-center'">
                        <div class="flex flex-col items-center justify-center py-12 gap-4">
                            <flux:icon name="table-cells" class="w-12 h-12 text-gray-400" />
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">No data found</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-show="searchQuery">
                                    Try adjusting your search query
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </template>
            
            {{-- DATA ROWS --}}
            <template x-if="!loading && filteredData.length > 0">
                <template x-for="(row, index) in filteredData" :key="row.id || index">
                    <tr :class="rowClasses + (hoverable ? ' transition-colors duration-150' : '') + (striped ? ' odd:bg-gray-25 dark:odd:bg-gray-800/50' : '')"
                        x-transition:enter="animationConfig.enter || 'transition-opacity duration-300'"
                        x-transition:enter-start="animationConfig.start || 'opacity-0'"  
                        x-transition:enter-end="animationConfig.end || 'opacity-100'"
                        :style="`transition-delay: ${index * 50}ms`">
                        
                        {{-- Selection Checkbox --}}
                        <template x-if="selectable">
                            <td :class="cellPadding">
                                <input type="checkbox"
                                       :checked="isRowSelected(row.id)"
                                       x-on:click="toggleRowSelection(row.id)"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                        </template>
                        
                        {{-- Data Columns --}}
                        <template x-for="column in columns" :key="column.key">
                            <td :class="cellPadding + ' text-sm text-gray-900 dark:text-white'">
                                
                                {{-- Actions Column --}}
                                <template x-if="column.key === 'actions'">
                                    <div class="flex items-center justify-end gap-1">
                                        <template x-if="row.id && actionRoute">
                                            <div class="flex items-center gap-1">
                                                <a :href="`/${actionRoute}/${row.id}`" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-blue-600 hover:bg-blue-50 rounded-md transition-colors"
                                                   title="View">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                    </svg>
                                                </a>
                                                <a :href="`/${actionRoute}/${row.id}/edit`" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 rounded-md transition-colors"
                                                   title="Edit">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                    </svg>
                                                </a>
                                                <button @click="$dispatch('delete-item', row.id)" 
                                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-md transition-colors"
                                                        title="Delete">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </template>
                                        <template x-if="!row.id || !actionRoute">
                                            <span class="text-gray-400 text-xs">No actions</span>
                                        </template>
                                    </div>
                                </template>
                                
                                {{-- Regular Data Columns --}}
                                <template x-if="column.key !== 'actions'">
                                    <span x-html="column.render ? column.render(getNestedValue(row, column.key), row) : renderCellValue(getNestedValue(row, column.key), column)"></span>
                                </template>
                            </td>
                        </template>
                    </tr>
                </template>
            </template>
            
            {{-- Custom Row Content --}}
            {{ $slot }}
            
        </tbody>
    </table>
</div>