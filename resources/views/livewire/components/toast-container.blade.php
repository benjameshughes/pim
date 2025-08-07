<div class="toast-container-wrapper">
    @foreach($this->toastsByPosition as $position => $toasts)
        @if($toasts->count() > 0)
            <div 
                x-data="toastGroup({{ json_encode($toasts->toArray()) }}, '{{ $position }}')" 
                class="{{ config("toasts.positions.{$position}.container") }} flex {{ config("toasts.positions.{$position}.alignment") }} gap-2"
                x-show="toasts.length > 0"
            >
                <template x-for="(toast, index) in toasts" :key="toast.id">
                    <div 
                        x-data="toastItem(toast)"
                        x-show="show"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="{{ config('toasts.animations.enter.from') }}"
                        x-transition:enter-end="{{ config('toasts.animations.enter.to') }}"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="{{ config('toasts.animations.exit.from') }}"
                        x-transition:leave-end="{{ config('toasts.animations.exit.to') }}"
                        @click.away="if (toast.closable) closeToast()"
                        class="toast-item relative max-w-sm w-full overflow-hidden rounded-lg shadow-lg"
                        x-bind:class="toast.type_config.background + ' ' + toast.type_config.border"
                    >
                        <div class="p-4">
                            <div class="flex items-start">
                                <!-- Icon -->
                                <div class="shrink-0">
                                    <!-- Success Icon -->
                                    <flux:icon name="circle-check" class="h-5 w-5" 
                                        x-show="toast.type === 'success'" 
                                        x-bind:class="toast.type_config ? toast.type_config.icon_color : 'text-green-500'" />
                                    
                                    <!-- Error Icon -->
                                    <flux:icon name="circle-x" class="h-5 w-5" 
                                        x-show="toast.type === 'error'" 
                                        x-bind:class="toast.type_config ? toast.type_config.icon_color : 'text-red-500'" />
                                    
                                    <!-- Warning Icon -->
                                    <flux:icon name="triangle-alert" class="h-5 w-5" 
                                        x-show="toast.type === 'warning'" 
                                        x-bind:class="toast.type_config ? toast.type_config.icon_color : 'text-yellow-500'" />
                                    
                                    <!-- Info Icon -->
                                    <flux:icon name="info" class="h-5 w-5" 
                                        x-show="toast.type === 'info'" 
                                        x-bind:class="toast.type_config ? toast.type_config.icon_color : 'text-blue-500'" />
                                </div>

                                <!-- Content -->
                                <div class="ml-3 w-0 flex-1">
                                    <!-- Title -->
                                    <p 
                                        class="text-sm font-medium"
                                        x-bind:class="toast.type_config.text"
                                        x-text="toast.title"
                                    ></p>

                                    <!-- Body -->
                                    <div 
                                        x-show="toast.body"
                                        class="mt-1 text-sm"
                                        x-bind:class="toast.type_config.text"
                                        x-html="toast.body"
                                    ></div>

                                    <!-- Actions -->
                                    <div x-show="toast.actions && toast.actions.length > 0" class="mt-3 flex space-x-2">
                                        <template x-for="action in toast.actions" :key="action.label">
                                            <flux:button 
                                                size="xs" 
                                                variant="ghost"
                                                @click="handleAction(action)"
                                                x-text="action.label"
                                            />
                                        </template>
                                    </div>
                                </div>

                                <!-- Close Button -->
                                <div x-show="toast.closable" class="ml-4 shrink-0">
                                    <flux:button 
                                        size="xs" 
                                        variant="ghost"
                                        @click="closeToast()"
                                        x-bind:class="toast.type_config.close_hover"
                                    >
                                        <flux:icon name="circle-x" class="h-4 w-4" />
                                    </flux:button>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar (for timed toasts) -->
                        <div 
                            x-show="!toast.persistent && toast.duration > 0"
                            class="absolute bottom-0 left-0 h-1 bg-current opacity-20 transition-all duration-75 ease-linear"
                            x-bind:style="{ width: progressWidth + '%' }"
                            x-bind:class="toast.type_config.icon_color"
                        ></div>
                    </div>
                </template>
            </div>
        @endif
    @endforeach
</div>

<script>
// Alpine.js components for toast functionality
document.addEventListener('alpine:init', () => {
    Alpine.data('toastGroup', (initialToasts, position) => ({
        toasts: initialToasts,
        position: position,

        init() {
            // Listen for Livewire events
            Livewire.on('toast-added', () => {
                this.$wire.handleToastAdded();
            });

            Livewire.on('toast-removed', (data) => {
                this.removeToast(data.toastId);
            });
        },

        removeToast(toastId) {
            this.toasts = this.toasts.filter(toast => toast.id !== toastId);
        }
    }));

    Alpine.data('toastItem', (toast) => ({
        toast: toast,
        show: false,
        progressWidth: 100,
        timer: null,
        progressTimer: null,

        init() {
            // Show toast with slight delay for transition
            this.$nextTick(() => {
                this.show = true;
            });

            // Start auto-dismiss timer if not persistent
            if (!this.toast.persistent && this.toast.duration > 0) {
                this.startTimer();
            }
        },

        startTimer() {
            const duration = this.toast.duration;
            const updateInterval = 50; // Update progress every 50ms
            let elapsed = 0;

            this.progressTimer = setInterval(() => {
                elapsed += updateInterval;
                this.progressWidth = Math.max(0, 100 - (elapsed / duration) * 100);
            }, updateInterval);

            this.timer = setTimeout(() => {
                this.closeToast();
            }, duration);
        },

        stopTimer() {
            if (this.timer) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            if (this.progressTimer) {
                clearInterval(this.progressTimer);
                this.progressTimer = null;
            }
        },

        pauseTimer() {
            this.stopTimer();
        },

        resumeTimer() {
            if (!this.toast.persistent && this.toast.duration > 0 && !this.timer) {
                const remainingTime = (this.progressWidth / 100) * this.toast.duration;
                
                this.timer = setTimeout(() => {
                    this.closeToast();
                }, remainingTime);

                const updateInterval = 50;
                let elapsed = this.toast.duration - remainingTime;

                this.progressTimer = setInterval(() => {
                    elapsed += updateInterval;
                    this.progressWidth = Math.max(0, 100 - (elapsed / this.toast.duration) * 100);
                }, updateInterval);
            }
        },

        closeToast() {
            this.show = false;
            this.stopTimer();
            
            // Wait for transition to complete before removing
            setTimeout(() => {
                this.$wire.removeToast(this.toast.id);
            }, 200);
        },

        handleAction(action) {
            // Emit Livewire event for action handling
            this.$wire.handleToastAction(this.toast.id, action);

            // Handle URL navigation
            if (action.url) {
                if (action.url.startsWith('http') || action.url.startsWith('//')) {
                    window.open(action.url, '_blank');
                } else {
                    window.location.href = action.url;
                }
            }

            // Close toast if specified
            if (action.should_close_toast) {
                this.closeToast();
            }
        }
    }));
});
</script>