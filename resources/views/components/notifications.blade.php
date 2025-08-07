{{-- Pure Livewire Notifications - No Alpine.js --}}
@if(!empty($notifications))
<div class="fixed top-4 right-4 z-50 space-y-2 max-w-sm">
    @foreach($notifications as $notification)
    <div 
        wire:key="notification-{{ $notification['id'] }}"
        class="notification-item bg-white dark:bg-gray-800 rounded-lg shadow-lg border-l-4 p-4 transition-all duration-300 ease-in-out
        @switch($notification['type'])
            @case('success') border-green-500 @break
            @case('error') border-red-500 @break  
            @case('warning') border-yellow-500 @break
            @default border-blue-500
        @endswitch"
        style="animation: slideIn 0.3s ease-out;"
    >
        <div class="flex items-start">
            {{-- Icon --}}
            <div class="flex-shrink-0 mr-3">
                @switch($notification['type'])
                    @case('success')
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        @break
                    @case('error')
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        @break
                    @case('warning')
                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        @break
                    @default
                        <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                @endswitch
            </div>
            
            {{-- Content --}}
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ $notification['title'] }}
                </h3>
                @if($notification['body'])
                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $notification['body'] }}
                </div>
                @endif
            </div>
            
            {{-- Close Button --}}
            <div class="flex-shrink-0 ml-3">
                <button 
                    wire:click="removeNotification('{{ $notification['id'] }}')"
                    class="inline-flex text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition-colors"
                >
                    <span class="sr-only">Close</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- CSS Animations --}}
<style>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100%);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.notification-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>

{{-- Auto-dismiss script (pure JS, no Alpine) --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss notifications after 5 seconds
    const notifications = document.querySelectorAll('.notification-item');
    notifications.forEach(function(notification) {
        setTimeout(function() {
            if (notification && notification.parentNode) {
                notification.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(function() {
                    // Find the wire:key to get the notification ID
                    const key = notification.getAttribute('wire:key');
                    if (key) {
                        const id = key.replace('notification-', '');
                        // Trigger Livewire method to remove notification
                        Livewire.find(notification.closest('[wire\\:id]').__livewire.id).call('removeNotification', id);
                    }
                }, 300);
            }
        }, 5000);
    });
});

// Slide out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
`;
document.head.appendChild(style);
</script>
@endif