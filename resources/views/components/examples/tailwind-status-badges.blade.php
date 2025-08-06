{{-- Status Badge Examples using Pure Tailwind --}}

@php
$statuses = [
    'active' => [
        'class' => 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
        'icon' => 'check-circle',
        'label' => 'Active'
    ],
    'pending' => [
        'class' => 'bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
        'icon' => 'clock',
        'label' => 'Pending'
    ],
    'disabled' => [
        'class' => 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-400 border-red-200 dark:border-red-800',
        'icon' => 'x-circle',
        'label' => 'Disabled'
    ],
    'draft' => [
        'class' => 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700',
        'icon' => 'document',
        'label' => 'Draft'
    ],
    'published' => [
        'class' => 'bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-400 border-blue-200 dark:border-blue-800',
        'icon' => 'globe-alt',
        'label' => 'Published'
    ],
];
@endphp

<div class="space-y-4 p-6 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700">
    <h3 class="text-lg font-medium text-zinc-900 dark:text-zinc-50">Status Badge Examples</h3>
    
    <div class="flex flex-wrap gap-2">
        @foreach($statuses as $key => $status)
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border transition-colors duration-150 {{ $status['class'] }}">
                <flux:icon name="{{ $status['icon'] }}" class="w-3 h-3 mr-1 shrink-0" />
                {{ $status['label'] }}
            </span>
        @endforeach
    </div>
    
    <div class="text-sm text-zinc-600 dark:text-zinc-400">
        <strong>Usage:</strong> These badges use pure Tailwind utilities with dark mode variants.
        No custom CSS properties required.
    </div>
</div>