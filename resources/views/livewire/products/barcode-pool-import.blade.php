<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <flux:heading size="xl">Barcode Pool Import</flux:heading>
        <flux:subheading>Import GS1 barcodes with Clean Slate Strategy for legacy archive management</flux:subheading>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-blue-600">{{ number_format($poolStats['total'] ?? 0) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Total Barcodes</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-green-600">{{ number_format($poolStats['available'] ?? 0) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Available for Assignment</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-orange-600">{{ number_format($poolStats['assigned'] ?? 0) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Currently Assigned</div>
        </div>
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <div class="text-2xl font-bold text-red-600">{{ number_format($poolStats['legacy_archive'] ?? 0) }}</div>
            <div class="text-sm text-zinc-600 dark:text-zinc-400">Legacy Archive</div>
        </div>
    </div>

    <!-- Clean Slate Strategy Explanation -->
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6 mb-8">
        <div class="flex items-start space-x-3">
            <div class="flex-shrink-0">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div>
                <flux:heading size="sm" class="text-blue-800 dark:text-blue-200">Clean Slate Strategy</flux:heading>
                <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">
                    Import your GS1 barcode spreadsheet with quality issue management. Barcodes below the threshold are marked as "legacy archive" and never assigned to new products, while clean barcodes above the threshold are available for assignment.
                </div>
            </div>
        </div>
    </div>

    <!-- Main Import Form -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-8">
        <flux:heading size="lg" class="mb-4">Import Configuration</flux:heading>
        
        <form wire:submit="importBarcodes" class="space-y-6">
            <!-- File Upload Section -->
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                <flux:heading size="sm" class="mb-4">File Selection</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- File Upload -->
                    <flux:field>
                        <flux:label>Select Barcode File</flux:label>
                        <input 
                            type="file" 
                            wire:model="file" 
                            accept=".xlsx,.xls,.csv,.txt"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                            @if($importing) disabled @endif
                        >
                        <flux:error name="file" />
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Supports Excel (.xlsx, .xls), CSV, or text files (one barcode per line)
                        </div>
                    </flux:field>

                    <!-- Barcode Type -->
                    <flux:field>
                        <flux:label>Barcode Type</flux:label>
                        <flux:select wire:model="barcodeType" :disabled="$importing">
                            @foreach($barcodeTypes as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="barcodeType" />
                    </flux:field>
                </div>
            </div>

            <!-- Clean Slate Strategy Settings -->
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                <flux:heading size="sm" class="mb-4">Clean Slate Strategy Settings</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Legacy Threshold -->
                    <flux:field>
                        <flux:label>Legacy Threshold</flux:label>
                        <flux:input 
                            type="number" 
                            wire:model="legacyThreshold"
                            placeholder="40000"
                            min="1"
                            :disabled="$importing"
                        />
                        <flux:error name="legacyThreshold" />
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Barcodes below or equal to this number will be marked as legacy archive
                        </div>
                    </flux:field>

                    <!-- Legacy Notes -->
                    <flux:field>
                        <flux:label>Legacy Archive Notes</flux:label>
                        <flux:input 
                            wire:model="legacyNotes"
                            placeholder="Notes for legacy barcodes..."
                            :disabled="$importing"
                        />
                        <flux:error name="legacyNotes" />
                        <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                            Optional notes to explain why these barcodes are archived
                        </div>
                    </flux:field>
                </div>
            </div>

            <!-- Advanced Options -->
            <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="sm">Advanced Options</flux:heading>
                    <flux:button 
                        type="button" 
                        variant="ghost" 
                        size="sm"
                        wire:click="toggleAdvanced"
                    >
                        @if($showAdvanced) Hide @else Show @endif
                    </flux:button>
                </div>

                @if($showAdvanced)
                    <div class="space-y-4">
                        <!-- Clear Existing -->
                        <flux:field>
                            <div class="flex items-center space-x-3">
                                <flux:checkbox wire:model="clearExisting" :disabled="$importing" />
                                <flux:label>Clear existing barcode pool before import</flux:label>
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                WARNING: This will remove all unassigned barcodes from the pool
                            </div>
                        </flux:field>

                        <!-- Validate Format -->
                        <flux:field>
                            <div class="flex items-center space-x-3">
                                <flux:checkbox wire:model="validateFormat" :disabled="$importing" />
                                <flux:label>Validate barcode format</flux:label>
                            </div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                Check if barcodes match the expected format for the selected type
                            </div>
                        </flux:field>
                    </div>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-between">
                <div class="flex space-x-3">
                    <flux:button 
                        type="button" 
                        variant="ghost"
                        wire:click="downloadSampleFile"
                    >
                        Download Sample File
                    </flux:button>
                    
                    @if($poolStats['total'] > 0)
                        <flux:button 
                            type="button" 
                            variant="danger"
                            wire:click="clearPool"
                            wire:confirm="Are you sure you want to clear the barcode pool? This will remove all unassigned barcodes."
                        >
                            Clear Pool
                        </flux:button>
                    @endif
                </div>

                <flux:button 
                    type="submit" 
                    variant="primary"
                    :disabled="!$file || $importing"
                >
                    @if($importing)
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                            <span>Importing...</span>
                        </div>
                    @else
                        Import Barcodes
                    @endif
                </flux:button>
            </div>
        </form>

        <!-- File Upload Progress -->
        <div wire:loading wire:target="file" class="mt-4">
            <div class="flex items-center space-x-2 text-blue-600">
                <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600"></div>
                <span>Uploading file...</span>
            </div>
        </div>
    </div>

    <!-- Import Results -->
    @if($importComplete && $importResults)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Import Results</flux:heading>
                <flux:button 
                    variant="ghost" 
                    size="sm"
                    wire:click="clearResults"
                >
                    Clear Results
                </flux:button>
            </div>

            <!-- Results Summary -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($importResults['summary']['total_imported']) }}</div>
                    <div class="text-sm text-green-600">Total Imported</div>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="text-2xl font-bold text-blue-600">{{ number_format($importResults['summary']['available_for_assignment']) }}</div>
                    <div class="text-sm text-blue-600">Available for Assignment</div>
                </div>
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <div class="text-2xl font-bold text-red-600">{{ number_format($importResults['summary']['legacy_archived']) }}</div>
                    <div class="text-sm text-red-600">Legacy Archived</div>
                </div>
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                    <div class="text-2xl font-bold text-purple-600">{{ $importResults['summary']['success_rate'] }}%</div>
                    <div class="text-sm text-purple-600">Success Rate</div>
                </div>
            </div>

            <!-- Detailed Results -->
            <div class="bg-zinc-50 dark:bg-zinc-900 rounded-lg p-4">
                <div class="text-sm space-y-1">
                    <div><strong>Batch ID:</strong> <code class="bg-zinc-200 dark:bg-zinc-700 px-2 py-1 rounded text-xs">{{ $importResults['batch_id'] }}</code></div>
                    <div><strong>Total Processed:</strong> {{ number_format($importResults['total_processed']) }} barcodes</div>
                    
                    @if(isset($importResults['results']['errors']) && count($importResults['results']['errors']) > 0)
                        <div class="mt-4">
                            <details class="cursor-pointer">
                                <summary class="font-medium text-red-600">View Errors ({{ count($importResults['results']['errors']) }})</summary>
                                <div class="mt-2 max-h-40 overflow-y-auto">
                                    @foreach(array_slice($importResults['results']['errors'], 0, 20) as $error)
                                        <div class="text-red-600 text-xs">• {{ $error }}</div>
                                    @endforeach
                                    @if(count($importResults['results']['errors']) > 20)
                                        <div class="text-red-600 text-xs italic">... and {{ count($importResults['results']['errors']) - 20 }} more errors</div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Recent Import Batches -->
    @if(count($batchStats['batches']) > 0)
        <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="lg" class="mb-4">Recent Import Batches</flux:heading>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Batch ID
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Import Date
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                                Count
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach(array_slice($batchStats['batches'], 0, 10) as $batch)
                            <tr>
                                <td class="px-4 py-2">
                                    <code class="bg-zinc-100 dark:bg-zinc-700 px-2 py-1 rounded text-xs">
                                        {{ Str::limit($batch['import_batch_id'], 8) }}
                                    </code>
                                </td>
                                <td class="px-4 py-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ \Carbon\Carbon::parse($batch['created_at'])->format('M j, Y g:i A') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-zinc-900 dark:text-zinc-100">
                                    {{ number_format($batch['count']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>