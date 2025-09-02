<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Livewire\Component;

class ImageLibraryHeader extends Component
{
    // Search and filtering properties
    public string $search = '';
    public string $selectedFolder = '';
    public string $selectedTag = '';
    public string $filterBy = 'all'; // all, attached, unattached
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // For bulk actions
    public array $selectedImages = [];
    public bool $showBulkActions = false;
    
    // Modal states
    public bool $showBulkMoveModal = false;
    public bool $showBulkTagModal = false;
    public string $bulkMoveTargetFolder = '';
    public string $bulkTagInput = '';
    public string $bulkTagOperation = 'add';

    protected $listeners = [
        'filterChanged' => 'handleFilterChange',
        'selection-changed' => 'handleSelectionChange',
        'selection-updated' => 'handleSelectionChange',
    ];

    public function mount(
        $search = '',
        $selectedFolder = '',
        $selectedTag = '',
        $filterBy = 'all',
        $sortBy = 'created_at',
        $sortDirection = 'desc'
    ) {
        $this->search = $search ?: '';
        $this->selectedFolder = $selectedFolder ?: '';
        $this->selectedTag = $selectedTag ?: '';
        $this->filterBy = $filterBy ?: 'all';
        $this->sortBy = $sortBy ?: 'created_at';
        $this->sortDirection = $sortDirection ?: 'desc';
    }

    /**
     * 🔍 CLEAR FILTERS
     */
    public function clearFilters()
    {
        $this->reset(['search', 'selectedFolder', 'selectedTag', 'filterBy']);
        $this->dispatch('filters-cleared');
    }

    /**
     * 🔄 TOGGLE SORT DIRECTION
     */
    public function toggleSortDirection()
    {
        $this->sortDirection = $this->sortDirection === 'desc' ? 'asc' : 'desc';
        $this->dispatch('sort-changed', [
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection
        ]);
    }

    /**
     * 📤 OPEN UPLOAD MODAL
     */
    public function openUploadModal()
    {
        $this->dispatch('show-upload-modal');
    }

    /**
     * 📊 GET IMAGES FOR STATS
     */
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

        return $query->paginate(24);
    }

    /**
     * 📁 GET FOLDERS FOR DROPDOWN
     */
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

    /**
     * 🏷️ GET TAGS FOR DROPDOWN
     */
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

    /**
     * 📡 HANDLE FILTER CHANGES
     */
    public function updatedSearch()
    {
        $this->dispatch('filter-changed', ['search' => $this->search]);
    }

    public function updatedSelectedFolder()
    {
        $this->dispatch('filter-changed', ['selectedFolder' => $this->selectedFolder]);
    }

    public function updatedSelectedTag()
    {
        $this->dispatch('filter-changed', ['selectedTag' => $this->selectedTag]);
    }

    public function updatedFilterBy()
    {
        $this->dispatch('filter-changed', ['filterBy' => $this->filterBy]);
    }

    public function updatedSortBy()
    {
        $this->dispatch('sort-changed', [
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection
        ]);
    }

    /**
     * 🎯 HANDLE SELECTION CHANGES (for bulk actions)
     */
    public function handleSelectionChange($selectedImages)
    {
        \Log::info('ImageLibraryHeader received selection-changed event', ['selectedImages' => $selectedImages]);
        $this->selectedImages = is_array($selectedImages) ? $selectedImages : [];
        $this->showBulkActions = !empty($this->selectedImages);
        \Log::info('ImageLibraryHeader showBulkActions set to', ['showBulkActions' => $this->showBulkActions]);
    }

    /**
     * 🗑️ BULK DELETE (placeholder for implementation)
     */
    public function bulkDelete()
    {
        if (empty($this->selectedImages)) {
            return;
        }

        $this->dispatch('bulk-action-requested', [
            'action' => 'delete',
            'images' => $this->selectedImages
        ]);
    }

    /**
     * 📁 OPEN BULK MOVE MODAL
     */
    public function openBulkMoveModal()
    {
        if (empty($this->selectedImages)) {
            return;
        }
        
        $this->bulkMoveTargetFolder = '';
        $this->showBulkMoveModal = true;
    }

    /**
     * 📁 EXECUTE BULK MOVE
     */
    public function executeBulkMove()
    {
        if (empty($this->selectedImages) || empty(trim($this->bulkMoveTargetFolder))) {
            return;
        }

        $this->dispatch('bulk-action-requested', [
            'action' => 'move',
            'images' => $this->selectedImages,
            'targetFolder' => trim($this->bulkMoveTargetFolder)
        ]);
        
        $this->showBulkMoveModal = false;
        $this->bulkMoveTargetFolder = '';
    }

    /**
     * 🏷️ OPEN BULK TAG MODAL
     */
    public function openBulkTagModal()
    {
        if (empty($this->selectedImages)) {
            return;
        }
        
        $this->bulkTagInput = '';
        $this->bulkTagOperation = 'add';
        $this->showBulkTagModal = true;
    }

    /**
     * 🏷️ EXECUTE BULK TAG
     */
    public function executeBulkTag()
    {
        if (empty($this->selectedImages) || empty(trim($this->bulkTagInput))) {
            return;
        }

        $this->dispatch('bulk-action-requested', [
            'action' => 'tag',
            'images' => $this->selectedImages,
            'tags' => trim($this->bulkTagInput),
            'operation' => $this->bulkTagOperation
        ]);
        
        $this->showBulkTagModal = false;
        $this->bulkTagInput = '';
    }

    public function render()
    {
        return view('livewire.images.image-library-header');
    }
}