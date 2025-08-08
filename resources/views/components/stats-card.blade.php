@props([
    'title' => null,
    'value' => null,
    'icon' => null,
    'iconColor' => 'indigo',
    'trend' => null,
    'trendDirection' => null, // 'up', 'down', 'neutral'
    'trendText' => null,
    'suffix' => null,
    'prefix' => null,
    'href' => null,
    'loading' => false,
    'size' => 'default', // 'sm', 'default', 'lg'
    'variant' => 'default', // 'default', 'colored', 'minimal'
    'color' => 'zinc',
])

@php
    $cardClasses = match($size) {
        'sm' => 'p-4',
        'lg' => 'p-8',
        default => 'p-6'
    };
    
    $titleClasses = match($size) {
        'sm' => 'text-sm',
        'lg' => 'text-base',
        default => 'text-sm'
    };
    
    $valueClasses = match($size) {
        'sm' => 'text-2xl',
        'lg' => 'text-4xl',
        default => 'text-3xl'
    };
    
    $iconSize = match($size) {
        'sm' => 'h-8 w-8',
        'lg' => 'h-16 w-16',
        default => 'h-12 w-12'
    };
    
    $iconClasses = match($iconColor) {
        'red' => 'text-red-600 bg-red-100 dark:bg-red-900/20',
        'green' => 'text-green-600 bg-green-100 dark:bg-green-900/20',
        'blue' => 'text-blue-600 bg-blue-100 dark:bg-blue-900/20',
        'yellow' => 'text-yellow-600 bg-yellow-100 dark:bg-yellow-900/20',
        'indigo' => 'text-indigo-600 bg-indigo-100 dark:bg-indigo-900/20',
        'purple' => 'text-purple-600 bg-purple-100 dark:bg-purple-900/20',
        'pink' => 'text-pink-600 bg-pink-100 dark:bg-pink-900/20',
        'zinc' => 'text-zinc-600 bg-zinc-100 dark:bg-zinc-900/20',
        default => 'text-indigo-600 bg-indigo-100 dark:bg-indigo-900/20'
    };
    
    $trendClasses = match($trendDirection) {
        'up' => 'text-green-600 dark:text-green-400',
        'down' => 'text-red-600 dark:text-red-400',
        'neutral' => 'text-zinc-500 dark:text-zinc-400',
        default => 'text-zinc-500 dark:text-zinc-400'
    };
    
    $trendIcon = match($trendDirection) {
        'up' => 'trending-up',
        'down' => 'trending-down',
        'neutral' => 'minus',
        default => 'minus'
    };
    
    $wrapperClasses = '';
    if ($variant === 'colored') {
        $wrapperClasses = match($color) {
            'red' => 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800',
            'green' => 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800',
            'blue' => 'bg-blue-50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800',
            'yellow' => 'bg-yellow-50 dark:bg-yellow-900/10 border-yellow-200 dark:border-yellow-800',
            'indigo' => 'bg-indigo-50 dark:bg-indigo-900/10 border-indigo-200 dark:border-indigo-800',
            'purple' => 'bg-purple-50 dark:bg-purple-900/10 border-purple-200 dark:border-purple-800',
            'pink' => 'bg-pink-50 dark:bg-pink-900/10 border-pink-200 dark:border-pink-800',
            default => 'bg-zinc-50 dark:bg-zinc-900/10 border-zinc-200 dark:border-zinc-800'
        };
    } elseif ($variant === 'minimal') {
        $wrapperClasses = 'bg-transparent border-transparent shadow-none';
    } else {
        $wrapperClasses = 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700';
    }
@endphp

<div class="rounded-xl border shadow-sm {{ $wrapperClasses }} {{ $cardClasses }} 
           @if($href) hover:shadow-md transition-shadow duration-200 cursor-pointer @endif"
     x-data="statsCard({ loading: @js($loading), value: @js($value) })"
     @if($href) 
         onclick="window.location.href='{{ $href }}'" 
         role="button" 
         tabindex="0"
         onkeydown="if(event.key==='Enter'||event.key===' '){window.location.href='{{ $href }}'}"
     @endif>
     
    {{-- Loading State --}}
    <div x-show="loading" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="animate-pulse"
         style="display: none;">
        <div class="flex items-center gap-4">
            <div class="{{ $iconSize }} bg-zinc-200 dark:bg-zinc-700 rounded-lg"></div>
            <div class="flex-1 space-y-2">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-3/4"></div>
                <div class="h-8 bg-zinc-200 dark:bg-zinc-700 rounded w-1/2"></div>
            </div>
        </div>
    </div>
    
    {{-- Content --}}
    <div x-show="!loading">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                {{-- Title --}}
                @if($title)
                    <p class="{{ $titleClasses }} font-medium text-zinc-600 dark:text-zinc-400 mb-2">
                        {{ $title }}
                    </p>
                @endif
                
                {{-- Value --}}
                <div class="flex items-baseline">
                    @if($prefix)
                        <span class="{{ $titleClasses }} text-zinc-500 dark:text-zinc-400 mr-1">{{ $prefix }}</span>
                    @endif
                    
                    <p class="{{ $valueClasses }} font-bold text-zinc-900 dark:text-zinc-100" 
                       x-text="formatValue(displayValue)"
                       x-transition:enter="transition ease-out duration-300"
                       x-transition:enter-start="opacity-0 transform scale-95"
                       x-transition:enter-end="opacity-100 transform scale-100">
                        {{ $loading ? '—' : ($value ?? '—') }}
                    </p>
                    
                    @if($suffix)
                        <span class="{{ $titleClasses }} text-zinc-500 dark:text-zinc-400 ml-1">{{ $suffix }}</span>
                    @endif
                </div>
                
                {{-- Trend --}}
                @if($trend || $trendText)
                    <div class="flex items-center mt-2 {{ $titleClasses }}">
                        @if($trendDirection)
                            <flux:icon 
                                name="{{ $trendIcon }}" 
                                class="h-4 w-4 mr-1 {{ $trendClasses }}"
                            />
                        @endif
                        
                        @if($trend)
                            <span class="{{ $trendClasses }} font-medium">
                                {{ $trend }}{{ $trend && is_numeric($trend) ? '%' : '' }}
                            </span>
                        @endif
                        
                        @if($trendText)
                            <span class="text-zinc-500 dark:text-zinc-400 {{ $trend ? 'ml-2' : '' }}">
                                {{ $trendText }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
            
            {{-- Icon --}}
            @if($icon)
                <div class="flex-shrink-0 ml-4">
                    <div class="{{ $iconSize }} rounded-lg {{ $iconClasses }} flex items-center justify-center">
                        @if(str_starts_with($icon, '<'))
                            {!! $icon !!}
                        @else
                            <flux:icon name="{{ $icon }}" class="h-6 w-6" />
                        @endif
                    </div>
                </div>
            @endif
        </div>
        
        {{-- Additional Content Slot --}}
        @if($slot->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $slot }}
            </div>
        @endif
    </div>
    
    {{-- Link Indicator --}}
    @if($href)
        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <flux:icon name="external-link" class="h-4 w-4 text-zinc-400" />
        </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('statsCard', (config) => ({
        loading: config.loading || false,
        value: config.value || 0,
        displayValue: config.value || 0,
        
        init() {
            // Animate value changes
            this.$watch('value', (newValue, oldValue) => {
                if (oldValue !== undefined && !this.loading) {
                    this.animateValue(oldValue, newValue);
                } else {
                    this.displayValue = newValue;
                }
            });
            
            // Listen for value updates from Livewire
            Livewire.on('stats-updated', (stats) => {
                if (stats[this.$el.dataset.stat]) {
                    this.value = stats[this.$el.dataset.stat];
                }
            });
        },
        
        formatValue(value) {
            if (value === null || value === undefined) return '—';
            if (typeof value === 'number') {
                if (value >= 1000000) {
                    return (value / 1000000).toFixed(1) + 'M';
                } else if (value >= 1000) {
                    return (value / 1000).toFixed(1) + 'K';
                } else {
                    return value.toLocaleString();
                }
            }
            return value;
        },
        
        animateValue(start, end, duration = 1000) {
            if (start === end) return;
            
            const startTime = performance.now();
            const startNum = parseFloat(start) || 0;
            const endNum = parseFloat(end) || 0;
            
            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function (ease out cubic)
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                
                this.displayValue = startNum + (endNum - startNum) * easeOutCubic;
                
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    this.displayValue = end;
                }
            };
            
            requestAnimationFrame(animate);
        }
    }));
});
</script>
@endpush