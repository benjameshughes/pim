@props(['accountsCount'])

<div class="flex items-center justify-between">
    <h3 class="text-lg font-semibold text-gray-900">Marketplace Sync Status</h3>
    <flux:badge color="zinc" size="sm">
        {{ $accountsCount }} {{ Str::plural('Account', $accountsCount) }}
    </flux:badge>
</div>