@props([
    'stats' => [],
    'columns' => null,
    'gap' => 'gap-6',
    'responsive' => true,
    'loading' => false,
])

@php
    $gridClasses = match($columns) {
        1 => 'grid grid-cols-1',
        2 => 'grid grid-cols-1 sm:grid-cols-2',
        3 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        4 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        5 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
        6 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6',
        default => $responsive 
            ? (count($stats) <= 2 ? 'grid grid-cols-1 sm:grid-cols-2' : 
               (count($stats) <= 4 ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 
                'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'))
            : 'grid grid-cols-1'
    };
@endphp

<div class="{{ $gridClasses }} {{ $gap }}" x-data="statsGrid({ loading: @js($loading), stats: @js($stats) })">
    {{-- Generated Stats Cards --}}
    @if(!empty($stats))
        @foreach($stats as $index => $stat)
            <x-stats-card
                :title="$stat['title'] ?? null"
                :value="$stat['value'] ?? null"
                :icon="$stat['icon'] ?? null"
                :iconColor="$stat['iconColor'] ?? 'indigo'"
                :trend="$stat['trend'] ?? null"
                :trendDirection="$stat['trendDirection'] ?? null"
                :trendText="$stat['trendText'] ?? null"
                :suffix="$stat['suffix'] ?? null"
                :prefix="$stat['prefix'] ?? null"
                :href="$stat['href'] ?? null"
                :loading="$stat['loading'] ?? $loading"
                :size="$stat['size'] ?? 'default'"
                :variant="$stat['variant'] ?? 'default'"
                :color="$stat['color'] ?? 'zinc'"
                :data-stat="$stat['key'] ?? $index"
            >
                @if(isset($stat['content']))
                    {!! $stat['content'] !!}
                @endif
            </x-stats-card>
        @endforeach
    @endif
    
    {{-- Slotted Content --}}
    {{ $slot }}
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('statsGrid', (config) => ({
        loading: config.loading || false,
        stats: config.stats || [],
        refreshInterval: null,
        
        init() {
            // Listen for bulk stats updates
            Livewire.on('stats-grid-updated', (newStats) => {
                this.updateStats(newStats);
            });
            
            // Auto-refresh stats if configured
            if (this.$el.dataset.autoRefresh) {
                const interval = parseInt(this.$el.dataset.autoRefresh) || 30000; // Default 30 seconds
                this.startAutoRefresh(interval);
            }
        },
        
        updateStats(newStats) {
            this.stats = newStats;
            // Trigger individual card updates
            this.$dispatch('stats-updated', newStats);
        },
        
        startAutoRefresh(interval) {
            this.refreshInterval = setInterval(() => {
                if (!this.loading) {
                    Livewire.emit('refreshStats');
                }
            }, interval);
        },
        
        stopAutoRefresh() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
        },
        
        refreshStats() {
            this.loading = true;
            Livewire.emit('refreshStats').then(() => {
                this.loading = false;
            });
        }
    }));
});
</script>
@endpush