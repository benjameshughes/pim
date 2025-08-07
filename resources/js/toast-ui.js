// Alpine.js components for enhanced toast functionality
document.addEventListener('alpine:init', () => {
    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    Alpine.data('toastGroup', (initialToasts, position) => ({
        toasts: initialToasts || [],
        position: position,

        init() {
            // Ensure toasts is always an array
            if (!Array.isArray(this.toasts)) {
                this.toasts = [];
            }
            
            // Listen for Livewire events
            Livewire.on('toast-added', () => {
                // Let Livewire handle the re-render, which will update our data
                this.$wire.$refresh();
            });

            Livewire.on('toast-removed', (data) => {
                this.removeToast(data.toastId);
            });

            // Watch for changes in Livewire data
            this.$watch('$wire.toastsByPosition', (value) => {
                // Update our local toasts when Livewire data changes
                if (value && value[this.position]) {
                    this.toasts = value[this.position];
                }
            });
        },

        removeToast(toastId) {
            this.toasts = this.toasts.filter(toast => toast.id !== toastId);
        }
    }));

    Alpine.data('toastItem', (toast, index = 0) => ({
        toast: toast,
        show: false,
        progressWidth: 100,
        timer: null,
        progressTimer: null,
        isHovered: false,
        isFocused: false,
        index: index,
        touchStartX: null,
        touchStartTime: null,

        init() {
            // Staggered entrance with reduced motion support
            const delay = prefersReducedMotion ? 0 : (this.index * 100);
            
            setTimeout(() => {
                this.show = true;
                
                // Start auto-dismiss timer if not persistent
                if (!this.toast.persistent && this.toast.duration > 0) {
                    this.startTimer();
                }
            }, delay);

            // Add keyboard navigation support
            this.$el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (this.toast.actions && this.toast.actions.length > 0) {
                        this.handleAction(this.toast.actions[0]);
                    } else if (this.toast.closable) {
                        this.closeToast();
                    }
                }
            });
        },

        startTimer() {
            const duration = this.toast.duration;
            const updateInterval = prefersReducedMotion ? 200 : 16; // Smooth 60fps or reduced
            let elapsed = 0;

            this.progressTimer = setInterval(() => {
                if (this.isHovered || this.isFocused) return; // Pause on hover/focus
                
                elapsed += updateInterval;
                const newWidth = Math.max(0, 100 - (elapsed / duration) * 100);
                
                // Smooth easing for progress bar
                this.progressWidth = this.easeOutQuart(newWidth / 100) * 100;
            }, updateInterval);

            this.timer = setTimeout(() => {
                if (!this.isHovered && !this.isFocused) {
                    this.closeToast();
                }
            }, duration);
        },

        // Smooth easing function for animations
        easeOutQuart(t) {
            return 1 - Math.pow(1 - t, 4);
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
            this.isHovered = true;
            // Don't fully stop timer, just pause progress
        },

        resumeTimer() {
            this.isHovered = false;
            
            if (!this.toast.persistent && this.toast.duration > 0 && this.progressWidth > 0 && !this.isFocused) {
                // Resume from current progress
                this.startTimer();
            }
        },

        handleFocus() {
            this.isFocused = true;
        },

        handleBlur() {
            this.isFocused = false;
            if (!this.isHovered) {
                this.resumeTimer();
            }
        },

        closeToast() {
            this.show = false;
            this.stopTimer();
            
            // Enhanced feedback with haptic if available
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            
            // Wait for transition to complete before removing
            const transitionDuration = prefersReducedMotion ? 0 : 300;
            setTimeout(() => {
                this.$wire.removeToast(this.toast.id);
            }, transitionDuration);
        },

        handleAction(action) {
            // Visual feedback for action click
            this.$el.classList.add('scale-95');
            setTimeout(() => {
                this.$el.classList.remove('scale-95');
            }, 150);

            // Emit Livewire event for action handling
            this.$wire.handleToastAction(this.toast.id, action);

            // Enhanced URL navigation with loading state
            if (action.url) {
                // Add loading indicator
                const actionButton = event.target.closest('button');
                if (actionButton) {
                    actionButton.disabled = true;
                    actionButton.innerHTML = '<div class="animate-spin h-3 w-3 border border-current border-t-transparent rounded-full"></div>';
                }

                if (action.url.startsWith('http') || action.url.startsWith('//')) {
                    window.open(action.url, '_blank');
                } else {
                    window.location.href = action.url;
                }
            }

            // Close toast if specified
            if (action.should_close_toast) {
                setTimeout(() => this.closeToast(), 100);
            }
        },

        // Swipe to dismiss functionality for mobile
        handleTouchStart(event) {
            this.touchStartX = event.touches[0].clientX;
            this.touchStartTime = Date.now();
        },

        handleTouchEnd(event) {
            if (!this.touchStartX) return;
            
            const touchEndX = event.changedTouches[0].clientX;
            const deltaX = touchEndX - this.touchStartX;
            const deltaTime = Date.now() - this.touchStartTime;
            
            // Swipe right to dismiss (minimum 50px swipe within 300ms)
            if (deltaX > 50 && deltaTime < 300 && this.toast.closable) {
                this.closeToast();
            }
            
            this.touchStartX = null;
        }
    }));
});