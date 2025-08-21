@props(['accountId'])

{{-- Loading States --}}
<div wire:loading wire:target="syncToMarketplace({{ $accountId }})" 
     class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
    <flux:icon name="arrow-path" class="w-5 h-5 animate-spin text-blue-600" />
</div>

<div wire:loading wire:target="updateMarketplaceListing({{ $accountId }})" 
     class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
    <flux:icon name="arrow-path" class="w-5 h-5 animate-spin text-green-600" />
</div>

<div wire:loading wire:target="unlinkFromMarketplace({{ $accountId }})" 
     class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center rounded-lg">
    <flux:icon name="arrow-path" class="w-5 h-5 animate-spin text-red-600" />
</div>