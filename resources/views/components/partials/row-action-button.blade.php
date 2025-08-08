@php
    $actionType = $action['type'] ?? 'button';
    $label = $action['label'] ?? 'Action';
@endphp

@if($actionType === 'link')
    <flux:button 
        href="{{ $action['href']($item) }}"
        variant="ghost"
        size="sm"
        icon="{{ $action['icon'] ?? 'eye' }}"
        aria-label="{{ $label }}"
    />
@else
    <flux:button 
        variant="ghost"
        size="sm"
        icon="{{ $action['icon'] ?? 'pencil' }}"
        aria-label="{{ $label }}"
    />
@endif