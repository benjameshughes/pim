<div>
    {{-- Page Header --}}
    <div class="mb-6">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        
        {{-- Breadcrumbs --}}
        <x-navigation.breadcrumbs :breadcrumbs="$breadcrumbs" />
        
        {{-- Sub Navigation (if available) --}}
        @if(count($subNavigationItems) > 0)
            <x-navigation.sub-navigation :items="collect($subNavigationItems)" />
        @endif
    </div>

    {{-- Create Form Content --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form wire:submit.prevent="create">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Basic form fields for creating a new record --}}
                {{-- This is a generic template - specific resources can override this --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Name
                    </label>
                    <input
                        type="text"
                        wire:model="form.name"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Enter name..."
                        required
                    >
                    @error('form.name')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description
                    </label>
                    <textarea
                        wire:model="form.description"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        rows="3"
                        placeholder="Enter description..."
                    ></textarea>
                    @error('form.description')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Status
                    </label>
                    <select
                        wire:model="form.status"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option value="">Select status...</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="draft">Draft</option>
                    </select>
                    @error('form.status')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            
            <div class="mt-6 flex gap-3">
                <flux:button type="submit" variant="primary">
                    Create {{ $config['modelLabel'] ?? 'Record' }}
                </flux:button>
                <flux:button href="{{ $this->resource::getUrl('index') }}" variant="ghost">
                    Cancel
                </flux:button>
            </div>
        </form>
    </div>

    {{-- Atom Notifications --}}
    <x-notifications :notifications="$this->getNotifications()" />
</div>