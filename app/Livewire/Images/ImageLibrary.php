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

    // New image metadata
    public string $newImageFolder = '';
    public array $newImageTags = [];
    public string $newTagInput = '';

    // View options
    public string $view = 'grid'; // grid or list
    public int $perPage = 24;
    
    // Modal state
    public bool $showUploadModal = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedFolder' => ['except' => ''],
        'selectedTag' => ['except' => ''],
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
            $metadata = [
                'folder' => $this->newImageFolder ?: null,
                'tags' => $this->newImageTags,
            ];

            $uploadService->uploadMultiple($this->newImages, $metadata);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => count($this->newImages) . ' images uploaded successfully! 🎉'
            ]);

            $this->reset(['newImages', 'newImageFolder', 'newImageTags']);
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
        $this->reset(['search', 'selectedFolder', 'selectedTag']);
        $this->resetPage();
    }

    /**
     * 📊 GET IMAGES
     */
    public function getImagesProperty()
    {
        $query = Image::query()->ordered();

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
     * 🎨 RENDER
     */
    public function render(): View
    {
        return view('livewire.images.image-library');
    }
}