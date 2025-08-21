{{-- 🚀 SIMPLE PRODUCT IMPORT - Clean & Fast! --}}
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Import Products</h2>
        <div class="text-sm text-gray-500">
            Step {{ match($step) {
                'upload' => '1',
                'mapping' => '2', 
                'importing' => '3',
                'complete' => '4'
            } }} of 4
        </div>
    </div>

    {{-- Progress Steps --}}
    <div class="flex items-center space-x-4 mb-8">
        @foreach(['upload', 'mapping', 'importing', 'complete'] as $stepName)
            <div class="flex items-center">
                <div class="flex items-center justify-center w-8 h-8 rounded-full {{ 
                    $step == $stepName ? 'bg-blue-500 text-white' : 
                    (array_search($step, ['upload', 'mapping', 'importing', 'complete']) > array_search($stepName, ['upload', 'mapping', 'importing', 'complete']) ? 'bg-green-500 text-white' : 'bg-gray-300 text-gray-600')
                }}">
                    {{ $loop->iteration }}
                </div>
                <span class="ml-2 text-sm font-medium {{ $step == $stepName ? 'text-blue-600' : 'text-gray-500' }}">
                    {{ ucfirst($stepName) }}
                </span>
                @unless($loop->last)
                    <div class="w-12 h-0.5 ml-4 {{ 
                        array_search($step, ['upload', 'mapping', 'importing', 'complete']) > $loop->index ? 'bg-green-500' : 'bg-gray-300'
                    }}"></div>
                @endunless
            </div>
        @endforeach
    </div>

    {{-- Step 1: File Upload --}}
    @if($step === 'upload')
        <flux:card>
            <flux:card.header>
                <flux:card.title>Upload CSV File</flux:card.title>
            </flux:card.header>
            
            <flux:card.content class="space-y-4">
                <div>
                    <flux:input 
                        type="file" 
                        wire:model="file" 
                        accept=".csv,.xlsx,.xls"
                        class="w-full"
                    />
                    @error('file') 
                        <flux:error class="mt-1">{{ $message }}</flux:error>
                    @enderror
                </div>
                
                <div wire:loading wire:target="file" class="flex items-center space-x-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                    <span class="text-sm text-gray-600">Analyzing file...</span>
                </div>
                
                <div class="text-sm text-gray-500">
                    <strong>Supported formats:</strong> CSV, Excel (.xlsx, .xls)<br>
                    <strong>Max size:</strong> 10MB<br>
                    <strong>Expected columns:</strong> SKU, Title, Barcode, Price, Brand
                </div>
            </flux:card.content>
        </flux:card>
    @endif

    {{-- Step 2: Column Mapping --}}
    @if($step === 'mapping')
        <flux:card>
            <flux:card.header>
                <flux:card.title>Map Columns</flux:card.title>
                <flux:card.description>Match your CSV columns to product fields</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                @foreach(['sku' => 'SKU (Required)', 'title' => 'Product Title (Required)', 'barcode' => 'Barcode', 'price' => 'Price', 'brand' => 'Brand'] as $field => $label)
                    <div class="grid grid-cols-3 gap-4 items-start">
                        <div class="space-y-1">
                            <flux:label>{{ $label }}</flux:label>
                            @if(str_contains($label, 'Required'))
                                <span class="text-xs text-red-500">Required</span>
                            @endif
                        </div>
                        
                        <div>
                            <flux:select wire:model="mappings.{{ $field }}">
                                @foreach($columnOptions as $value => $text)
                                    <option value="{{ $value }}">{{ $text }}</option>
                                @endforeach
                            </flux:select>
                        </div>
                        
                        <div class="text-xs text-gray-500">
                            @if($mappings[$field] !== '')
                                <strong>Sample data:</strong><br>
                                @foreach($this->getSampleData($mappings[$field]) as $sample)
                                    • {{ $sample }}<br>
                                @endforeach
                            @else
                                <em>Column not mapped</em>
                            @endif
                        </div>
                    </div>
                @endforeach
                
                @error('mappings') 
                    <flux:error>{{ $message }}</flux:error>
                @enderror
                
                <div class="flex justify-between pt-4 border-t">
                    <flux:button wire:click="startOver" variant="ghost">
                        Start Over
                    </flux:button>
                    <flux:button wire:click="executeImport" variant="primary">
                        Start Import
                    </flux:button>
                </div>
            </flux:card.content>
        </flux:card>
    @endif

    {{-- Step 3: Importing --}}
    @if($step === 'importing')
        <flux:card>
            <flux:card.header>
                <flux:card.title>Importing Products</flux:card.title>
                <flux:card.description>Processing your CSV file...</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-4">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-300" style="width: {{ $progress }}%"></div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">{{ $progress }}%</div>
                    <div class="text-sm text-gray-600">Processing rows...</div>
                </div>
                
                <div class="flex items-center justify-center space-x-2">
                    <flux:icon name="arrow-path" class="w-5 h-5 animate-spin text-blue-500" />
                    <span class="text-sm text-gray-600">Please wait while we import your products</span>
                </div>
            </flux:card.content>
        </flux:card>
    @endif

    {{-- Step 4: Results --}}
    @if($step === 'complete' && $results)
        <flux:card>
            <flux:card.header>
                <flux:card.title class="{{ $results['success'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $results['success'] ? '✅ Import Complete!' : '❌ Import Failed' }}
                </flux:card.title>
            </flux:card.header>
            
            <flux:card.content class="space-y-4">
                @if($results['success'])
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-green-600">{{ $results['created_products'] }}</div>
                            <div class="text-sm text-green-600">Products Created</div>
                        </div>
                        <div class="text-center p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-emerald-600">{{ $results['updated_products'] ?? 0 }}</div>
                            <div class="text-sm text-emerald-600">Products Updated</div>
                        </div>
                        <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600">{{ $results['created_variants'] }}</div>
                            <div class="text-sm text-blue-600">Variants Created</div>
                        </div>
                        <div class="text-center p-4 bg-sky-50 dark:bg-sky-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-sky-600">{{ $results['updated_variants'] ?? 0 }}</div>
                            <div class="text-sm text-sky-600">Variants Updated</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600">{{ $results['skipped_rows'] }}</div>
                            <div class="text-sm text-yellow-600">Rows Skipped</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600">{{ $results['duration'] }}s</div>
                            <div class="text-sm text-gray-600">Duration</div>
                        </div>
                    </div>
                    
                    @if(!empty($results['errors']))
                        <div class="mt-6">
                            <flux:heading size="md" class="text-yellow-600 mb-2">Warnings</flux:heading>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4 max-h-32 overflow-y-auto">
                                @foreach($results['errors'] as $error)
                                    <div class="text-sm text-yellow-700 dark:text-yellow-300">• {{ $error }}</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                        <div class="text-red-700 dark:text-red-300">
                            {{ $results['message'] }}
                        </div>
                    </div>
                @endif
                
                <div class="flex justify-between pt-4 border-t">
                    <flux:button wire:click="startOver" variant="ghost">
                        Import Another File
                    </flux:button>
                    <flux:button href="{{ route('products.index') }}" variant="primary">
                        View Products
                    </flux:button>
                </div>
            </flux:card.content>
        </flux:card>
    @endif
</div>

@push('scripts')
<script>
    // Listen for import progress updates
    document.addEventListener('livewire:init', () => {
        Livewire.on('import-progress', (event) => {
            console.log('Import progress:', event.progress + '%');
        });
    });
</script>
@endpush