@props([
    'placeholder' => 'Search table...',
    'icon' => 'magnifying-glass',
    'clearable' => true,
    'size' => 'normal',           // sm, normal, lg
    'width' => 'auto',            // auto, sm, md, lg, full
    'debounce' => '300ms',        // Debounce delay for performance
    'shortcuts' => false,         // Show keyboard shortcuts
])

@php
$inputClasses = collect([
    'block border border-gray-300 dark:border-gray-600 rounded-lg',
    'bg-white dark:bg-gray-800 text-gray-900 dark:text-white',
    'focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'placeholder:text-gray-400 dark:placeholder:text-gray-500',
    'transition-all duration-200',
    match($size) {
        'sm' => 'pl-8 pr-4 py-1.5 text-sm',
        'lg' => 'pl-12 pr-6 py-3 text-lg', 
        default => 'pl-10 pr-4 py-2 text-sm sm:text-base'
    },
    match($width) {
        'sm' => 'w-48',
        'md' => 'w-64',
        'lg' => 'w-96',
        'full' => 'w-full',
        default => 'w-auto min-w-64'
    }
])->filter()->implode(' ');

$iconSize = match($size) {
    'sm' => 'w-3 h-3',
    'lg' => 'w-6 h-6',
    default => 'w-4 h-4'
};

$iconPosition = match($size) {
    'sm' => 'left-2.5 top-1/2',
    'lg' => 'left-4 top-1/2',
    default => 'left-3 top-1/2'
};
@endphp

<div class="relative group">
    
    {{-- ✨ SEARCH ICON ✨ --}}
    <div class="absolute {{ $iconPosition }} transform -translate-y-1/2 pointer-events-none">
        <flux:icon 
            :name="$icon" 
            class="{{ $iconSize }} text-gray-400 group-focus-within:text-blue-500 transition-colors" 
        />
    </div>
    
    {{-- ✨ SEARCH INPUT WITH SPARKLE EFFECTS ✨ --}}
    <input 
        type="text"
        x-model="searchQuery"
        @if($debounce) x-model.debounce.{{ $debounce }}="searchQuery" @endif
        placeholder="{{ $placeholder }}"
        class="{{ $inputClasses }} group-hover:shadow-md focus:shadow-lg"
        autocomplete="off"
        spellcheck="false"
        x-data="{
            focused: false,
            hasValue: false
        }"
        x-init="
            $watch('searchQuery', value => {
                hasValue = value && value.length > 0;
            });
        "
        @focus="focused = true"
        @blur="focused = false"
        {{ $attributes }}
    >
    
    {{-- ✨ CLEAR BUTTON ✨ --}}
    @if($clearable)
        <button 
            x-show="hasValue"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-90"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-100" 
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-90"
            @click="searchQuery = ''"
            class="absolute right-3 top-1/2 transform -translate-y-1/2 
                   text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 
                   transition-colors duration-150 focus:outline-none focus:text-gray-600"
            type="button"
            tabindex="-1"
        >
            <flux:icon name="x-mark" class="w-4 h-4" />
        </button>
    @endif
    
    {{-- ✨ SEARCH SHORTCUTS HINT ✨ --}}
    @if($shortcuts)
        <div x-show="!focused && !hasValue"
             class="absolute right-3 top-1/2 transform -translate-y-1/2 
                    text-xs text-gray-400 pointer-events-none select-none">
            <kbd class="px-1.5 py-0.5 text-xs font-mono bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">
                ⌘K
            </kbd>
        </div>
    @endif
    
    {{-- ✨ SEARCH RESULTS COUNTER ✨ --}}
    <div x-show="hasValue && typeof filteredData !== 'undefined'"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute -bottom-6 left-0 text-xs text-gray-500 dark:text-gray-400">
        <span x-text="filteredData.length"></span>
        <span x-text="'result' + (filteredData && filteredData.length !== 1 ? 's' : '') + ' found'"></span>
    </div>
    
    {{-- ✨ LOADING INDICATOR ✨ --}}
    <div x-show="loading" 
         class="absolute right-3 top-1/2 transform -translate-y-1/2">
        <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-300 border-t-blue-600"></div>
    </div>
    
    {{-- ✨ GLAMOUR GLOW EFFECT ✨ --}}
    <div x-show="focused" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100" 
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute inset-0 -m-1 bg-gradient-to-r from-blue-400/20 via-purple-400/20 to-pink-400/20 rounded-xl blur-sm pointer-events-none">
    </div>
    
</div>