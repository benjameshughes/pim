<x-layouts.app>
    <x-slot:title>Appearance Settings</x-slot:title>
    
    <div class="container max-w-4xl mx-auto px-4 py-8">
        <div class="space-y-8">
            
            {{-- Page Header --}}
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                            🎨 Appearance Settings
                        </h1>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            Customize your interface theme and appearance
                        </p>
                    </div>
                </div>
            </div>

            {{-- Settings Navigation Tabs --}}
            <nav class="flex space-x-8" aria-label="Settings">
                <a href="{{ route('settings.profile') }}" 
                   class="border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    Profile
                </a>
                <a href="{{ route('settings.password') }}" 
                   class="border-b-2 border-transparent py-2 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                    Password
                </a>
                <a href="{{ route('settings.appearance') }}" 
                   class="border-b-2 border-primary-500 py-2 px-1 text-sm font-medium text-primary-600">
                    Appearance
                </a>
            </nav>

            {{-- Appearance Settings Component --}}
            <livewire:settings.appearance />
            
        </div>
    </div>
</x-layouts.app>