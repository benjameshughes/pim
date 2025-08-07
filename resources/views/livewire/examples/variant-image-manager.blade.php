<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <flux:heading size="lg" class="text-zinc-900 dark:text-zinc-50">
                {{ $variant->product->name }} - {{ $variant->color }} {{ $variant->size }}
            </flux:heading>
            <flux:subheading class="text-zinc-600 dark:text-zinc-400">
                Manage variant images across all image types
            </flux:subheading>
        </div>

        <flux:button 
            href="{{ route('pim.variants.view', $variant) }}"
            variant="outline"
            icon="arrow-left"
            wire:navigate
        >
            Back to Variant
        </flux:button>
    </div>

    <!-- Image Type Stats -->
    @if($imageStats['total'] > 0)
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
            <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50 mb-4">
                Image Statistics
            </flux:heading>
            
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">
                        {{ $imageStats['total'] }}
                    </div>
                    <div class="text-sm text-zinc-500">Total Images</div>
                </div>
                
                @foreach($imageStats['by_type'] as $type => $count)
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {{ $count }}
                        </div>
                        <div class="text-sm text-zinc-500">{{ ucfirst($type) }}</div>
                    </div>
                @endforeach
            </div>
            
            <!-- Processing Status -->
            <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                <div class="grid grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-lg font-semibold text-yellow-600">{{ $imageStats['processing_stats']['pending'] }}</div>
                        <div class="text-xs text-zinc-500">Pending</div>
                    </div>
                    <div>
                        <div class="text-lg font-semibold text-blue-600">{{ $imageStats['processing_stats']['processing'] }}</div>
                        <div class="text-xs text-zinc-500">Processing</div>
                    </div>
                    <div>
                        <div class="text-lg font-semibold text-green-600">{{ $imageStats['processing_stats']['completed'] }}</div>
                        <div class="text-xs text-zinc-500">Completed</div>
                    </div>
                    <div>
                        <div class="text-lg font-semibold text-red-600">{{ $imageStats['processing_stats']['failed'] }}</div>
                        <div class="text-xs text-zinc-500">Failed</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Image Type Tabs -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden">
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="flex space-x-8 px-6" aria-label="Image Types">
                @foreach($imageTypes as $type => $config)
                    <button
                        wire:click="setActiveImageType('{{ $type }}')"
                        class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150
                               {{ $activeImageType === $type 
                                  ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                                  : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300 hover:border-zinc-300 dark:hover:border-zinc-600' }}"
                    >
                        {{ $config['label'] }}
                        @if(isset($imageStats['by_type'][$type]))
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                       {{ $activeImageType === $type 
                                          ? 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400' 
                                          : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                {{ $imageStats['by_type'][$type] }}
                            </span>
                        @endif
                    </button>
                @endforeach
            </nav>
        </div>

        <!-- Active Image Type Description -->
        <div class="px-6 py-4 bg-zinc-50/50 dark:bg-zinc-800/20">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-zinc-900 dark:text-zinc-50">
                        {{ $imageTypes[$activeImageType]['label'] }}
                    </flux:heading>
                    <flux:subheading class="text-zinc-600 dark:text-zinc-400 text-sm">
                        {{ $imageTypes[$activeImageType]['description'] }}
                    </flux:subheading>
                </div>
                
                <div class="text-xs text-zinc-500 space-y-1">
                    <div>Max files: {{ $imageTypes[$activeImageType]['max_files'] }}</div>
                    @if(isset($imageTypes[$activeImageType]['max_size']))
                        <div>Max size: {{ number_format($imageTypes[$activeImageType]['max_size'] / 1024, 1) }}MB</div>
                    @endif
                    <div>Reorderable: {{ $imageTypes[$activeImageType]['allow_reorder'] ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Uploader Component -->
    <livewire:components.image-uploader 
        :model-type="'variant'"
        :model-id="$variant->id"
        :image-type="$activeImageType"
        :multiple="true"
        :max-files="$uploaderConfig['max_files']"
        :max-size="$uploaderConfig['max_size']"
        :accept-types="$uploaderConfig['accept_types']"
        :process-immediately="$uploaderConfig['process_immediately']"
        :show-preview="$uploaderConfig['show_preview']"
        :allow-reorder="$uploaderConfig['allow_reorder']"
        :show-upload-area="$uploaderConfig['show_upload_area']"
        :show-existing-images="$uploaderConfig['show_existing_images']"
        :view-mode="$uploaderConfig['view_mode']"
        wire:key="uploader-{{ $activeImageType }}"
    />
</div>