@props([
    'options' => [10, 25, 50, 100],  // Available per-page options
    'current' => 15,                  // Current per-page value
    'label' => 'Show',               // Label text
    'suffix' => 'per page',          // Suffix text
    'size' => 'normal',              // sm, normal, lg
    'align' => 'left',               // left, center, right
    'showLabel' => true,             // Show label text
])

@php
$containerClasses = collect([
    'flex items-center gap-2',
    match($align) {
        'center' => 'justify-center',
        'right' => 'justify-end',
        default => 'justify-start'
    }
])->filter()->implode(' ');

$selectSize = match($size) {
    'sm' => 'sm',
    'lg' => 'lg',
    default => 'md'
};

$textSize = match($size) {
    'sm' => 'text-xs',
    'lg' => 'text-base',
    default => 'text-sm'
};
@endphp

<div class="{{ $containerClasses }}">
    
    {{-- ✨ LABEL WITH SPARKLE ✨ --}}
    @if($showLabel && $label)
        <label class="{{ $textSize }} font-medium text-gray-700 dark:text-gray-300 
                       select-none cursor-pointer transition-colors duration-200
                       hover:text-gray-900 dark:hover:text-white">
            {{ $label }}
        </label>
    @endif
    
    {{-- ✨ PER PAGE SELECT DROPDOWN ✨ --}}
    <div class="relative group" 
         x-data="{ 
             currentPerPage: @js($current),
             options: @js($options),
             
             init() {
                 // Restore from localStorage if available
                 const stored = localStorage.getItem('table_per_page');
                 if (stored && this.options.includes(parseInt(stored))) {
                     this.currentPerPage = parseInt(stored);
                 }
                 
                 // Watch for changes
                 this.$watch('currentPerPage', value => {
                     // Dispatch Livewire event if available
                     if (window.Livewire) {
                         Livewire.dispatch('per-page-updated', { perPage: value });
                     }
                     
                     // Dispatch custom event
                     this.$dispatch('per-page-changed', { perPage: value });
                     
                     // Store in localStorage
                     localStorage.setItem('table_per_page', value);
                 });
             }
         }">
        <flux:select 
            x-model="currentPerPage"
            :size="$selectSize"
            class="min-w-20 transition-all duration-200 
                   group-hover:shadow-md focus:shadow-lg
                   group-focus-within:ring-2 group-focus-within:ring-blue-500/20"
        >
            @foreach($options as $option)
                <flux:select.option 
                    value="{{ $option }}" 
                    :selected="$option === $current"
                >
                    {{ $option }}
                </flux:select.option>
            @endforeach
        </flux:select>
        
        {{-- Glow effect on focus --}}
        <div x-show="false" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="absolute inset-0 -m-1 bg-gradient-to-r from-blue-400/10 via-purple-400/10 to-pink-400/10 
                    rounded-lg blur-sm pointer-events-none group-focus-within:block">
        </div>
    </div>
    
    {{-- ✨ SUFFIX WITH ANIMATION ✨ --}}
    @if($suffix)
        <span class="{{ $textSize }} text-gray-600 dark:text-gray-400 
                     select-none transition-all duration-200
                     group-hover:text-gray-800 dark:group-hover:text-gray-200">
            {{ $suffix }}
        </span>
    @endif
    
    {{-- ✨ CURRENT SELECTION INDICATOR ✨ --}}
    <div x-show="false" class="flex items-center gap-1">
        <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
        <span class="{{ $textSize }} text-blue-600 dark:text-blue-400 font-medium">
            Current: <span x-text="currentPerPage"></span>
        </span>
    </div>
    
    {{-- ✨ LOADING STATE ✨ --}}
    <div wire:loading wire:target="updatedPerPage,gotoPage" 
         class="flex items-center gap-1">
        <div class="animate-spin rounded-full h-3 w-3 border border-gray-300 border-t-blue-500"></div>
        <span class="text-xs text-gray-500 dark:text-gray-400">Updating...</span>
    </div>
    
</div>

{{-- ✨ PURE ALPINE FUNCTIONALITY ✨ --}}
{{-- All functionality is now handled through x-data and x-init directives above! --}}