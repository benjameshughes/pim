@props(['item'])

<div class="flex flex-col items-end space-y-1">
    @if($item->isLinked)
        <flux:badge color="blue" size="sm">
            <flux:icon name="link" class="w-3 h-3 mr-1" />
            Linked
        </flux:badge>
    @endif
    
    @if($item->syncStatus)
        <flux:badge 
            :color="match($item->status) {
                'synced' => 'green',
                'pending' => 'yellow',
                'failed' => 'red',
                'out_of_sync' => 'orange',
                default => 'gray'
            }"
            size="sm">
            {{ ucfirst(str_replace('_', ' ', $item->status)) }}
        </flux:badge>
    @else
        <flux:badge color="gray" size="sm">Not Synced</flux:badge>
    @endif
</div>