<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-flux-appearance>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-zinc-900">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <!-- Logo -->
            <div class="mb-8">
                <a href="/" class="flex items-center space-x-2">
                    <x-app-logo class="w-12 h-12" />
                    <span class="text-xl font-bold text-gray-900 dark:text-white">
                        Products
                    </span>
                </a>
            </div>

            <!-- Main Content -->
            <div class="w-full sm:max-w-md">
                {{ $slot }}
            </div>
        </div>

        {{-- Toast notifications --}}
        <flux:toast 
            position="bottom-right" 
            animation="slide" 
            :duration="5000" 
            :max-toasts="3"
            x-on:notify.window="addToast($event.detail.message, $event.detail.type, $event.detail.duration)"
        />

        @fluxScripts
        @livewireScripts
    </body>
</html>