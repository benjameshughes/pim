<div class="p-8 max-w-4xl mx-auto">
    <flux:heading size="xl">Toast Stacking Demo</flux:heading>
    
    <div class="mt-6 space-y-4">
        <flux:card>
            <flux:heading size="lg">Create Multiple Toasts</flux:heading>
            <div class="mt-4 grid grid-cols-2 gap-4">
                {{-- Quick Stack Test --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createMultipleToasts"
                    icon="layers"
                >
                    Stack 5 Toasts Quickly
                </flux:button>
                
                {{-- Different Positions --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createToastsInDifferentPositions"
                    icon="grid"
                >
                    Toasts in All Positions
                </flux:button>
                
                {{-- Different Types --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createDifferentTypes"
                    icon="sparkles"
                >
                    All Toast Types
                </flux:button>
                
                {{-- With Different Timers --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createWithDifferentTimers"
                    icon="clock"
                >
                    Different Durations
                </flux:button>
                
                {{-- Mixed Persistent --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createMixedPersistent"
                    icon="pin"
                >
                    Mix Persistent & Timed
                </flux:button>
                
                {{-- With Actions --}}
                <flux:button 
                    variant="primary" 
                    wire:click="createWithActions"
                    icon="cursor-click"
                >
                    Toasts with Actions
                </flux:button>
                
                {{-- Stress Test --}}
                <flux:button 
                    variant="danger" 
                    wire:click="stressTest"
                    icon="zap"
                >
                    Stress Test (20 toasts)
                </flux:button>
                
                {{-- Clear All --}}
                <flux:button 
                    variant="ghost" 
                    wire:click="clearAll"
                    icon="x"
                >
                    Clear All Toasts
                </flux:button>
            </div>
        </flux:card>
        
        <flux:card>
            <flux:heading size="lg">Position-Specific Tests</flux:heading>
            <div class="mt-4 grid grid-cols-3 gap-4">
                @foreach(['top-left', 'top-center', 'top-right', 'bottom-left', 'bottom-center', 'bottom-right'] as $position)
                    <flux:button 
                        size="sm"
                        variant="outline" 
                        wire:click="createInPosition('{{ $position }}')"
                    >
                        {{ ucwords(str_replace('-', ' ', $position)) }}
                    </flux:button>
                @endforeach
            </div>
        </flux:card>
        
        <flux:card>
            <flux:heading size="lg">Current Toast Count</flux:heading>
            <div class="mt-4">
                <flux:badge size="lg" variant="primary">
                    {{ $this->toastCount }} Active Toasts
                </flux:badge>
            </div>
        </flux:card>
    </div>
</div>