<div 
    class="w-full mx-auto p-6 min-h-screen"
    x-data="{ init() { Alpine.store('productWizard', productWizardStore()) } }"
    @keydown.window="$store.productWizard.handleKeyboardNavigation($event)"
    x-init="init()"
>
    {{-- Header --}}
    <div class="max-w-7xl mx-auto mb-8">
        <div class="flex items-center justify-between">
            <h1 class="text-4xl font-bold text-gray-900 dark:text-white">
                {{ $isEditMode ? 'Edit Product' : 'Create Product' }}
            </h1>
            
            {{-- Draft Status --}}
            @if($autoSave && $lastSaved)
                <div class="flex items-center gap-3 bg-green-50 dark:bg-green-900/20 px-4 py-2 rounded-lg border border-green-200 dark:border-green-800">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium text-green-800 dark:text-green-200">
                            Auto-saved {{ $lastSaved }}
                        </span>
                    </div>
                    <flux:button wire:click="clearDraft" size="xs" variant="ghost" class="text-green-700 hover:text-green-900">
                        <flux:icon name="trash-2" class="h-3 w-3" />
                    </flux:button>
                </div>
            @endif
        </div>
        
        {{-- Keyboard Shortcuts Button --}}
        <div class="mt-2 flex justify-end">
            <flux:button 
                @click="$dispatch('open-modal', 'keyboard-shortcuts')" 
                size="sm" 
                variant="ghost"
                class="flex items-center gap-2"
            >
                <flux:icon name="keyboard" class="h-4 w-4" />
                Shortcuts
            </flux:button>
        </div>
        
        {{-- Keyboard Shortcuts Modal --}}
        <flux:modal name="keyboard-shortcuts" class="md:w-96">
            <div class="space-y-6">
                <flux:heading size="lg">Keyboard Shortcuts</flux:heading>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-sm font-medium">Next Step</span>
                        <kbd class="px-2 py-1 bg-white dark:bg-gray-700 rounded shadow text-xs">⌘/Ctrl + →</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-sm font-medium">Previous Step</span>
                        <kbd class="px-2 py-1 bg-white dark:bg-gray-700 rounded shadow text-xs">⌘/Ctrl + ←</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-sm font-medium">Save Product</span>
                        <kbd class="px-2 py-1 bg-white dark:bg-gray-700 rounded shadow text-xs">⌘/Ctrl + S</kbd>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <span class="text-sm font-medium">Clear Draft</span>
                        <kbd class="px-2 py-1 bg-white dark:bg-gray-700 rounded shadow text-xs">Esc</kbd>
                    </div>
                    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                        <p class="text-sm text-blue-700 dark:text-blue-300">
                            <flux:icon name="sparkles" class="h-4 w-4 inline mr-1" />
                            All shortcuts work while typing in form fields!
                        </p>
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <flux:button variant="outline">
                        Got it
                    </flux:button>
                </div>
            </div>
        </flux:modal>

    </div>

    {{-- Step Content (slot) --}}
    @if(isset($slot))
        {{ $slot }}
    @else
        @yield('step-content')
    @endif

    {{-- Navigation --}}
    <div class="max-w-7xl mx-auto mt-12">
        <div class="flex justify-between items-center">
            {{-- Previous Button --}}
            <div>
                @if($currentStep > 1)
                    <flux:button wire:click="previousStep" variant="outline" tabindex="97" class="flex items-center gap-2">
                        <flux:icon name="arrow-left" class="h-4 w-4" />
                        Previous
                    </flux:button>
                @endif
            </div>
            
            {{-- Progress --}}
            <div class="flex items-center space-x-4">
                @php
                    $stepColors = [
                        1 => 'bg-blue-600',
                        2 => 'bg-purple-600', 
                        3 => 'bg-green-600',
                        4 => 'bg-orange-600'
                    ];
                    $stepIcons = [
                        1 => 'info',
                        2 => 'grid-3x3',
                        3 => 'image', 
                        4 => 'pound-sterling'
                    ];
                @endphp
                
                @for($i = 1; $i <= 4; $i++)
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg transition-all duration-300
                            {{ $currentStep >= $i 
                                ? $stepColors[$i] . ' text-white' 
                                : 'bg-gray-200 dark:bg-gray-600 text-gray-500' 
                            }}">
                            <flux:icon name="{{ $stepIcons[$i] }}" class="h-4 w-4" />
                        </div>
                        @if($i < 4)
                            <div class="w-12 h-0.5 mx-2 transition-all duration-500
                                {{ $currentStep > $i ? $stepColors[$i] : 'bg-gray-200 dark:bg-gray-600' }}">
                            </div>
                        @endif
                    </div>
                @endfor
            </div>
            
            {{-- Next/Save Button --}}
            <div class="flex items-center gap-4">
                @if($currentStep < 4)
                    <flux:button wire:click="nextStep" tabindex="98" class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700">
                        Next Step
                        <flux:icon name="arrow-right" class="h-4 w-4" />
                    </flux:button>
                @else
                    <flux:button wire:click="saveProduct" :disabled="$isSaving" tabindex="99" class="flex items-center gap-2 bg-green-600 hover:bg-green-700">
                        <flux:icon name="check" class="h-4 w-4" />
                        {{ $isSaving ? 'Saving...' : 'Save Product' }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Alpine.js ProductWizard Store Script --}}
    <script>
        function productWizardStore() {
            return {
                handleKeyboardNavigation(event) {
                    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
                    const cmdKey = isMac ? event.metaKey : event.ctrlKey;
                    const isFormElement = ['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName);
                    
                    // CMD/Ctrl + Right Arrow: Next Step
                    if (cmdKey && event.key === 'ArrowRight') {
                        event.preventDefault();
                        event.stopPropagation();
                        Livewire.dispatch('wizard-next-step');
                        return;
                    }
                    
                    // CMD/Ctrl + Left Arrow: Previous Step  
                    else if (cmdKey && event.key === 'ArrowLeft') {
                        event.preventDefault();
                        event.stopPropagation();
                        Livewire.dispatch('wizard-previous-step');
                        return;
                    }
                    
                    // CMD/Ctrl + S: Save Product
                    else if (cmdKey && event.key === 's') {
                        event.preventDefault();
                        event.stopPropagation();
                        Livewire.dispatch('wizard-save-product');
                        return;
                    }
                    
                    // ESC: Clear Draft
                    else if (event.key === 'Escape') {
                        if (isFormElement && event.target.hasAttribute('x-model')) {
                            return;
                        }
                        event.preventDefault();
                        event.stopPropagation();
                        if (confirm('Are you sure you want to clear the draft?')) {
                            Livewire.dispatch('wizard-clear-draft');
                        }
                    }
                }
            }
        }
    </script>
</div>