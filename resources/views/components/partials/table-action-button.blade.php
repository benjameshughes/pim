@php
    $label = $action['label'] ?? 'Action';
    $variant = $action['variant'] ?? 'outline';
    $size = $action['size'] ?? 'sm';
    $type = $action['type'] ?? 'button';
@endphp

@if($type === 'link')
    <flux:button 
        href="{{ $action['href'] ?? '#' }}" 
        variant="{{ $variant }}" 
        size="{{ $size }}"
    >
        {{ $label }}
    </flux:button>
@else
    <flux:button 
        variant="{{ $variant }}" 
        size="{{ $size }}"
    >
        {{ $label }}
    </flux:button>
@endif