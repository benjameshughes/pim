{{-- Complete Data Row Example using Pure Tailwind --}}

@props(['item', 'selected' => false])

<div class="group relative flex items-center gap-4 py-4 px-3 -mx-3 rounded-lg transition-all duration-150
           {{ $selected ? 'bg-blue-25 dark:bg-blue-950/30 border-l-4 border-blue-500 dark:border-blue-400' : '' }}
           hover:bg-zinc-25 dark:hover:bg-zinc-925 
           hover:shadow-sm dark:hover:shadow-zinc-900/50
           focus-within:bg-zinc-50 dark:focus-within:bg-zinc-900
           focus-within:ring-1 focus-within:ring-zinc-200 dark:focus-within:ring-zinc-700">
    
    {{-- Selection Checkbox --}}
    <div class="flex items-center justify-center w-5 h-5 shrink-0">
        <input 
            type="checkbox" 
            {{ $selected ? 'checked' : '' }}
            class="w-4 h-4 rounded border-zinc-300 dark:border-zinc-600 
                   bg-white dark:bg-zinc-800 
                   text-blue-600 dark:text-blue-500
                   focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20
                   focus:border-blue-500 dark:focus:border-blue-400
                   transition-all duration-150"
        />
    </div>

    {{-- Primary Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3">
            {{-- Product Name --}}
            <div class="min-w-0 flex-1">
                <h4 class="text-sm font-medium text-zinc-900 dark:text-zinc-50 truncate">
                    Premium Wireless Headphones - Black Edition
                </h4>
                <p class="text-xs text-zinc-500 dark:text-zinc-500 truncate">
                    SKU: WH-001-BLK | Updated 2 hours ago
                </p>
            </div>

            {{-- Status Badge --}}
            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium
                       bg-emerald-50 dark:bg-emerald-950/30 
                       text-emerald-700 dark:text-emerald-400 
                       border border-emerald-200 dark:border-emerald-800">
                <flux:icon name="check-circle" class="w-3 h-3 mr-1 shrink-0" />
                Active
            </span>

            {{-- Price --}}
            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                £299.99
            </div>

            {{-- Stock Count --}}
            <div class="text-sm text-zinc-600 dark:text-zinc-400 min-w-16 text-right">
                <span class="font-medium">142</span> in stock
            </div>
        </div>
    </div>

    {{-- Actions (hidden by default, shown on hover) --}}
    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
        <button class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                       text-zinc-600 dark:text-zinc-400 
                       hover:text-zinc-900 dark:hover:text-zinc-100
                       hover:bg-zinc-100 dark:hover:bg-zinc-800
                       focus:outline-none focus:ring-2 focus:ring-blue-500/20 dark:focus:ring-blue-400/20
                       transition-all duration-150">
            <flux:icon name="pencil" class="w-4 h-4 mr-1" />
            Edit
        </button>
        
        <button class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                       text-zinc-600 dark:text-zinc-400 
                       hover:text-red-700 dark:hover:text-red-400
                       hover:bg-red-50 dark:hover:bg-red-950/30
                       focus:outline-none focus:ring-2 focus:ring-red-500/20 dark:focus:ring-red-400/20
                       transition-all duration-150">
            <flux:icon name="trash" class="w-4 h-4 mr-1" />
            Delete
        </button>
    </div>
</div>