@props([
    'title' => null,
    'description' => null,
    'columns' => 1,
    'gap' => 'gap-6',
    'responsive' => true,
    'progress' => null,
    'steps' => null,
    'currentStep' => null,
    'submitButton' => true,
    'submitText' => 'Submit',
    'cancelButton' => false,
    'cancelText' => 'Cancel',
    'cancelHref' => null,
    'method' => 'POST',
    'action' => null,
    'wireSubmit' => null,
    'validationSummary' => false,
])

<div class="space-y-6" x-data="formLayout({ progress: @js($progress), steps: @js($steps), currentStep: @js($currentStep) })">
    {{-- Form Header --}}
    @if($title || $description || $progress || $steps)
        <div class="space-y-4">
            {{-- Title and Description --}}
            @if($title || $description)
                <div>
                    @if($title)
                        <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-100">
                            {{ $title }}
                        </flux:heading>
                    @endif
                    @if($description)
                        <flux:subheading class="text-zinc-600 dark:text-zinc-400 mt-2">
                            {{ $description }}
                        </flux:subheading>
                    @endif
                </div>
            @endif
            
            {{-- Progress Bar --}}
            @if($progress && $progress > 0)
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-2">
                    <div 
                        class="bg-indigo-600 h-2 rounded-full transition-all duration-300 ease-out"
                        x-bind:style="`width: ${progress}%`"
                    ></div>
                </div>
                <div class="text-right text-sm text-zinc-500">
                    <span x-text="Math.round(progress)"></span>% complete
                </div>
            @endif
            
            {{-- Step Indicators --}}
            @if($steps && $currentStep)
                <div class="flex items-center justify-between mb-6">
                    @foreach($steps as $index => $step)
                        @php $stepNumber = $index + 1; @endphp
                        <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium transition-all duration-200
                                    @if($stepNumber < $currentStep) 
                                        bg-indigo-600 text-white
                                    @elseif($stepNumber == $currentStep) 
                                        bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 ring-2 ring-indigo-600
                                    @else 
                                        bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400
                                    @endif">
                                    @if($stepNumber < $currentStep)
                                        <flux:icon name="check" class="h-5 w-5" />
                                    @else
                                        {{ $stepNumber }}
                                    @endif
                                </div>
                                <span class="text-xs mt-2 text-center text-zinc-600 dark:text-zinc-400 max-w-20">
                                    {{ $step['label'] ?? "Step {$stepNumber}" }}
                                </span>
                            </div>
                            @if(!$loop->last)
                                <div class="flex-1 h-0.5 bg-zinc-200 dark:bg-zinc-700 mx-4">
                                    <div class="h-full bg-indigo-600 transition-all duration-500" 
                                         style="width: {{ $stepNumber < $currentStep ? '100' : '0' }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    
    {{-- Validation Summary --}}
    @if($validationSummary && $errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-6">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                        There were {{ $errors->count() }} error(s) with your submission
                    </h3>
                    <ul class="mt-2 text-sm text-red-700 dark:text-red-300 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif
    
    {{-- Form Content --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm p-6 space-y-6">
        <form 
            @if($action) action="{{ $action }}" @endif
            @if($wireSubmit) wire:submit="{{ $wireSubmit }}" @endif
            method="{{ $method }}"
            @if($method === 'POST') 
                @csrf 
            @endif
        >
            {{-- Form Fields --}}
            <div class="@if($responsive && $columns > 1) grid grid-cols-1 lg:grid-cols-{{ $columns }} {{ $gap }} @else space-y-6 @endif">
                {{ $slot }}
            </div>
            
            {{-- Form Actions --}}
            @if($submitButton || $cancelButton)
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    @if($cancelButton)
                        @if($cancelHref)
                            <a 
                                href="{{ $cancelHref }}" 
                                wire:navigate
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ $cancelText }}
                            </a>
                        @else
                            <button 
                                type="button" 
                                x-on:click="$dispatch('form-cancelled')"
                                class="inline-flex items-center px-4 py-2 bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            >
                                {{ $cancelText }}
                            </button>
                        @endif
                    @endif
                    
                    @if($submitButton)
                        <button 
                            type="submit" 
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span wire:loading.remove wire:target="{{ $wireSubmit ?? 'save' }}">{{ $submitText }}</span>
                            <span wire:loading wire:target="{{ $wireSubmit ?? 'save' }}" class="flex items-center">
                                <div class="w-4 h-4 border-2 border-indigo-200 border-t-white rounded-full animate-spin mr-2"></div>
                                Processing...
                            </span>
                        </button>
                    @endif
                </div>
            @endif
            
            {{-- Actions Slot --}}
            @isset($actions)
                <div class="flex justify-end gap-3 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    {{ $actions }}
                </div>
            @endisset
        </form>
    </div>
    
    {{-- Footer Content --}}
    @isset($footer)
        <div>
            {{ $footer }}
        </div>
    @endisset
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('formLayout', (config) => ({
        progress: config.progress || 0,
        steps: config.steps || [],
        currentStep: config.currentStep || 1,
        errors: [],
        
        init() {
            // Listen for validation errors
            this.$watch('$wire.errors', (errors) => {
                this.errors = errors;
                this.highlightErrorFields();
            });
            
            // Listen for form events
            Livewire.on('form-validation-error', (errors) => {
                this.showValidationErrors(errors);
            });
            
            Livewire.on('form-step-changed', (step) => {
                this.currentStep = step;
            });
            
            Livewire.on('form-progress-updated', (progress) => {
                this.progress = progress;
            });
        },
        
        highlightErrorFields() {
            // Remove existing error highlights
            this.$el.querySelectorAll('.field-error').forEach(el => {
                el.classList.remove('field-error');
            });
            
            // Add error highlights to fields with validation errors
            Object.keys(this.errors).forEach(fieldName => {
                const field = this.$el.querySelector(`[wire\\:model*="${fieldName}"]`);
                if (field) {
                    field.closest('.flux\\:field, .field-group, [data-field]')?.classList.add('field-error');
                }
            });
        },
        
        showValidationErrors(errors) {
            // Scroll to first error
            const firstErrorField = this.$el.querySelector('.field-error input, .field-error select, .field-error textarea');
            if (firstErrorField) {
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrorField.focus();
            }
        },
        
        updateProgress(percentage) {
            this.progress = Math.max(0, Math.min(100, percentage));
        },
        
        nextStep() {
            if (this.currentStep < this.steps.length) {
                this.currentStep++;
            }
        },
        
        previousStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
            }
        }
    }));
});

// Auto-validation on field changes
document.addEventListener('alpine:init', () => {
    // Add real-time validation feedback
    Alpine.directive('validate-field', (el, { expression }, { evaluate }) => {
        el.addEventListener('blur', () => {
            const fieldName = el.getAttribute('wire:model') || el.getAttribute('name');
            if (fieldName) {
                // Trigger field-level validation
                Livewire.emit('validateField', fieldName, el.value);
            }
        });
    });
});
</script>
@endpush

<style>
.field-error input,
.field-error select,
.field-error textarea {
    @apply border-red-300 dark:border-red-600 focus:border-red-500 focus:ring-red-500;
}

.field-error label {
    @apply text-red-700 dark:text-red-400;
}

.form-loading {
    pointer-events: none;
    opacity: 0.7;
}

.form-loading input,
.form-loading select,
.form-loading textarea,
.form-loading button {
    cursor: not-allowed;
}
</style>