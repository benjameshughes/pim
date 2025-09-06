<div class="max-w-4xl mx-auto p-6">
    {{-- 🎯 WIZARD HEADER --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Add New Marketplace Integration</h1>
        <p class="text-gray-600">Connect your store to a new marketplace in just a few simple steps.</p>
        
        {{-- Progress Bar --}}
        <div class="mt-6">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700">{{ $this->stepTitle }}</span>
                <span class="text-sm text-gray-500">Step {{ $currentStep }} of {{ self::TOTAL_STEPS }}</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                     style="width: {{ $this->progressPercentage }}%"></div>
            </div>
        </div>
    </div>

    {{-- 🏪 STEP 1: MARKETPLACE SELECTION --}}
    @if($currentStep === 1)
        <livewire:marketplace.wizard.step1-marketplace-select :available-marketplaces="$availableMarketplaces" />
    @endif

    {{-- ⚙️ STEP 2: CONFIGURATION STEP --}}
    @if($currentStep === 2)
        <livewire:marketplace.wizard.step2-configuration 
            :selected-marketplace="$selectedMarketplace"
            :selected-operator="$selectedOperator"
            :display-name="$displayName"
            :credentials="$credentials"
            :settings="$settings"
            :wire:key="'step2-'.$selectedMarketplace"
        />
    @endif

    {{-- No Step 3: handled within Step 2 via Fetch Store Info and Create --}}

    {{-- 🔄 RESET WIZARD --}}
    <div class="mt-6 text-center">
        <flux:button variant="ghost" wire:click="resetWizard" class="text-gray-500 hover:text-gray-700">
            <flux:icon.arrow-path class="w-4 h-4 mr-2" />
            Start Over
        </flux:button>
    </div>
</div>
