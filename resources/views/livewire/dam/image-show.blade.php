<div class="space-y-6">
    {{-- ✨ IMAGE HEADER (mirrors ProductShow header) --}}
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-4 mb-2">
                <a href="{{ route('dam.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors">
                    <flux:icon name="arrow-left" class="h-4 w-4" />
                    <span>Back to Library</span>
                </a>
            </div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $image->display_title }}</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 font-mono">{{ $image->filename }}</p>
        </div>
        
        <div class="flex items-center gap-3">
            <flux:button wire:navigate href="{{ route('dam.images.show.edit', $image) }}" variant="primary" icon="pencil">
                Edit
            </flux:button>
            
            <flux:dropdown>
                <flux:button variant="ghost" icon="ellipsis-horizontal" />
                
                <flux:menu>
                    <flux:menu.item wire:click="duplicateImage" icon="document-duplicate">
                        Duplicate Metadata
                    </flux:menu.item>
                    <flux:menu.separator />
                    <flux:menu.item 
                        @click="confirmAction({
                            title: 'Delete Image',
                            message: 'This will permanently delete the image and cannot be undone.\\n\\nAny product attachments will also be removed.',
                            confirmText: 'Yes, Delete It',
                            cancelText: 'Cancel',
                            variant: 'danger',
                            onConfirm: () => $wire.deleteImage()
                        })" 
                        icon="trash" 
                        variant="danger"
                    >
                        Delete Image
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    {{-- 📑 TAB NAVIGATION (mirrors ProductShow tabs) --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
            @foreach($this->imageTabs->toArray($image) as $tab)
                <a href="{{ $tab['url'] }}"
                   class="py-2 px-1 border-b-2 font-medium text-sm whitespace-nowrap flex items-center space-x-2 transition-colors
                          {{ $tab['active'] 
                             ? 'border-blue-500 text-blue-600 dark:text-blue-400' 
                             : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600' }}"
                   @if($tab['wireNavigate']) wire:navigate @endif>
                    
                    <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                    <span>{{ $tab['label'] }}</span>
                    
                    @if(isset($tab['badge']) && $tab['badge'])
                        <flux:badge 
                            :color="$tab['badgeColor'] ?? 'gray'" 
                            size="sm">
                            {{ $tab['badge'] }}
                        </flux:badge>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    {{-- 📋 TAB CONTENT --}}
    @php
        $currentRoute = request()->route()->getName();
        $isEdit = str_contains($currentRoute, '.edit');
        $isAttachments = str_contains($currentRoute, '.attachments');
        $isHistory = str_contains($currentRoute, '.history');
        $isOverview = str_contains($currentRoute, '.overview') || $currentRoute === 'dam.images.show';
    @endphp

    @if($isEdit)
        {{-- ✏️ EDIT TAB CONTENT --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8">
            <livewire:dam.image-edit-form :image="$image" />
        </div>
    @elseif($isAttachments)
        {{-- 🔗 ATTACHMENTS TAB CONTENT --}}
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-8">
                <livewire:dam.image-product-attachment :image="$image" />
            </div>
        </div>
    @elseif($isHistory)
        {{-- 📜 HISTORY TAB CONTENT --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg p-8">
            <div class="text-center py-8">
                <flux:icon name="clock" class="h-12 w-12 text-gray-400 mx-auto mb-4" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">History Coming Soon</h3>
                <p class="text-gray-600 dark:text-gray-400">Image change history will be available here.</p>
            </div>
        </div>
    @else
        {{-- 👁️ OVERVIEW TAB CONTENT (DEFAULT) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Image Preview Column --}}
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6 sticky top-6">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Image Preview</h3>
                    </div>
                    
                    <div class="aspect-square bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden mb-4">
                        <img 
                            src="{{ $image->url }}" 
                            alt="{{ $image->alt_text ?: $image->title ?: 'Image preview' }}"
                            class="w-full h-full object-cover"
                        />
                    </div>
                    
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <div class="flex justify-between">
                            <span>Size:</span>
                            <span class="font-medium">{{ $image->file_size_human }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Dimensions:</span>
                            <span class="font-medium">{{ $image->width }}×{{ $image->height }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Type:</span>
                            <span class="font-medium">{{ $image->mime_type }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Created:</span>
                            <span class="font-medium">{{ $image->created_at?->format('M j, Y') }}</span>
                        </div>
                        @if($image->createdBy)
                        <div class="flex justify-between">
                            <span>Uploaded by:</span>
                            <span class="font-medium">{{ $image->createdBy->name }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Details Column --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Basic Information --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <flux:icon name="info" class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Basic Information
                    </h3>
                    
                    <div class="space-y-3">
                        @if($image->title)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Title</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $image->title }}</dd>
                        </div>
                        @endif
                        
                        @if($image->alt_text)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Alt Text</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $image->alt_text }}</dd>
                        </div>
                        @endif
                        
                        @if($image->description)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $image->description }}</dd>
                        </div>
                        @endif
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Folder</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">{{ $image->folder ?: 'Uncategorized' }}</dd>
                        </div>
                        
                        @if($image->tags && count($image->tags) > 0)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Tags</dt>
                            <dd class="text-sm text-gray-900 dark:text-white">
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($image->tags as $tag)
                                        <flux:badge size="sm" color="gray">{{ $tag }}</flux:badge>
                                    @endforeach
                                </div>
                            </dd>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Attachments Summary --}}
                @if($image->products->count() > 0 || $image->variants->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <flux:icon name="link" class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        Current Attachments
                    </h3>
                    
                    <div class="space-y-2">
                        @foreach($image->products as $product)
                        <div class="flex items-center gap-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <flux:badge variant="primary" size="sm">
                                <flux:icon name="package" class="h-3 w-3" />
                                Product
                            </flux:badge>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">{{ $product->name }}</p>
                                <p class="text-xs font-mono text-gray-500">SKU: {{ $product->parent_sku }}</p>
                            </div>
                        </div>
                        @endforeach
                        
                        @foreach($image->variants as $variant)
                        <div class="flex items-center gap-3 p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                            <flux:badge variant="success" size="sm">
                                <flux:icon name="box" class="h-3 w-3" />
                                Variant
                            </flux:badge>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white text-sm">
                                    {{ $variant->product->name }} - {{ $variant->name }}
                                </p>
                                <p class="text-xs font-mono text-gray-500">SKU: {{ $variant->sku }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <div class="mt-4">
                        <flux:button wire:navigate href="{{ route('dam.images.show.attachments', $image) }}" size="sm" variant="ghost">
                            View All Attachments
                            <flux:icon name="arrow-right" class="h-4 w-4" />
                        </flux:button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    @endif
</div>