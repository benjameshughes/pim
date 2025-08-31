/**
 * 🎯 ACTIVITY TRACKER - Because Ben loves verbose logging! 
 * 
 * This script automatically tracks button clicks, form submissions, and other interactions
 * throughout the application and sends them to the backend for gorgeous logging.
 */

class ActivityTracker {
    constructor() {
        this.init();
    }

    init() {
        // Track button clicks
        this.trackButtonClicks();
        
        // Track form submissions
        this.trackFormSubmissions();
        
        // Track page navigation
        this.trackPageNavigation();
        
        // Track search interactions
        this.trackSearchInteractions();

        console.log('🎯 Activity Tracker initialized - Ben is going to love this data!');
    }

    trackButtonClicks() {
        document.addEventListener('click', (event) => {
            const button = event.target.closest('button, [role="button"], .btn, [wire\\:click]');
            
            if (button && !button.classList.contains('activity-tracker-ignore')) {
                const buttonText = this.getButtonText(button);
                const context = this.getButtonContext(button);
                
                // Send to backend via Livewire if available
                if (window.Livewire && button.hasAttribute('wire:click')) {
                    // Let the Livewire method handle its own tracking
                    return;
                }
                
                // For non-Livewire buttons, send via AJAX
                this.sendActivity('button_clicked', {
                    button_text: buttonText,
                    button_class: button.className,
                    button_id: button.id || null,
                    page_url: window.location.href,
                    page_title: document.title,
                    ...context
                });
            }
        });
    }

    trackFormSubmissions() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            const formName = form.name || form.id || 'unnamed-form';
            const formMethod = form.method || 'GET';
            const formAction = form.action || window.location.href;
            
            this.sendActivity('form_submitted', {
                form_name: formName,
                form_method: formMethod,
                form_action: formAction,
                field_count: form.elements.length,
                page_url: window.location.href,
            });
        });
    }

    trackPageNavigation() {
        // Track initial page load
        this.sendActivity('page_viewed', {
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer || null,
            user_agent: navigator.userAgent,
            screen_resolution: `${screen.width}x${screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
        });

        // Track navigation (for SPAs and wire:navigate)
        let currentUrl = window.location.href;
        const observer = new MutationObserver(() => {
            if (window.location.href !== currentUrl) {
                currentUrl = window.location.href;
                this.sendActivity('page_navigated', {
                    page_url: currentUrl,
                    page_title: document.title,
                    navigation_type: 'spa',
                });
            }
        });
        
        observer.observe(document, { subtree: true, childList: true });
    }

    trackSearchInteractions() {
        // Track search input changes (debounced)
        document.addEventListener('input', (event) => {
            const input = event.target;
            
            if (input.type === 'search' || 
                input.name?.includes('search') || 
                input.placeholder?.toLowerCase().includes('search')) {
                
                // Debounce search tracking
                clearTimeout(input.searchTimeout);
                input.searchTimeout = setTimeout(() => {
                    if (input.value.length >= 3) {
                        this.sendActivity('search_performed', {
                            search_query: input.value,
                            search_field: input.name || input.id || 'unknown',
                            query_length: input.value.length,
                            page_url: window.location.href,
                        });
                    }
                }, 500); // 500ms debounce
            }
        });
    }

    getButtonText(button) {
        // Try to get meaningful button text
        return button.textContent?.trim() || 
               button.getAttribute('aria-label') || 
               button.getAttribute('title') || 
               button.getAttribute('wire:click') ||
               button.className.split(' ')[0] ||
               'Unknown Button';
    }

    getButtonContext(button) {
        const context = {};
        
        // Get parent component context
        const livewireComponent = button.closest('[wire\\:id]');
        if (livewireComponent) {
            context.livewire_component = livewireComponent.getAttribute('wire:id');
        }
        
        // Get section context
        const section = button.closest('section, .card, .panel, [data-section]');
        if (section) {
            context.page_section = section.getAttribute('data-section') || 
                                  section.className.split(' ')[0] ||
                                  'unknown-section';
        }
        
        // Get any data attributes
        Array.from(button.attributes).forEach(attr => {
            if (attr.name.startsWith('data-')) {
                context[attr.name] = attr.value;
            }
        });
        
        return context;
    }

    sendActivity(eventType, data) {
        // Send activity data to Laravel backend
        fetch('/api/activity-tracking', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                event_type: eventType,
                data: data,
                timestamp: new Date().toISOString(),
            })
        }).catch(error => {
            // Silently fail - we don't want activity tracking to break the app
            console.debug('Activity tracking failed:', error);
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ActivityTracker();
    });
} else {
    new ActivityTracker();
}

// Make it available globally for manual tracking
window.ActivityTracker = ActivityTracker;