<x-layouts.app.sidebar>
    <x-slot:header>
        Create New Import
    </x-slot:header>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">Import Configuration</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Upload your file and configure how the import should be processed.
                    </p>
                    <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-900">Supported Formats</h4>
                        <ul class="mt-2 text-sm text-blue-800">
                            <li>• CSV files (.csv)</li>
                            <li>• Excel files (.xlsx, .xls)</li>
                            <li>• Maximum size: 10MB</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form wire:submit="submit">
                    <div class="shadow sm:rounded-md sm:overflow-hidden">
                        <div class="px-4 py-5 bg-white space-y-6 sm:p-6">
                            
                            <!-- File Upload -->
                            <div>
                                <x-flux:field>
                                    <x-flux:label for="file">Import File</x-flux:label>
                                    <x-flux:description>
                                        Choose a CSV or Excel file to import
                                    </x-flux:description>
                                    
                                    <div class="mt-2">
                                        <div class="flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md {{ $file ? 'border-green-300 bg-green-50' : 'hover:border-gray-400' }}">
                                            <div class="space-y-1 text-center">
                                                @if($file)
                                                    <x-flux:icon.upload class="mx-auto h-12 w-12 text-green-400" />
                                                    <div class="flex text-sm text-gray-600">
                                                        <span class="font-medium text-green-600">{{ $file->getClientOriginalName() }}</span>
                                                    </div>
                                                    <p class="text-xs text-gray-500">
                                                        {{ number_format($file->getSize() / 1024, 1) }} KB
                                                    </p>
                                                @else
                                                    <x-flux:icon.cloud-upload class="mx-auto h-12 w-12 text-gray-400" />
                                                    <div class="flex text-sm text-gray-600">
                                                        <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                                            <span>Upload a file</span>
                                                            <input wire:model="file" id="file" name="file" type="file" class="sr-only" accept=".csv,.xlsx,.xls">
                                                        </label>
                                                        <p class="pl-1">or drag and drop</p>
                                                    </div>
                                                    <p class="text-xs text-gray-500">CSV, XLSX, XLS up to 10MB</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @error('file')
                                        <x-flux:error>{{ $message }}</x-flux:error>
                                    @enderror
                                </x-flux:field>
                            </div>

                            <!-- Import Mode -->
                            <div>
                                <x-flux:field>
                                    <x-flux:label>Import Mode</x-flux:label>
                                    <x-flux:description>
                                        Choose how to handle existing records
                                    </x-flux:description>
                                    
                                    <div class="mt-4 space-y-4">
                                        @foreach($importModes as $mode => $config)
                                            <div class="flex items-center">
                                                <input wire:model="import_mode" 
                                                       id="mode_{{ $mode }}" 
                                                       name="import_mode" 
                                                       type="radio" 
                                                       value="{{ $mode }}"
                                                       class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                                <label for="mode_{{ $mode }}" class="ml-3 block">
                                                    <span class="text-sm font-medium text-gray-900">{{ $config['name'] }}</span>
                                                    <span class="text-sm text-gray-500 block">{{ $config['description'] }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </x-flux:field>
                            </div>

                            <!-- Processing Options -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Processing Options</h4>
                                <div class="space-y-4">
                                    
                                    <!-- Smart Attribute Extraction -->
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input wire:model="extract_attributes" 
                                                   id="extract_attributes" 
                                                   type="checkbox" 
                                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="extract_attributes" class="font-medium text-gray-700">Smart Attribute Extraction</label>
                                            <p class="text-gray-500">Automatically extract colors, sizes, and other attributes from product names and descriptions.</p>
                                        </div>
                                    </div>

                                    <!-- MTM Detection -->
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input wire:model="detect_made_to_measure" 
                                                   id="detect_made_to_measure" 
                                                   type="checkbox" 
                                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="detect_made_to_measure" class="font-medium text-gray-700">Made-to-Measure Detection</label>
                                            <p class="text-gray-500">Detect MTM, bespoke, and custom products to enhance product titles and attributes.</p>
                                        </div>
                                    </div>

                                    <!-- Digits Only Dimensions -->
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input wire:model="dimensions_digits_only" 
                                                   id="dimensions_digits_only" 
                                                   type="checkbox" 
                                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="dimensions_digits_only" class="font-medium text-gray-700">Digits-Only Dimensions</label>
                                            <p class="text-gray-500">Extract dimensions as pure numbers (150, 200) without units (cm, mm).</p>
                                        </div>
                                    </div>

                                    <!-- SKU Grouping -->
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input wire:model="group_by_sku" 
                                                   id="group_by_sku" 
                                                   type="checkbox" 
                                                   class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="group_by_sku" class="font-medium text-gray-700">SKU-Based Product Grouping</label>
                                            <p class="text-gray-500">Group variants by SKU pattern (e.g., 001-001, 001-002) instead of product names.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Settings -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Advanced Settings</h4>
                                
                                <x-flux:field>
                                    <x-flux:label for="chunk_size">Processing Chunk Size</x-flux:label>
                                    <x-flux:description>
                                        Number of rows to process at once. Lower values use less memory but may be slower.
                                    </x-flux:description>
                                    <x-flux:input 
                                        wire:model="chunk_size" 
                                        id="chunk_size" 
                                        type="number" 
                                        min="10" 
                                        max="500" 
                                        class="mt-1 block w-32" 
                                    />
                                    @error('chunk_size')
                                        <x-flux:error>{{ $message }}</x-flux:error>
                                    @enderror
                                </x-flux:field>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                            <div class="flex justify-between items-center">
                                <x-flux:button href="{{ route('import.index') }}" variant="ghost">
                                    Cancel
                                </x-flux:button>
                                
                                <x-flux:button 
                                    type="submit" 
                                    variant="primary" 
                                    :disabled="$uploading || !$file"
                                >
                                    @if($uploading)
                                        <x-flux:icon.loader class="w-4 h-4 mr-2 animate-spin" />
                                        Creating Import...
                                    @else
                                        <x-flux:icon.cloud-upload class="w-4 h-4 mr-2" />
                                        Create Import
                                    @endif
                                </x-flux:button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Upload Progress -->
        @if($uploading && $uploadProgress > 0)
            <div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3 text-center">
                        <h3 class="text-lg font-medium text-gray-900">Uploading File</h3>
                        <div class="mt-4">
                            <div class="bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $uploadProgress }}%"></div>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">{{ $uploadProgress }}% complete</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:initialized', function () {
            @this.on('import-created', (event) => {
                window.location.href = event.redirect_url;
            });
        });
    </script>
</x-layouts.app.sidebar>