@php
    $config = $toastManager->toArray();
@endphp

{{-- Toast Container - FilamentPHP Style --}}
@if($config['hasToasts'])
    <div 
        x-data="toastContainer(@js($config))"
        class="fixed inset-0 pointer-events-none z-50"
    >
        {{-- Render toasts by position --}}
        @foreach($config['toastsByPosition'] as $positionGroup)
            @php
                $position = $positionGroup['position'];
                $positionToasts = $positionGroup['toasts'];
                
                // Map positions to CSS classes
                $positionClasses = [
                    'top-left' => 'top-4 left-4',
                    'top-center' => 'top-4 left-1/2 transform -translate-x-1/2',
                    'top-right' => 'top-4 right-4',
                    'bottom-left' => 'bottom-4 left-4',
                    'bottom-center' => 'bottom-4 left-1/2 transform -translate-x-1/2',
                    'bottom-right' => 'bottom-4 right-4',
                ];
                
                $positionClass = $positionClasses[$position] ?? $positionClasses['top-right'];
            @endphp
            
            @if(!empty($positionToasts))
                <div class="absolute {{ $positionClass }} space-y-2 pointer-events-auto max-w-sm w-full">
                    @foreach($positionToasts as $toast)
                        <div
                            x-data="toastItem(@js($toast))"
                            x-show="visible"
                            x-transition:enter="transform ease-out duration-300 transition"
                            x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                            x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            x-init="initializeToast()"
                            @mouseenter="pauseTimer()"
                            @mouseleave="resumeTimer()"
                            class="flex items-start p-4 rounded-lg shadow-lg border backdrop-blur-sm
                                   @switch($toast['type'])
                                       @case('success')
                                           bg-green-50 border-green-200 dark:bg-green-900/30 dark:border-green-700
                                           @break
                                       @case('error')
                                           bg-red-50 border-red-200 dark:bg-red-900/30 dark:border-red-700
                                           @break
                                       @case('warning')
                                           bg-yellow-50 border-yellow-200 dark:bg-yellow-900/30 dark:border-yellow-700
                                           @break
                                       @default
                                           bg-blue-50 border-blue-200 dark:bg-blue-900/30 dark:border-blue-700
                                   @endswitch"
                        >
                            {{-- Toast Icon --}}
                            @if($toast['icon'])
                                <div class="flex-shrink-0 mr-3">
                                    <div class="w-5 h-5
                                               @switch($toast['type'])
                                                   @case('success')
                                                       text-green-500 dark:text-green-400
                                                       @break
                                                   @case('error')
                                                       text-red-500 dark:text-red-400
                                                       @break
                                                   @case('warning')
                                                       text-yellow-500 dark:text-yellow-400
                                                       @break
                                                   @default
                                                       text-blue-500 dark:text-blue-400
                                               @endswitch">
                                        @switch($toast['icon'])
                                            @case('check-circle')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                @break
                                            @case('x-circle')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                @break
                                            @case('exclamation-triangle')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                </svg>
                                                @break
                                            @case('information-circle')
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                @break
                                            @default
                                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                        @endswitch
                                    </div>
                                </div>
                            @endif

                            {{-- Toast Content --}}
                            <div class="flex-1 min-w-0">
                                {{-- Toast Title --}}
                                @if($toast['title'])
                                    <h3 class="text-sm font-medium
                                              @switch($toast['type'])
                                                  @case('success')
                                                      text-green-800 dark:text-green-200
                                                      @break
                                                  @case('error')
                                                      text-red-800 dark:text-red-200
                                                      @break
                                                  @case('warning')
                                                      text-yellow-800 dark:text-yellow-200
                                                      @break
                                                  @default
                                                      text-blue-800 dark:text-blue-200
                                              @endswitch">
                                        {{ $toast['title'] }}
                                    </h3>
                                @endif

                                {{-- Toast Body --}}
                                @if($toast['body'])
                                    <div class="mt-1 text-sm
                                               @switch($toast['type'])
                                                   @case('success')
                                                       text-green-700 dark:text-green-300
                                                       @break
                                                   @case('error')
                                                       text-red-700 dark:text-red-300
                                                       @break
                                                   @case('warning')
                                                       text-yellow-700 dark:text-yellow-300
                                                       @break
                                                   @default
                                                       text-blue-700 dark:text-blue-300
                                               @endswitch">
                                        {{ $toast['body'] }}
                                    </div>
                                @endif

                                {{-- Toast Actions --}}
                                @if(!empty($toast['actions']))
                                    <div class="mt-3 flex space-x-2">
                                        @foreach($toast['actions'] as $action)
                                            @if($action['url'])
                                                <a
                                                    href="{{ $action['url'] }}"
                                                    @if($action['open_in_new_tab']) target="_blank" @endif
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                                                           @if($action['variant'] === 'filled')
                                                               @switch($action['color'])
                                                                   @case('primary')
                                                                       bg-blue-600 text-white hover:bg-blue-700
                                                                       @break
                                                                   @case('success')
                                                                       bg-green-600 text-white hover:bg-green-700
                                                                       @break
                                                                   @case('danger')
                                                                       bg-red-600 text-white hover:bg-red-700
                                                                       @break
                                                                   @default
                                                                       bg-gray-600 text-white hover:bg-gray-700
                                                               @endswitch
                                                           @else
                                                               @switch($action['color'])
                                                                   @case('primary')
                                                                       border border-blue-300 text-blue-700 hover:bg-blue-50
                                                                       @break
                                                                   @case('success')
                                                                       border border-green-300 text-green-700 hover:bg-green-50
                                                                       @break
                                                                   @case('danger')
                                                                       border border-red-300 text-red-700 hover:bg-red-50
                                                                       @break
                                                                   @default
                                                                       border border-gray-300 text-gray-700 hover:bg-gray-50
                                                               @endswitch
                                                           @endif"
                                                >
                                                    @if($action['icon'])
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    @endif
                                                    {{ $action['label'] }}
                                                </a>
                                            @else
                                                <button
                                                    wire:click="executeToastAction('{{ $toast['id'] }}', '{{ $action['key'] ?? $loop->index }}')"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition-colors
                                                           @if($action['variant'] === 'filled')
                                                               @switch($action['color'])
                                                                   @case('primary')
                                                                       bg-blue-600 text-white hover:bg-blue-700
                                                                       @break
                                                                   @case('success')
                                                                       bg-green-600 text-white hover:bg-green-700
                                                                       @break
                                                                   @case('danger')
                                                                       bg-red-600 text-white hover:bg-red-700
                                                                       @break
                                                                   @default
                                                                       bg-gray-600 text-white hover:bg-gray-700
                                                               @endswitch
                                                           @else
                                                               @switch($action['color'])
                                                                   @case('primary')
                                                                       border border-blue-300 text-blue-700 hover:bg-blue-50
                                                                       @break
                                                                   @case('success')
                                                                       border border-green-300 text-green-700 hover:bg-green-50
                                                                       @break
                                                                   @case('danger')
                                                                       border border-red-300 text-red-700 hover:bg-red-50
                                                                       @break
                                                                   @default
                                                                       border border-gray-300 text-gray-700 hover:bg-gray-50
                                                               @endswitch
                                                           @endif"
                                                >
                                                    @if($action['icon'])
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                    @endif
                                                    {{ $action['label'] }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Close Button --}}
                            @if($toast['closable'])
                                <div class="flex-shrink-0 ml-3">
                                    <button
                                        @click="closeToast()"
                                        class="inline-flex rounded-md p-1.5 transition-colors focus:outline-none
                                               @switch($toast['type'])
                                                   @case('success')
                                                       text-green-500 hover:bg-green-100 focus:bg-green-100 dark:text-green-400 dark:hover:bg-green-800 dark:focus:bg-green-800
                                                       @break
                                                   @case('error')
                                                       text-red-500 hover:bg-red-100 focus:bg-red-100 dark:text-red-400 dark:hover:bg-red-800 dark:focus:bg-red-800
                                                       @break
                                                   @case('warning')
                                                       text-yellow-500 hover:bg-yellow-100 focus:bg-yellow-100 dark:text-yellow-400 dark:hover:bg-yellow-800 dark:focus:bg-yellow-800
                                                       @break
                                                   @default
                                                       text-blue-500 hover:bg-blue-100 focus:bg-blue-100 dark:text-blue-400 dark:hover:bg-blue-800 dark:focus:bg-blue-800
                                               @endswitch"
                                    >
                                        <span class="sr-only">Dismiss</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            @endif

                            {{-- Progress Bar for Auto-dismiss (only if not persistent and has duration) --}}
                            @if(!$toast['persistent'] && $toast['duration'] > 0)
                                <div class="absolute bottom-0 left-0 right-0 h-1 bg-gray-200 dark:bg-gray-600 rounded-b-lg overflow-hidden">
                                    <div 
                                        x-ref="progressBar"
                                        class="h-full transition-all duration-75 ease-linear
                                               @switch($toast['type'])
                                                   @case('success')
                                                       bg-green-500
                                                       @break
                                                   @case('error')
                                                       bg-red-500
                                                       @break
                                                   @case('warning')
                                                       bg-yellow-500
                                                       @break
                                                   @default
                                                       bg-blue-500
                                               @endswitch"
                                        :style="`width: ${progressWidth}%`"
                                    ></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    </div>

    {{-- Alpine.js Toast Components --}}
    <script>
    document.addEventListener('alpine:init', () => {
        // Toast Container Component
        Alpine.data('toastContainer', (config) => ({
            init() {
                // Listen for Livewire navigation events to handle persistence
                window.addEventListener('livewire:navigate', () => {
                    // Handle navigation persistence is managed in session
                });
            }
        }));
        
        // Individual Toast Item Component  
        Alpine.data('toastItem', (toast) => ({
            visible: true,
            timer: null,
            progressTimer: null,
            progressWidth: 100,
            toast: toast,
            
            initializeToast() {
                // Auto-close timer for non-persistent toasts
                if (!this.toast.persistent && this.toast.duration > 0) {
                    this.startProgressTimer();
                }
            },
            
            startProgressTimer() {
                if (this.timer || this.progressTimer) return;
                
                const duration = this.toast.duration;
                let elapsed = 0;
                const updateInterval = 75; // Update every 75ms for smooth animation
                
                this.progressTimer = setInterval(() => {
                    elapsed += updateInterval;
                    this.progressWidth = Math.max(0, 100 - (elapsed / duration) * 100);
                }, updateInterval);
                
                this.timer = setTimeout(() => {
                    this.closeToast();
                }, duration);
            },
            
            pauseTimer() {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
                if (this.progressTimer) {
                    clearInterval(this.progressTimer);
                    this.progressTimer = null;
                }
            },
            
            resumeTimer() {
                if (!this.toast.persistent && this.toast.duration > 0 && this.progressWidth > 0) {
                    // Calculate remaining time based on progress
                    const remainingTime = (this.progressWidth / 100) * this.toast.duration;
                    
                    if (remainingTime > 0) {
                        const updateInterval = 75;
                        let elapsed = 0;
                        
                        this.progressTimer = setInterval(() => {
                            elapsed += updateInterval;
                            this.progressWidth = Math.max(0, this.progressWidth - (updateInterval / remainingTime) * this.progressWidth);
                        }, updateInterval);
                        
                        this.timer = setTimeout(() => {
                            this.closeToast();
                        }, remainingTime);
                    }
                }
            },
            
            closeToast() {
                this.visible = false;
                this.pauseTimer();
                
                // Remove from session via Livewire after animation
                setTimeout(() => {
                    if (window.Livewire) {
                        window.Livewire.dispatch('removeToast', [this.toast.id]);
                    }
                }, 150);
            }
        }));
    });
    </script>
@endif