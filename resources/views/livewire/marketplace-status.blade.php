{{-- 
🏷️ MARKETPLACE STATUS INDICATOR
Clean, modern status display for any marketplace channel
--}}
<div wire:poll.5s="refreshStatus" class="space-y-1">
    {{-- Primary Status Line --}}
    <div class="flex items-center gap-2">
        {{-- Status Dot --}}
        <div class="relative flex items-center">
            <div class="{{ $this->getDotClasses() }}"></div>
            @if($this->status['animated'])
                <div class="{{ $this->getDotClasses() }} absolute animate-ping"></div>
            @endif
        </div>
        
        {{-- Status Text --}}
        @if($showLabel)
            <span class="text-sm font-medium {{ $this->getTextColor() }}">
                {{ $this->getStatusText() }}
            </span>
        @endif
    </div>
    
    {{-- Secondary Status Details --}}
    @if($this->shouldShowSyncDetails())
        <div class="ml-4 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
            @if($this->getLastSyncTime())
                <div>Last sync: {{ $this->getLastSyncTime() }}</div>
            @endif
            @if($this->getSyncUser())
                <div>By: {{ $this->getSyncUser() }}</div>
            @endif
        </div>
    @endif
</div>
