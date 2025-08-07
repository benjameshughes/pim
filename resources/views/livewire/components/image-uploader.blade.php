<div class="space-y-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h3 class="font-medium text-zinc-900 dark:text-zinc-50">{{ ucfirst($imageType) }} Images</h3>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Max {{ $maxFiles }} files • Max {{ number_format($maxSize / 1024, 1) }}MB each</p>
        </div>
    </div>
    
    <!-- Flash Messages -->
    @if(session()->has('message'))
        <div class="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">
            {{ session('message') }}
        </div>
    @endif
    
    @if(session()->has('error'))
        <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
            {{ session('error') }}
        </div>
    @endif
    
    @if($showUploadArea)
        <!-- Upload Area -->
        <div class="border-2 border-dashed border-zinc-300 dark:border-zinc-600 rounded-lg p-6 text-center">
            <div class="space-y-4">
                <div>
                    <svg class="mx-auto h-12 w-12 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="text-zinc-600 dark:text-zinc-400">{{ $uploadText }}</p>
                </div>
                
                <input 
                    type="file" 
                    wire:model="files" 
                    {{ $multiple ? 'multiple' : '' }}
                    accept="{{ $acceptTypesString }}"
                    class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                >
            </div>
        </div>
        
        <!-- Selected Files Preview -->
        @if(!empty($files))
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
                <h4 class="font-medium text-zinc-900 dark:text-zinc-50 mb-3">Selected Files ({{ count($files) }})</h4>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
                    @foreach($files as $index => $file)
                        <div class="relative group border border-zinc-200 dark:border-zinc-700 rounded-lg p-2" wire:key="file-{{ $index }}">
                            @if($showPreview && in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp']))
                                <img 
                                    src="{{ $file->temporaryUrl() }}" 
                                    alt="Preview"
                                    class="w-full h-20 object-cover rounded"
                                >
                            @else
                                <div class="w-full h-20 bg-zinc-100 dark:bg-zinc-700 rounded flex items-center justify-center">
                                    <svg class="w-6 h-6 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                            @endif
                            
                            <p class="text-xs text-zinc-600 dark:text-zinc-400 truncate mt-1" title="{{ $file->getClientOriginalName() }}">
                                {{ $file->getClientOriginalName() }}
                            </p>
                            <p class="text-xs text-zinc-500">{{ number_format($file->getSize() / 1024, 1) }}KB</p>
                            
                            <!-- Remove Button -->
                            <button 
                                wire:click="removeFile({{ $index }})"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition-colors opacity-0 group-hover:opacity-100"
                            >
                                ×
                            </button>
                        </div>
                    @endforeach
                </div>
                
                <!-- Upload Button -->
                <div class="flex justify-end">
                    <button 
                        wire:click="uploadFiles" 
                        @disabled(!$this->canUpload)
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center space-x-2"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="uploadFiles">
                            Upload {{ count($files) }} File{{ count($files) === 1 ? '' : 's' }}
                        </span>
                        <span wire:loading wire:target="uploadFiles" class="flex items-center space-x-2">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Uploading...</span>
                        </span>
                    </button>
                </div>
            </div>
        @endif
    @endif
    
    <!-- Existing Images -->
    @if($showExistingImages && $existingImages->count() > 0)
        <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg p-4">
            <h4 class="font-medium text-zinc-900 dark:text-zinc-50 mb-3">Existing Images ({{ $existingImages->count() }})</h4>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach($existingImages as $image)
                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg p-2">
                        <img 
                            src="{{ $image->getVariantUrl('small') }}" 
                            alt="{{ $image->alt_text }}" 
                            class="w-full h-20 object-cover rounded"
                        >
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 truncate mt-1">{{ $image->original_filename }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>