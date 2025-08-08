<div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Column Mapping</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Map your file columns to product fields. {{ $session->original_filename }}
                </p>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-500">
                    {{ $mappingStats['mapped'] }} of {{ $mappingStats['total'] }} columns mapped
                </div>
                <div class="mt-1">
                    <div class="bg-gray-200 rounded-full h-2 w-32">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: {{ $mappingStats['percentage'] }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="mb-6 flex space-x-3">
        <x-flux:button wire:click="autoMap" variant="outline">
            <x-flux:icon.bolt class="w-4 h-4 mr-2" />
            Auto-Map Columns
        </x-flux:button>
        
        <x-flux:button wire:click="clearMapping" variant="ghost">
            <x-flux:icon.x-mark class="w-4 h-4 mr-2" />
            Clear All
        </x-flux:button>
    </div>

    <!-- Mapping Interface -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Column Mapping -->
        <div class="lg:col-span-2">
            <x-flux:card>
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Map File Columns</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Select the corresponding product field for each column in your file.
                    </p>
                </div>

                <div class="divide-y divide-gray-200">
                    @foreach($fileHeaders as $index => $header)
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- File Column Info -->
                                <div>
                                    <div class="flex items-center">
                                        <h4 class="text-sm font-medium text-gray-900">{{ $header }}</h4>
                                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Column {{ $index + 1 }}
                                        </span>
                                    </div>
                                    
                                    @if(isset($sampleData[$index]) && !empty($sampleData[$index]))
                                        <div class="mt-2">
                                            <p class="text-xs text-gray-500 mb-1">Sample values:</p>
                                            <div class="flex flex-wrap gap-1">
                                                @foreach(array_slice($sampleData[$index], 0, 3) as $sample)
                                                    @if(!empty($sample))
                                                        <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-blue-50 text-blue-700">
                                                            {{ Str::limit($sample, 20) }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Field Mapping -->
                                <div>
                                    <x-flux:select 
                                        wire:model="columnMapping.{{ $index }}" 
                                        placeholder="Select field to map to..."
                                        class="w-full"
                                    >
                                        <x-flux:option value="">-- No mapping --</x-flux:option>
                                        @foreach($availableFields as $groupName => $fields)
                                            <optgroup label="{{ $groupName }}">
                                                @foreach($fields as $fieldKey => $fieldInfo)
                                                    <x-flux:option value="{{ $fieldKey }}">
                                                        {{ $fieldInfo['label'] }}
                                                        @if($fieldInfo['required'])
                                                            <span class="text-red-500">*</span>
                                                        @endif
                                                    </x-flux:option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </x-flux:select>
                                    
                                    @if(!empty($columnMapping[$index]))
                                        @php
                                            $fieldInfo = collect($availableFields)->flatten(1)->get($columnMapping[$index]);
                                        @endphp
                                        @if($fieldInfo && !empty($fieldInfo['description']))
                                            <p class="mt-1 text-xs text-gray-500">{{ $fieldInfo['description'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-flux:card>
        </div>

        <!-- Mapping Summary -->
        <div class="lg:col-span-1">
            <div class="space-y-6">
                
                <!-- Progress Card -->
                <x-flux:card class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Mapping Progress</h3>
                    
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Mapped Columns</span>
                                <span class="font-medium">{{ $mappingStats['mapped'] }}/{{ $mappingStats['total'] }}</span>
                            </div>
                            <div class="mt-1 bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all" style="width: {{ $mappingStats['percentage'] }}%"></div>
                            </div>
                        </div>

                        <!-- Required Fields Check -->
                        @php
                            $mappedFields = array_filter($columnMapping);
                            $requiredFields = collect($availableFields)->flatten(1)->where('required', true)->keys();
                            $mappedRequired = $requiredFields->intersect($mappedFields);
                            $missingRequired = $requiredFields->diff($mappedFields);
                        @endphp

                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Required Fields</h4>
                            <div class="space-y-1">
                                @foreach($requiredFields as $field)
                                    @php
                                        $fieldInfo = collect($availableFields)->flatten(1)->get($field);
                                    @endphp
                                    <div class="flex items-center text-sm">
                                        @if(in_array($field, $mappedFields))
                                            <x-flux:icon.check-circle class="h-4 w-4 text-green-500 mr-2" />
                                            <span class="text-green-700">{{ $fieldInfo['label'] }}</span>
                                        @else
                                            <x-flux:icon.x-circle class="h-4 w-4 text-red-500 mr-2" />
                                            <span class="text-red-700">{{ $fieldInfo['label'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </x-flux:card>

                <!-- Mapped Fields Summary -->
                @if(count(array_filter($columnMapping)) > 0)
                    <x-flux:card class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Mapped Fields</h3>
                        
                        <div class="space-y-2">
                            @foreach($columnMapping as $index => $fieldKey)
                                @if(!empty($fieldKey))
                                    @php
                                        $fieldInfo = collect($availableFields)->flatten(1)->get($fieldKey);
                                    @endphp
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">{{ $fileHeaders[$index] }}</span>
                                        <span class="font-medium text-blue-600">{{ $fieldInfo['label'] ?? $fieldKey }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </x-flux:card>
                @endif

                <!-- Actions -->
                <x-flux:card class="p-6">
                    <div class="space-y-4">
                        <x-flux:button 
                            wire:click="saveMapping" 
                            variant="outline" 
                            class="w-full"
                            :disabled="$processing"
                        >
                            @if($processing)
                                <x-flux:icon.arrow-path class="w-4 h-4 mr-2 animate-spin" />
                                Saving...
                            @else
                                <x-flux:icon.document-check class="w-4 h-4 mr-2" />
                                Save Mapping
                            @endif
                        </x-flux:button>

                        <x-flux:button 
                            wire:click="startDryRun" 
                            variant="primary" 
                            class="w-full"
                            :disabled="$processing || count($missingRequired) > 0"
                        >
                            <x-flux:icon.play class="w-4 h-4 mr-2" />
                            Start Dry Run
                        </x-flux:button>

                        @if(count($missingRequired) > 0)
                            <p class="text-xs text-red-600">
                                Map all required fields to continue
                            </p>
                        @endif
                    </div>
                </x-flux:card>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('livewire:initialized', function () {
        @this.on('redirect-after-delay', (event) => {
            setTimeout(() => {
                window.location.href = event.url;
            }, event.delay);
        });
    });
</script>