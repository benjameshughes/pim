@props([
    'status' => 'not_synced', // synced, not_synced, failed, pending
    'lastSyncedAt' => null,
    'marketplace' => 'Shopify',
    'size' => 'sm' // sm, md
])

@php
    $statusConfig = [
        'synced' => [
            'icon' => 'check-circle',
            'text' => 'Synced',
            'variant' => 'outline',
            'classes' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-300 dark:border-emerald-800'
        ],
        'not_synced' => [
            'icon' => 'clock',
            'text' => 'Not Synced',
            'variant' => 'outline', 
            'classes' => 'bg-slate-50 text-slate-700 border-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600'
        ],
        'failed' => [
            'icon' => 'x-circle',
            'text' => 'Failed',
            'variant' => 'outline',
            'classes' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-300 dark:border-red-800'  
        ],
        'pending' => [
            'icon' => 'arrow-path',
            'text' => 'Pending',
            'variant' => 'outline',
            'classes' => 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-800'
        ]
    ];
    
    $config = $statusConfig[$status] ?? $statusConfig['not_synced'];
    $sizeClasses = $size === 'md' ? 'text-sm px-3 py-1' : 'text-xs px-2 py-1';
    $iconSize = $size === 'md' ? 'w-4 h-4' : 'w-3 h-3';
@endphp

<div class="flex items-center gap-2">
    <flux:badge 
        variant="{{ $config['variant'] }}" 
        class="{{ $config['classes'] }} {{ $sizeClasses }}"
        {{ $attributes }}
    >
        <flux:icon name="{{ $config['icon'] }}" class="{{ $iconSize }} mr-1" />
        {{ $marketplace }}: {{ $config['text'] }}
    </flux:badge>
    
    @if($lastSyncedAt && $status === 'synced')
        <span class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ $lastSyncedAt->diffForHumans() }}
        </span>
    @endif
</div>