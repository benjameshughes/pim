<x-layouts.app>
    <div class="min-h-screen">
        {{-- Header --}}
        <div class="bg-white dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-6 md:py-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                Digital Assets
                            </h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">
                                Manage your product images and media library
                            </p>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <flux:button 
                                variant="primary" 
                                icon="upload"
                                onclick="Livewire.dispatch('open-upload-modal')"
                                class="px-6 py-3"
                            >
                                Upload Images
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <livewire:dam.image-library key="library-{{ now()->timestamp }}" />
        </div>
    </div>
</x-layouts.app>