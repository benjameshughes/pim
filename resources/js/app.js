import './bootstrap';
import './activity-tracker';

// Phoenix Toast System - Simple and Elegant ✨
window.phoenixToastData = () => ({
    toasts: [],
    nextId: 1,
    
    addToast(message, type = 'success', duration = 4000) {
        const toast = {
            id: this.nextId++,
            message,
            type,
            show: false
        };
        
        this.toasts.push(toast);
        
        // Trigger entrance animation
        setTimeout(() => {
            toast.show = true;
        }, 50);
        
        // Auto-remove after duration
        if (duration > 0) {
            setTimeout(() => {
                this.removeToast(toast.id);
            }, duration);
        }
    },
    
    removeToast(id) {
        const toast = this.toasts.find(t => t.id === id);
        if (toast) {
            toast.show = false;
            setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id);
            }, 300);
        }
    }
});