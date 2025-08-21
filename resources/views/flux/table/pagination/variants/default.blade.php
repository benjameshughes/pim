@props([
    'paginator' => null,         // Laravel paginator instance
    'showInfo' => true,          // Show "Showing X to Y of Z results"
    'showPages' => true,         // Show page numbers
    'showPrevNext' => true,      // Show prev/next buttons
    'showFirstLast' => false,    // Show first/last buttons  
    'maxPages' => 7,            // Max page numbers to show
    'size' => 'normal',          // sm, normal, lg
    'align' => 'center',         // left, center, right
    'responsive' => true,        // Hide some elements on mobile
])

@php
// Handle both Laravel paginator and manual pagination data
if (is_object($paginator)) {
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $total = $paginator->total();
    $from = $paginator->firstItem();
    $to = $paginator->lastItem();
    $perPage = $paginator->perPage();
    $hasPages = $paginator->hasPages();
    $hasMorePages = $paginator->hasMorePages();
    $onFirstPage = $paginator->onFirstPage();
} else {
    $currentPage = $paginator['current_page'] ?? 1;
    $lastPage = $paginator['last_page'] ?? 1;
    $total = $paginator['total'] ?? 0;
    $from = $paginator['from'] ?? 0;
    $to = $paginator['to'] ?? 0;
    $perPage = $paginator['per_page'] ?? 10;
    $hasPages = $lastPage > 1;
    $hasMorePages = $currentPage < $lastPage;
    $onFirstPage = $currentPage <= 1;
}

$containerClasses = collect([
    'flex items-center',
    match($align) {
        'left' => 'justify-start',
        'right' => 'justify-end', 
        default => 'justify-center'
    },
    $responsive ? 'flex-col sm:flex-row gap-4' : 'gap-6'
])->filter()->implode(' ');

$buttonSize = match($size) {
    'sm' => 'w-8 h-8 text-sm',
    'lg' => 'w-12 h-12 text-lg',
    default => 'w-10 h-10'
};

// Calculate page range
$start = max(1, $currentPage - floor($maxPages / 2));
$end = min($lastPage, $start + $maxPages - 1);
$start = max(1, $end - $maxPages + 1);
@endphp

@if($hasPages)
<div class="{{ $containerClasses }}">
    
    {{-- ✨ RESULTS INFO ✨ --}}
    @if($showInfo && $total > 0)
        <div class="text-sm text-gray-700 dark:text-gray-300 
                    {{ $responsive ? 'order-2 sm:order-1' : '' }}">
            <span>Showing</span>
            <span class="font-medium">{{ number_format($from) }}</span>
            <span>to</span>
            <span class="font-medium">{{ number_format($to) }}</span>
            <span>of</span>
            <span class="font-medium">{{ number_format($total) }}</span>
            <span>results</span>
        </div>
    @endif
    
    {{-- ✨ PAGINATION CONTROLS ✨ --}}
    <div class="flex items-center {{ $responsive ? 'order-1 sm:order-2' : '' }}">
        
        {{-- First Page Button --}}
        @if($showFirstLast && !$onFirstPage)
            <button 
                @if(is_object($paginator))
                    wire:click="gotoPage(1)"
                @else
                    @click="$dispatch('paginate', { page: 1 })"
                @endif
                class="{{ $buttonSize }} flex items-center justify-center rounded-lg border border-gray-300 
                       bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 
                       dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 
                       dark:hover:bg-gray-700 dark:hover:text-gray-300 
                       transition-all duration-150 shadow-sm hover:shadow group"
                title="First page"
            >
                <flux:icon name="chevron-double-left" class="w-4 h-4 group-hover:scale-110 transition-transform" />
            </button>
        @endif
        
        {{-- Previous Page Button --}}
        @if($showPrevNext)
            <button 
                @if(is_object($paginator))
                    @if(!$onFirstPage) wire:click="previousPage" @endif
                @else
                    @if(!$onFirstPage) @click="$dispatch('paginate', { page: {{ $currentPage - 1 }} })" @endif
                @endif
                @if($onFirstPage) disabled @endif
                class="{{ $buttonSize }} flex items-center justify-center rounded-lg border border-gray-300 
                       {{ $onFirstPage 
                           ? 'bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-gray-800 dark:text-gray-600' 
                           : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300' 
                       }}
                       transition-all duration-150 shadow-sm hover:shadow group 
                       {{ $onFirstPage ? '' : 'ml-3' }}"
                title="Previous page"
            >
                <flux:icon name="chevron-left" class="w-4 h-4 {{ $onFirstPage ? '' : 'group-hover:scale-110 transition-transform' }}" />
            </button>
        @endif
        
        {{-- Page Numbers --}}
        @if($showPages)
            <div class="flex mx-2">
                @for($page = $start; $page <= $end; $page++)
                    <button 
                        @if(is_object($paginator))
                            @if($page !== $currentPage) wire:click="gotoPage({{ $page }})" @endif
                        @else
                            @if($page !== $currentPage) @click="$dispatch('paginate', { page: {{ $page }} })" @endif
                        @endif
                        class="{{ $buttonSize }} flex items-center justify-center rounded-lg border mx-1
                               {{ $page === $currentPage 
                                   ? 'border-blue-500 bg-blue-500 text-white shadow-lg shadow-blue-500/25' 
                                   : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300' 
                               }}
                               transition-all duration-150 hover:shadow group
                               {{ $page === $currentPage ? 'transform hover:scale-105' : '' }}"
                        title="Page {{ $page }}"
                        @if($page === $currentPage) disabled @endif
                    >
                        <span class="font-medium {{ $page === $currentPage ? 'animate-pulse' : 'group-hover:scale-110 transition-transform' }}">
                            {{ $page }}
                        </span>
                    </button>
                @endfor
                
                {{-- Ellipsis if there are more pages --}}
                @if($end < $lastPage)
                    <span class="{{ $buttonSize }} flex items-center justify-center text-gray-400 dark:text-gray-600">
                        ...
                    </span>
                @endif
            </div>
        @endif
        
        {{-- Next Page Button --}}
        @if($showPrevNext)
            <button 
                @if(is_object($paginator))
                    @if($hasMorePages) wire:click="nextPage" @endif
                @else
                    @if($hasMorePages) @click="$dispatch('paginate', { page: {{ $currentPage + 1 }} })" @endif
                @endif
                @if(!$hasMorePages) disabled @endif
                class="{{ $buttonSize }} flex items-center justify-center rounded-lg border border-gray-300 
                       {{ !$hasMorePages 
                           ? 'bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-gray-800 dark:text-gray-600' 
                           : 'bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300' 
                       }}
                       transition-all duration-150 shadow-sm hover:shadow group
                       {{ !$hasMorePages ? '' : 'mr-3' }}"
                title="Next page"
            >
                <flux:icon name="chevron-right" class="w-4 h-4 {{ !$hasMorePages ? '' : 'group-hover:scale-110 transition-transform' }}" />
            </button>
        @endif
        
        {{-- Last Page Button --}}
        @if($showFirstLast && $hasMorePages)
            <button 
                @if(is_object($paginator))
                    wire:click="gotoPage({{ $lastPage }})"
                @else
                    @click="$dispatch('paginate', { page: {{ $lastPage }} })"
                @endif
                class="{{ $buttonSize }} flex items-center justify-center rounded-lg border border-gray-300 
                       bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 
                       dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400 
                       dark:hover:bg-gray-700 dark:hover:text-gray-300 
                       transition-all duration-150 shadow-sm hover:shadow group"
                title="Last page"
            >
                <flux:icon name="chevron-double-right" class="w-4 h-4 group-hover:scale-110 transition-transform" />
            </button>
        @endif
    </div>
    
    {{-- ✨ SPARKLE LOADING INDICATOR ✨ --}}
    <div wire:loading wire:target="gotoPage,nextPage,previousPage" 
         class="absolute inset-0 flex items-center justify-center bg-white/80 dark:bg-gray-800/80 rounded-lg">
        <div class="flex items-center gap-2 px-3 py-1 bg-blue-500 text-white rounded-full shadow-lg">
            <div class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
            <span class="text-sm font-medium">Loading...</span>
        </div>
    </div>
    
</div>
@endif