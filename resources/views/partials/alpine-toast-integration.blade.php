{{-- Toast Alpine.js Integration - Following ResourceManager Pattern --}}
{{-- This provides seamless Livewire + Alpine.js state synchronization --}}

@persist('toast-integration')
<script>
document.addEventListener('alpine:init', () => {
    // Global Toast Store (like ResourceManager pattern)
    Alpine.store('toasts', {
        // State
        items: [],
        positions: ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'],
        
        // Computed properties
        get hasToasts() {
            return this.items.length > 0;
        },
        
        get byPosition() {
            return this.positions.reduce((groups, position) => {
                groups[position] = this.items.filter(toast => toast.position === position);
                return groups;
            }, {});
        },
        
        // Actions (following ResourceManager API style)
        add(toast) {
            this.items.push({
                ...toast,
                visible: true,
                id: toast.id || this.generateId()
            });
        },
        
        remove(toastId) {
            const index = this.items.findIndex(toast => toast.id === toastId);
            if (index !== -1) {
                this.items.splice(index, 1);
                
                // Sync back to Livewire (like table actions)
                if (window.Livewire && window.Livewire.find) {
                    const component = this.findLivewireComponent();
                    if (component) {
                        component.call('removeToast', toastId);
                    }
                }
            }
        },
        
        clear() {
            this.items = [];
        },
        
        // Wire:Navigate persistence (like ResourceManager)
        handleNavigate() {
            // Filter only persistent toasts across navigation
            this.items = this.items.filter(toast => toast.navigatePersist);
        },
        
        // Sync with Livewire state (like ResourceManager pattern)
        syncFromLivewire(toasts) {
            // Only add new toasts, don't replace existing ones
            toasts.forEach(toast => {
                if (!this.items.find(item => item.id === toast.id)) {
                    this.add(toast);
                }
            });
        },
        
        // Utility methods
        generateId() {
            return 'toast-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        },
        
        findLivewireComponent() {
            // Find the current Livewire component (following ResourceManager pattern)
            return document.querySelector('[wire\\:id]')?.__livewire || null;
        },
        
        // Icon helper (like ResourceManager utilities)
        getToastIcon(iconName, toastType) {
            const icons = {
                'check-circle': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'x-circle': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
                'exclamation-triangle': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>',
                'information-circle': '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
            };
            
            // Default icons by type if no specific icon provided
            const defaultIcons = {
                'success': 'check-circle',
                'error': 'x-circle', 
                'warning': 'exclamation-triangle',
                'info': 'information-circle'
            };
            
            const icon = iconName || defaultIcons[toastType] || defaultIcons['info'];
            return icons[icon] || icons['information-circle'];
        }
    });
    
    // Toast Container Component (enhanced version)
    Alpine.data('toastContainer', (initialConfig = {}) => ({
        config: initialConfig,
        
        init() {
            // Sync initial data with store
            if (this.config.toasts) {
                Alpine.store('toasts').syncFromLivewire(this.config.toasts);
            }
            
            // Listen for Livewire navigation (like ResourceManager)
            this.handleWireNavigate();
            
            // Listen for Livewire events
            this.handleLivewireEvents();
        },
        
        handleWireNavigate() {
            // Handle wire:navigate like ResourceManager does
            window.addEventListener('livewire:navigate', () => {
                Alpine.store('toasts').handleNavigate();
            });
            
            window.addEventListener('livewire:navigated', () => {
                // Re-sync after navigation
                this.syncWithLivewire();
            });
        },
        
        handleLivewireEvents() {
            // Listen for toast events from Livewire
            window.addEventListener('toast:added', (event) => {
                if (event.detail && event.detail.toast) {
                    Alpine.store('toasts').add(event.detail.toast);
                }
            });
            
            window.addEventListener('toast:removed', (event) => {
                if (event.detail && event.detail.toastId) {
                    Alpine.store('toasts').remove(event.detail.toastId);
                }
            });
        },
        
        syncWithLivewire() {
            // Find current Livewire component and sync
            const component = Alpine.store('toasts').findLivewireComponent();
            if (component && component.get) {
                try {
                    const livewireToasts = component.get('toasts') || [];
                    Alpine.store('toasts').syncFromLivewire(livewireToasts);
                } catch (e) {
                    // Graceful fallback
                }
            }
        }
    }));
    
    // Individual Toast Item Component (enhanced)
    Alpine.data('toastItem', (toast) => ({
        toast: toast,
        visible: true,
        timer: null,
        progressTimer: null,
        progressWidth: 100,
        
        init() {
            this.$nextTick(() => {
                this.startAutoClose();
            });
        },
        
        startAutoClose() {
            if (!this.toast.persistent && this.toast.duration > 0) {
                this.startProgressTimer();
            }
        },
        
        startProgressTimer() {
            if (this.timer || this.progressTimer) return;
            
            const duration = this.toast.duration;
            let elapsed = 0;
            const updateInterval = 75;
            
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
            
            // Remove from Alpine store (which syncs with Livewire)
            setTimeout(() => {
                Alpine.store('toasts').remove(this.toast.id);
            }, 150); // Wait for animation
        },
        
        executeAction(actionKey) {
            // Execute toast action via Livewire (like table actions)
            const component = Alpine.store('toasts').findLivewireComponent();
            if (component) {
                component.call('executeToastAction', this.toast.id, actionKey);
            }
        }
    }));
});
</script>
@endpersist