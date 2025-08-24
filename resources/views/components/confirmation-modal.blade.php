{{-- Beautiful Native Dialog Confirmation Modal --}}
<dialog 
    id="confirmation-dialog"
    x-data="{
        show: false,
        title: 'Confirm Action',
        message: 'Are you sure?',
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        variant: 'danger',
        onConfirm: null,

        open(options) {
            this.title = options.title || 'Confirm Action'
            this.message = options.message || 'Are you sure?'
            this.confirmText = options.confirmText || 'Confirm'
            this.cancelText = options.cancelText || 'Cancel'  
            this.variant = options.variant || 'danger'
            this.onConfirm = options.onConfirm || null
            
            this.show = true
            $el.showModal()
            document.body.style.overflow = 'hidden'
        },

        close() {
            this.show = false
            $el.close()
            document.body.style.overflow = ''
            this.onConfirm = null
        },

        confirm() {
            if (this.onConfirm) {
                this.onConfirm()
            }
            this.close()
        }
    }"
    class="fixed inset-0 size-auto max-h-none max-w-none overflow-y-auto bg-transparent backdrop:bg-gray-500/75 backdrop:transition-opacity backdrop:duration-300"
    @keydown.escape="close()"
    @click.self="close()"
>
    {{-- Modal Container --}}
    <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div class="relative transform overflow-hidden rounded-xl bg-white dark:bg-zinc-900 text-left shadow-2xl ring-1 ring-black/5 dark:ring-white/10 transition-all sm:my-8 sm:w-full sm:max-w-lg">
            
            {{-- Modal Content --}}
            <div class="bg-white dark:bg-zinc-900 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    {{-- Icon --}}
                    <div 
                        class="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full sm:mx-0 sm:size-10"
                        :class="{
                            'bg-red-100 dark:bg-red-900/30': variant === 'danger',
                            'bg-amber-100 dark:bg-amber-900/30': variant === 'warning',  
                            'bg-blue-100 dark:bg-blue-900/30': variant === 'info'
                        }"
                    >
                        <flux:icon 
                            x-show="variant === 'danger'"
                            name="exclamation-triangle"
                            class="size-6 text-red-600 dark:text-red-400"
                        />
                        <flux:icon 
                            x-show="variant === 'warning'"  
                            name="exclamation-triangle"
                            class="size-6 text-amber-600 dark:text-amber-400"
                        />
                        <flux:icon 
                            x-show="variant === 'info'"
                            name="information-circle" 
                            class="size-6 text-blue-600 dark:text-blue-400"
                        />
                    </div>
                    
                    {{-- Title & Message --}}
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 
                            x-text="title"
                            class="text-lg font-semibold leading-6 text-gray-900 dark:text-white"
                        ></h3>
                        <div class="mt-2">
                            <p 
                                x-text="message"
                                class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line"
                            ></p>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Modal Actions --}}
            <div class="bg-gray-50 dark:bg-zinc-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                {{-- Confirm Button - Danger --}}
                <flux:button 
                    x-show="variant === 'danger'" 
                    @click="confirm()" 
                    variant="danger"
                >
                    <span x-text="confirmText"></span>
                </flux:button>

                {{-- Confirm Button - Warning (use primary since warning not available in free) --}}
                <flux:button 
                    x-show="variant === 'warning'" 
                    @click="confirm()" 
                    variant="primary"
                >
                    <span x-text="confirmText"></span>
                </flux:button>

                {{-- Confirm Button - Default --}}
                <flux:button 
                    x-show="variant !== 'danger' && variant !== 'warning'" 
                    @click="confirm()" 
                    variant="primary"
                >
                    <span x-text="confirmText"></span>
                </flux:button>
                
                {{-- Cancel Button --}}
                <flux:button
                    @click="close()" 
                    variant="ghost"
                >
                    <span x-text="cancelText"></span>
                </flux:button>
            </div>
        </div>
    </div>
</dialog>