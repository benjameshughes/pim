{{-- 📊 BARCODE CSV IMPORT ✨ --}}
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">
                📤 Import Barcodes
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Upload CSV files and map columns to import barcodes
            </p>
        </div>
        
        {{-- Breadcrumb --}}
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('barcodes.index') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 dark:text-gray-400 dark:hover:text-white">
                        <flux:icon name="bars-2" class="w-3 h-3 mr-2.5" />
                        Barcodes
                    </a>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <flux:icon name="chevron-right" class="w-3 h-3 text-gray-400 mx-1" />
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2 dark:text-gray-400">Import</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>

    {{-- Progress Steps --}}
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                {{-- Step 1 --}}
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $step >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                        1
                    </div>
                    <span class="text-sm {{ $step >= 1 ? 'text-blue-600 font-medium' : 'text-gray-500' }}">Upload</span>
                </div>
                
                <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400" />
                
                {{-- Step 2 --}}
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $step >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                        2
                    </div>
                    <span class="text-sm {{ $step >= 2 ? 'text-blue-600 font-medium' : 'text-gray-500' }}">Map Columns</span>
                </div>
                
                <flux:icon name="chevron-right" class="w-4 h-4 text-gray-400" />
                
                {{-- Step 3 --}}
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $step >= 3 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                        3
                    </div>
                    <span class="text-sm {{ $step >= 3 ? 'text-green-600 font-medium' : 'text-gray-500' }}">Complete</span>
                </div>
            </div>
            
            @if($step > 1)
                <flux:button wire:click="startOver" variant="ghost" size="sm">
                    Start Over
                </flux:button>
            @endif
        </div>

        {{-- Step 1: File Upload --}}
        @if($step === 1)
            <div class="text-center py-8">
                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-8">
                    <flux:icon name="document-arrow-up" class="mx-auto h-12 w-12 text-gray-400" />
                    <div class="mt-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Upload CSV File</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Choose a CSV file containing your barcode data
                        </p>
                    </div>
                    <div class="mt-6">
                        <flux:input 
                            type="file" 
                            wire:model="csvFile" 
                            accept=".csv,.txt"
                            class="w-full"
                        />
                        @error('csvFile') 
                            <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                    
                    <div wire:loading wire:target="csvFile" class="flex items-center justify-center space-x-2 mt-4">
                        <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        <span class="text-sm text-gray-600">Analyzing file...</span>
                    </div>
                    @if ($csvFile)
                        <div class="mt-4 text-sm text-green-600">
                            File selected: {{ $csvFile->getClientOriginalName() }}
                        </div>
                    @endif
                </div>
                
                {{-- Format Guide --}}
                <div class="mt-8 text-left">
                    <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Expected CSV Format</h4>
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <pre class="text-sm text-gray-700 dark:text-gray-300">barcode,sku,title,is_assigned
123456789012,SKU001,Product Barcode,true
123456789013,SKU002,Another Barcode,false</pre>
                    </div>
                    <div class="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <p>• <strong>barcode</strong> - Required. The barcode value</p>
                        <p>• <strong>sku</strong> - Optional. Product SKU to link</p>
                        <p>• <strong>title</strong> - Optional. Description or title</p>
                        <p>• <strong>is_assigned</strong> - Optional. true/false or 1/0</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Step 2: Column Mapping --}}
        @if($step === 2)
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Map CSV Columns</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Map your CSV columns to the barcode database fields. Smart mapping has been applied automatically.
                </p>
                
                {{-- Column Mapping Table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    CSV Column
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Sample Data
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Map To
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($availableColumns as $index => $column)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $column }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        @if(isset($csvData[0][$index]))
                                            <div class="max-w-xs truncate">{{ $csvData[0][$index] }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <flux:select wire:model="columnMapping.{{ $index }}" class="min-w-48">
                                            <option value="">Don't Import</option>
                                            @foreach($databaseColumns as $dbCol => $dbLabel)
                                                <option value="{{ $dbCol }}">{{ $dbLabel }}</option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                {{-- Sample Preview --}}
                @if(count($csvData) > 0)
                    <div class="mt-8">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Preview (First 5 rows)</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-900/50">
                                    <tr>
                                        @foreach($availableColumns as $column)
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                                                {{ $column }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($csvData as $row)
                                        <tr>
                                            @foreach($row as $cell)
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                                    <div class="max-w-32 truncate">{{ $cell }}</div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
                
                <div class="flex justify-end gap-3 mt-6">
                    <flux:button 
                        wire:click="importBarcodes" 
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="importBarcodes"
                    >
                        <span wire:loading.remove wire:target="importBarcodes">Import Barcodes</span>
                        <span wire:loading wire:target="importBarcodes" class="flex items-center gap-2">
                            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                            Starting Import...
                        </span>
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- Step 3: Progress & Results --}}
        @if($step === 3)
            <div>
                @if($isImporting)
                    {{-- Processing State --}}
                    <div class="text-center py-8">
                        <flux:icon name="arrow-path" class="mx-auto h-16 w-16 text-blue-600 animate-spin" />
                        <h3 class="text-2xl font-medium text-gray-900 dark:text-white mt-4">Processing Import...</h3>
                        <p class="text-gray-600 dark:text-gray-400 mt-2">
                            Your barcode import is being processed in the background
                        </p>
                        
                        @if($progressCount > 0)
                            <div class="mt-4">
                                <div class="text-2xl font-bold text-blue-600">{{ number_format($progressCount) }}</div>
                                <div class="text-sm text-blue-700 dark:text-blue-400">Records Processed</div>
                            </div>
                        @endif
                        
                        @if(!empty($importProgress))
                            <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-md mx-auto">
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-blue-600">{{ $importProgress['processed'] ?? 0 }}</div>
                                    <div class="text-sm text-blue-700 dark:text-blue-400">Processed</div>
                                </div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-green-600">{{ $importProgress['imported'] ?? 0 }}</div>
                                    <div class="text-sm text-green-700 dark:text-green-400">Imported</div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                    <div class="text-2xl font-bold text-gray-600">{{ $importProgress['skipped'] ?? 0 }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">Skipped</div>
                                </div>
                            </div>
                        @endif
                        
                        <div class="mt-6">
                            <div class="text-sm text-gray-500 mb-2">
                                Processed: {{ number_format($progressCount) }}@if($totalRows > 0) of {{ number_format($totalRows) }}@endif records
                                @if($totalRows > 0 && $progressCount > 0)
                                    ({{ round(($progressCount / $totalRows) * 100, 1) }}%)
                                @endif
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-1000 ease-out" style="width: {{ $totalRows > 0 && $progressCount > 0 ? min(100, ($progressCount / $totalRows) * 100) : 5 }}%"></div>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- Completed State --}}
                    <div class="text-center py-8">
                        <flux:icon name="check-circle" class="mx-auto h-16 w-16 text-green-600" />
                        <h3 class="text-2xl font-medium text-gray-900 dark:text-white mt-4">Import Complete!</h3>
                        
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-4 max-w-md mx-auto">
                            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ $importResults['imported'] ?? 0 }}</div>
                                <div class="text-sm text-green-700 dark:text-green-400">Imported</div>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-gray-600">{{ $importResults['skipped'] ?? 0 }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Skipped</div>
                            </div>
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                <div class="text-2xl font-bold text-blue-600">{{ $importResults['processed'] ?? 0 }}</div>
                                <div class="text-sm text-blue-700 dark:text-blue-400">Total Processed</div>
                            </div>
                        </div>
                        
                        @if(!empty($importResults['errors']))
                            <div class="mt-8">
                                <h4 class="text-lg font-medium text-red-600 mb-4">Errors ({{ count($importResults['errors']) }})</h4>
                                <div class="bg-red-50 dark:bg-red-900/20 p-4 rounded-lg text-left max-h-60 overflow-y-auto">
                                    <ul class="space-y-1 text-sm text-red-700 dark:text-red-400">
                                        @foreach(array_slice($importResults['errors'], 0, 50) as $error)
                                            <li>• {{ $error }}</li>
                                        @endforeach
                                        @if(count($importResults['errors']) > 50)
                                            <li class="text-gray-500">... and {{ count($importResults['errors']) - 50 }} more errors</li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        @endif
                        
                        <div class="flex justify-center gap-3 mt-8">
                            <flux:button href="{{ route('barcodes.index') }}" variant="primary" wire:navigate>
                                View Barcodes
                            </flux:button>
                            <flux:button wire:click="startOver" variant="ghost">
                                Import More
                            </flux:button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>


