@props(['product'])

@if($product->syncLogs->count() > 0)
    <div class="border-t pt-6">
        <h4 class="text-md font-medium text-gray-900 mb-4">Recent Sync Activity</h4>
        <div class="space-y-2">
            @foreach($product->syncLogs->take(5) as $log)
                <div class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <flux:badge 
                            :color="match($log->status) {
                                'success' => 'green',
                                'failed' => 'red',
                                'warning' => 'yellow',
                                default => 'gray'
                            }"
                            size="sm">
                            {{ $log->status }}
                        </flux:badge>
                        <div>
                            <p class="text-sm font-medium text-gray-900">
                                {{ ucfirst($log->action) }} • {{ ucfirst($log->syncAccount->channel) }}
                            </p>
                            @if($log->message)
                                <p class="text-xs text-gray-500">{{ $log->message }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</p>
                        @if($log->duration)
                            <p class="text-xs text-gray-400">{{ $log->duration }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif