<div class="max-w-6xl mx-auto p-6">
    <flux:header>
        <flux:heading size="xl">Import Product Data (Async)</flux:heading>
        <flux:subheading>{{ $stepLabel }} - Step {{ $step }} of 5</flux:subheading>
    </flux:header>

    {{-- Progress Bar --}}
    <div class="mb-8">
        <div class="flex justify-between text-sm text-gray-600 mb-2">
            <span>Overall Progress</span>
            <span>{{ $progressPercentage }}%</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                 style="width: {{ $progressPercentage }}%"></div>
        </div>
        
        {{-- Job Progress Details --}}
        @if($isProcessing && !empty($jobProgress))
        <div class="mt-4 p-4 bg-blue-50 rounded-lg">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="font-medium">{{ $jobProgress['message'] ?? 'Processing...' }}</flux:text>
                    <flux:text size="xs" class="text-gray-600">{{ $jobProgress['current_step_description'] ?? '' }}</flux:text>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                    <flux:text size="sm">{{ $jobProgress['elapsed_time'] ?? '' }}</flux:text>
                    <flux:button size="sm" variant="outline" wire:click="cancelCurrentJob">Cancel</flux:button>
                </div>
            </div>
            @if(isset($jobProgress['progress_percent']) && $jobProgress['progress_percent'] > 0)
            <div class="mt-2">
                <div class="w-full bg-gray-200 rounded-full h-1">
                    <div class="bg-blue-600 h-1 rounded-full transition-all duration-300" 
                         style="width: {{ $jobProgress['progress_percent'] }}%"></div>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- Active Jobs Panel --}}
    @if(!empty($activeJobs))
    <flux:card class="mb-6">
        <flux:card.header>
            <flux:heading size="lg">Active Background Jobs</flux:heading>
        </flux:card.header>
        <flux:card.body>
            <div class="space-y-2">
                @foreach($activeJobs as $job)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                    <div>
                        <flux:text class="font-medium">{{ $job['file_name'] }}</flux:text>
                        <flux:text size="sm" class="text-gray-600">{{ $job['processing_type'] }} - {{ $job['status'] }}</flux:text>
                    </div>
                    <div class="flex items-center space-x-2">
                        <flux:text size="sm">{{ $job['progress_percent'] }}%</flux:text>
                        <flux:text size="xs" class="text-gray-500">{{ $job['elapsed_time'] }}</flux:text>
                    </div>
                </div>
                @endforeach
            </div>
        </flux:card.body>
    </flux:card>
    @endif

    {{-- Step 1: File Upload & Analysis --}}
    @if($step === 1)
    <flux:card>
        <flux:card.header>
            <flux:heading size="lg">Upload Excel File for Analysis</flux:heading>
            <flux:subheading>Upload your Excel file and we'll analyze it in the background</flux:subheading>
        </flux:card.header>
        <flux:card.body>
            <div class="space-y-4">
                <div>
                    <flux:input type="file" wire:model="config.file" accept=".xlsx,.xls,.csv" />
                    @error('config.file') 
                        <flux:error>{{ $message }}</flux:error> 
                    @enderror
                </div>
                
                <flux:button wire:click="analyzeFile" 
                           :disabled="$isProcessing || !$config->file"
                           class="w-full">
                    @if($isProcessing)
                        Analyzing File...
                    @else
                        Analyze File
                    @endif
                </flux:button>
            </div>
        </flux:card.body>
    </flux:card>
    @endif

    {{-- Step 2: Configuration --}}
    @if($step === 2)
    <div class="space-y-6">
        {{-- Worksheet Selection --}}
        <flux:card>
            <flux:card.header>
                <flux:heading size="lg">Select Worksheets</flux:heading>
                <flux:subheading>Choose which worksheets to import from your file</flux:subheading>
            </flux:card.header>
            <flux:card.body>
                @if(!empty($worksheetAnalysis['worksheets']))
                <div class="grid gap-4">
                    @foreach($worksheetAnalysis['worksheets'] as $worksheet)
                    <div class="flex items-center p-4 border rounded-lg {{ in_array($worksheet['name'], $config->selectedWorksheets) ? 'border-blue-500 bg-blue-50' : 'border-gray-200' }}">
                        <input type="checkbox" 
                               wire:click="toggleWorksheet('{{ $worksheet['name'] }}')"
                               {{ in_array($worksheet['name'], $config->selectedWorksheets) ? 'checked' : '' }}
                               class="mr-3">
                        <div class="flex-1">
                            <flux:heading size="sm">{{ $worksheet['name'] }}</flux:heading>
                            <flux:text size="sm" class="text-gray-600">
                                {{ $worksheet['rows'] }} rows, {{ $worksheet['headers'] }} columns
                            </flux:text>
                            <flux:text size="xs" class="text-gray-500">{{ $worksheet['preview'] }}</flux:text>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <flux:text class="text-gray-500">No worksheets analyzed yet.</flux:text>
                @endif
            </flux:card.body>
        </flux:card>

        {{-- Column Mapping --}}
        @if(!empty($sampleData))
        <flux:card>
            <flux:card.header>
                <flux:heading size="lg">Column Mapping</flux:heading>
                <flux:subheading>Map your Excel columns to product fields</flux:subheading>
            </flux:card.header>
            <flux:card.body>
                {{-- Sample Data Preview --}}
                <div class="mb-6">
                    <flux:heading size="sm" class="mb-3">Sample Data Preview</flux:heading>
                    @foreach($sampleData as $worksheetName => $rows)
                    @if(!empty($rows))
                    <div class="mb-4">
                        <flux:text class="font-medium">{{ $worksheetName }}</flux:text>
                        <div class="overflow-x-auto mt-2">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        @foreach(array_keys($rows[0] ?? []) as $header)
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                            {{ $header }}
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach(array_slice($rows, 0, 3) as $row)
                                    <tr>
                                        @foreach($row as $value)
                                        <td class="px-3 py-2 text-sm text-gray-900">{{ $value }}</td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                    @endforeach
                </div>

                {{-- Column Mapping Form --}}
                <div class="space-y-4">
                    @php
                        $availableHeaders = !empty($sampleData) ? array_keys($sampleData[array_key_first($sampleData)][0] ?? []) : [];
                        $productFields = [
                            'variant_sku' => 'Variant SKU',
                            'product_name' => 'Product Name', 
                            'description' => 'Description',
                            'color' => 'Color',
                            'size' => 'Size',
                            'price_ex_vat' => 'Price (Ex VAT)',
                            'price_inc_vat' => 'Price (Inc VAT)',
                        ];
                    @endphp

                    @foreach($productFields as $field => $label)
                    <div class="flex items-center space-x-4">
                        <div class="w-1/3">
                            <flux:text class="font-medium">{{ $label }}</flux:text>
                        </div>
                        <div class="w-2/3">
                            <flux:select wire:model="config.columnMapping.{{ $field }}">
                                <flux:select.option value="">-- Not Mapped --</flux:select.option>
                                @foreach($availableHeaders as $header)
                                <flux:select.option value="{{ $header }}">{{ $header }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>
                    @endforeach
                </div>
            </flux:card.body>
        </flux:card>
        @endif

        {{-- Import Settings --}}
        <flux:card>
            <flux:card.header>
                <flux:heading size="lg">Import Settings</flux:heading>
            </flux:card.header>
            <flux:card.body>
                <div class="space-y-4">
                    <div>
                        <flux:text class="font-medium mb-2">Import Mode</flux:text>
                        <flux:select wire:model="config.importMode">
                            <flux:select.option value="create_only">Create Only (Skip Existing)</flux:select.option>
                            <flux:select.option value="update_existing">Update Existing Only</flux:select.option>
                            <flux:select.option value="create_or_update">Create or Update</flux:select.option>
                        </flux:select>
                    </div>
                    
                    <div class="space-y-2">
                        <flux:checkbox wire:model="config.enableAutoParentCreation">
                            Auto-create Parent Products
                        </flux:checkbox>
                        <flux:checkbox wire:model="config.enableSmartAttributeExtraction">
                            Smart Attribute Extraction
                        </flux:checkbox>
                        <flux:checkbox wire:model="config.enableAutoBarcodeAssignment">
                            Auto-assign GS1 Barcodes
                        </flux:checkbox>
                    </div>
                </div>
            </flux:card.body>
        </flux:card>

        <div class="flex justify-end">
            <flux:button wire:click="proceedToValidation" 
                       :disabled="$isProcessing || empty($config->selectedWorksheets)">
                Proceed to Validation
            </flux:button>
        </div>
    </div>
    @endif

    {{-- Step 3: Validation --}}
    @if($step === 3)
    <flux:card>
        <flux:card.header>
            <flux:heading size="lg">Dry Run Validation</flux:heading>
            <flux:subheading>Validating your data without making changes</flux:subheading>
        </flux:card.header>
        <flux:card.body>
            @if($isProcessing)
                <div class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <flux:text>Running validation in the background...</flux:text>
                </div>
            @elseif(!empty($validationResults))
                {{-- Validation Summary --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-green-50 p-4 rounded-lg">
                        <flux:text class="text-green-800 font-medium">Valid Rows</flux:text>
                        <flux:text size="xl" class="text-green-900">{{ $validationResults['valid_rows'] ?? 0 }}</flux:text>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg">
                        <flux:text class="text-red-800 font-medium">Error Rows</flux:text>
                        <flux:text size="xl" class="text-red-900">{{ $validationResults['error_rows'] ?? 0 }}</flux:text>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <flux:text class="text-yellow-800 font-medium">Warnings</flux:text>
                        <flux:text size="xl" class="text-yellow-900">{{ count($validationResults['warnings'] ?? []) }}</flux:text>
                    </div>
                </div>

                {{-- Validation Errors --}}
                @if(!empty($validationResults['errors']))
                <div class="mb-6">
                    <flux:heading size="sm" class="mb-3 text-red-800">Validation Errors</flux:heading>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
                        @foreach($validationResults['errors'] as $error)
                        <div class="bg-red-50 border border-red-200 p-3 rounded">
                            <flux:text size="sm" class="text-red-800">{{ $error }}</flux:text>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                <div class="flex justify-between">
                    <flux:button variant="outline" wire:click="resetImport">Start Over</flux:button>
                    <flux:button wire:click="proceedToImport" 
                               :disabled="!empty($validationResults['errors'])">
                        Proceed to Import
                    </flux:button>
                </div>
            @endif
        </flux:card.body>
    </flux:card>
    @endif

    {{-- Step 4: Import --}}
    @if($step === 4)
    <flux:card>
        <flux:card.header>
            <flux:heading size="lg">Running Import</flux:heading>
            <flux:subheading>Importing your data in the background</flux:subheading>
        </flux:card.header>
        <flux:card.body>
            <div class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <flux:text size="lg">Import in progress...</flux:text>
                <flux:text size="sm" class="text-gray-600">This may take several minutes for large files</flux:text>
            </div>
        </flux:card.body>
    </flux:card>
    @endif

    {{-- Step 5: Complete --}}
    @if($step === 5)
    <flux:card>
        <flux:card.header>
            <flux:heading size="lg">Import Complete!</flux:heading>
            <flux:subheading>Your data has been successfully imported</flux:subheading>
        </flux:card.header>
        <flux:card.body>
            @if(!empty($jobProgress['result_data']))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-green-50 p-4 rounded-lg">
                    <flux:text class="text-green-800 font-medium">Products Created</flux:text>
                    <flux:text size="xl" class="text-green-900">{{ $jobProgress['result_data']['products_created'] ?? 0 }}</flux:text>
                </div>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <flux:text class="text-blue-800 font-medium">Variants Created</flux:text>
                    <flux:text size="xl" class="text-blue-900">{{ $jobProgress['result_data']['variants_created'] ?? 0 }}</flux:text>
                </div>
            </div>
            @endif
            
            <div class="flex justify-center">
                <flux:button wire:click="resetImport">Import Another File</flux:button>
            </div>
        </flux:card.body>
    </flux:card>
    @endif

    {{-- Error Messages --}}
    @error('processing')
    <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
        <flux:text class="text-red-800">{{ $message }}</flux:text>
    </div>
    @enderror
</div>

{{-- JavaScript for Progress Polling --}}
<script>
document.addEventListener('livewire:init', () => {
    let progressInterval = null;
    
    Livewire.on('start-progress-polling', (event) => {
        const jobId = event[0].jobId;
        console.log('Starting progress polling for job:', jobId);
        
        // Clear any existing interval
        if (progressInterval) {
            clearInterval(progressInterval);
        }
        
        // Start polling every 2 seconds
        progressInterval = setInterval(() => {
            Livewire.dispatch('checkProgress');
        }, 2000);
    });
    
    Livewire.on('stop-progress-polling', () => {
        console.log('Stopping progress polling');
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    });
    
    Livewire.on('progress-updated', (event) => {
        const progress = event[0].progress;
        console.log('Progress updated:', progress);
        
        // Stop polling if job is complete or failed
        if (!progress.is_active && progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        if (progressInterval) {
            clearInterval(progressInterval);
        }
    });
});
</script>