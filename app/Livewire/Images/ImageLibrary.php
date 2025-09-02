<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ImageLibrary extends Component
{
    use WithFileUploads, WithPagination;

    // Upload
    /** @var \Illuminate\Http\UploadedFile[] */
    public $newImages = [];

    // Search and filtering (moved to ImageLibraryHeader)
    public string $search = '';
    public string $selectedFolder = '';
    public string $selectedTag = '';
    public string $filterBy = 'all';
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';
    
    // Selection state for bulk actions
    public array $selectedImages = [];
    public bool $selectAll = false;

    // New image metadata (for simple interface)
    public string $newImageFolder = '';

    public array $newImageTags = [];

    public string $newTagInput = '';

    // Upload metadata (for modal interface)
    public array $uploadMetadata = [
        'title' => '',
        'alt_text' => '',
        'description' => '',
        'folder' => '',
        'tags' => '',
    ];

    // View options
    public string $view = 'grid'; // grid or list

    public int $perPage = 24;

    // Modal state
    public bool $showUploadModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedFolder' => ['except' => ''],
        'selectedTag' => ['except' => ''],
        'filterBy' => ['except' => 'all'],
        'view' => ['except' => 'grid'],
        'page' => ['except' => 1],
    ];
    
    protected $listeners = [
        'filter-changed' => 'handleFilterChange',
        'sort-changed' => 'handleSortChange', 
        'filters-cleared' => 'handleFiltersClear',
        'bulk-action-requested' => 'handleBulkAction',
        'image-selection-toggled' => 'toggleImageSelection',
        'delete-image-requested' => 'handleDeleteImageRequest',
    ];

    public function mount()
    {
        $this->authorize('manage-images');
    }

    /**
     * 📤 OPEN UPLOAD MODAL
     */
    public function openUploadModal()
    {
        $this->showUploadModal = true;
    }

    /**
     * 📤 LISTEN FOR SHOW UPLOAD MODAL EVENT
     */
    #[\Livewire\Attributes\On('show-upload-modal')]
    public function showUploadModal()
    {
        $this->openUploadModal();
    }

    /**
     * 📤 UPLOAD IMAGES - Using Action pattern like reprocess button
     */
    public function uploadImages(\App\Actions\Images\UploadImagesAction $uploadAction)
    {
        // Authorize uploading images
        $this->authorize('upload-images');

        $this->validate([
            'newImages.*' => 'required|image|max:10240', // 10MB
        ]);

        try {
            $result = $uploadAction->execute($this->newImages, $this->uploadMetadata);

            if ($result['success']) {
                $uploadCount = $result['data']['upload_count'];
                
                // No tracking needed - images are self-managing
                $uploadedImages = $result['data']['uploaded_images'] ?? [];
                
                $this->dispatch('success', "{$uploadCount} images uploaded! Processing in background... 📤");

                $this->reset(['newImages', 'uploadMetadata']);
                $this->showUploadModal = false;
                $this->resetPage();
            } else {
                $this->dispatch('error', 'Upload failed: ' . $result['message']);
            }

        } catch (\Exception $e) {
            $this->dispatch('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * 🗑️ DELETE IMAGE - Using Action pattern for consistency
     */
    public function deleteImage(int $imageId, \App\Actions\Images\DeleteImageAction $deleteAction)
    {
        // Authorize deleting images
        $this->authorize('delete-images');

        $image = Image::find($imageId);
        if (!$image) {
            return;
        }

        try {
            $deleteAction->execute($image);
            $this->dispatch('success', 'Image deleted successfully! 🗑️');

        } catch (\Exception $e) {
            $this->dispatch('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    /**
     * 🏷️ ADD TAG TO NEW IMAGES
     */
    public function addTag()
    {
        if (empty($this->newTagInput)) {
            return;
        }

        $tag = trim($this->newTagInput);
        if (! in_array($tag, $this->newImageTags)) {
            $this->newImageTags[] = $tag;
        }

        $this->newTagInput = '';
    }

    /**
     * 🗑️ REMOVE TAG FROM NEW IMAGES
     */
    public function removeTag(string $tag)
    {
        $this->newImageTags = array_values(array_filter($this->newImageTags, fn ($t) => $t !== $tag));
    }

    /**
     * 📡 HANDLE FILTER CHANGES FROM HEADER
     */
    public function handleFilterChange($filters)
    {
        foreach ($filters as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        $this->resetPage();
    }
    
    /**
     * 📡 HANDLE SORT CHANGES FROM HEADER
     */
    public function handleSortChange($sortData)
    {
        $this->sortBy = $sortData['sortBy'];
        $this->sortDirection = $sortData['sortDirection'];
        $this->resetPage();
    }
    
    /**
     * 📡 HANDLE FILTERS CLEAR FROM HEADER
     */
    public function handleFiltersClear()
    {
        $this->reset(['search', 'selectedFolder', 'selectedTag', 'filterBy']);
        $this->resetPage();
    }
    
    /**
     * 📡 HANDLE BULK ACTIONS FROM HEADER
     */
    public function handleBulkAction($actionData)
    {
        $action = $actionData['action'];
        $imageIds = $actionData['images'];
        
        match ($action) {
            'delete' => $this->bulkDeleteImages($imageIds),
            'move' => $this->bulkMoveImages($imageIds),
            'tag' => $this->bulkTagImages($imageIds),
            default => null,
        };
    }
    
    /**
     * ☑️ TOGGLE IMAGE SELECTION (from row component)
     */
    public function toggleImageSelection($imageId)
    {
        if (in_array($imageId, $this->selectedImages)) {
            $this->selectedImages = array_values(array_diff($this->selectedImages, [$imageId]));
        } else {
            $this->selectedImages[] = $imageId;
        }
        
        $this->selectAll = count($this->selectedImages) === $this->getImages()->count();
        $this->dispatch('selection-changed', $this->selectedImages);
        $this->dispatch('selection-updated', $this->selectedImages);
    }
    
    // Note: toggleSelectAll is now handled by Alpine.js in the template
    
    /**
     * 🗑️ HANDLE DELETE IMAGE REQUEST (from row component)
     */
    public function handleDeleteImageRequest($imageId)
    {
        $this->deleteImage($imageId, app(\App\Actions\Images\DeleteImageAction::class));
    }
    
    /**
     * 🗑️ BULK DELETE IMAGES
     */
    public function bulkDeleteImages($imageIds, \App\Actions\Images\BulkDeleteImagesAction $bulkDeleteAction)
    {
        $this->authorize('delete-images');
        
        if (empty($imageIds)) {
            $this->dispatch('error', 'No images selected for deletion.');
            return;
        }
        
        try {
            $result = $bulkDeleteAction->execute($imageIds);
            
            if ($result['success']) {
                $deletedCount = $result['data']['deleted_count'];
                $this->dispatch('success', "{$deletedCount} images deleted successfully! 🗑️");
                
                // Clear selection and refresh
                $this->selectedImages = [];
                $this->selectAll = false;
                $this->dispatch('selection-changed', $this->selectedImages);
                $this->resetPage();
            } else {
                $this->dispatch('error', 'Bulk delete failed: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            $this->dispatch('error', 'Bulk delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 📁 BULK MOVE IMAGES
     */
    public function bulkMoveImages($imageIds, $targetFolder = null, \App\Actions\Images\BulkMoveImagesAction $bulkMoveAction = null)
    {
        $this->authorize('manage-images');
        
        if (empty($imageIds)) {
            $this->dispatch('error', 'No images selected for moving.');
            return;
        }
        
        // For now, prompt for folder name (can be enhanced with a modal later)
        if (!$targetFolder) {
            $this->dispatch('info', 'Bulk move will prompt for target folder in next implementation!');
            return;
        }
        
        try {
            $result = $bulkMoveAction->execute($imageIds, $targetFolder);
            
            if ($result['success']) {
                $movedCount = $result['data']['moved_count'];
                $folder = $result['data']['target_folder'];
                $this->dispatch('success', "{$movedCount} images moved to '{$folder}' folder! 📁");
                
                // Clear selection and refresh
                $this->selectedImages = [];
                $this->selectAll = false;
                $this->dispatch('selection-changed', $this->selectedImages);
                $this->resetPage();
            } else {
                $this->dispatch('error', 'Bulk move failed: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            $this->dispatch('error', 'Bulk move failed: ' . $e->getMessage());
        }
    }
    
    /**
     * 🏷️ BULK TAG IMAGES
     */
    public function bulkTagImages($imageIds, $tags = null, $operation = 'add', \App\Actions\Images\BulkTagImagesAction $bulkTagAction = null)
    {
        $this->authorize('manage-images');
        
        if (empty($imageIds)) {
            $this->dispatch('error', 'No images selected for tagging.');
            return;
        }
        
        // For now, prompt for tags (can be enhanced with a modal later)
        if (!$tags) {
            $this->dispatch('info', 'Bulk tag will prompt for tags in next implementation!');
            return;
        }
        
        try {
            $result = $bulkTagAction->execute($imageIds, $tags, $operation);
            
            if ($result['success']) {
                $updatedCount = $result['data']['updated_count'];
                $tagsStr = implode(', ', $result['data']['tags_processed']);
                $this->dispatch('success', "{$operation} tags ({$tagsStr}) on {$updatedCount} images! 🏷️");
                
                // Clear selection and refresh
                $this->selectedImages = [];
                $this->selectAll = false;
                $this->dispatch('selection-changed', $this->selectedImages);
                $this->resetPage();
            } else {
                $this->dispatch('error', 'Bulk tag failed: ' . $result['message']);
            }
            
        } catch (\Exception $e) {
            $this->dispatch('error', 'Bulk tag failed: ' . $e->getMessage());
        }
    }

    public function getImages()
    {
        $query = Image::query();

        $query->where('folder', '!=', 'variants')
            ->orWhereNull('folder');

        if ($this->search) {
            $query->search($this->search);
        }

        if ($this->selectedFolder) {
            $query->inFolder($this->selectedFolder);
        }

        if ($this->selectedTag) {
            $query->withTag($this->selectedTag);
        }

        match ($this->filterBy) {
            'attached' => $query->attached(),
            'unattached' => $query->unattached(),
            default => null,
        };

        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }


    public function getFolders()
    {
        return Image::select('folder')
            ->whereNotNull('folder')
            ->where('folder', '!=', '')
            ->groupBy('folder')
            ->pluck('folder')
            ->sort()
            ->values();
    }

    public function getTags()
    {
        return Image::select('tags')
            ->whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedFolder(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedTag(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBy(): void
    {
        $this->resetPage();
    }

    #[On('echo:images,.App\\Events\\Images\\ImageProcessingCompleted')]
    public function onImageProcessed($event)
    {
        // Debug: Add a log to see if this method is being called
        \Log::info('Image processing completed event received in Livewire', [
            'image_id' => $event['image_id'] ?? 'unknown',
            'status' => $event['status'] ?? 'unknown'
        ]);
        
        // Force a full component refresh
        $this->render();
    }

    public function render(): View
    {
        return view('livewire.images.image-library');
    }
}
