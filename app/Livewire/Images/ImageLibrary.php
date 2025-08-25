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


    /**
     * 📤 OPEN UPLOAD MODAL
     */
    public function openUploadModal()
    {
        $this->showUploadModal = true;
    }

    /**
     * 📤 UPLOAD IMAGES
     */
    public function uploadImages(ImageUploadService $uploadService)
    {
        $this->validate([
            'newImages.*' => 'required|image|max:10240', // 10MB
        ]);

        try {
            // Convert tags string to array if needed
            $tags = is_string($this->uploadMetadata['tags']) 
                ? array_filter(array_map('trim', explode(',', $this->uploadMetadata['tags'])))
                : ($this->uploadMetadata['tags'] ?? []);

            $metadata = [
                'title' => $this->uploadMetadata['title'] ?: null,
                'alt_text' => $this->uploadMetadata['alt_text'] ?: null,
                'description' => $this->uploadMetadata['description'] ?: null,
                'folder' => $this->uploadMetadata['folder'] ?: null,
                'tags' => $tags,
            ];

            $uploadService->uploadMultiple($this->newImages, $metadata);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => count($this->newImages) . ' images uploaded successfully! 🎉'
            ]);

            $this->reset(['newImages', 'uploadMetadata']);
            $this->showUploadModal = false;
            $this->resetPage();

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Upload failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 🗑️ DELETE IMAGE
     */
    public function deleteImage(int $imageId, ImageUploadService $uploadService)
    {
        $image = Image::find($imageId);
        if (!$image) return;

        try {
            $uploadService->delete($image);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image deleted successfully! 🗑️'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error', 
                'message' => 'Delete failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 🏷️ ADD TAG TO NEW IMAGES
     */
    public function addTag()
    {
        if (empty($this->newTagInput)) return;

        $tag = trim($this->newTagInput);
        if (!in_array($tag, $this->newImageTags)) {
            $this->newImageTags[] = $tag;
        }
        
        $this->newTagInput = '';
    }

    /**
     * 🗑️ REMOVE TAG FROM NEW IMAGES
     */
    public function removeTag(string $tag)
    {
        $this->newImageTags = array_values(array_filter($this->newImageTags, fn($t) => $t !== $tag));
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
     * 📊 GET IMAGES
     */
    public function getImagesProperty()
    {
        $query = Image::query();

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
     * 📋 COPY URL TO CLIPBOARD
     */
    public function copyUrl(string $url): void
    {
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Image URL copied to clipboard!'
        ]);
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
     * 🎨 RENDER
     */
    public function render(): View
    {
        return view('livewire.images.image-library');
    }
}