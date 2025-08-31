<div class="space-y-6">
    {{-- Activity Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-2">
                <flux:icon name="funnel" class="h-5 w-5 text-gray-500" />
                <h3 class="font-medium text-gray-900 dark:text-white">Activity Filters</h3>
                <flux:badge size="sm" color="gray">{{ count($activities) }} activities</flux:badge>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-3">
                {{-- Activity Type Filter --}}
                <flux:select wire:model.live="selectedType" class="min-w-[140px]">
                    <flux:select.option value="all">All Activities</flux:select.option>
                    @foreach($activityTypes as $type)
                        <flux:select.option value="{{ $type }}">
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                
                {{-- Date Range Filter --}}
                <flux:select wire:model.live="dateRange" class="min-w-[120px]">
                    <flux:select.option value="all">All Time</flux:select.option>
                    <flux:select.option value="today">Today</flux:select.option>
                    <flux:select.option value="week">This Week</flux:select.option>
                    <flux:select.option value="month">This Month</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- Activity Timeline --}}
    @if(count($activities) > 0)
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            {{-- Header --}}
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <flux:icon name="clock" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    <h3 class="font-medium text-gray-900 dark:text-white">Activity Timeline</h3>
                </div>
            </div>

            {{-- Timeline Items --}}
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($activities as $activity)
                    @php
                        $eventType = str_replace('image.', '', $activity['event']);
                        $activityData = $activity['data'] ?? [];
                        $subject = $activityData['subject'] ?? [];
                        $occurredAt = \Carbon\Carbon::parse($activity['occurred_at']);
                    @endphp
                    
                    <div class="p-6 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="flex items-start gap-4">
                            {{-- Activity Icon --}}
                            <div class="flex-shrink-0">
                                <div class="flex items-center justify-center w-10 h-10 rounded-full 
                                           bg-{{ $this->getActivityColor($eventType) }}-100 
                                           dark:bg-{{ $this->getActivityColor($eventType) }}-900/20">
                                    <flux:icon 
                                        name="{{ $this->getActivityIcon($eventType) }}" 
                                        class="h-5 w-5 text-{{ $this->getActivityColor($eventType) }}-600 
                                               dark:text-{{ $this->getActivityColor($eventType) }}-400"
                                    />
                                </div>
                            </div>
                            
                            {{-- Activity Content --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-medium text-gray-900 dark:text-white">
                                            {{ ucfirst(str_replace('_', ' ', $eventType)) }}
                                        </h4>
                                        <flux:badge 
                                            size="xs" 
                                            :color="$this->getActivityColor($eventType)"
                                        >
                                            {{ $eventType }}
                                        </flux:badge>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                        @if(isset($activity['user_name']) && $activity['user_name'])
                                            <span>by {{ $activity['user_name'] }}</span>
                                        @endif
                                        <time datetime="{{ $occurredAt->toISOString() }}" 
                                              title="{{ $occurredAt->format('F j, Y \a\t g:i A') }}">
                                            {{ $occurredAt->diffForHumans() }}
                                        </time>
                                    </div>
                                </div>
                                
                                {{-- Activity Description --}}
                                @if(isset($activityData['description']) && $activityData['description'])
                                    <p class="text-gray-700 dark:text-gray-300 mb-3">
                                        {{ $activityData['description'] }}
                                    </p>
                                @endif
                                
                                {{-- Activity Details --}}
                                <div class="space-y-2 text-sm">
                                    {{-- Subject Information --}}
                                    @if($subject)
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 dark:text-gray-400">Target:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $subject['title'] ?? $subject['name'] ?? ("Image #" . ($subject['id'] ?? 'unknown')) }}
                                            </span>
                                            @if(isset($subject['filename']))
                                                <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
                                                    {{ $subject['filename'] }}
                                                </code>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    {{-- Variant-specific details --}}
                                    @if($eventType === 'variants_generated')
                                        <div class="grid grid-cols-2 gap-4 mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400">Variants Created:</span>
                                                <span class="font-medium text-gray-900 dark:text-white ml-2">
                                                    {{ $activityData['variants_count'] ?? 0 }}
                                                </span>
                                            </div>
                                            @if(isset($activityData['variant_types']))
                                                <div>
                                                    <span class="text-gray-500 dark:text-gray-400">Types:</span>
                                                    <div class="flex gap-1 mt-1">
                                                        @foreach($activityData['variant_types'] as $type)
                                                            <flux:badge size="xs" color="blue">{{ $type }}</flux:badge>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    
                                    {{-- Processing details --}}
                                    @if($eventType === 'processed' && isset($activityData['dimensions_changed']) && $activityData['dimensions_changed'])
                                        <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                            <div class="text-gray-500 dark:text-gray-400 mb-2">Dimension Changes:</div>
                                            <div class="grid grid-cols-2 gap-4 text-xs">
                                                @if(isset($activityData['original_dimensions']))
                                                    <div>
                                                        <span class="text-gray-500">Before:</span>
                                                        <span class="font-mono ml-1">
                                                            {{ $activityData['original_dimensions']['width'] ?? '?' }}×{{ $activityData['original_dimensions']['height'] ?? '?' }}
                                                        </span>
                                                    </div>
                                                @endif
                                                @if(isset($activityData['new_dimensions']))
                                                    <div>
                                                        <span class="text-gray-500">After:</span>
                                                        <span class="font-mono ml-1">
                                                            {{ $activityData['new_dimensions']['width'] ?? '?' }}×{{ $activityData['new_dimensions']['height'] ?? '?' }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    
                                    {{-- Attachment details --}}
                                    @if(in_array($eventType, ['attached', 'detached']) && isset($activityData['attached_to_name']))
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-500 dark:text-gray-400">
                                                {{ $eventType === 'attached' ? 'Attached to:' : 'Detached from:' }}
                                            </span>
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $activityData['attached_to_name'] ?? $activityData['detached_from_name'] }}
                                            </span>
                                            <flux:badge size="xs" color="gray">
                                                {{ $activityData['attached_to_type'] ?? $activityData['detached_from_type'] }}
                                            </flux:badge>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Empty State --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8 border border-gray-200 dark:border-gray-700">
            <div class="text-center">
                <flux:icon name="clock" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Activity Found</h3>
                <p class="text-gray-600 dark:text-gray-400">
                    @if($selectedType !== 'all' || $dateRange !== 'all')
                        No activities match your current filters.
                    @else
                        This image doesn't have any recorded activity yet.
                    @endif
                </p>
                @if($selectedType !== 'all' || $dateRange !== 'all')
                    <flux:button 
                        wire:click="$set('selectedType', 'all'); $set('dateRange', 'all')" 
                        variant="ghost" 
                        size="sm"
                        class="mt-3"
                    >
                        Clear Filters
                    </flux:button>
                @endif
            </div>
        </div>
    @endif
</div>