@props(['item'])

<div class="space-y-3">
    {{-- Last Sync --}}
    <div class="flex justify-between text-sm">
        <span class="text-gray-500">Last Sync:</span>
        <span class="font-medium text-gray-900">{{ $item->lastSync }}</span>
    </div>

    {{-- External ID (if exists) --}}
    @if($item->syncStatus?->external_product_id)
        <div class="flex justify-between text-sm">
            <span class="text-gray-500">External ID:</span>
            <span class="font-mono text-xs text-gray-700">
                {{ Str::limit($item->syncStatus->external_product_id, 20) }}
            </span>
        </div>
    @endif

    {{-- Error Message --}}
    @if($item->syncStatus?->error_message)
        <div class="text-sm">
            <span class="text-red-600 font-medium">Error:</span>
            <p class="text-red-700 text-xs mt-1">{{ $item->syncStatus->error_message }}</p>
        </div>
    @endif
</div>