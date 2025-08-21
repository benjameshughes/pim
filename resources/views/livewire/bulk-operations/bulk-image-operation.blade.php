{{-- 🚀 BULK IMAGE OPERATION - Full-Page Dedicated Interface --}}
<div class="space-y-6" x-data="{ 
    isProcessing: @entangle('isProcessing'),
    progress: @entangle('processingProgress'),
    errorMessage: @entangle('errorMessage'),
    operationType: @entangle('imageData.operation_type'),
    uploadedCount: @entangle('uploadedFilesCount'),
    previewFiles: @entangle('imagePreviews')
}">
    
    {{-- Breadcrumb Navigation --}}
    <div class="flex items-center gap-2 text-sm text-gray-500">
        <flux:button wire:click="backToBulkOperations" variant="ghost" size="sm" class="hover:text-gray-700">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-1" />
            Bulk Operations
        </flux:button>
        <span>/</span>
        <span class="text-gray-900 dark:text-white font-medium">Manage Images</span>
    </div>

    {{-- Error Message --}}
    <div x-show="errorMessage" x-transition class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600" />
                <span x-text="errorMessage" class="text-red-800 font-medium"></span>
            </div>
            <flux:button wire:click="clearError" variant="ghost" size="sm" class="text-red-600">
                <flux:icon name="x" class="w-4 h-4" />
            </flux:button>
        </div>
    </div>

    {{-- Processing Indicator --}}
    <div x-show="isProcessing" x-transition class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-center gap-3">
            <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
            <span>Processing images for {{ $this->selectedCount }} {{ $this->targetDisplayName }}...</span>
            <div class="bg-gray-200 rounded-full h-3 flex-1">
                <div class="bg-blue-600 h-3 rounded-full transition-all duration-300" 
                     :style="`width: ${progress}%`"></div>
            </div>
            <span x-text="`${progress}%`" class="text-sm font-medium text-blue-600 min-w-12 text-right"></span>
        </div>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Manage Images
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">
                Bulk image operations for {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}
            </p>
        </div>
        
        {{-- Upload Stats --}}
        <div class="text-right" x-show="uploadedCount > 0">
            <div class="text-sm text-gray-500">Files Selected</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white" x-text="uploadedCount"></div>
            <div class="text-xs text-gray-500">{{ $this->estimatedStorageSize }}</div>
        </div>
    </div>

    {{-- Main Content Cards --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Operation Configuration --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Image Operation</flux:card.title>
                <flux:card.description>Choose what you want to do with images</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Operation Type Selection --}}
                <flux:radio.group wire:model.live="imageData.operation_type">
                    <flux:radio value="upload_assign">Upload & assign new images</flux:radio>
                    <flux:radio value="clear_images">Clear all existing images</flux:radio>
                    <flux:radio value="reorganize" disabled>Reorganize existing images (Coming Soon)</flux:radio>
                </flux:radio.group>
                
                {{-- Upload & Assign Options --}}
                <template x-if="operationType === 'upload_assign'">
                    <div class="space-y-4 border-t pt-4">
                        <h4 class="font-medium text-gray-900 dark:text-white">Upload Settings</h4>
                        
                        {{-- File Upload --}}
                        <div class="space-y-3">
                            <flux:input 
                                type="file" 
                                wire:model.live="imageFiles"
                                multiple
                                accept="image/*"
                                label="Select Images"
                                description="Select up to 10 images (JPEG, PNG, GIF, WebP, max 10MB each)"
                            />
                            
                            {{-- Upload Preview --}}
                            <template x-if="uploadedCount > 0">
                                <div class="grid grid-cols-3 gap-2 mt-3">
                                    <template x-for="(preview, index) in previewFiles" :key="index">
                                        <div class="relative group">
                                            <img :src="preview" class="w-full h-20 object-cover rounded border" />
                                            <button 
                                                @click="$wire.removeUploadedFile(index)"
                                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-xs opacity-0 group-hover:opacity-100 transition-opacity"
                                            >×</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Assignment Type --}}
                        <flux:select wire:model.live="imageData.assignment_type" label="Assignment Type">
                            <option value="primary">Set as primary image</option>
                            <option value="gallery">Add to image gallery</option>
                            <option value="all">Primary + gallery</option>
                        </flux:select>

                        {{-- Replace Existing --}}
                        <flux:checkbox 
                            wire:model.live="imageData.replace_existing"
                            label="Replace existing images"
                            description="Remove current images before adding new ones"
                        />
                    </div>
                </template>

                {{-- Clear Images Confirmation --}}
                <template x-if="operationType === 'clear_images'">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 mt-0.5" />
                            <div>
                                <div class="font-medium text-red-800">Warning: Destructive Operation</div>
                                <div class="text-red-700 text-sm mt-1">
                                    This will permanently delete all images for the selected {{ strtolower($this->targetDisplayName) }}. 
                                    This action cannot be undone.
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </flux:card.content>
        </flux:card>

        {{-- Preview & Summary --}}
        <flux:card>
            <flux:card.header>
                <flux:card.title>Preview & Summary</flux:card.title>
                <flux:card.description>Review your image operation before applying</flux:card.description>
            </flux:card.header>
            
            <flux:card.content class="space-y-6">
                {{-- Operation Preview --}}
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm text-blue-600 font-medium mb-2">Operation Summary</div>
                    <div class="space-y-2">
                        <template x-if="operationType === 'upload_assign'">
                            <div class="text-sm">
                                <div>✓ Upload <span x-text="uploadedCount"></span> new images</div>
                                <div>✓ Assign to {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}</div>
                                <div x-show="$wire.imageData.replace_existing">✓ Replace existing images</div>
                            </div>
                        </template>
                        <template x-if="operationType === 'clear_images'">
                            <div class="text-sm text-red-700">
                                <div>🗑️ Remove all existing images</div>
                                <div>🗑️ Clear from {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}</div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Selection Summary --}}
                <div class="border rounded-lg p-4 space-y-3">
                    <h3 class="font-medium text-gray-900 dark:text-white">Selection Summary</h3>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Target:</span>
                            <div class="font-medium">{{ $this->targetDisplayName }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Count:</span>
                            <div class="font-medium">{{ $this->selectedCount }} items</div>
                        </div>
                    </div>
                </div>

                {{-- Storage & Performance Info --}}
                <template x-if="operationType === 'upload_assign' && uploadedCount > 0">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Storage Impact</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Total Size:</span>
                                <div class="font-medium">{{ $this->estimatedStorageSize }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">Images per Item:</span>
                                <div class="font-medium" x-text="uploadedCount"></div>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Images will be optimized during upload
                        </div>
                    </div>
                </template>

                {{-- Important Notes --}}
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <flux:icon name="exclamation-triangle" class="w-5 h-5 text-yellow-600 mt-0.5" />
                        <div class="text-sm">
                            <div class="font-medium text-yellow-800">Important Notes</div>
                            <div class="text-yellow-700 mt-1 space-y-1">
                                <div>• Large files may take longer to process</div>
                                <div>• Images will be automatically optimized</div>
                                <div>• Operation affects {{ $this->selectedCount }} {{ strtolower($this->targetDisplayName) }}</div>
                                <template x-if="operationType === 'clear_images'">
                                    <div class="font-medium">• Deleted images cannot be recovered</div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </flux:card.content>
        </flux:card>
    </div>

    {{-- Action Buttons --}}
    <div class="flex justify-between items-center">
        <flux:button wire:click="backToBulkOperations" variant="ghost" ::disabled="isProcessing">
            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
            Back to Selection
        </flux:button>
        
        <flux:button 
            wire:click="applyBulkImages" 
            variant="primary"
            size="base"
            ::disabled="isProcessing || (operationType === 'upload_assign' && uploadedCount === 0)"
        >
            <template x-if="!isProcessing">
                <div class="flex items-center gap-2">
                    <flux:icon name="photo" class="w-4 h-4" />
                    <span x-text="operationType === 'upload_assign' ? `Upload to ${$wire.selectedCount} ${$wire.targetDisplayName}` : `Apply to ${$wire.selectedCount} ${$wire.targetDisplayName}`"></span>
                </div>
            </template>
            <template x-if="isProcessing">
                <div class="flex items-center gap-2">
                    <flux:icon name="arrow-path" class="w-4 h-4 animate-spin" />
                    <span>Processing...</span>
                </div>
            </template>
        </flux:button>
    </div>
</div>