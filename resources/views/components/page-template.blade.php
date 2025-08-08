@props([
    'title' => null,
    'breadcrumbs' => [],
    'actions' => [],
    'loading' => false,
    'loadingText' => 'Loading...',
    'maxWidth' => '7xl',
    'withPadding' => true,
    'stats' => null,
])

<div class="max-w-{{ $maxWidth }} mx-auto @if($withPadding) space-y-6 @endif" 
     x-data="{ loading: @js($loading) }"
     x-bind:class="{ 'opacity-50 pointer-events-none': loading }">
     
    {{-- Page Header --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        <div class="p-6">
            {{-- Breadcrumbs --}}
            @if(!empty($breadcrumbs))
                <x-breadcrumb :items="$breadcrumbs" class="mb-4" />
            @endif
            
            {{-- Title and Actions Row --}}
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                {{-- Title Section --}}
                @if($title)
                    <div class="flex items-center gap-4">
                        @isset($icon)
                            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center">
                                {{ $icon }}
                            </div>
                        @endisset
                        <div>
                            <flux:heading size="xl" class="text-zinc-900 dark:text-zinc-100 font-semibold">
                                {{ $title }}
                            </flux:heading>
                            @isset($subtitle)
                                <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                                    {{ $subtitle }}
                                </flux:subheading>
                            @endisset
                        </div>
                    </div>
                @endif
                
                {{-- Action Buttons --}}
                @if(!empty($actions))
                    <div class="flex items-center gap-3 flex-wrap">
                        @foreach($actions as $action)
                            @php
                                $actionType = $action['type'] ?? 'button';
                                $variant = $action['variant'] ?? 'outline';
                                $size = $action['size'] ?? 'base';
                                $icon = $action['icon'] ?? null;
                                $label = $action['label'] ?? 'Action';
                                $isVisible = isset($action['visible']) ? $action['visible'] : true;
                            @endphp
                            
                            @if($isVisible)
                                @if($actionType === 'link')
                                    <flux:button 
                                        href="{{ $action['href'] }}" 
                                        variant="{{ $variant }}" 
                                        size="{{ $size }}"
                                        @if($icon) icon="{{ $icon }}" @endif
                                        @if($action['wire:navigate'] ?? false) wire:navigate @endif
                                        @if($action['target'] ?? false) target="{{ $action['target'] }}" @endif
                                    >
                                        {{ $label }}
                                    </flux:button>
                                @else
                                    <flux:button 
                                        @if(isset($action['wire:click'])) wire:click="{{ $action['wire:click'] }}" @endif
                                        @if(isset($action['x-on:click'])) x-on:click="{{ $action['x-on:click'] }}" @endif
                                        variant="{{ $variant }}" 
                                        size="{{ $size }}"
                                        @if($icon) icon="{{ $icon }}" @endif
                                        @if(isset($action['loading'])) wire:loading.attr="disabled" @endif
                                    >
                                        <span @if(isset($action['loading'])) wire:loading.remove wire:target="{{ $action['loading'] }}" @endif>
                                            {{ $label }}
                                        </span>
                                        @if(isset($action['loading']))
                                            <div wire:loading wire:target="{{ $action['loading'] }}" class="flex items-center">
                                                <div class="w-4 h-4 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin mr-2"></div>
                                                {{ $action['loadingText'] ?? 'Processing...' }}
                                            </div>
                                        @endif
                                    </flux:button>
                                @endif
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
            
            {{-- Description --}}
            @isset($description)
                <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-4">
                    {{ $description }}
                </p>
            @endisset
        </div>
    </div>
    
    {{-- Stats Section (if provided) --}}
    @if($stats)
        <div>
            {{ $stats }}
        </div>
    @endif
    
    {{-- Header Slot (for additional content like filters, tabs) --}}
    @isset($header)
        <div>
            {{ $header }}
        </div>
    @endisset
    
    {{-- Main Content --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm">
        @isset($contentHeader)
            <div class="px-6 py-4 border-b border-zinc-200 dark:border-zinc-700">
                {{ $contentHeader }}
            </div>
        @endisset
        
        <div @if($withPadding) class="p-6" @endif>
            {{ $slot }}
        </div>
        
        @isset($contentFooter)
            <div class="px-6 py-4 border-t border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 rounded-b-xl">
                {{ $contentFooter }}
            </div>
        @endisset
    </div>
    
    {{-- Footer Content --}}
    @isset($footer)
        <div>
            {{ $footer }}
        </div>
    @endisset
    
    {{-- Loading Overlay --}}
    <div x-show="loading" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
         style="display: none;">
        <div class="bg-white dark:bg-zinc-800 rounded-lg p-6 max-w-sm mx-4 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="w-6 h-6 border-2 border-zinc-200 border-t-indigo-600 rounded-full animate-spin"></div>
                <span class="text-sm font-medium">{{ $loadingText }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Alpine.js integration for dynamic loading states --}}
@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('pageTemplate', () => ({
        loading: false,
        
        showLoading(text = 'Loading...') {
            this.loading = true;
            this.loadingText = text;
        },
        
        hideLoading() {
            this.loading = false;
        }
    }));
});

// Livewire integration
document.addEventListener('livewire:init', () => {
    Livewire.on('page-loading-start', (data) => {
        Alpine.nextTick(() => {
            const template = document.querySelector('[x-data*="loading"]');
            if (template) {
                template._x_dataStack[0].loading = true;
            }
        });
    });
    
    Livewire.on('page-loading-end', () => {
        Alpine.nextTick(() => {
            const template = document.querySelector('[x-data*="loading"]');
            if (template) {
                template._x_dataStack[0].loading = false;
            }
        });
    });
});
</script>
@endpush