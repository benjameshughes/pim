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

    {{-- Edit Form Content --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        @if($record)
            <form wire:submit.prevent="save">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @foreach($record->getAttributes() as $key => $value)
                        @if(!in_array($key, ['id', 'created_at', 'updated_at']))
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    {{ ucfirst(str_replace('_', ' ', $key)) }}
                                </label>
                                <input
                                    type="text"
                                    wire:model="record.{{ $key }}"
                                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    value="{{ $value }}"
                                >
                            </div>
                        @endif
                    @endforeach
                </div>
                
                <div class="mt-6 flex gap-3">
                    <flux:button type="submit" variant="primary">
                        Save Changes
                    </flux:button>
                    <flux:button href="{{ $this->resource::getUrl('view', ['record' => $record]) }}" variant="ghost">
                        Cancel
                    </flux:button>
                </div>
            </form>
        @endif
    </div>

    {{-- Atom Notifications --}}
    <x-notifications :notifications="$this->getNotifications()" />
</div>