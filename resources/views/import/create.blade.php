<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create Import') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <!-- Upload Form -->
                    <div x-data="importForm()" x-init="init()">
                        <form @submit="submitForm" enctype="multipart/form-data" class="space-y-6">
                            @csrf
                            
                            <!-- File Upload -->
                            <div>
                                <label for="file" class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                                <div 
                                    @drop="handleDrop"
                                    @dragover.prevent
                                    @dragenter.prevent="isDragging = true"
                                    @dragleave="isDragging = false"
                                    :class="{ 'border-indigo-500 bg-indigo-50': isDragging || selectedFile, 'border-gray-300': !isDragging && !selectedFile }"
                                    class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed rounded-md hover:border-gray-400 transition-colors"
                                >
                                    <div class="space-y-1 text-center">
                                        <div class="mx-auto h-12 w-12 text-gray-400">
                                            <span class="text-3xl">📄</span>
                                        </div>
                                        <div class="flex text-sm text-gray-600">
                                            <label for="file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload a file</span>
                                                <input 
                                                    id="file" 
                                                    name="file" 
                                                    type="file" 
                                                    accept=".csv,.xlsx,.xls" 
                                                    class="sr-only" 
                                                    required 
                                                    @change="handleFileSelect"
                                                    x-ref="fileInput"
                                                >
                                            </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs text-gray-500">CSV, XLSX, XLS up to 10MB</p>
                                    </div>
                                </div>
                                <div x-show="selectedFile" x-transition class="mt-2 text-sm text-gray-600">
                                    Selected: <strong x-text="selectedFile?.name"></strong> 
                                    (<span x-text="fileSize"></span> MB)
                                </div>
                                @error('file')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Import Mode -->
                            <div>
                                <label for="import_mode" class="block text-sm font-medium text-gray-700 mb-2">Import Mode</label>
                                <select 
                                    id="import_mode" 
                                    name="import_mode" 
                                    x-model="formData.import_mode"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md"
                                >
                                    @foreach($importModes as $mode => $description)
                                        <option value="{{ $mode }}">
                                            {{ ucwords(str_replace('_', ' ', $mode)) }} - {{ $description }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('import_mode')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Advanced Options -->
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Advanced Options</h4>
                                <div class="space-y-4">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input 
                                                id="extract_attributes" 
                                                name="extract_attributes" 
                                                type="checkbox" 
                                                value="1" 
                                                x-model="formData.extract_attributes"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="extract_attributes" class="font-medium text-gray-700">Extract Attributes</label>
                                            <p class="text-gray-500">Automatically extract colors and sizes from product names</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input 
                                                id="detect_made_to_measure" 
                                                name="detect_made_to_measure" 
                                                type="checkbox" 
                                                value="1" 
                                                x-model="formData.detect_made_to_measure"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="detect_made_to_measure" class="font-medium text-gray-700">Detect Made to Measure</label>
                                            <p class="text-gray-500">Identify made-to-measure products automatically</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input 
                                                id="group_by_sku" 
                                                name="group_by_sku" 
                                                type="checkbox" 
                                                value="1" 
                                                x-model="formData.group_by_sku"
                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded"
                                            >
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="group_by_sku" class="font-medium text-gray-700">Group by SKU Pattern</label>
                                            <p class="text-gray-500">Automatically group variants using SKU patterns (001-001 format)</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="chunk_size" class="block text-sm font-medium text-gray-700">Chunk Size</label>
                                        <input 
                                            type="number" 
                                            id="chunk_size" 
                                            name="chunk_size" 
                                            min="10" 
                                            max="500" 
                                            x-model="formData.chunk_size"
                                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        >
                                        <p class="mt-2 text-sm text-gray-500">Number of rows to process in each batch (10-500)</p>
                                        @error('chunk_size')
                                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                        <!-- Info Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 pt-6">
                            <!-- Supported Formats Info -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">Supported Formats</h4>
                                <ul class="text-sm text-blue-800">
                                    @foreach($supportedFormats as $format)
                                        <li>• {{ strtoupper($format) }} files</li>
                                    @endforeach
                                    <li>• Maximum size: 10MB</li>
                                </ul>
                            </div>

                            <!-- Process Flow -->
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-gray-900 mb-2">Import Process</h4>
                                <ol class="text-sm text-gray-700 space-y-1">
                                    <li>1. File analysis and validation</li>
                                    <li>2. Column mapping configuration</li>
                                    <li>3. Dry run preview</li>
                                    <li>4. Full import processing</li>
                                </ol>
                            </div>
                        </div>

                            <!-- Action Buttons -->
                            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                                <a href="{{ route('import.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                    Back to Dashboard
                                </a>
                                
                                <button 
                                    type="submit" 
                                    :disabled="isSubmitting || !selectedFile"
                                    :class="{ 'opacity-50 cursor-not-allowed': isSubmitting || !selectedFile }"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded inline-flex items-center"
                                >
                                    <span x-text="isSubmitting ? 'Uploading...' : 'Start Import'"></span>
                                    <span x-show="isSubmitting" class="ml-2">
                                        <span class="animate-spin h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
                                    </span>
                                </button>
                            </div>
                        </form>
                </div>
            </div>
        </div>
                    </div>
    </div>

    <script>
    function importForm() {
        return {
            isDragging: false,
            selectedFile: null,
            fileSize: '',
            isSubmitting: false,
            formData: {
                import_mode: 'create_or_update',
                extract_attributes: false,
                detect_made_to_measure: false,
                group_by_sku: false,
                chunk_size: 100
            },

            init() {
                // Component initialization
            },

            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    this.selectedFile = file;
                    this.fileSize = (file.size / 1024 / 1024).toFixed(2);
                } else {
                    this.selectedFile = null;
                    this.fileSize = '';
                }
            },

            handleDrop(event) {
                event.preventDefault();
                event.stopPropagation();
                this.isDragging = false;
                
                const files = event.dataTransfer.files;
                if (files.length > 0) {
                    const file = files[0];
                    // Validate file type
                    const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
                    if (allowedTypes.includes(file.type) || file.name.match(/\.(csv|xls|xlsx)$/i)) {
                        this.selectedFile = file;
                        this.fileSize = (file.size / 1024 / 1024).toFixed(2);
                        this.$refs.fileInput.files = files;
                    } else {
                        alert('Please select a CSV, XLS, or XLSX file.');
                    }
                }
            },

            async submitForm(event) {
                event.preventDefault();
                
                if (!this.selectedFile) {
                    alert('Please select a file to upload.');
                    return;
                }

                this.isSubmitting = true;

                try {
                    // Create FormData
                    const formData = new FormData();
                    formData.append('file', this.selectedFile);
                    formData.append('import_mode', this.formData.import_mode);
                    formData.append('chunk_size', this.formData.chunk_size);
                    
                    if (this.formData.extract_attributes) {
                        formData.append('extract_attributes', '1');
                    }
                    if (this.formData.detect_made_to_measure) {
                        formData.append('detect_made_to_measure', '1');
                    }
                    if (this.formData.group_by_sku) {
                        formData.append('group_by_sku', '1');
                    }
                    
                    // Add CSRF token
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                                   || document.querySelector('input[name="_token"]')?.value;
                    if (csrfToken) {
                        formData.append('_token', csrfToken);
                    }

                    const response = await fetch('{{ route("import.store") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Redirect to session page
                        window.location.href = data.redirect_url;
                    } else {
                        alert('Import failed: ' + data.error);
                        this.isSubmitting = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while uploading the file.');
                    this.isSubmitting = false;
                }
            }
        };
    }
    </script>
</x-layouts.app>