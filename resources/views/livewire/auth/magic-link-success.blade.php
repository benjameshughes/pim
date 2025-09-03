<div class="bg-white dark:bg-zinc-800 shadow-lg rounded-xl p-8">
    <div class="space-y-6 text-center">
        <!-- Success Icon -->
        <div class="mx-auto w-20 h-20 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center">
            <flux:icon.check-circle class="w-12 h-12 text-green-600 dark:text-green-400" />
        </div>

        <!-- Success Message -->
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Welcome back!
            </h2>
            <p class="text-gray-600 dark:text-gray-400">
                You've been successfully signed in. 
                <br>
                Redirecting you to your dashboard...
            </p>
        </div>

        <!-- Loading Indicator -->
        @if($autoRedirect)
            <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
                <flux:icon.arrow-path class="w-4 h-4 animate-spin" />
                <span>Redirecting in a moment...</span>
            </div>
        @endif

        <!-- Manual Redirect Button -->
        <div class="pt-4">
            <flux:button
                wire:click="goToDashboard"
                variant="primary"
                size="lg"
                class="w-full"
            >
                <flux:icon.home class="w-4 h-4 mr-2" />
                Continue to Dashboard
            </flux:button>
        </div>
    </div>
</div>

@if($autoRedirect)
    <script>
        setTimeout(() => {
            @this.goToDashboard();
        }, 3000);
    </script>
@endif

