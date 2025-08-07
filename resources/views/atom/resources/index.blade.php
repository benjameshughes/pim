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

    {{-- Resource Table (Magic!) --}}
    {{ $this->table }}

    {{-- Simplified Pure Livewire Notifications --}}
    <x-notifications :notifications="$this->getNotifications()" />
</div>