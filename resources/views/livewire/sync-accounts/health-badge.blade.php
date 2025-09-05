<div>
    <button type="button" wire:click="openHistory" class="inline-flex items-center group">
        <flux:badge size="sm" color="{{ $badge['color'] ?? 'gray' }}">
            <flux:icon name="{{ $badge['icon'] ?? 'question-mark-circle' }}" class="w-3.5 h-3.5 mr-1.5" />
            {{ ucfirst($badge['status'] ?? 'unknown') }}
        </flux:badge>
    </button>

    <flux:modal wire:model="showHistory" class="w-full max-w-lg mx-auto">
        <div class="p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Connection Health — {{ $account?->display_name }}</h3>
                <flux:badge size="sm" color="{{ $badge['color'] ?? 'gray' }}">
                    {{ ucfirst($badge['status'] ?? 'unknown') }}
                </flux:badge>
            </div>

            <p class="text-sm text-gray-600 mb-4">
                {{ $badge['message'] ?? 'No recent connection test recorded.' }}
                @if(!empty($badge['tested_at']))
                    <span class="text-gray-500">(tested {{ \Carbon\Carbon::parse($badge['tested_at'])->diffForHumans() }})</span>
                @endif
            </p>

            <div class="mb-4">
                <flux:button wire:click="testConnection" variant="filled">
                    <flux:icon name="activity" class="w-4 h-4 mr-2" />
                    Test Connection
                </flux:button>
            </div>

            @if(empty($history))
                <div class="text-center py-6 text-gray-500">No history available</div>
            @else
                <div class="space-y-3 max-h-80 overflow-y-auto pr-1">
                    @foreach(array_reverse($history) as $entry)
                        <div class="flex items-start justify-between border rounded-md p-3">
                            <div class="flex items-center">
                                <div class="w-2 h-2 rounded-full mr-2"
                                     style="background-color: {{ ($entry['status'] ?? 'unknown') === 'healthy' ? '#10B981' : (($entry['status'] ?? 'unknown') === 'failing' ? '#EF4444' : '#9CA3AF') }}"></div>
                                <div>
                                    <div class="text-sm font-medium">{{ ucfirst($entry['status'] ?? 'unknown') }}</div>
                                    <div class="text-xs text-gray-600">{{ $entry['message'] ?? '' }}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-600">{{ isset($entry['response_time_ms']) ? $entry['response_time_ms'].'ms' : 'n/a' }}</div>
                                <div class="text-xs text-gray-500">{{ isset($entry['tested_at']) ? \Carbon\Carbon::parse($entry['tested_at'])->diffForHumans() : '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </flux:modal>
</div>
