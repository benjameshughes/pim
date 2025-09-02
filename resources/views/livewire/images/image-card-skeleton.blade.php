@if($showActualCard)
    <livewire:images.image-card :image="$image" />
@else
<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
    {{-- Skeleton Image Area --}}
    <div class="aspect-square bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
        {{-- Animated Background --}}
        <div class="absolute inset-0 bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 dark:from-gray-700 dark:via-gray-600 dark:to-gray-700 animate-pulse"></div>
        
        {{-- Simple Processing Overlay --}}
        <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
            <div class="text-center text-white p-4">
                <flux:icon name="arrow-path" class="w-8 h-8 mx-auto animate-spin text-blue-400 mb-3" />
                <div class="text-sm font-medium">Processing...</div>
            </div>
        </div>
    </div>

    {{-- Skeleton Content Area --}}
    <div class="p-4 space-y-3">
        {{-- Title Skeleton --}}
        <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
        
        {{-- Metadata Skeleton --}}
        <div class="flex items-center justify-between">
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-16 animate-pulse"></div>
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 animate-pulse"></div>
        </div>
        
        {{-- Badges Skeleton --}}
        <div class="flex items-center justify-between">
            <div class="flex gap-2">
                <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-16 animate-pulse"></div>
                <div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-14 animate-pulse"></div>
            </div>
            
            {{-- Action Buttons Skeleton --}}
            <div class="flex gap-1">
                <div class="w-8 h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <div class="w-8 h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
                <div class="w-8 h-6 bg-gray-200 dark:bg-gray-700 rounded animate-pulse"></div>
            </div>
        </div>
    </div>

    {{-- Loading Wire Effect --}}
    <div wire:loading class="absolute inset-0 bg-white/20 dark:bg-black/20">
        <div class="absolute inset-0 flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        </div>
    </div>
</div>
@endif