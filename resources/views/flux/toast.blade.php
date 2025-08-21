@props([
    'position' => 'bottom-right',     // top-left, top-center, top-right, bottom-left, bottom-center, bottom-right
    'duration' => 4000,               // Auto-dismiss duration in ms, 0 = manual only
    'animation' => 'slide',           // slide, bounce, fade, glitter, sparkle
    'theme' => 'modern',              // modern, glass, neon, minimal
    'maxToasts' => 5,                 // Maximum number of toasts to show
    'glitterIntensity' => 'medium',   // none, low, medium, high, maximum
    'soundEnabled' => false,          // Enable sound effects (for future)
])

@php
$classes = Flux::classes()
    ->add('fixed inset-0 pointer-events-none isolate')
    ->add('contain-layout contain-style') // Modern CSS containment
    ->add('flex')
    ->add(match ($position) {
        'top-left' => 'items-start justify-start p-6',
        'top-center' => 'items-start justify-center p-6',
        'top-right' => 'items-start justify-end p-6',
        'bottom-left' => 'items-end justify-start p-6',
        'bottom-center' => 'items-end justify-center p-6',
        'bottom-right' => 'items-end justify-end p-6',
        default => 'items-end justify-end p-6',
    });

// ✨ THEME CONFIGURATIONS
$themeConfig = match ($theme) {
    'glass' => [
        'container' => 'backdrop-blur-xl bg-white/80 dark:bg-gray-900/80 border border-white/20',
        'success' => 'border-emerald-400/50 bg-emerald-50/80',
        'error' => 'border-rose-400/50 bg-rose-50/80',
        'info' => 'border-blue-400/50 bg-blue-50/80'
    ],
    'neon' => [
        'container' => 'bg-gray-900 border-2',
        'success' => 'border-emerald-400 shadow-emerald-400/50 shadow-lg',
        'error' => 'border-rose-400 shadow-rose-400/50 shadow-lg', 
        'info' => 'border-blue-400 shadow-blue-400/50 shadow-lg'
    ],
    'minimal' => [
        'container' => 'bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm',
        'success' => 'border-l-4 border-l-emerald-500',
        'error' => 'border-l-4 border-l-rose-500',
        'info' => 'border-l-4 border-l-blue-500'
    ],
    default => [ // modern
        'container' => 'bg-white border shadow-xl',
        'success' => 'border-emerald-400',
        'error' => 'border-rose-400',
        'info' => 'border-blue-400'
    ]
};

// ✨ ANIMATION CONFIGURATIONS
$animationConfig = match ($animation) {
    'bounce' => [
        'enter' => 'transform transition duration-500 ease-out',
        'enterStart' => 'translate-y-2 opacity-0 scale-90',
        'enterEnd' => 'translate-y-0 opacity-100 scale-100',
        'leave' => 'transform transition duration-300 ease-in',
        'leaveStart' => 'translate-y-0 opacity-100 scale-100',
        'leaveEnd' => 'translate-y-2 opacity-0 scale-90'
    ],
    'fade' => [
        'enter' => 'transition duration-300 ease-out',
        'enterStart' => 'opacity-0',
        'enterEnd' => 'opacity-100',
        'leave' => 'transition duration-200 ease-in',
        'leaveStart' => 'opacity-100',
        'leaveEnd' => 'opacity-0'
    ],
    'glitter' => [
        'enter' => 'transform transition duration-700 ease-out',
        'enterStart' => 'translate-x-full opacity-0 scale-75 rotate-12',
        'enterEnd' => 'translate-x-0 opacity-100 scale-100 rotate-0',
        'leave' => 'transform transition duration-500 ease-in',
        'leaveStart' => 'translate-x-0 opacity-100 scale-100 rotate-0',
        'leaveEnd' => 'translate-x-full opacity-0 scale-75 -rotate-12'
    ],
    'sparkle' => [
        'enter' => 'transform transition duration-600 ease-out',
        'enterStart' => 'translate-y-4 opacity-0 scale-50 rotate-180',
        'enterEnd' => 'translate-y-0 opacity-100 scale-100 rotate-0',
        'leave' => 'transform transition duration-400 ease-in',
        'leaveStart' => 'translate-y-0 opacity-100 scale-100 rotate-0',
        'leaveEnd' => 'translate-y-4 opacity-0 scale-50 rotate-180'
    ],
    default => [ // slide
        'enter' => 'transform transition duration-300 ease-out',
        'enterStart' => 'translate-x-full opacity-0 scale-95',
        'enterEnd' => 'translate-x-0 opacity-100 scale-100',
        'leave' => 'transform transition duration-200 ease-in',
        'leaveStart' => 'translate-x-0 opacity-100 scale-100',
        'leaveEnd' => 'translate-x-full opacity-0 scale-90'
    ]
};

// ✨ GLITTER INTENSITY CONFIGURATIONS
$glitterConfig = match ($glitterIntensity) {
    'none' => ['sparkles' => 0, 'shine' => false],
    'low' => ['sparkles' => 2, 'shine' => false],
    'medium' => ['sparkles' => 4, 'shine' => true],
    'high' => ['sparkles' => 6, 'shine' => true],
    'maximum' => ['sparkles' => 8, 'shine' => true],
    default => ['sparkles' => 4, 'shine' => true]
};
@endphp

<div {{ $attributes->class($classes) }}
     x-data="{ 
         toasts: [],
         nextId: 1,
         maxToasts: @js($maxToasts),
         storageKey: 'phoenix_toasts',
         isNavigating: false,
         navigationTimeout: null,
         
         init() {
             // ✨ RESTORE TOASTS FROM SESSION STORAGE
             this.loadPersistedToasts();
             
             // 🎯 ULTRA-SMART NAVIGATION DETECTION - Pure Alpine!
             this.$nextTick(() => {
                 // History API interception using Alpine's reactive approach
                 this.interceptHistoryAPI();
             });
         },
         
         interceptHistoryAPI() {
             const self = this;
             const originalPushState = history.pushState;
             const originalReplaceState = history.replaceState;
             
             history.pushState = function(...args) {
                 self.triggerSmartNavigationMode();
                 return originalPushState.apply(history, args);
             };
             
             history.replaceState = function(...args) {
                 self.triggerSmartNavigationMode(); 
                 return originalReplaceState.apply(history, args);
             };
         },
         
         triggerSmartNavigationMode() {
             this.isNavigating = true;
             // Alpine-managed timeout cleanup
             if (this.navigationTimeout) {
                 clearTimeout(this.navigationTimeout);
             }
             // Auto-exit smart mode after 2 seconds if no navigation completes
             this.navigationTimeout = setTimeout(() => {
                 this.isNavigating = false;
             }, 2000);
         },
         
         persistToasts() {
             if (this.toasts.length === 0) return;
             
             const persistData = {
                 toasts: this.toasts,
                 nextId: this.nextId,
                 timestamp: Date.now()
             };
             sessionStorage.setItem(this.storageKey, JSON.stringify(persistData));
         },
         
         loadPersistedToasts() {
             const stored = sessionStorage.getItem(this.storageKey);
             if (!stored) return;
             
             try {
                 const data = JSON.parse(stored);
                 const isRecent = Date.now() - data.timestamp < 30000;
                 
                 if (isRecent && data.toasts?.length > 0) {
                     this.toasts = data.toasts.map(toast => ({...toast, persisted: true}));
                     this.nextId = data.nextId || 1;
                     
                     // Alpine-managed auto-dismiss timers
                     this.setupAutoTimers();
                 }
                 
                 sessionStorage.removeItem(this.storageKey);
             } catch (e) {
                 // Silently handle errors
                 sessionStorage.removeItem(this.storageKey);
             }
         },
         
         setupAutoTimers() {
             if (@js($duration) <= 0) return;
             
             this.toasts.forEach(toast => {
                 setTimeout(() => this.removeToast(toast.id), @js($duration));
             });
         },
         
         addToast(msg, type, duration, persisted = false) {
             const toast = this.createToast(msg, type, persisted);
             
             // 🧠 SMART MODE: Auto-persist during navigation
             if (this.isNavigating && !persisted) {
                 this.toasts.push(toast);
                 this.persistToasts();
                 return; // Will show on next page
             }
             
             // Normal display logic
             this.manageToastLimit();
             this.toasts.push(toast);
             this.scheduleRemoval(toast, duration);
         },
         
         createToast(msg, type, persisted = false) {
             return {
                 id: this.nextId++,
                 message: msg,
                 type: type || 'success', 
                 persisted: persisted
             };
         },
         
         manageToastLimit() {
             while (this.toasts.length >= this.maxToasts) {
                 this.toasts.shift();
             }
         },
         
         scheduleRemoval(toast, customDuration = null) {
             const duration = customDuration || @js($duration);
             if (duration > 0) {
                 setTimeout(() => this.removeToast(toast.id), duration);
             }
         },
         
         removeToast(id) {
             this.toasts = this.toasts.filter(t => t.id !== id);
         }
     }"
     x-on:toast.window="addToast($event.detail.message, $event.detail.type, $event.detail.duration)"
     x-on:success.window="addToast($event.detail.message || $event.detail, 'success')"
     x-on:error.window="addToast($event.detail.message || $event.detail, 'error')"
     x-on:livewire--navigating.window="isNavigating = true; persistToasts()"
     x-on:livewire--navigated.window="isNavigating = false; $nextTick(() => loadPersistedToasts())"
     x-on:beforeunload.window="isNavigating = true; persistToasts()">

    <!-- Toast stack container -->
    <div class="flex flex-col gap-3 pointer-events-none"
         :class="{
             'items-start': @js(str_contains($position, 'left')),
             'items-center': @js(str_contains($position, 'center')),
             'items-end': @js(str_contains($position, 'right')),
             'flex-col-reverse': @js(str_starts_with($position, 'bottom'))
         }">
        
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition:enter="@js($animationConfig['enter'])"
                 x-transition:enter-start="@js($animationConfig['enterStart'])"
                 x-transition:enter-end="@js($animationConfig['enterEnd'])"
                 x-transition:leave="@js($animationConfig['leave'])"
                 x-transition:leave-start="@js($animationConfig['leaveStart'])"
                 x-transition:leave-end="@js($animationConfig['leaveEnd'])"
                 class="relative max-w-sm w-full rounded-xl overflow-hidden pointer-events-auto {{ $themeConfig['container'] }}"
                 :class="{
                     '{{ $themeConfig['success'] }}': toast.type === 'success',
                     '{{ $themeConfig['error'] }}': toast.type === 'error',
                     '{{ $themeConfig['info'] }}': toast.type === 'info'
                 }">
                
                <!-- Colored top stripe -->
                <div class="h-1 w-full relative"
                     :class="{
                         'bg-gradient-to-r from-emerald-400 via-emerald-500 to-emerald-600': toast.type === 'success',
                         'bg-gradient-to-r from-rose-400 via-rose-500 to-rose-600': toast.type === 'error',
                         'bg-gradient-to-r from-blue-400 via-blue-500 to-blue-600': toast.type === 'info'
                     }">
                    
                    {{-- ✨ DYNAMIC SPARKLES - Intensity Based --}}
                    @if ($glitterConfig['sparkles'] > 0)
                        <div class="absolute inset-0 overflow-hidden">
                            @for ($i = 0; $i < $glitterConfig['sparkles']; $i++)
                                @php
                                    $positions = ['left-6', 'right-8', 'left-12', 'right-4', 'left-3', 'right-12', 'left-20', 'right-2'];
                                    $delays = ['0s', '0.3s', '0.6s', '0.9s', '1.2s', '1.5s', '1.8s', '2.1s'];
                                    $pos = $positions[$i % count($positions)];
                                    $delay = $delays[$i % count($delays)];
                                @endphp
                                <div class="absolute top-0 {{ $pos }} w-2 h-2 -mt-0.5 rounded-full animate-ping"
                                     style="animation-delay: {{ $delay }}"
                                     :class="{
                                         'bg-emerald-300': toast.type === 'success',
                                         'bg-rose-300': toast.type === 'error',
                                         'bg-blue-300': toast.type === 'info'
                                     }"></div>
                            @endfor
                        </div>
                    @endif
                    
                    {{-- ✨ DYNAMIC SHINE EFFECT --}}
                    @if ($glitterConfig['shine'])
                        <div class="absolute inset-0 -skew-x-12 bg-gradient-to-r from-transparent via-white/60 to-transparent translate-x-[-100%] animate-[shine_2s_ease-in-out_infinite]"></div>
                    @endif
                </div>

                <!-- Content -->
                <div class="relative p-4 bg-white">
                    <div class="flex items-center gap-3">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-sm font-bold"
                                 :class="{
                                     'bg-emerald-100 text-emerald-700': toast.type === 'success',
                                     'bg-rose-100 text-rose-700': toast.type === 'error',
                                     'bg-blue-100 text-blue-700': toast.type === 'info'
                                 }">
                                <span x-show="toast.type === 'success'">✓</span>
                                <span x-show="toast.type === 'error'">✕</span>
                                <span x-show="toast.type === 'info'">i</span>
                            </div>
                        </div>
                        
                        <!-- Message -->
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900" x-text="toast.message"></p>
                        </div>
                        
                        <!-- Close Button -->
                        <button x-on:click="removeToast(toast.id)" 
                                class="flex-shrink-0 w-6 h-6 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 text-xs font-bold transition-colors duration-150">
                            ✕
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
    
    <style>
        @keyframes shine {
            0% { transform: translateX(-100%) skewX(-12deg); }
            100% { transform: translateX(200%) skewX(-12deg); }
        }
    </style>
</div>