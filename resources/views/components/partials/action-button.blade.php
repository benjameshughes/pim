@php
    $label = $action['label'] ?? 'Action';
    $variant = $action['variant'] ?? 'outline';
    $size = $action['size'] ?? 'base';
    $icon = $action['icon'] ?? null;
    $actionType = $action['type'] ?? 'button';
    
    // Build attributes string
    $attrs = "variant=\"{$variant}\" size=\"{$size}\"";
    if ($icon) $attrs .= " icon=\"{$icon}\"";
    
    if ($actionType === 'link') {
        $href = $action['href'] ?? '#';
        $attrs .= " href=\"{$href}\"";
        if (isset($action['wire:navigate']) && $action['wire:navigate']) {
            $attrs .= " wire:navigate";
        }
    } else {
        if (isset($action['wire:click'])) {
            $wireClick = $action['wire:click'];
            $attrs .= " wire:click=\"{$wireClick}\"";
        }
    }
@endphp

<flux:button {!! $attrs !!}>{{ $label }}</flux:button>