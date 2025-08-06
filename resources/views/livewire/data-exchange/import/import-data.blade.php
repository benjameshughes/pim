<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Import Product Data</flux:heading>
        <flux:subheading>Upload spreadsheets to import products and variants with images</flux:subheading>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center overflow-x-auto">
            <!-- Step 1 -->
            <div class="flex items-center {{ $step >= 1 ? 'text-blue-600' : 'text-zinc-300' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 1 ? 'bg-blue-600 text-white' : 'bg-zinc-300' }}">
                    1
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Upload File</span>
            </div>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 2 ? 'bg-blue-600' : 'bg-zinc-300' }}"></div>
            
            <!-- Step 2 -->
            <div class="flex items-center {{ $step >= 2 ? 'text-blue-600' : 'text-zinc-300' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 2 ? 'bg-blue-600 text-white' : 'bg-zinc-300' }}">
                    2
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Select Sheet</span>
            </div>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 3 ? 'bg-blue-600' : 'bg-zinc-300' }}"></div>
            
            <!-- Step 3 -->
            <div class="flex items-center {{ $step >= 3 ? 'text-blue-600' : 'text-zinc-300' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 3 ? 'bg-blue-600 text-white' : 'bg-zinc-300' }}">
                    3
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Map Columns</span>
            </div>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 4 ? 'bg-blue-600' : 'bg-zinc-300' }}"></div>
            
            <!-- Step 4 -->
            <div class="flex items-center {{ $step >= 4 ? 'text-blue-600' : 'text-zinc-300' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 4 ? 'bg-blue-600 text-white' : 'bg-zinc-300' }}">
                    4
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Dry Run</span>
            </div>
            
            <!-- Connector -->
            <div class="mx-3 h-px w-8 {{ $step >= 5 ? 'bg-blue-600' : 'bg-zinc-300' }}"></div>
            
            <!-- Step 5 -->
            <div class="flex items-center {{ $step >= 5 ? 'text-blue-600' : 'text-zinc-300' }}">
                <div class="flex h-8 w-8 items-center justify-center rounded-full {{ $step >= 5 ? 'bg-blue-600 text-white' : 'bg-zinc-300' }}">
                    5
                </div>
                <span class="ml-2 font-medium whitespace-nowrap">Import</span>
            </div>
        </div>
    </div>

    @if($step === 1)
        <!-- Step 1: File Upload -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">Step 1: Upload Your File</flux:heading>
            
            <form wire:submit="analyzeFile" class="space-y-6">
                <div>
                    <flux:field>
                        <flux:label>Select File</flux:label>
                        <input 
                            type="file" 
                            wire:model="file" 
                            accept=".xlsx,.xls,.csv"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <flux:error name="file" />
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Supported formats: Excel (.xlsx, .xls) and CSV files. Maximum size: 10MB
                        </div>
                    </flux:field>
                    
                    <!-- Upload Progress -->
                    <div wire:loading wire:target="file" class="mt-2">
                        <div class="text-blue-600 text-sm">Uploading file...</div>
                    </div>
                </div>

                <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                    <flux:heading size="sm" class="mb-2">Expected File Format</flux:heading>
                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                        <p class="mb-2">Your spreadsheet should have:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Header row with column names</li>
                            <li>Product name and variant information (SKU, color, size)</li>
                            <li>Stock levels and package dimensions</li>
                            <li>Image URLs (optional, comma-separated for multiple images)</li>
                            <li>One row per product variant</li>
                        </ul>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" :disabled="!$file">
                        Analyze File
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    @if($step === 2)
        <!-- Step 2: Worksheet Selection -->
        <x-card>
            <x-slot:header>
                <flux:heading size="lg">Step 2: Select Worksheets</flux:heading>
                <flux:subheading>Choose which worksheets contain your variant data (you can select multiple)</flux:subheading>
            </x-slot:header>

            <!-- Select All/None controls -->
            @if(count($availableWorksheets) > 1)
                <div class="flex items-center justify-between mb-4 p-3 bg-zinc-50 dark:bg-zinc-900 rounded-lg">
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ count($selectedWorksheets) }} of {{ count($availableWorksheets) }} sheets selected
                    </span>
                    <div class="flex gap-2">
                        @if(count($selectedWorksheets) === count($availableWorksheets))
                            <flux:button size="sm" variant="ghost" wire:click="deselectAllWorksheets">
                                Deselect All
                            </flux:button>
                        @else
                            <flux:button size="sm" variant="ghost" wire:click="selectAllWorksheets">
                                Select All
                            </flux:button>
                        @endif
                    </div>
                </div>
            @endif

            <div class="space-y-4">
                @forelse($availableWorksheets as $worksheet)
                    <div wire:key="worksheet-{{ $worksheet['index'] }}" 
                         class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4 hover:border-blue-500 cursor-pointer transition-colors {{ in_array($worksheet['index'], $selectedWorksheets) ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' }}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <flux:checkbox 
                                    wire:model.live="selectedWorksheets"
                                    value="{{ $worksheet['index'] }}"
                                />
                                <div wire:click="toggleWorksheet({{ $worksheet['index'] }})" class="cursor-pointer flex-1">
                                    <h3 class="font-medium text-zinc-900 dark:text-zinc-100">
                                        {{ $worksheet['name'] }}
                                    </h3>
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $worksheet['headers'] }} headers • {{ $worksheet['rows'] }} rows
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1">
                                        Preview: {{ $worksheet['preview'] }}
                                    </p>
                                </div>
                            </div>
                            @if(in_array($worksheet['index'], $selectedWorksheets))
                                <flux:badge color="blue" size="sm">
                                    Selected
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-zinc-500">
                        No worksheets found in the uploaded file.
                    </div>
                @endforelse

                @if(count($selectedWorksheets) > 0)
                    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-blue-900 dark:text-blue-100">
                                    {{ count($selectedWorksheets) }} sheet(s) selected
                                </h4>
                                <p class="text-sm text-blue-700 dark:text-blue-300">
                                    All selected sheets will be imported together
                                </p>
                            </div>
                            <flux:button 
                                wire:click="proceedWithSelectedSheets"
                                variant="primary"
                            >
                                Configure Import
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        </x-card>
    @endif

    @if($step === 3)
        <!-- Step 3: Column Mapping -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Step 3: Map Your Columns</flux:heading>
                    <flux:subheading>Match your spreadsheet columns to the correct fields</flux:subheading>
                </div>
                
                <!-- Saved Mappings Info -->
                @php $mappingStats = $this->getMappingStats(); @endphp
                @if($mappingStats['has_saved_mapping'])
                    <div class="text-right">
                        <div class="text-sm text-green-600 dark:text-green-400 mb-1">
                            ✓ Using saved mappings ({{ $mappingStats['total_mappings'] }} fields)
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400 mb-2">
                            Last saved: {{ \Carbon\Carbon::parse($mappingStats['created_at'])->diffForHumans() }}
                        </div>
                        <flux:button size="sm" variant="ghost" wire:click="clearSavedMappings">
                            Clear Saved
                        </flux:button>
                    </div>
                @endif
            </div>
            
            <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-zinc-600 dark:text-zinc-400">Your Column</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-zinc-600 dark:text-zinc-400">Sample Data</th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-zinc-600 dark:text-zinc-400">Maps To</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach($headers as $index => $header)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-sm">{{ $header }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                        @if(isset($sampleData[0][$index]))
                                            {{ Str::limit($sampleData[0][$index] ?? '', 30) }}
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <select 
                                        wire:model="columnMapping.{{ $index }}"
                                        class="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">-- Skip this column --</option>
                                        @foreach($availableFields as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Sample Data Preview -->
            @if(count($sampleData) > 0)
                <div class="mt-6">
                    <flux:heading size="sm" class="mb-3">Sample Data Preview</flux:heading>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-100 dark:bg-zinc-800">
                                <tr>
                                    @foreach($headers as $header)
                                        <th class="px-3 py-2 text-left font-medium">{{ $header }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach(array_slice($sampleData, 0, 3) as $row)
                                    <tr>
                                        @foreach($row as $cell)
                                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">
                                                {{ Str::limit($cell ?? '', 30) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Mapping Cache Info -->
            @if(!$mappingStats['has_saved_mapping'])
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                    <div class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>💡 Tip:</strong> Your column mappings and import settings will be remembered for future imports, saving you time on similar files.
                    </div>
                </div>
            @endif

            <div class="mt-6">
                <!-- Import Mode Selection -->
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-4">
                    <flux:heading size="sm" class="text-amber-800 dark:text-amber-200 mb-3">Import Mode</flux:heading>
                    <div class="space-y-3">
                        @foreach($importModeOptions as $mode => $description)
                            <div class="flex items-start">
                                <input 
                                    type="radio" 
                                    wire:model="importMode" 
                                    value="{{ $mode }}"
                                    id="importMode_{{ $mode }}"
                                    class="mt-1 rounded border-gray-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                >
                                <label for="importMode_{{ $mode }}" class="ml-3 text-sm">
                                    <div class="font-medium text-amber-800 dark:text-amber-200">{{ $description }}</div>
                                    <div class="text-amber-700 dark:text-amber-300 text-xs mt-1">
                                        @if($mode === 'create_only')
                                            Skip rows with existing product names or variant SKUs. Best for first-time imports.
                                        @elseif($mode === 'update_existing')
                                            Only update rows where product names and variant SKUs already exist. Best for updating existing data.
                                        @else
                                            Create new records or update existing ones. Best for regular sync operations.
                                        @endif
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <!-- Smart Attribute Extraction Option -->
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model="smartAttributeExtraction" 
                            id="smartAttributeExtraction"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <label for="smartAttributeExtraction" class="ml-2 text-sm font-medium text-green-800 dark:text-green-200">
                            Smart extraction of color and size from product names
                        </label>
                    </div>
                    <div class="text-sm text-green-700 dark:text-green-300 mt-1">
                        Uses AI algorithms to detect colors and sizes in product names like "Blackout Roller Blind Blue 60cm" → Color: Blue, Size: 60cm
                    </div>
                </div>

                <!-- Import Mode Selection -->
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 mb-4">
                    <flux:heading size="sm" class="text-purple-800 dark:text-purple-200 mb-3">Parent/Child Handling</flux:heading>
                    <div class="space-y-3">
                        <div class="flex items-start">
                            <input 
                                type="radio" 
                                wire:model.boolean="autoGenerateParentMode" 
                                value="1"
                                id="autoGenerateMode"
                                class="mt-1 rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50"
                            >
                            <label for="autoGenerateMode" class="ml-3 text-sm">
                                <div class="font-medium text-purple-800 dark:text-purple-200">Auto-Generate Parent Mode</div>
                                <div class="text-purple-700 dark:text-purple-300 text-xs mt-1">
                                    <strong>Lazy Mode:</strong> Treats ALL rows as variants and auto-creates parent products using SKU patterns (001-001 → parent: 001) and smart name extraction. Perfect for importing only variant data! 🚀
                                </div>
                            </label>
                        </div>
                        
                        <div class="flex items-start">
                            <input 
                                type="radio" 
                                wire:model.boolean="autoGenerateParentMode" 
                                value="0"
                                id="explicitMode"
                                class="mt-1 rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50"
                            >
                            <label for="explicitMode" class="ml-3 text-sm">
                                <div class="font-medium text-purple-800 dark:text-purple-200">Explicit Parent/Child Mode</div>
                                <div class="text-purple-700 dark:text-purple-300 text-xs mt-1">
                                    Uses your data columns to determine which rows are parents vs children. Map columns like "Is Parent" or "Parent Name" to specify the relationship explicitly.
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Legacy auto-create option (only shown in explicit mode) -->
                    @if(!$autoGenerateParentMode)
                        <div class="mt-3 pt-3 border-t border-purple-200 dark:border-purple-700">
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    wire:model="autoCreateParents" 
                                    id="autoCreateParents"
                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50"
                                >
                                <label for="autoCreateParents" class="ml-2 text-sm text-purple-800 dark:text-purple-200">
                                    Enable legacy auto-parent creation for missing parents
                                </label>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- GS1 Auto-Assignment Option -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <input 
                            type="checkbox" 
                            wire:model="autoAssignGS1Barcodes" 
                            id="autoAssignGS1Barcodes"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                        >
                        <label for="autoAssignGS1Barcodes" class="ml-2 text-sm font-medium text-blue-800 dark:text-blue-200">
                            Auto-assign GS1 barcodes for variants without barcodes
                        </label>
                    </div>
                    <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                        When enabled, variants imported without barcodes will automatically get the next available EAN13 barcode from your GS1 pool.
                    </div>
                </div>
                
                <div class="flex justify-between">
                    <flux:button variant="ghost" wire:click="resetImport">
                        Start Over
                    </flux:button>
                    <flux:button variant="outline" wire:click="debugColumnMapping">
                        Debug Mappings
                    </flux:button>
                    <flux:button variant="primary" wire:click="runDryRun">
                        Run Dry Run
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    @if($step === 4)
        <!-- Step 4: Dry Run Results -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">Step 4: Dry Run Results</flux:heading>
            <flux:subheading class="mb-6">Review potential issues before importing</flux:subheading>
            
            <!-- Summary Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $dryRunResults['valid_rows'] ?? 0 }}</div>
                    <div class="text-sm text-green-700 dark:text-green-300">Valid Rows</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $dryRunResults['error_rows'] ?? 0 }}</div>
                    <div class="text-sm text-red-700 dark:text-red-300">Error Rows</div>
                </div>
                <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg text-center">
                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $dryRunResults['barcodes_needed'] ?? 0 }}</div>
                    <div class="text-sm text-amber-700 dark:text-amber-300">Barcodes Needed</div>
                </div>
                <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg text-center">
                    <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400">{{ ucfirst(str_replace('_', ' ', $importMode)) }}</div>
                    <div class="text-sm text-indigo-700 dark:text-indigo-300">Import Mode</div>
                </div>
            </div>

            <!-- Import Actions Breakdown -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Products -->
                <div class="bg-white dark:bg-zinc-800 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-3">Product Actions</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700 dark:text-green-300">Create</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ $dryRunResults['products_to_create'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-700 dark:text-blue-300">Update</span>
                            <span class="font-medium text-blue-600 dark:text-blue-400">{{ $dryRunResults['products_to_update'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">Skip</span>
                            <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ $dryRunResults['products_to_skip'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Variants -->
                <div class="bg-white dark:bg-zinc-800 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-3">Variant Actions</flux:heading>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-700 dark:text-green-300">Create</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ $dryRunResults['variants_to_create'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-700 dark:text-blue-300">Update</span>
                            <span class="font-medium text-blue-600 dark:text-blue-400">{{ $dryRunResults['variants_to_update'] ?? 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-zinc-700 dark:text-zinc-300">Skip</span>
                            <span class="font-medium text-zinc-600 dark:text-zinc-400">{{ $dryRunResults['variants_to_skip'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Errors -->
            @if(!empty($dryRunResults['errors']))
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4">
                    <flux:heading size="sm" class="text-red-700 dark:text-red-300 mb-3">Errors Found</flux:heading>
                    <div class="space-y-1 max-h-60 overflow-y-auto">
                        @foreach($dryRunResults['errors'] as $error)
                            <div class="text-sm text-red-600 dark:text-red-400">• {{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Warnings -->
            @if(!empty($dryRunResults['warnings']))
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-4">
                    <flux:heading size="sm" class="text-amber-700 dark:text-amber-300 mb-3">Warnings</flux:heading>
                    <div class="space-y-1">
                        @foreach($dryRunResults['warnings'] as $warning)
                            <div class="text-sm text-amber-600 dark:text-amber-400">• {{ $warning }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- GS1 Barcode Info -->
            @if(($dryRunResults['barcodes_needed'] ?? 0) > 0)
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <flux:heading size="sm" class="text-blue-700 dark:text-blue-300 mb-2">GS1 Barcode Assignment</flux:heading>
                    <div class="text-sm text-blue-600 dark:text-blue-400">
                        {{ $dryRunResults['barcodes_needed'] }} variants will receive automatic GS1 barcodes
                    </div>
                </div>
            @endif

            <div class="flex justify-between">
                <flux:button variant="ghost" wire:click="resetImport">
                    Start Over
                </flux:button>
                
                <div class="flex gap-3">
                    @if(($dryRunResults['error_rows'] ?? 0) === 0)
                        <flux:button variant="primary" wire:click="startActualImport">
                            Start Import
                        </flux:button>
                    @else
                        <div class="text-sm text-red-600 dark:text-red-400 flex items-center">
                            Fix errors before importing
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($step === 5)
        <!-- Step 5: Import Progress -->
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6" 
             wire:poll.1s="checkImportProgress">
            <flux:heading size="lg" class="mb-4">Step 5: Import in Progress</flux:heading>
            
            <div class="space-y-6">
                <!-- Progress Bar -->
                <div>
                    <div class="flex justify-between text-sm mb-2">
                        <span>Import Progress</span>
                        <span>{{ $importProgress }}%</span>
                    </div>
                    <div class="w-full bg-zinc-200 dark:bg-zinc-700 rounded-full h-3">
                        <div class="bg-blue-600 h-3 rounded-full transition-all duration-500 ease-out" 
                             style="width: {{ $importProgress }}%"></div>
                    </div>
                </div>

                <!-- Detailed Status -->
                <div class="text-center">
                    <div class="text-lg font-medium mb-2">{{ $importStatus }}</div>
                    @if($importProgress < 100)
                        <div class="text-blue-600 animate-pulse">
                            Processing your import...
                        </div>
                        <!-- Live update indicator -->
                        <div class="text-xs text-zinc-500 mt-2">
                            <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse mr-1"></span>
                            Auto-updating every second
                        </div>
                    @else
                        <div class="text-green-600 font-medium">
                            ✓ Import completed successfully!
                        </div>
                    @endif
                </div>

                <!-- Progress Details (if available) -->
                @if(isset($importProgressDetails) && !empty($importProgressDetails))
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="text-sm text-blue-700 dark:text-blue-300">
                            <div class="grid grid-cols-2 gap-4">
                                @if(isset($importProgressDetails['current_sheet']))
                                    <div>
                                        <span class="font-medium">Current Sheet:</span>
                                        {{ $importProgressDetails['current_sheet'] }}
                                    </div>
                                @endif
                                @if(isset($importProgressDetails['processed_rows']))
                                    <div>
                                        <span class="font-medium">Rows Processed:</span>
                                        {{ $importProgressDetails['processed_rows'] }}
                                    </div>
                                @endif
                                @if(isset($importProgressDetails['total_rows']))
                                    <div>
                                        <span class="font-medium">Total Rows:</span>
                                        {{ $importProgressDetails['total_rows'] }}
                                    </div>
                                @endif
                                @if(isset($importProgressDetails['products_created']))
                                    <div>
                                        <span class="font-medium">Products Created:</span>
                                        {{ $importProgressDetails['products_created'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Warnings (if any) -->
                @if(count($importWarnings) > 0)
                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                        <flux:heading size="sm" class="text-amber-700 dark:text-amber-300 mb-2">Import Warnings</flux:heading>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            @foreach($importWarnings as $warning)
                                <div class="text-sm text-amber-600 dark:text-amber-400">
                                    ⚠ {{ $warning }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Errors (if any) -->
                @if(count($importErrors) > 0)
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                        <flux:heading size="sm" class="text-red-700 dark:text-red-300 mb-2">Import Errors</flux:heading>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            @foreach($importErrors as $error)
                                <div class="text-sm text-red-600 dark:text-red-400">
                                    • {{ $error }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex justify-center">
                    @if($importProgress >= 100)
                        <flux:button variant="primary" wire:click="resetImport">
                            Import Another File
                        </flux:button>
                    @else
                        <flux:button variant="ghost" wire:click="resetImport">
                            Cancel Import
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
