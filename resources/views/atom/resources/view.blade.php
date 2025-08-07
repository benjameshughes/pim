<div>
    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        
        {{-- Breadcrumbs --}}
        <x-navigation.breadcrumbs :breadcrumbs="$breadcrumbs" />
        
        {{-- Sub Navigation (if available) --}}
        @if(count($subNavigationItems) > 0)
            <x-navigation.sub-navigation :items="collect($subNavigationItems)" />
        @endif
    </div>

    {{-- Record View Content --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if($record)
                @foreach($record->getAttributes() as $key => $value)
                    @if(!in_array($key, ['created_at', 'updated_at']))
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ ucfirst(str_replace('_', ' ', $key)) }}
                            </label>
                            <div class="text-sm text-gray-900 dark:text-gray-100 p-3 bg-gray-50 dark:bg-gray-700/50 rounded">
                                {{ $value ?? 'N/A' }}
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>
        
        {{-- Record Metadata --}}
        @if($record)
            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-600">
                <div class="grid grid-cols-2 gap-4 text-sm text-gray-500 dark:text-gray-400">
                    <div>Created: {{ $record->created_at?->format('M j, Y g:i A') }}</div>
                    <div>Updated: {{ $record->updated_at?->format('M j, Y g:i A') }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- Actions --}}
    @if(!empty($actions))
        <div class="mt-6 flex gap-3">
            @foreach($actions as $action)
                @if($action['variant'] === 'primary')
                    <flux:button href="{{ $action['url'] }}" variant="primary">
                        {{ $action['label'] }}
                    </flux:button>
                @else
                    <flux:button href="{{ $action['url'] }}" variant="ghost">
                        {{ $action['label'] }}
                    </flux:button>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Atom Notifications --}}
    <x-notifications :notifications="$this->getNotifications()" />
</div>