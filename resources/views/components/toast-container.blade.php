{{-- Toast Container Component --}}
@persist('toast-container')
{{-- Initialize Alpine Toast Store --}}
<script>
document.addEventListener('alpine:init', () => {
    if (!Alpine.store('toasts')) {
        Alpine.store('toasts', {
            items: [],
            timers: new Map(),
            progressTimers: new Map(),

            // Get toasts grouped by position for rendering
            get byPosition() {
                const positions = ['top-right', 'top-left', 'bottom-right', 'bottom-left', 'top-center', 'bottom-center'];
                return positions.reduce((acc, pos) => {
                    acc[pos] = this.items.filter(t => (t.position || 'top-right') === pos);
                    return acc;
                }, {});
            },

            // Initialize store with toasts from Livewire
            init(toasts = []) {
                // Ensure toasts is an array
                const toastArray = Array.isArray(toasts) ? toasts : [];
                this.items = toastArray.map(toast => ({
                    ...toast,
                    show: false,
                    progressWidth: 100,
                    isHovered: false,
                    isFocused: false,
                    touchStartX: null,
                    touchStartTime: null,
                    prefersReducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                }));
                
                // Start entrance animations with stagger
                this.items.forEach((toast, index) => {
                    const delay = toast.prefersReducedMotion ? 0 : (index * 100);
                    setTimeout(() => {
                        toast.show = true;
                        if (!toast.persistent && toast.duration > 0) {
                            this.startTimer(toast.id);
                        }
                    }, delay);
                });
            },

            // Add a new toast
            add(toast) {
                if (!toast.id) {
                    toast.id = 'toast_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                }

                const toastItem = {
                    ...toast,
                    show: false,
                    progressWidth: 100,
                    isHovered: false,
                    isFocused: false,
                    touchStartX: null,
                    touchStartTime: null,
                    prefersReducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches
                };

                this.items.push(toastItem);

                requestAnimationFrame(() => {
                    toastItem.show = true;
                    if (!toast.persistent && toast.duration > 0) {
                        this.startTimer(toast.id);
                    }
                });
            },

            // Remove a toast by ID
            remove(id) {
                const toastIndex = this.items.findIndex(t => t.id === id);
                if (toastIndex === -1) return;

                const toast = this.items[toastIndex];
                toast.show = false;
                
                this.stopTimer(id);

                const transitionDuration = toast.prefersReducedMotion ? 0 : 300;
                setTimeout(() => {
                    this.items = this.items.filter(t => t.id !== id);
                    if (window.Livewire) {
                        window.Livewire.dispatch('toast-removed-from-store', { toastId: id });
                    }
                }, transitionDuration);
            },

            // Clear all toasts
            clear() {
                this.items.forEach(toast => {
                    this.stopTimer(toast.id);
                    toast.show = false;
                });

                const hasReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                const transitionDuration = hasReducedMotion ? 0 : 300;
                
                setTimeout(() => {
                    this.items = [];
                    if (window.Livewire) {
                        window.Livewire.dispatch('toasts-cleared-from-store');
                    }
                }, transitionDuration);
            },

            // Timer management methods
            startTimer(id) {
                const toast = this.items.find(t => t.id === id);
                if (!toast || toast.persistent || toast.duration <= 0) return;

                this.stopTimer(id);

                const duration = toast.duration;
                const updateInterval = toast.prefersReducedMotion ? 200 : 16;
                let elapsed = 0;

                const progressTimer = setInterval(() => {
                    if (toast.isHovered || toast.isFocused) return;
                    
                    elapsed += updateInterval;
                    const newWidth = Math.max(0, 100 - (elapsed / duration) * 100);
                    toast.progressWidth = this.easeOutQuart(newWidth / 100) * 100;
                }, updateInterval);

                const dismissTimer = setTimeout(() => {
                    if (!toast.isHovered && !toast.isFocused) {
                        this.remove(id);
                    }
                }, duration);

                this.progressTimers.set(id, progressTimer);
                this.timers.set(id, dismissTimer);
            },

            stopTimer(id) {
                if (this.timers.has(id)) {
                    clearTimeout(this.timers.get(id));
                    this.timers.delete(id);
                }
                if (this.progressTimers.has(id)) {
                    clearInterval(this.progressTimers.get(id));
                    this.progressTimers.delete(id);
                }
            },

            pause(id) {
                const toast = this.items.find(t => t.id === id);
                if (toast) {
                    toast.isHovered = true;
                }
            },

            resume(id) {
                const toast = this.items.find(t => t.id === id);
                if (!toast) return;

                toast.isHovered = false;
                
                if (!toast.persistent && toast.duration > 0 && toast.progressWidth > 0 && !toast.isFocused) {
                    this.startTimer(id);
                }
            },

            focus(id) {
                const toast = this.items.find(t => t.id === id);
                if (toast) {
                    toast.isFocused = true;
                }
            },

            blur(id) {
                const toast = this.items.find(t => t.id === id);
                if (!toast) return;

                toast.isFocused = false;
                if (!toast.isHovered) {
                    this.resume(id);
                }
            },

            handleAction(toastId, action) {
                if (window.Livewire) {
                    window.Livewire.dispatch('toast-action-clicked', { 
                        toastId, 
                        actionData: action 
                    });
                }

                if (action.url) {
                    if (action.url.startsWith('http') || action.url.startsWith('//')) {
                        window.open(action.url, '_blank');
                    } else {
                        window.location.href = action.url;
                    }
                }

                if (action.should_close_toast !== false) {
                    setTimeout(() => this.remove(toastId), 100);
                }
            },

            handleTouchStart(id, event) {
                const toast = this.items.find(t => t.id === id);
                if (toast) {
                    toast.touchStartX = event.touches[0].clientX;
                    toast.touchStartTime = Date.now();
                }
            },

            handleTouchEnd(id, event) {
                const toast = this.items.find(t => t.id === id);
                if (!toast || !toast.touchStartX) return;
                
                const touchEndX = event.changedTouches[0].clientX;
                const deltaX = touchEndX - toast.touchStartX;
                const deltaTime = Date.now() - toast.touchStartTime;
                
                if (deltaX > 50 && deltaTime < 300 && toast.closable) {
                    this.remove(id);
                    
                    if (navigator.vibrate) {
                        navigator.vibrate(50);
                    }
                }
                
                toast.touchStartX = null;
            },

            easeOutQuart(t) {
                return 1 - Math.pow(1 - t, 4);
            },

            // Handle navigation - keep only toasts marked to persist
            handleNavigation() {
                // Clear timers for non-persistent navigation toasts
                this.items.forEach(toast => {
                    if (!toast.navigatePersist) {
                        this.stopTimer(toast.id);
                    }
                });
                
                // Filter out non-persistent navigation toasts
                this.items = this.items.filter(toast => toast.navigatePersist);
            }
        });
    }
    
    // Listen for Livewire navigation events
    document.addEventListener('livewire:navigate', () => {
        if (Alpine.store('toasts')) {
            Alpine.store('toasts').handleNavigation();
        }
    });
    
    // Listen for navigation start to prepare toasts
    document.addEventListener('livewire:navigating', () => {
        if (Alpine.store('toasts')) {
            // Mark non-persistent toasts to not show during transition
            Alpine.store('toasts').items.forEach(toast => {
                if (!toast.navigatePersist) {
                    toast.show = false;
                }
            });
        }
    });
});
</script>

<livewire:components.toast-container />
@endpersist