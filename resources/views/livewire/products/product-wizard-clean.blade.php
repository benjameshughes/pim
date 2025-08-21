<div class="p-4 space-y-4">
    {{-- Compact Header with Steps & Progress --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
        {{-- Title Row --}}
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ $this->isEditMode ? 'Edit Product' : 'Create Product' }}
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $this->isEditMode ? 'Update your product details' : 'Follow the steps to create your product' }}
                </p>
            </div>
            
            {{-- Draft Status --}}
            @if(!$this->isEditMode && auth()->check())
                <div class="flex items-center gap-2">
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1">
                        <flux:icon name="cloud" class="w-3 h-3" />
                        {{ $this->autoSaveStatus }}
                    </div>
                    @if($this->draftStatus['exists'])
                        <flux:button wire:click="clearDraft" variant="ghost" size="xs" icon="trash">
                            Clear
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Compact Steps & Progress Row --}}
        <div class="flex items-center justify-between">
            {{-- Step Indicators --}}
            <div class="flex items-center gap-2">
                @foreach($this->stepNames as $stepNum => $stepName)
                    <button 
                        wire:click="goToStep({{ $stepNum }})"
                        @class([
                            'flex items-center gap-2 text-sm transition-colors',
                            'text-blue-600 dark:text-blue-400 font-medium' => $currentStep === $stepNum,
                            'text-green-600 dark:text-green-400' => in_array($stepNum, $completedSteps) && $currentStep !== $stepNum,
                            'text-gray-400 dark:text-gray-500' => !in_array($stepNum, $completedSteps) && $currentStep !== $stepNum,
                        ])
                        @if(!$this->canProceedToStep($stepNum)) disabled @endif
                    >
                        <div @class([
                            'w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium transition-colors',
                            'bg-blue-600 text-white' => $currentStep === $stepNum,
                            'bg-green-100 text-green-600 dark:bg-green-900/50 dark:text-green-400' => in_array($stepNum, $completedSteps) && $currentStep !== $stepNum,
                            'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => !in_array($stepNum, $completedSteps) && $currentStep !== $stepNum,
                        ])>
                            @if(in_array($stepNum, $completedSteps) && $currentStep !== $stepNum)
                                <flux:icon name="check" class="w-3 h-3" />
                            @else
                                {{ $stepNum }}
                            @endif
                        </div>
                        <span class="hidden sm:inline">{{ $stepName }}</span>
                    </button>
                    
                    {{-- Subtle Arrow Between Steps --}}
                    @if(!$loop->last)
                        <flux:icon name="chevron-right" class="w-3 h-3 text-gray-300 dark:text-gray-600 mx-1" />
                    @endif
                @endforeach
            </div>
            
            {{-- Compact Progress --}}
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                    {{ number_format($this->progressPercentage, 0) }}%
                </span>
                <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" 
                         style="width: {{ $this->progressPercentage }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Current Step Component --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        @livewire($this->currentStepComponent, [
            'stepData' => $wizardData[$currentStep === 1 ? 'product_info' : ($currentStep === 2 ? 'variants' : ($currentStep === 3 ? 'images' : 'pricing'))],
            'allStepData' => [
                1 => $wizardData['product_info'] ?? [],
                2 => $wizardData['variants'] ?? [],
                3 => $wizardData['images'] ?? [],
                4 => $wizardData['pricing'] ?? [],
            ],
            'isActive' => true,
            'currentStep' => $currentStep,
            'isEditMode' => $this->isEditMode,
            'product' => $this->product,
        ], key("step-{$currentStep}"))
    </div>

    {{-- Compact Navigation --}}
    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800/50 rounded-lg px-4 py-3">
        <flux:button 
            wire:click="previousStep"
            variant="ghost"
            size="sm"
            icon="arrow-left"
            disabled="{{ $currentStep <= 1 }}"
        >
            Previous
        </flux:button>

        <div class="text-xs text-gray-500 dark:text-gray-400 font-medium">
            Step {{ $currentStep }} of 4
        </div>

        @if($currentStep < 4)
            <flux:button 
                wire:click="nextStep"
                variant="primary"
                size="sm"
                icon="arrow-right"
            >
                Next Step
            </flux:button>
        @else
            <flux:button 
                wire:click="saveProduct"
                variant="primary"
                size="sm"
                icon="{{ $this->isEditMode ? 'save' : 'plus' }}"
                wire:loading.attr="disabled"
                class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700"
            >
                <span wire:loading.remove wire:target="saveProduct">
                    {{ $this->isEditMode ? 'Update Product' : 'Create Product' }}
                </span>
                <span wire:loading wire:target="saveProduct" class="flex items-center gap-2">
                    <flux:icon name="loader" class="w-3 h-3 animate-spin" />
                    {{ $this->isEditMode ? 'Updating...' : 'Creating...' }}
                </span>
            </flux:button>
        @endif
    </div>
</div>