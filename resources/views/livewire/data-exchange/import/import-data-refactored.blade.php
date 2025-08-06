<div class="max-w-6xl mx-auto">
    <!-- Screen reader status updates -->
    <div class="sr-only" aria-live="polite" aria-atomic="true">
        @if($step === 1)
            Step 1 of 5: Upload your Excel or CSV file
        @elseif($step === 2)
            Step 2 of 5: Configure worksheets and column mapping
        @elseif($step === 3)
            Step 3 of 5: Validate import data
        @elseif($step === 4)
            Step 4 of 5: Import in progress
        @else
            Step 5 of 5: Import completed
        @endif
    </div>

    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Import Product Data (v2)</flux:heading>
        <flux:subheading>Clean architecture import with advanced validation and progress tracking</flux:subheading>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8" role="navigation" aria-label="Import progress steps">
        <div class="flex items-center overflow-x-auto">
            <!-- Step 1 -->
            <button 
                type="button"
                wire:click="goToStep(1)"
                class="flex items-center {{ $step >= 1 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-300 dark:text-zinc-600' }} {{ $step > 1 ? 'hover:text-blue-700 dark:hover:text-blue-300 cursor-pointer' : 'cursor-default' }} transition-colors"
                @if($step <= 1) disabled @endif
                aria-label="Go to step 1: Upload File"
            >
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 1 ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600' }} {{ $step > 1 ? 'group-hover:bg-blue-700' : '' }}">
                    @if($step > 1)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        1
                    @endif
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Upload File</span>
            </button>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 2 ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
            
            <!-- Step 2 -->
            <button 
                type="button"
                wire:click="goToStep(2)" 
                class="flex items-center {{ $step >= 2 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-300 dark:text-zinc-600' }} {{ $step > 2 && !empty($worksheetData) ? 'hover:text-blue-700 dark:hover:text-blue-300 cursor-pointer' : 'cursor-default' }} transition-colors"
                @if($step <= 2 || empty($worksheetData)) disabled @endif
                aria-label="Go to step 2: Configure"
            >
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 2 ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                    @if($step > 2)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        2
                    @endif
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Configure</span>
            </button>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 3 ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
            
            <!-- Step 3 -->
            <button 
                type="button"
                wire:click="goToStep(3)"
                class="flex items-center {{ $step >= 3 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-300 dark:text-zinc-600' }} {{ $step > 3 && !empty($validationResults) ? 'hover:text-blue-700 dark:hover:text-blue-300 cursor-pointer' : 'cursor-default' }} transition-colors"
                @if($step <= 3 || empty($validationResults)) disabled @endif
                aria-label="Go to step 3: Validate"
            >
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 3 ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                    @if($step > 3)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        3
                    @endif
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Validate</span>
            </button>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 4 ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
            
            <!-- Step 4 -->
            <div class="flex items-center {{ $step >= 4 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-300 dark:text-zinc-600' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 4 ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                    @if($step > 4)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        4
                    @endif
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Import</span>
            </div>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 5 ? 'bg-blue-600' : 'bg-zinc-300 dark:bg-zinc-600' }}"></div>
            
            <!-- Step 5 -->
            <div class="flex items-center {{ $step >= 5 ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-300 dark:text-zinc-600' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 5 ? 'bg-blue-600 text-white' : 'bg-zinc-300 dark:bg-zinc-600' }}">
                    @if($step >= 5)
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        5
                    @endif
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Complete</span>
            </div>
        </div>
    </div>

    @if($step === 1)
        <!-- Step 1: File Upload -->
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Step 1: Upload Import File</flux:heading>
                <flux:subheading>Select your Excel or CSV file containing product data</flux:subheading>
            </x-slot:header>

            <form wire:submit.prevent="analyzeFile" class="space-y-6">
                <div>
                    <flux:input 
                        type="file" 
                        wire:model="config.file" 
                        accept=".xlsx,.xls,.csv"
                        placeholder="Choose file..."
                    />
                    @error('config.file') 
                        <flux:error>{{ $message }}</flux:error> 
                    @enderror
                </div>

                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                File Requirements
                            </h3>
                            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                <ul class="list-disc pl-5 space-y-1">
                                    <li>Excel files (.xlsx, .xls) or CSV files</li>
                                    <li>Maximum file size: 100MB</li>
                                    <li>At least one column for SKU or Product Name</li>
                                    <li>Headers in the first row (recommended)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" :disabled="!$config->file">
                        <span wire:loading.remove wire:target="analyzeFile">
                            Analyze File
                        </span>
                        <span wire:loading wire:target="analyzeFile" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Analyzing file... 🔍
                        </span>
                    </flux:button>
                </div>
            </form>
        </x-card>

        <!-- Loading Skeleton while analyzing -->
        <div wire:loading wire:target="analyzeFile" class="mt-6">
            <x-card>
                <x-slot:header>
                    <div class="animate-pulse">
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-700 rounded w-48"></div>
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-64 mt-2"></div>
                    </div>
                </x-slot:header>
                
                <div class="space-y-4 animate-pulse">
                    <!-- Skeleton worksheets -->
                    @for($i = 0; $i < 3; $i++)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                            <div class="flex items-center space-x-3">
                                <div class="h-5 w-5 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                                <div>
                                    <div class="h-5 bg-zinc-200 dark:bg-zinc-700 rounded w-32"></div>
                                    <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-24 mt-1"></div>
                                </div>
                            </div>
                        </div>
                    @endfor
                </div>
            </x-card>
        </div>

    @elseif($step === 2)
        <!-- Step 2: Configuration -->
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Step 2: Select Worksheets & Map Columns</flux:heading>
                <flux:subheading>Choose worksheets and configure column mappings</flux:subheading>
            </x-slot:header>

            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 lg:gap-8">
                
                {{-- Worksheet Selection --}}
                <div class="lg:col-span-1 xl:col-span-1 space-y-4">
                    <flux:heading size="base">Select Worksheets</flux:heading>
                    
                    {{-- Debug info --}}
                    @if(app()->environment('local'))
                        <div class="text-xs text-red-500 mb-2 p-2 border border-red-300 rounded">
                            Debug: Cache Key: {{ $worksheetAnalysisCacheKey ?? 'null' }}<br>
                            Debug: Analysis Keys: {{ $this->worksheetAnalysis ? implode(', ', array_keys($this->worksheetAnalysis)) : 'null' }}<br>  
                            Debug: Worksheets Count: {{ isset($this->worksheetAnalysis['worksheets']) ? count($this->worksheetAnalysis['worksheets']) : 'none' }}<br>
                            Debug: Debug Array Count: {{ count($debugWorksheetAnalysis['worksheets'] ?? []) }}<br>
                            Debug: Direct Worksheet Data Count: {{ count($worksheetData ?? []) }}<br>
                            <button wire:click="debugWorksheetAnalysis" class="text-xs bg-red-500 text-white px-2 py-1 rounded mt-1">
                                Debug Log Analysis
                            </button>
                        </div>
                    @endif
                    
                    @forelse($worksheetData as $worksheet)
                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 hover:border-blue-500 cursor-pointer transition-colors {{ in_array($worksheet['name'], $config->selectedWorksheets) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' }}"
                             wire:click="toggleWorksheet('{{ $worksheet['name'] }}')">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-3">
                                    <flux:checkbox 
                                        :checked="in_array($worksheet['name'], $config->selectedWorksheets)"
                                        wire:click.stop="toggleWorksheet('{{ $worksheet['name'] }}')"
                                    />
                                    <div>
                                        <h3 class="font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $worksheet['name'] }}
                                        </h3>
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ $worksheet['headers'] }} columns • {{ $worksheet['rows'] }} rows
                                        </p>
                                        @if($worksheet['rows'] > 0)
                                            <p class="text-xs text-green-600 dark:text-green-400 mt-1">
                                                ✓ Contains data
                                            </p>
                                        @else
                                            <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                                ⚠ No data detected
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                @if(in_array($worksheet['name'], $config->selectedWorksheets))
                                    <flux:badge color="blue" size="sm">
                                        Selected
                                    </flux:badge>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            No worksheets available. Please upload a file first.
                        </div>
                    @endforelse
                </div>

                {{-- Column Mapping --}}
                <div class="lg:col-span-1 xl:col-span-2 space-y-4">
                    <flux:heading size="base">Map Columns</flux:heading>
                    
                    {{-- Debug info for sample data --}}
                    @if(app()->environment('local'))
                        <div class="text-xs text-blue-500 mb-2 p-2 border border-blue-300 rounded">
                            Debug: Selected Worksheets: {{ implode(', ', $config->selectedWorksheets) }}<br>
                            Debug: Sample Data Cache Key: {{ $sampleDataCacheKey ?? 'null' }}<br>
                            Debug: Sample Data Count: {{ count($sampleDataDirect ?? []) }}<br>
                            Debug: Sample Data Keys: {{ $sampleDataDirect ? implode(', ', array_keys($sampleDataDirect)) : 'none' }}
                        </div>
                    @endif
                    
                    @if(!empty($sampleDataDirect))
                        <div class="space-y-3">
                            @foreach($config->availableFields as $field => $label)
                                <div>
                                    <flux:label>{{ $label }}</flux:label>
                                    <flux:select wire:model="config.columnMapping.{{ $field }}">
                                        <flux:select.option value="">-- Select Column --</flux:select.option>
                                        @foreach(array_keys(reset($sampleDataDirect)[0] ?? []) as $header)
                                            <flux:select.option value="{{ $header }}">{{ $header }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-zinc-500 dark:text-zinc-400">
                            Select worksheets to see available columns for mapping.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Import Options --}}
            <div class="mt-8 border-t border-zinc-200 dark:border-zinc-700 pt-8">
                <flux:heading size="base" class="mb-6">Import Configuration</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:label>Import Mode</flux:label>
                        <flux:select wire:model="config.importMode">
                            <flux:select.option value="create_only">Create Only</flux:select.option>
                            <flux:select.option value="update_existing">Update Only</flux:select.option>
                            <flux:select.option value="create_or_update">Create or Update</flux:select.option>
                        </flux:select>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $config->getImportModeDescription() }}</p>
                    </div>

                    <div class="space-y-3">
                        <flux:checkbox wire:model="config.enableAutoParentCreation">
                            Auto-create parent products
                        </flux:checkbox>
                        <flux:checkbox wire:model="config.enableSmartAttributeExtraction">
                            Smart color/size extraction
                        </flux:checkbox>
                        <flux:checkbox wire:model="config.enableAutoBarcodeAssignment">
                            Auto-assign GS1 barcodes
                        </flux:checkbox>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-between">
                <flux:button wire:click="resetImport" variant="subtle">
                    Start Over
                </flux:button>
                <flux:button wire:click="proceedToValidation" variant="primary">
                    Validate Import
                </flux:button>
            </div>
        </x-card>

    @elseif($step === 3)
        <!-- Step 3: Validation Results -->
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Step 3: Validation Results</flux:heading>
                <flux:subheading>Review what will happen during the import</flux:subheading>
            </x-slot:header>

            @if(!empty($validationResults))
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                            {{ $validationResults['will_create'] ?? 0 }}
                        </div>
                        <div class="text-sm text-green-700 dark:text-green-300">Will Create</div>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $validationResults['will_update'] ?? 0 }}
                        </div>
                        <div class="text-sm text-blue-700 dark:text-blue-300">Will Update</div>
                    </div>
                    
                    <div class="bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                        <div class="text-2xl font-bold text-zinc-600 dark:text-zinc-400">
                            {{ $validationResults['will_skip'] ?? 0 }}
                        </div>
                        <div class="text-sm text-zinc-700 dark:text-zinc-300">Will Skip</div>
                    </div>
                </div>

                @if(!empty($validationResults['errors']))
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-red-800 dark:text-red-200 mb-3 flex items-center">
                            <svg class="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Errors Found ({{ count($validationResults['errors']) }})
                        </h4>
                        
                        <div class="space-y-3">
                            @php
                                $errorsByType = collect($this->getValidationErrors())->groupBy('type');
                            @endphp
                            
                            @foreach($errorsByType as $type => $errors)
                                <div class="border-l-4 {{ $type === 'validation' ? 'border-red-400' : ($type === 'constraint' ? 'border-orange-400' : 'border-yellow-400') }} pl-3">
                                    <h5 class="font-medium text-red-700 dark:text-red-300 capitalize mb-1">
                                        {{ ucfirst($type) }} Issues ({{ count($errors) }})
                                    </h5>
                                    <ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
                                        @foreach($errors as $error)
                                            <li class="flex items-start">
                                                <span class="inline-block w-2 h-2 bg-red-400 rounded-full mt-2 mr-2 flex-shrink-0"></span>
                                                {{ $error['message'] }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($validationResults['warnings']))
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Warnings</h4>
                        <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                            @foreach($validationResults['warnings'] as $warning)
                                <li>• {{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif

            <div class="flex justify-between">
                <flux:button wire:click="$set('step', 2)" variant="subtle">
                    Back to Configuration
                </flux:button>
                
                @if(empty($validationResults['errors']))
                    <flux:button wire:click="proceedToImport" variant="primary">
                        Start Import
                    </flux:button>
                @else
                    <flux:button wire:click="runValidation" variant="primary">
                        Re-validate
                    </flux:button>
                @endif
            </div>
        </x-card>

    @elseif($step === 4)
        <!-- Step 4: Import Progress -->
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Step 4: Import in Progress</flux:heading>
                <flux:subheading>Processing your data with real-time updates</flux:subheading>
            </x-slot:header>

            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="font-medium text-zinc-900 dark:text-zinc-100">Processing rows...</span>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $progress->processedRows }} / {{ $progress->totalRows }}
                    </span>
                </div>
                
                <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3 mb-4">
                    <div 
                        class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                        style="width: {{ $progress->getProgressPercentage() }}%"
                    ></div>
                </div>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="font-semibold text-green-600 dark:text-green-400">{{ $progress->productsCreated }}</div>
                        <div class="text-zinc-600 dark:text-zinc-400">Products Created</div>
                    </div>
                    <div>
                        <div class="font-semibold text-blue-600 dark:text-blue-400">{{ $progress->variantsCreated }}</div>
                        <div class="text-zinc-600 dark:text-zinc-400">Variants Created</div>
                    </div>
                    <div>
                        <div class="font-semibold text-yellow-600 dark:text-yellow-400">{{ $progress->variantsUpdated }}</div>
                        <div class="text-zinc-600 dark:text-zinc-400">Variants Updated</div>
                    </div>
                    <div>
                        <div class="font-semibold text-zinc-600 dark:text-zinc-400">{{ $progress->getFormattedProcessingRate() }}</div>
                        <div class="text-zinc-600 dark:text-zinc-400">Processing Rate</div>
                    </div>
                </div>
                
                @if($progress->estimatedTimeRemaining > 0)
                    <div class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $progress->getFormattedEstimatedTime() }}
                    </div>
                @endif
            </div>
        </x-card>

    @elseif($step === 5)
        <!-- Step 5: Complete -->
        <x-card>
            <x-slot:header>
                <div class="text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 dark:bg-green-900/20 mb-4">
                        <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <flux:heading size="lg">Import Complete!</flux:heading>
                    <flux:subheading>Your products have been successfully imported</flux:subheading>
                </div>
            </x-slot:header>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $progress->productsCreated }}</div>
                    <div class="text-sm text-green-700 dark:text-green-300">Products Created</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $progress->variantsCreated }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">Variants Created</div>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $progress->variantsUpdated }}</div>
                    <div class="text-sm text-yellow-700 dark:text-yellow-300">Variants Updated</div>
                </div>
                <div class="bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 text-center">
                    <div class="text-2xl font-bold text-zinc-600 dark:text-zinc-400">{{ $progress->getFormattedElapsedTime() }}</div>
                    <div class="text-sm text-zinc-700 dark:text-zinc-300">Total Time</div>
                </div>
            </div>

            @if($progress->hasErrors)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-red-800 dark:text-red-200 mb-2">Errors Encountered</h4>
                    <ul class="text-sm text-red-700 dark:text-red-300 space-y-1">
                        @foreach($progress->errors as $error)
                            <li>• {{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($progress->hasWarnings)
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                    <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Warnings</h4>
                    <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                        @foreach($progress->warnings as $warning)
                            <li>• {{ $warning }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="text-center">
                <flux:button wire:click="resetImport" variant="primary" class="mr-4">
                    Import Another File
                </flux:button>
                <flux:button onclick="window.location.href = '{{ route('products.index') }}'" variant="outline">
                    View Products
                </flux:button>
            </div>
        </x-card>
    @endif
</div>

@script
<script>
    // Enhanced progress tracking with memory leak prevention
    class ImportProgressTracker {
        constructor() {
            this.pollInterval = 1000; // Start with 1 second
            this.maxInterval = 10000; // Max 10 seconds
            this.errorCount = 0;
            this.maxErrors = 5;
            this.intervalId = null;
            this.abortController = new AbortController();
            this.isDestroyed = false;
        }
        
        startPolling() {
            if (this.isDestroyed || this.abortController.signal.aborted) {
                return;
            }
            
            if ($wire.step === 4 && $wire.progress.isProcessing) {
                this.poll();
            }
        }
        
        async poll() {
            if (this.isDestroyed || this.abortController.signal.aborted) {
                return;
            }
            
            try {
                // Check if component still exists before polling
                if (!$wire || !$wire.checkImportProgress) {
                    this.cleanup();
                    return;
                }
                
                await $wire.checkImportProgress();
                this.errorCount = 0;
                // Gradually reduce polling frequency for efficiency
                this.pollInterval = Math.max(1000, this.pollInterval * 0.95);
                
                if ($wire.progress.isProcessing && !this.isDestroyed) {
                    this.intervalId = setTimeout(() => this.poll(), this.pollInterval);
                } else {
                    this.cleanup();
                }
            } catch (error) {
                if (!this.abortController.signal.aborted && !this.isDestroyed) {
                    console.warn('Import progress check failed:', error);
                    this.errorCount++;
                    // Exponential backoff on errors
                    this.pollInterval = Math.min(this.maxInterval, this.pollInterval * 1.5);
                    
                    if (this.errorCount < this.maxErrors && $wire.progress.isProcessing && !this.isDestroyed) {
                        this.intervalId = setTimeout(() => this.poll(), this.pollInterval);
                    } else {
                        console.error('Max polling errors reached or import completed');
                        this.cleanup();
                    }
                }
            }
        }
        
        cleanup() {
            this.isDestroyed = true;
            this.abortController.abort();
            if (this.intervalId) {
                clearTimeout(this.intervalId);
                this.intervalId = null;
            }
        }
        
        stop() {
            this.cleanup();
        }
    }
    
    // Initialize tracker
    const progressTracker = new ImportProgressTracker();
    
    // Start polling when step 4 is reached
    if ($wire.step === 4 && $wire.progress.isProcessing) {
        progressTracker.startPolling();
    }
    
    // Listen for step changes to start/stop polling
    $wire.on('import-progress-updated', () => {
        if ($wire.step === 4 && $wire.progress.isProcessing) {
            progressTracker.startPolling();
        } else {
            progressTracker.stop();
        }
    });
    
    // Enhanced cleanup event listeners
    const cleanupTracker = () => {
        if (progressTracker) {
            progressTracker.stop();
        }
    };
    
    // Multiple cleanup triggers to prevent memory leaks
    window.addEventListener('beforeunload', cleanupTracker);
    window.addEventListener('pagehide', cleanupTracker);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            cleanupTracker();
        }
    });
    
    // Livewire component destruction cleanup
    document.addEventListener('livewire:navigating', cleanupTracker);
    
    // Cleanup on Livewire component updates if step changes away from import
    $wire.on('import-progress-updated', () => {
        if ($wire.step !== 4 || !$wire.progress.isProcessing) {
            cleanupTracker();
        }
    });
</script>
@endscript