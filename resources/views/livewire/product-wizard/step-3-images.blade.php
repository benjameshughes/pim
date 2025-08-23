{{-- Step 3: Images --}}
<div class="max-w-7xl mx-auto">
    <div class="bg-white dark:bg-gray-800 rounded-lg p-8 transition-all duration-500 ease-in-out transform"
         x-transition:enter="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-4">
         
        <div class="flex items-center gap-3 mb-6">
            <div class="flex items-center justify-center w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg">
                <flux:icon name="image" class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <h2 class="text-2xl font-semibold text-gray-900 dark:text-white">Product Images</h2>
        </div>
        
        {{-- Image Upload --}}
        <div class="space-y-4">
            <flux:field>
                <flux:label>Upload Images</flux:label>
                <input 
                    type="file" 
                    wire:model="image_files" 
                    multiple 
                    accept="image/*"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    tabindex="1"
                    x-init="$nextTick(() => $el.focus())"
                >
                <flux:description>Select multiple images (JPG, PNG, WEBP, GIF)</flux:description>
            </flux:field>
            
            {{-- Upload Button --}}
            @if(!empty($image_files))
                <flux:button wire:click="uploadImages" size="sm" tabindex="2" x-on:click="console.log('Upload button clicked!')">
                    Upload {{ count($image_files) }} Image(s)
                </flux:button>
            @endif
            
            {{-- Uploaded Images Preview --}}
            @if(!empty($uploaded_images))
                <div class="mt-6">
                    <h4 class="font-medium mb-2">Uploaded Images ({{ count($uploaded_images) }})</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @foreach($uploaded_images as $image)
                            <div class="relative group">
                                <img 
                                    src="{{ $image['url'] }}" 
                                    alt="{{ $image['filename'] }}"
                                    class="w-full h-32 object-cover rounded-lg border"
                                >
                                <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white p-1 rounded-b-lg">
                                    <p class="text-xs truncate">{{ $image['filename'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            {{-- No images message --}}
            @if(empty($uploaded_images) && empty($image_files))
                <div class="p-12 border-2 border-dashed border-green-300 dark:border-green-600 rounded-lg text-center bg-green-50 dark:bg-green-900/10">
                    <div class="flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full mx-auto mb-4">
                        <flux:icon name="image" class="h-8 w-8 text-green-600 dark:text-green-400" />
                    </div>
                    <p class="text-gray-600 dark:text-gray-400 text-lg">No images uploaded yet</p>
                    <p class="text-gray-500 dark:text-gray-500 text-sm mt-1">Images are optional but recommended</p>
                </div>
            @endif
        </div>
    </div>
</div>