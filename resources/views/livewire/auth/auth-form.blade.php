<div class="bg-white dark:bg-zinc-800 shadow-lg rounded-xl p-8">
    @if(!$emailSent)
        {{-- Email Input Form --}}
        <div class="space-y-6">
            <div class="text-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                    Welcome
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Enter your email address to receive a magic link
                </p>
            </div>

            <form wire:submit="sendMagicLink" class="space-y-4">
                <div>
                    <flux:input
                        wire:model.live.debounce.300ms="email"
                        type="email"
                        placeholder="your.email@example.com"
                        icon="at-sign"
                        :disabled="$isLoading"
                        class="text-center"
                    />
                    @error('email') 
                        <div class="mt-2 text-sm text-red-600 dark:text-red-400">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <flux:button
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :disabled="$isLoading || empty($email)"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>
                        <flux:icon.paper-airplane class="w-4 h-4 mr-2" />
                        Send Magic Link
                    </span>
                    <span wire:loading>
                        <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                        Sending...
                    </span>
                </flux:button>
            </form>

            <div class="text-center">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Only authorized email addresses can access this system.
                    <br>
                    By continuing, you agree to our terms of service.
                </p>
            </div>
        </div>
    @else
        {{-- Email Sent Confirmation --}}
        <div class="space-y-6 text-center">
            <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
                <flux:icon.envelope class="w-8 h-8 text-green-600 dark:text-green-400" />
            </div>

            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Check your email
                </h2>
                <p class="text-gray-600 dark:text-gray-400 mb-1">
                    We've sent a magic link to:
                </p>
                <p class="font-medium text-gray-900 dark:text-white">
                    {{ $email }}
                </p>
            </div>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start space-x-3">
                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                    <div class="text-sm text-blue-800 dark:text-blue-200">
                        <p class="font-medium mb-1">Click the link in your email to sign in</p>
                        <p>The link will expire in 30 minutes for security.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <flux:button
                    wire:click="requestNewLink"
                    variant="outline"
                    size="sm"
                    class="w-full"
                    :disabled="$isLoading"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="requestNewLink">
                        <flux:icon.arrow-path class="w-4 h-4 mr-2" />
                        Send another link
                    </span>
                    <span wire:loading wire:target="requestNewLink">
                        <flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                        Sending...
                    </span>
                </flux:button>

                <flux:button
                    wire:click="backToForm"
                    variant="ghost"
                    size="sm"
                    class="w-full"
                >
                    <flux:icon.arrow-left class="w-4 h-4 mr-2" />
                    Use different email
                </flux:button>
            </div>
        </div>
    @endif
</div>