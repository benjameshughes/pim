@php
    $variant = $action['variant'] ?? 'outline';
    $label = $action['label'] ?? 'Action';
    $key = $action['key'] ?? 'action';
@endphp

<flux:button 
    x-on:click="executeBulkAction('{{ $key }}', selectedItems)"
    variant="{{ $variant }}"
    size="sm"
>
    {{ $label }}
</flux:button>