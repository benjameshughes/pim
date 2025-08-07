<script>
document.addEventListener('alpine:init', () => {
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
            // Ensure unique ID
            if (!toast.id) {
                toast.id = 'toast_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }

            // Initialize animation and state properties
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

            // Start entrance animation
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

            // Wait for exit animation to complete
            const transitionDuration = toast.prefersReducedMotion ? 0 : 300;
            setTimeout(() => {
                this.items = this.items.filter(t => t.id !== id);
                // Notify Livewire to update session
                window.Livewire.dispatch('toast-removed-from-store', { toastId: id });
            }, transitionDuration);
        },

        // Clear all toasts
        clear() {
            // Stop all timers
            this.items.forEach(toast => {
                this.stopTimer(toast.id);
                toast.show = false;
            });

            // Clear after animations
            const hasReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            const transitionDuration = hasReducedMotion ? 0 : 300;
            
            setTimeout(() => {
                this.items = [];
                // Notify Livewire to clear session
                window.Livewire.dispatch('toasts-cleared-from-store');
            }, transitionDuration);
        },

        // Start auto-dismiss timer for a toast
        startTimer(id) {
            const toast = this.items.find(t => t.id === id);
            if (!toast || toast.persistent || toast.duration <= 0) return;

            this.stopTimer(id); // Clear any existing timers

            const duration = toast.duration;
            const updateInterval = toast.prefersReducedMotion ? 200 : 16; // 60fps or reduced
            let elapsed = 0;

            // Progress bar animation
            const progressTimer = setInterval(() => {
                if (toast.isHovered || toast.isFocused) return; // Pause on hover/focus
                
                elapsed += updateInterval;
                const newWidth = Math.max(0, 100 - (elapsed / duration) * 100);
                
                // Smooth easing for progress bar
                toast.progressWidth = this.easeOutQuart(newWidth / 100) * 100;
            }, updateInterval);

            // Auto-dismiss timer
            const dismissTimer = setTimeout(() => {
                if (!toast.isHovered && !toast.isFocused) {
                    this.remove(id);
                }
            }, duration);

            this.progressTimers.set(id, progressTimer);
            this.timers.set(id, dismissTimer);
        },

        // Stop timer for a toast
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

        // Pause timer (on hover)
        pause(id) {
            const toast = this.items.find(t => t.id === id);
            if (toast) {
                toast.isHovered = true;
            }
        },

        // Resume timer (on unhover)
        resume(id) {
            const toast = this.items.find(t => t.id === id);
            if (!toast) return;

            toast.isHovered = false;
            
            if (!toast.persistent && toast.duration > 0 && toast.progressWidth > 0 && !toast.isFocused) {
                // Restart timer from current progress
                this.startTimer(id);
            }
        },

        // Handle focus events
        focus(id) {
            const toast = this.items.find(t => t.id === id);
            if (toast) {
                toast.isFocused = true;
            }
        },

        // Handle blur events
        blur(id) {
            const toast = this.items.find(t => t.id === id);
            if (!toast) return;

            toast.isFocused = false;
            if (!toast.isHovered) {
                this.resume(id);
            }
        },

        // Handle toast actions
        handleAction(toastId, action) {
            // Notify Livewire about the action
            window.Livewire.dispatch('toast-action-clicked', { 
                toastId, 
                actionData: action 
            });

            // Handle URL navigation
            if (action.url) {
                if (action.url.startsWith('http') || action.url.startsWith('//')) {
                    window.open(action.url, '_blank');
                } else {
                    window.location.href = action.url;
                }
            }

            // Close toast if specified
            if (action.should_close_toast !== false) {
                setTimeout(() => this.remove(toastId), 100);
            }
        },

        // Handle touch events for swipe-to-dismiss
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
            
            // Swipe right to dismiss (minimum 50px swipe within 300ms)
            if (deltaX > 50 && deltaTime < 300 && toast.closable) {
                this.remove(id);
                
                // Haptic feedback if available
                if (navigator.vibrate) {
                    navigator.vibrate(50);
                }
            }
            
            toast.touchStartX = null;
        },

        // Smooth easing function for animations
        easeOutQuart(t) {
            return 1 - Math.pow(1 - t, 4);
        }
    });

    // Listen for browser events from Livewire
    window.addEventListener('browser-event', (event) => {
        if (event.detail.event === 'toasts-updated') {
            Alpine.store('toasts').init(event.detail.data.toasts || []);
        }
    });

    // Listen for toast updates from Livewire
    window.addEventListener('toasts-updated', (event) => {
        Alpine.store('toasts').init(event.detail.toasts || []);
    });

    // Listen for new toast additions
    window.addEventListener('toast-added', (event) => {
        Alpine.store('toasts').add(event.detail.toast);
    });
});
</script>