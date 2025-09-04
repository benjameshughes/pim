<x-layouts.app>
    <div class="min-h-screen">
        {{-- Header --}}
        <div class="bg-white dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="py-6 md:py-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                Images
                            </h1>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">
                                Manage your product images and media library
                            </p>
                        </div>
                        <div>
                            <flux:modal.trigger name="upload-modal">
                                <flux:button variant="primary" icon="plus">
                                    Upload Images
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <livewire:images.image-library />
        </div>
    </div>
</x-layouts.app>