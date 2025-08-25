<x-layouts.app>
    <div class="px-6 py-6 mx-auto max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('images.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
                    <flux:icon name="arrow-left" class="h-4 w-4" />
                    <span>Back to Library</span>
                </a>
            </div>
        </div>

        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                <flux:icon name="pencil" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                Edit Image
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Update image metadata, organize with folders and tags
            </p>
        </div>

        <div class="space-y-8">
            {{-- Core Image Editing --}}
            <livewire:images.image-edit-core :image="$image" />
            
            {{-- Product Attachment (separate component) --}}
            <livewire:images.image-product-attachment :image="$image" />
        </div>
    </div>
</x-layouts.app>