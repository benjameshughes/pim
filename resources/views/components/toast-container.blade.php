{{-- Toast Container - ResourceManager Style Integration --}}
{{-- Alpine.js handles rendering, Livewire provides data --}}
<div 
    x-data="toastContainer(@js($config ?? []))"
    x-show="$store.toasts.hasToasts"
    class="fixed inset-0 pointer-events-none z-50"
>
    {{-- Render toast positions dynamically via Alpine store --}}
    <template x-for="position in ['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right']" :key="position">
        <div 
            x-show="$store.toasts.byPosition[position] && $store.toasts.byPosition[position].length > 0"
            :class="{
                'top-4 left-4': position === 'top-left',
                'top-4 left-1/2 transform -translate-x-1/2': position === 'top-center',
                'top-4 right-4': position === 'top-right',
                'bottom-4 left-4': position === 'bottom-left',
                'bottom-4 left-1/2 transform -translate-x-1/2': position === 'bottom-center',
                'bottom-4 right-4': position === 'bottom-right'
            }"
            class="absolute space-y-2 pointer-events-auto max-w-sm w-full"
        >
            {{-- Individual toasts for this position --}}
            <template x-for="toast in ($store.toasts.byPosition[position] || [])" :key="toast.id">
                <div
                    x-data="toastItem(toast)"
                    x-show="visible"
                    x-transition:enter="transform ease-out duration-300 transition"
                    x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                    x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    @mouseenter="pauseTimer()"
                    @mouseleave="resumeTimer()"
                    :class="{
                        'bg-green-50 border-green-200 dark:bg-green-900/30 dark:border-green-700': toast.type === 'success',
                        'bg-red-50 border-red-200 dark:bg-red-900/30 dark:border-red-700': toast.type === 'error',
                        'bg-yellow-50 border-yellow-200 dark:bg-yellow-900/30 dark:border-yellow-700': toast.type === 'warning',
                        'bg-blue-50 border-blue-200 dark:bg-blue-900/30 dark:border-blue-700': toast.type === 'info' || !toast.type
                    }"
                    class="flex items-start p-4 rounded-lg shadow-lg border backdrop-blur-sm"
                >
                    {{-- Toast Icon --}}
                    <div x-show="toast.icon" class="flex-shrink-0 mr-3">
                        <div 
                            :class="{
                                'text-green-500 dark:text-green-400': toast.type === 'success',
                                'text-red-500 dark:text-red-400': toast.type === 'error',
                                'text-yellow-500 dark:text-yellow-400': toast.type === 'warning',
                                'text-blue-500 dark:text-blue-400': toast.type === 'info' || !toast.type
                            }"
                            class="w-5 h-5"
                            x-html="$store.toasts.getToastIcon(toast.icon, toast.type)"
                        ></div>
                    </div>

                    {{-- Toast Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Toast Title --}}
                        <h3 
                            x-show="toast.title"
                            x-text="toast.title"
                            :class="{
                                'text-green-800 dark:text-green-200': toast.type === 'success',
                                'text-red-800 dark:text-red-200': toast.type === 'error',
                                'text-yellow-800 dark:text-yellow-200': toast.type === 'warning',
                                'text-blue-800 dark:text-blue-200': toast.type === 'info' || !toast.type
                            }"
                            class="text-sm font-medium"
                        ></h3>

                        {{-- Toast Body --}}
                        <div 
                            x-show="toast.body"
                            x-text="toast.body"
                            :class="{
                                'text-green-700 dark:text-green-300': toast.type === 'success',
                                'text-red-700 dark:text-red-300': toast.type === 'error',
                                'text-yellow-700 dark:text-yellow-300': toast.type === 'warning',
                                'text-blue-700 dark:text-blue-300': toast.type === 'info' || !toast.type
                            }"
                            class="mt-1 text-sm"
                        ></div>

                        {{-- Toast Actions --}}
                        <div x-show="toast.actions && toast.actions.length > 0" class="mt-3 flex space-x-2">
                            <template x-for="(action, actionIndex) in (toast.actions || [])" :key="actionIndex">
                                <button
                                    @click="executeAction(action.key || actionIndex)"
                                    x-text="action.label"
                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition-colors border border-gray-300 text-gray-700 hover:bg-gray-50"
                                ></button>
                            </template>
                        </div>
                    </div>

                    {{-- Close Button --}}
                    <div x-show="toast.closable !== false" class="flex-shrink-0 ml-3">
                        <button
                            @click="closeToast()"
                            :class="{
                                'text-green-500 hover:bg-green-100 focus:bg-green-100 dark:text-green-400 dark:hover:bg-green-800 dark:focus:bg-green-800': toast.type === 'success',
                                'text-red-500 hover:bg-red-100 focus:bg-red-100 dark:text-red-400 dark:hover:bg-red-800 dark:focus:bg-red-800': toast.type === 'error',
                                'text-yellow-500 hover:bg-yellow-100 focus:bg-yellow-100 dark:text-yellow-400 dark:hover:bg-yellow-800 dark:focus:bg-yellow-800': toast.type === 'warning',
                                'text-blue-500 hover:bg-blue-100 focus:bg-blue-100 dark:text-blue-400 dark:hover:bg-blue-800 dark:focus:bg-blue-800': toast.type === 'info' || !toast.type
                            }"
                            class="inline-flex rounded-md p-1.5 transition-colors focus:outline-none"
                        >
                            <span class="sr-only">Dismiss</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {{-- Progress Bar --}}
                    <div 
                        x-show="!toast.persistent && toast.duration > 0"
                        class="absolute bottom-0 left-0 right-0 h-1 bg-gray-200 dark:bg-gray-600 rounded-b-lg overflow-hidden"
                    >
                        <div 
                            :class="{
                                'bg-green-500': toast.type === 'success',
                                'bg-red-500': toast.type === 'error',
                                'bg-yellow-500': toast.type === 'warning',
                                'bg-blue-500': toast.type === 'info' || !toast.type
                            }"
                            class="h-full transition-all duration-75 ease-linear"
                            :style="`width: ${progressWidth}%`"
                        ></div>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>