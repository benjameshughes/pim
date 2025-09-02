<?php

namespace App\Livewire\Images;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * 🖼️ SIMPLE IMAGE LIBRARY
 *
 * Clean image management with upload, search, tags, folders
 */
class ImageLibrary extends Component
{
    use WithFileUploads, WithPagination;

    // Processing tracking
    public array $processingImages = [];

    // Upload
    /** @var \Illuminate\Http\UploadedFile[] */
    public $newImages = [];

    // Search and filtering
    public string $search = '';

    public string $selectedFolder = '';

    public string $selectedTag = '';

    public string $filterBy = 'all'; // all, attached, unattached

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

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

    public function mount()
    {
        // Authorize managing images
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
                
                // Track which images are processing
                $uploadedImages = $result['data']['uploaded_images'] ?? [];
                $this->processingImages = array_map(fn($img) => $img->id, $uploadedImages);
                
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
     * 🔍 CLEAR FILTERS
     */
    public function clearFilters()
    {
        $this->reset(['search', 'selectedFolder', 'selectedTag', 'filterBy']);
        $this->resetPage();
    }

    /**
     * 📊 GET IMAGES - Only show original images, exclude variants
     */
    public function getImagesProperty()
    {
        $query = Image::query();

        // Only show original images (exclude variants)
        $query->where('folder', '!=', 'variants')
            ->orWhereNull('folder');

        // Search
        if ($this->search) {
            $query->search($this->search);
        }

        // Filter by folder
        if ($this->selectedFolder) {
            $query->inFolder($this->selectedFolder);
        }

        // Filter by tag
        if ($this->selectedTag) {
            $query->withTag($this->selectedTag);
        }

        // Filter by attachment status
        match ($this->filterBy) {
            'attached' => $query->attached(),
            'unattached' => $query->unattached(),
            default => null, // Show all
        };

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate($this->perPage);
    }

    /**
     * 🎨 GET VARIANT COUNT for an image
     */
    public function getVariantCount(int $imageId): int
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$imageId}")
            ->count();
    }

    /**
     * 🖼️ GET VARIANTS for an image
     */
    public function getImageVariants(int $imageId): \Illuminate\Database\Eloquent\Collection
    {
        return Image::where('folder', 'variants')
            ->whereJsonContains('tags', "original-{$imageId}")
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * 📁 GET FOLDERS
     */
    public function getFoldersProperty()
    {
        return Image::select('folder')
            ->whereNotNull('folder')
            ->where('folder', '!=', '')
            ->groupBy('folder')
            ->pluck('folder')
            ->sort()
            ->values();
    }

    /**
     * 🏷️ GET TAGS
     */
    public function getTagsProperty()
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

    /**
     * 📊 GET STATS
     */
    public function getStatsProperty()
    {
        return [
            'total' => Image::count(),
            'unattached' => Image::unattached()->count(),
            'folders' => Image::select('folder')->whereNotNull('folder')->distinct()->count(),
        ];
    }

    /**
     * 🔍 UPDATE FILTERS - Reset pagination when filters change
     */
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

    /**
     * Get real-time event listeners - Individual channels per image
     */
    public function getListeners()
    {
        $listeners = [
            // Legacy events (still using global channel)
            'echo:images,.ImageProcessingCompleted' => 'onImageProcessed',
            'echo:images,.ImageVariantsGenerated' => 'onVariantsGenerated',
            // Skeleton replacement event
            'image-ready' => 'replaceSkeletonWithCard',
        ];
        
        // Add individual listeners for each processing image
        foreach ($this->processingImages as $imageId) {
            $listeners["echo:image-processing.{$imageId},.ImageProcessingProgress"] = 'updateProcessingProgress';
        }
        
        return $listeners;
    }

    /**
     * 📻 HANDLE REAL-TIME PROCESSING PROGRESS - Simplified
     */
    public function updateProcessingProgress($event): void
    {
        $imageId = $event['imageId'] ?? null;
        $status = $event['status'] ?? null;
        $statusLabel = $event['statusLabel'] ?? '';
        $currentAction = $event['currentAction'] ?? '';

        // Only show notifications for images we're currently tracking
        if ($imageId && in_array($imageId, $this->processingImages)) {
            $this->dispatch('notify', [
                'type' => match($status) {
                    'processing' => 'info', 
                    'optimising' => 'info',
                    'success' => 'success',
                    'failed' => 'error',
                    default => 'info'
                },
                'message' => $currentAction ?: $statusLabel,
            ]);
        }

        // Remove from processing array when completed or failed
        if (in_array($status, ['success', 'failed']) && $imageId) {
            $this->processingImages = array_filter($this->processingImages, fn($id) => $id !== $imageId);
            
            // Refresh the page to show updated images
            $this->resetPage();
        }
    }

    /**
     * 📻 IMAGE PROCESSING COMPLETED - Real-time UI update (legacy)
     */
    public function onImageProcessed($event): void
    {
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $event['message'],
        ]);

        // Refresh the images list
        $this->resetPage();
    }

    /**
     * 🎨 VARIANTS GENERATED - Real-time UI update (legacy)
     */
    public function onVariantsGenerated($event): void
    {
        $this->dispatch('notify', [
            'type' => 'success', 
            'message' => $event['message'],
        ]);

        // Legacy event - just refresh to show new variants
        // Job tracking is handled in updateProcessingProgress

        // Refresh the images list to show updated variant counts
        $this->resetPage();
    }

    /**
     * 🔄 REPLACE SKELETON WITH ACTUAL CARD
     */
    public function replaceSkeletonWithCard($event): void
    {
        $imageId = $event['imageId'] ?? null;
        
        // Refresh component to show updated cards
        if ($imageId) {
            $this->resetPage();
        }
    }

    /**
     * 🎨 RENDER
     */
    public function render(): View
    {
        return view('livewire.images.image-library');
    }
}
