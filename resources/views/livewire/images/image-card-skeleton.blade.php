<div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
    {{-- Skeleton Image Area --}}
    <div class="aspect-square bg-gray-100 dark:bg-gray-700 relative overflow-hidden">
        {{-- Animated Background --}}
        <div class="absolute inset-0 bg-gradient-to-r from-gray-200 via-gray-300 to-gray-200 dark:from-gray-700 dark:via-gray-600 dark:to-gray-700 animate-pulse"></div>
        
        {{-- Processing Status Overlay --}}
        <div class="absolute inset-0 bg-black/60 flex items-center justify-center">
            <div class="text-center text-white p-4">
                {{-- Dynamic Icon Based on Status --}}
                <div class="mb-3">
                    @if($status === \App\Enums\ImageProcessingStatus::UPLOADING)
                        <flux:icon name="arrow-up-tray" class="w-8 h-8 mx-auto animate-bounce text-blue-400" />
                    @elseif($status === \App\Enums\ImageProcessingStatus::PROCESSING)
                        <flux:icon name="arrow-path" class="w-8 h-8 mx-auto animate-spin text-green-400" />
                    @elseif($status === \App\Enums\ImageProcessingStatus::OPTIMISING)
                        <flux:icon name="sparkles" class="w-8 h-8 mx-auto animate-pulse text-purple-400" />
                    @elseif($status === \App\Enums\ImageProcessingStatus::SUCCESS)
                        <flux:icon name="arrow-down-tray" class="w-8 h-8 mx-auto animate-bounce text-yellow-400" />
                    @elseif($status === \App\Enums\ImageProcessingStatus::FAILED)
                        <flux:icon name="x-circle" class="w-8 h-8 mx-auto text-red-400" />
                    @else
                        <flux:icon name="clock" class="w-8 h-8 mx-auto animate-pulse text-gray-400" />
                    @endif
                </div>
                
                {{-- Status Message --}}
                <div class="text-sm font-medium mb-2">{{ $statusMessage }}</div>
                
                {{-- Progress Bar --}}
                @if($progress > 0 && $progress < 100)
                    <div class="w-full bg-gray-700 rounded-full h-2 mb-2">
                        <div class="bg-blue-400 h-2 rounded-full transition-all duration-300" 
                             style="width: {{ $progress }}%"></div>
                    </div>
                    <div class="text-xs text-gray-300">{{ $progress }}%</div>
                @endif
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