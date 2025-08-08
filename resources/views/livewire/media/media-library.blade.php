<x-page-template 
    title="Media Library"
    :breadcrumbs="[
        ['name' => 'Dashboard', 'url' => route('dashboard')],
        ['name' => 'Media Library']
    ]"
    :actions="[
        [
            'label' => $stats['failed'] > 0 ? 'Retry Failed (' . $stats['failed'] . ')' : null,
            'wire:click' => 'reprocessFailed',
            'variant' => 'outline',
            'icon' => 'arrow-path',
            'visible' => $stats['failed'] > 0
        ],
        [
            'label' => $assignmentMode ? 'Exit Assignment' : 'Assign Mode',
            'wire:click' => 'toggleAssignmentMode',
            'variant' => $assignmentMode ? 'primary' : 'ghost',
            'icon' => 'link'
        ]
    ]"
>
    <x-slot:subtitle>
        Manage images for products and variants
    </x-slot:subtitle>

    <x-slot:stats>
        <x-stats-grid>
            <x-stats-card 
                title="Total Images" 
                :value="number_format($stats['total'])" 
                icon="photo"
                color="zinc" />
            <x-stats-card 
                title="Unassigned" 
                :value="number_format($stats['unassigned'])" 
                icon="exclamation-triangle"
                color="orange" />
            <x-stats-card 
                title="Products" 
                :value="number_format($stats['products'])" 
                icon="cube"
                color="blue" />
            <x-stats-card 
                title="Variants" 
                :value="number_format($stats['variants'])" 
                icon="squares-2x2"
                color="green" />
            <x-stats-card 
                title="Pending" 
                :value="number_format($stats['pending'])" 
                icon="clock"
                color="yellow" />
            <x-stats-card 
                title="Processing" 
                :value="number_format($stats['processing'])" 
                icon="cpu-chip"
                color="blue" />
            <x-stats-card 
                title="Completed" 
                :value="number_format($stats['completed'])" 
                icon="check-circle"
                color="green" />
            <x-stats-card 
                title="Failed" 
                :value="number_format($stats['failed'])" 
                icon="exclamation-circle"
                color="red" />
        </x-stats-grid>
    </x-slot:stats>

    {{-- Assignment Panel and Tabs in Header Slot --}}
    <x-slot:header>
        <!-- Assignment Panel (when in assignment mode) -->
        @if($assignmentMode)
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg" class="text-blue-900 dark:text-blue-100">
                        Assignment Mode
                    </flux:heading>
                    <flux:button variant="ghost" size="sm" wire:click="toggleAssignmentMode">
                        <flux:icon name="x-mark" class="h-4 w-4" />
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Product Assignment -->
                    <div class="space-y-4">
                        <flux:subheading class="text-blue-800 dark:text-blue-200">
                            Assign to Product
                        </flux:subheading>
                        
                        <flux:field>
                            <flux:label>Select Product</flux:label>
                            <flux:select wire:model.live="selectedProductId" placeholder="Choose a product...">
                                @foreach($products as $product)
                                    <flux:select.option value="{{ $product->id }}">
                                        {{ $product->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        @if(!empty($selectedImages) && $selectedProductId)
                            <flux:button 
                                variant="primary" 
                                wire:click="bulkAssignToProduct"
                                class="w-full">
                                <flux:icon name="link" class="h-4 w-4 mr-2" />
                                Assign {{ count($selectedImages) }} Images to Product
                            </flux:button>
                        @endif
                    </div>

                    <!-- Variant Assignment -->
                    <div class="space-y-4">
                        <flux:subheading class="text-blue-800 dark:text-blue-200">
                            Assign to Variant
                        </flux:subheading>
                        
                        <flux:field>
                            <flux:label>Select Product First</flux:label>
                            <flux:select wire:model.live="selectedProductId" placeholder="Choose a product...">
                                @foreach($products as $product)
                                    <flux:select.option value="{{ $product->id }}">
                                        {{ $product->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>

                        @if($selectedProductId && $variants->isNotEmpty())
                            <flux:field>
                                <flux:label>Select Variant</flux:label>
                                <flux:select wire:model.live="selectedVariantId" placeholder="Choose a variant...">
                                    @foreach($variants as $variant)
                                        <flux:select.option value="{{ $variant->id }}">
                                            {{ $variant->sku }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        @endif

                        @if(!empty($selectedImages) && $selectedVariantId)
                            <flux:button 
                                variant="primary" 
                                wire:click="bulkAssignToVariant"
                                class="w-full">
                                <flux:icon name="link" class="h-4 w-4 mr-2" />
                                Assign {{ count($selectedImages) }} Images to Variant
                            </flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Tabs -->
        <div class="border-b border-zinc-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-8">
                <button 
                    wire:click="$set('activeTab', 'library')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'library' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}">
                    <flux:icon name="photo" class="h-4 w-4 mr-2 inline" />
                    Image Library
                </button>
                
                <button 
                    wire:click="$set('activeTab', 'upload')"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'upload' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}">
                    <flux:icon name="cloud-arrow-up" class="h-4 w-4 mr-2 inline" />
                    Bulk Upload
                </button>

                @if($stats['unassigned'] > 0)
                    <button 
                        wire:click="$set('activeTab', 'unassigned')"
                        class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'unassigned' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}">
                        <flux:icon name="exclamation-triangle" class="h-4 w-4 mr-2 inline" />
                        Unassigned ({{ $stats['unassigned'] }})
                    </button>
                @endif

                @if($stats['failed'] > 0)
                    <button 
                        wire:click="$set('activeTab', 'failed')"
                        class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'failed' ? 'border-blue-500 text-blue-600' : 'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300' }}">
                        <flux:icon name="exclamation-circle" class="h-4 w-4 mr-2 inline" />
                        Failed ({{ $stats['failed'] }})
                    </button>
                @endif
            </nav>
        </div>
    </x-slot:header>

    <!-- Tab Content -->
    @if($activeTab === 'library')
        <!-- All Images Gallery -->
        @livewire('media.image-gallery', [
            'layout' => $viewMode,
            'allowReorder' => false,
            'allowDelete' => true,
            'showUploader' => false,
            'bulkMode' => $bulkMode || $assignmentMode,
            'selectedImages' => $assignmentMode ? $selectedImages : []
        ], key('library-gallery'))

    @elseif($activeTab === 'upload')
        <!-- Bulk Upload Interface -->
        <div class="max-w-4xl">
            <flux:heading size="lg" class="mb-4">Bulk Upload</flux:heading>
            <flux:subheading class="text-zinc-500 mb-6">
                Upload multiple images at once. Images will be processed automatically.
            </flux:subheading>
            
            @livewire('media.image-uploader', [
                'multiple' => true,
                'imageType' => 'main',
                'createThumbnails' => true
            ], key('bulk-uploader'))
        </div>

    @elseif($activeTab === 'unassigned')
        <!-- Unassigned Images -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-orange-600 dark:text-orange-400">
                    Unassigned Images ({{ $stats['unassigned'] }})
                </flux:subheading>
                
                @if($stats['unassigned'] > 0)
                    <div class="flex space-x-2">
                        <flux:button variant="outline" wire:click="toggleBulkMode">
                            <flux:icon name="check-circle" class="h-4 w-4 mr-2" />
                            {{ $bulkMode ? 'Exit Bulk' : 'Bulk Select' }}
                        </flux:button>
                        
                        @if($bulkMode && !empty($selectedImages))
                            <flux:button 
                                variant="danger" 
                                wire:click="bulkDelete"
                                wire:confirm="Are you sure you want to delete the selected images? This action cannot be undone.">
                                <flux:icon name="trash" class="h-4 w-4 mr-2" />
                                Delete Selected
                            </flux:button>
                            
                            <flux:button 
                                variant="primary" 
                                wire:click="bulkProcess">
                                <flux:icon name="cpu-chip" class="h-4 w-4 mr-2" />
                                Process Selected
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>

            @livewire('media.image-gallery', [
                'modelType' => null, // This will show unassigned images
                'layout' => $viewMode,
                'allowReorder' => false,
                'allowDelete' => true,
                'showUploader' => true,
                'bulkMode' => $bulkMode || $assignmentMode,
                'selectedImages' => $assignmentMode ? $selectedImages : []
            ], key('unassigned-gallery'))
        </div>

    @elseif($activeTab === 'failed')
        <!-- Failed Processing Images -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:subheading class="text-red-600 dark:text-red-400">
                    Failed Processing ({{ $stats['failed'] }})
                </flux:subheading>
                
                <flux:button variant="primary" wire:click="reprocessFailed">
                    <flux:icon name="arrow-path" class="h-4 w-4 mr-2" />
                    Reprocess All Failed
                </flux:button>
            </div>

            @livewire('media.image-gallery', [
                'layout' => $viewMode,
                'allowReorder' => false,
                'allowDelete' => true,
                'showUploader' => false,
                'filters' => ['failed']
            ], key('failed-gallery'))
        </div>
    @endif

    <x-slot:footer>
        <!-- Flash Messages -->
        @if(session()->has('success'))
            <div class="fixed bottom-4 right-4 z-50" 
                 x-data="{ show: true }" 
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-full">
                <div class="bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <flux:icon name="check-circle" class="h-5 w-5 mr-2" />
                        {{ session('success') }}
                    </div>
                </div>
            </div>
        @endif

        @if(session()->has('error'))
            <div class="fixed bottom-4 right-4 z-50" 
                 x-data="{ show: true }" 
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-full">
                <div class="bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <flux:icon name="exclamation-circle" class="h-5 w-5 mr-2" />
                        {{ session('error') }}
                    </div>
                </div>
            </div>
        @endif

        @if(session()->has('info'))
            <div class="fixed bottom-4 right-4 z-50" 
                 x-data="{ show: true }" 
                 x-show="show"
                 x-init="setTimeout(() => show = false, 5000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-full">
                <div class="bg-blue-500 text-white px-6 py-3 rounded-lg shadow-lg">
                    <div class="flex items-center">
                        <flux:icon name="information-circle" class="h-5 w-5 mr-2" />
                        {{ session('info') }}
                    </div>
                </div>
            </div>
        @endif
    </x-slot:footer>

</x-page-template>