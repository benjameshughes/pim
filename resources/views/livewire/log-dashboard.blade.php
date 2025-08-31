<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">📊 Log Dashboard</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Application logs, activity tracking, and performance metrics</p>
        </div>
    </div>

    {{-- 📑 TAB NAVIGATION --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach($this->logDashboardTabs->toArray() as $tab)
                <a href="{{ $tab['url'] }}"
                   class="py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap flex items-center space-x-2 transition-colors
                          {{ $tab['active'] 
                             ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                             : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}"
                   @if($tab['wireNavigate']) wire:navigate @endif>
                    
                    <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                    <span>{{ $tab['label'] }}</span>
                    
                    @if(isset($tab['badge']) && $tab['badge'])
                        <flux:badge 
                            :color="$tab['badgeColor'] ?? 'gray'" 
                            size="sm"
                        >
                            {{ $tab['badge'] }}
                        </flux:badge>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>
</div>