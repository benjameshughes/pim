@props([
    'primary' => null,
    'secondary' => [],
    'align' => 'right',
    'gap' => 'normal',
    'size' => 'normal',
])

<div class="flex items-center justify-end gap-3">
    
    @if($primary)
        <div class="flex-shrink-0">
            @if(is_array($primary))
                <flux:button 
                    :href="$primary['href'] ?? null"
                    :icon="$primary['icon'] ?? 'plus'" 
                    :variant="$primary['variant'] ?? 'filled'"
                    size="base"
                >
                    {{ $primary['label'] ?? 'Action' }}
                </flux:button>
            @else
                {{ $primary }}
            @endif
        </div>
    @endif
    
    @if(!empty($secondary))
        <div class="flex items-center gap-2">
            @foreach($secondary as $action)
                @if(is_array($action))
                    <flux:button 
                        :href="$action['href'] ?? null"
                        :icon="$action['icon'] ?? null" 
                        :variant="$action['variant'] ?? 'outline'"
                        size="base"
                    >
                        {{ $action['label'] ?? 'Action' }}
                    </flux:button>
                @endif
            @endforeach
        </div>
    @endif
    
</div>