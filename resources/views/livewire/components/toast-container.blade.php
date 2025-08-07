<div 
    class="toast-container-wrapper" 
    role="alert" 
    aria-live="polite"
    x-data="{ 
        toastData: @js($this->allToasts->values()->toArray()),
        init() {
            $store.toasts.init(this.toastData);
        }
    }"
    x-init="init()"
>
    @foreach(config('toasts.positions') as $position => $config)
        <div 
            x-data="{ 
                position: '{{ $position }}',
                get toasts() { return $store.toasts.byPosition['{{ $position }}'] || []; },
                get hasToasts() { return this.toasts && this.toasts.length > 0; }
            }"
            class="{{ $config['container'] }} flex {{ $config['alignment'] }} gap-3 pointer-events-none"
            x-show="hasToasts"
            wire:key="toast-group-{{ $position }}"
        >
            <template x-for="(toast, index) in toasts" :key="toast.id">
                <div 
                    x-data="{
                        get toastData() { return $store.toasts.items.find(t => t.id === toast.id) || toast; },
                        
                        init() {
                            // Add keyboard navigation support
                            this.$el.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    if (this.toastData.actions && this.toastData.actions.length > 0) {
                                        $store.toasts.handleAction(this.toastData.id, this.toastData.actions[0]);
                                    } else if (this.toastData.closable) {
                                        $store.toasts.remove(this.toastData.id);
                                    }
                                }
                            });
                        }
                    }"
                    x-show="toastData.show"
                    x-transition:enter="transition ease-out duration-500"
                    x-transition:enter-start="{{ config('toasts.animations.enter.from') }}"
                    x-transition:enter-end="{{ config('toasts.animations.enter.to') }}"
                    x-transition:leave="transition ease-in duration-300"
                    x-transition:leave-start="{{ config('toasts.animations.exit.from') }}"
                    x-transition:leave-end="{{ config('toasts.animations.exit.to') }}"
                    @mouseenter="$store.toasts.pause(toastData.id)"
                    @mouseleave="$store.toasts.resume(toastData.id)"
                    @focus="$store.toasts.focus(toastData.id)"
                    @blur="$store.toasts.blur(toastData.id)"
                    @touchstart="$store.toasts.handleTouchStart(toastData.id, $event)"
                    @touchend="$store.toasts.handleTouchEnd(toastData.id, $event)"
                    class="toast-item relative w-full max-w-md overflow-hidden rounded-xl shadow-2xl backdrop-blur-sm pointer-events-auto cursor-default"
                    x-bind:class="toastData.type_config.background + ' ' + toastData.type_config.border"
                    x-bind:style="{ 'animation-delay': (index * 100) + 'ms' }"
                    role="alertdialog"
                    x-bind:aria-labelledby="'toast-title-' + toastData.id"
                    x-bind:aria-describedby="'toast-body-' + toastData.id"
                    tabindex="0"
                    @keydown.escape="if (toastData.closable) $store.toasts.remove(toastData.id)"
                    >
                        <!-- Modern gradient overlay for enhanced depth -->
                        <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent pointer-events-none"></div>
                        
                        <div class="relative p-5">
                            <div class="flex items-start gap-4">
                            <!-- Enhanced Icon with background -->
                            <div class="shrink-0 relative">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center" 
                                     x-bind:class="toastData.type_config.icon_background">
                                    <!-- Success Icon -->
                                    <flux:icon name="circle-check" class="h-5 w-5" 
                                        x-show="toastData.type === 'success'" 
                                        x-bind:class="toastData.type_config.icon_color" />
                                    
                                    <!-- Error Icon -->
                                    <flux:icon name="circle-x" class="h-5 w-5" 
                                        x-show="toastData.type === 'error'" 
                                        x-bind:class="toastData.type_config.icon_color" />
                                    
                                    <!-- Warning Icon -->
                                    <flux:icon name="triangle-alert" class="h-5 w-5" 
                                        x-show="toastData.type === 'warning'" 
                                        x-bind:class="toastData.type_config.icon_color" />
                                    
                                    <!-- Info Icon -->
                                    <flux:icon name="info" class="h-5 w-5" 
                                        x-show="toastData.type === 'info'" 
                                        x-bind:class="toastData.type_config.icon_color" />
                                </div>
                                <!-- Icon pulse animation for emphasis -->
                                <div class="absolute inset-0 rounded-full animate-ping opacity-20"
                                     x-bind:class="toastData.type_config.icon_background"
                                     x-show="toastData.show"
                                     x-transition:enter="transition duration-1000"
                                     x-transition:enter-start="opacity-0 scale-0"
                                     x-transition:enter-end="opacity-20 scale-100"
                                     x-init="setTimeout(() => $el.style.display = 'none', 1000)"></div>
                            </div>

                            <!-- Enhanced Content Section -->
                            <div class="flex-1 min-w-0">
                                <!-- Title with improved typography -->
                                <h3 
                                    x-bind:id="'toast-title-' + toastData.id"
                                    class="text-sm font-semibold tracking-tight leading-tight"
                                    x-bind:class="toastData.type_config.text"
                                    x-text="toastData.title"
                                ></h3>

                                <!-- Body with improved spacing and typography -->
                                <div 
                                    x-show="toastData.body"
                                    x-bind:id="'toast-body-' + toastData.id"
                                    class="mt-2 text-sm leading-relaxed opacity-90"
                                    x-bind:class="toastData.type_config.text"
                                    x-html="toastData.body"
                                ></div>

                                <!-- Enhanced Actions Section -->
                                <div x-show="toastData.actions && toastData.actions.length > 0" class="mt-4 flex flex-wrap gap-2">
                                    <template x-for="action in toastData.actions" :key="action.label">
                                        <flux:button 
                                            size="xs" 
                                            variant="ghost"
                                            @click="$store.toasts.handleAction(toastData.id, action)"
                                            x-text="action.label"
                                            class="transition-all duration-200 hover:scale-105 focus:scale-105"
                                            x-bind:class="toastData.type_config.action_hover"
                                        />
                                    </template>
                                </div>
                            </div>

                            <!-- Enhanced Close Button -->
                            <div x-show="toastData.closable" class="shrink-0">
                                <flux:button 
                                    size="xs" 
                                    variant="ghost"
                                    @click="$store.toasts.remove(toastData.id)"
                                    class="transition-all duration-200 hover:scale-110 focus:scale-110 rounded-full p-1.5"
                                    x-bind:class="toastData.type_config.close_hover"
                                    aria-label="Close notification"
                                    tabindex="0"
                                >
                                    <flux:icon name="x" class="h-4 w-4" />
                                </flux:button>
                            </div>
                            </div>
                        </div>

                        <!-- Enhanced Progress Bar with smooth animation -->
                        <div 
                            x-show="!toastData.persistent && toastData.duration > 0"
                            class="absolute bottom-0 left-0 h-1 transition-all duration-100 ease-out rounded-full"
                            x-bind:style="{ 
                                width: toastData.progressWidth + '%',
                                background: 'linear-gradient(90deg, ' + toastData.type_config.progress_color + ', ' + toastData.type_config.progress_color_end + ')'
                            }"
                        ></div>
                        
                        <!-- Side accent bar for additional visual emphasis -->
                        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl"
                             x-bind:class="toastData.type_config.accent_bar"></div>
                    </div>
                </template>
        </div>
    @endforeach
</div>