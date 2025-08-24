<?php

namespace App\Livewire\DAM;

use App\Models\Image;
use App\Services\ImageUploadService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * 🎨✨ IMAGE LIBRARY - DIGITAL ASSET MANAGEMENT SYSTEM ✨🎨
 *
 * Complete DAM interface for managing standalone and linked images
 * Features: upload, search, tag, organize, and link images to products
 */
class ImageLibrary extends Component
{
    use WithFileUploads, WithPagination;

    // Upload functionality
    /** @var \Illuminate\Http\UploadedFile[] */
    public $newImages = [];

    public bool $isUploading = false;

    public int $uploadProgress = 0;

    // Upload progress tracking
    public int $uploadingCount = 0;

    public int $uploadedCount = 0;

    public int $totalToUpload = 0;

    /** @var array<string, string> */
    public array $uploadingFiles = []; // filename => status

    /** @var array<string, string> */
    public array $uploadResults = []; // filename => result message

    // Search and filtering
    public string $search = '';

    public string $selectedFolder = '';

    /** @var string[] */
    public array $selectedTags = [];

    public string $filterBy = 'all'; // all, attached, unattached, mine

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    // Bulk operations
    /** @var int[] */
    public array $selectedImages = [];

    public bool $selectAll = false;

    // Modals and UI state
    public bool $showUploadModal = false;

    public bool $showDeleteConfirmModal = false;

    // Delete confirmation state
    /** @var array<string, mixed> */
    public array $pendingDeleteAction = [];

    // Upload metadata
    /** @var array<string, mixed> */
    public array $uploadMetadata = [
        'title' => '',
        'alt_text' => '',
        'description' => '',
        'folder' => 'uncategorized',
        'tags' => [],
    ];

    // Bulk operations
    /** @var array<string, mixed> */
    public array $bulkAction = [
        'type' => '',
        'folder' => '',
        'tags' => [],
    ];

    // Services
    protected ?ImageUploadService $imageUploadService = null;

    // Listeners for floating action bar and upload modal
    protected $listeners = [
        'floating-action-execute' => 'handleFloatingAction',
        'floating-action-clear-selection' => 'clearSelection',
        'open-upload-modal' => 'openUploadModal',
    ];

    /**
     * 🛠️ Get Image Upload Service
     */
    protected function getImageUploadService(): ImageUploadService
    {
        if (! $this->imageUploadService) {
            $this->imageUploadService = new ImageUploadService;
        }

        return $this->imageUploadService;
    }

    /**
     * 📊 GET IMAGES WITH FILTERS AND PAGINATION
     */
    /**
     * @return LengthAwarePaginator<Image>
     */
    #[Computed]
    public function images(): LengthAwarePaginator
    {
        $query = Image::query();

        // Apply search filter
        if ($this->search) {
            $query->search($this->search);
        }

        // Apply folder filter
        if ($this->selectedFolder) {
            $query->inFolder($this->selectedFolder);
        }

        // Apply tag filters
        if (! empty($this->selectedTags)) {
            $query->withAnyTag($this->selectedTags);
        }

        // Apply attachment filter
        match ($this->filterBy) {
            'attached' => $query->attached(),
            'unattached' => $query->unattached(),
            'mine' => $query->byUser((int) auth()->id()),
            default => null, // Show all
        };

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate(24);
    }

    /**
     * 📁 GET AVAILABLE FOLDERS
     *
     * @return string[]
     */
    #[Computed]
    public function folders(): array
    {
        return Image::query()
            ->select('folder')
            ->distinct()
            ->whereNotNull('folder')
            ->pluck('folder')
            ->toArray();
    }

    /**
     * 🏷️ GET AVAILABLE TAGS
     *
     * @return string[]
     */
    #[Computed]
    public function availableTags(): array
    {
        $tags = Image::query()
            ->whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->values()
            ->toArray();

        return array_filter($tags);
    }

    /**
     * 📤 OPEN UPLOAD MODAL
     */
    public function openUploadModal(): void
    {
        // Reset progress state when opening modal
        $this->resetUploadProgress();
        $this->showUploadModal = true;
    }

    /**
     * 🔄 RESET UPLOAD PROGRESS
     */
    private function resetUploadProgress(): void
    {
        $this->uploadingCount = 0;
        $this->uploadedCount = 0;
        $this->totalToUpload = 0;
        $this->uploadingFiles = [];
        $this->uploadResults = [];
        $this->isUploading = false;
    }

    /**
     * 📋 COPY IMAGE URL TO CLIPBOARD
     */
    public function copyUrl(string $url): void
    {
        $this->dispatch('success', 'Image URL copied to clipboard!');
    }

    /**
     * 📤 UPLOAD NEW IMAGES
     */
    public function uploadImages(): void
    {
        $this->validate([
            'newImages' => 'required|array|min:1|max:10',
            'newImages.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            'uploadMetadata.folder' => 'required|string|max:255',
        ]);

        // Initialize upload progress
        $this->isUploading = true;
        $this->totalToUpload = count($this->newImages);
        $this->uploadedCount = 0;
        $this->uploadingCount = 0;
        $this->uploadingFiles = [];
        $this->uploadResults = [];

        try {
            // Convert tags string to array if needed
            if (isset($this->uploadMetadata['tags']) && is_string($this->uploadMetadata['tags'])) {
                $this->uploadMetadata['tags'] = array_filter(
                    array_map('trim', explode(',', $this->uploadMetadata['tags']))
                );
            }

            $successCount = 0;
            $errorCount = 0;

            // Process each file individually for progress tracking
            foreach ($this->newImages as $index => $file) {
                $filename = $file->getClientOriginalName();

                // Mark as currently uploading
                $this->uploadingFiles[$filename] = 'uploading';
                $this->uploadingCount++;

                try {
                    // Upload single file
                    $this->getImageUploadService()->uploadStandalone(
                        [$file],
                        $this->uploadMetadata
                    );

                    // Mark as completed
                    $this->uploadResults[$filename] = 'success';
                    $successCount++;

                } catch (\Exception $e) {
                    // Mark as failed
                    $this->uploadResults[$filename] = 'error: '.$e->getMessage();
                    $errorCount++;
                }

                // Update progress
                unset($this->uploadingFiles[$filename]);
                $this->uploadingCount--;
                $this->uploadedCount++;

                // Brief pause for UI updates (optional)
                usleep(100000); // 0.1 second
            }

            // Show final notification
            if ($errorCount === 0) {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => "Successfully uploaded {$successCount} image".($successCount === 1 ? '' : 's').'!',
                ]);
            } elseif ($successCount === 0) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'All uploads failed. Please try again.',
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => "Uploaded {$successCount} image".($successCount === 1 ? '' : 's')." but {$errorCount} failed.",
                ]);
            }

            // Reset after successful completion
            if ($errorCount === 0) {
                $this->reset(['newImages', 'uploadMetadata', 'showUploadModal']);
                $this->resetPage();
            }

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Upload failed: '.$e->getMessage(),
            ]);
        } finally {
            $this->isUploading = false;
            $this->uploadingCount = 0;
        }
    }

    /**
     * 🗑️ DELETE IMAGE
     */
    public function deleteImage(int $imageId, \App\Actions\Images\DeleteImageAction $deleteImageAction): void
    {
        $image = Image::findOrFail($imageId);
        $imageName = $image->display_title;
        
        $deleteImageAction->execute($image);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Image '{$imageName}' deleted successfully!",
        ]);

        $this->resetPage();
    }

    /**
     * 📊 CHECK IF ALL IMAGES ARE SELECTED
     */
    public function allSelected(): bool
    {
        $totalImages = $this->images->count();

        return $totalImages > 0 && count($this->selectedImages) === $totalImages;
    }

    /**
     * 📊 CHECK IF SOME IMAGES ARE SELECTED
     */
    public function someSelected(): bool
    {
        $selectedCount = count($this->selectedImages);

        return $selectedCount > 0 && $selectedCount < $this->images->count();
    }

    /**
     * ✅ HANDLE SELECT ALL CHANGES
     */
    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            // Select all images
            $this->selectedImages = $this->images->pluck('id')->toArray();
        } else {
            // Deselect all images
            $this->selectedImages = [];
        }
    }

    /**
     * ✅ HANDLE INDIVIDUAL SELECTION CHANGES
     */
    public function updatedSelectedImages(): void
    {
        // Update selectAll state based on current selection
        $this->selectAll = $this->allSelected();
    }

    /**
     * 📦 APPLY BULK ACTION
     */
    public function applyBulkAction(): void
    {
        if (empty($this->selectedImages) || empty($this->bulkAction['type'])) {
            return;
        }

        $images = Image::whereIn('id', $this->selectedImages)->get();
        $count = $images->count();

        match ($this->bulkAction['type']) {
            'move_folder' => $this->bulkMoveToFolder($images),
            'add_tags' => $this->bulkAddTags($images),
            'remove_tags' => $this->bulkRemoveTags($images),
            'delete' => $this->bulkDelete($images),
        };

        // Only show generic success message for non-delete actions (delete handles its own notifications)
        if ($this->bulkAction['type'] !== 'delete') {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Bulk action applied to {$count} image(s)!",
            ]);
        }

        $this->reset(['selectedImages', 'bulkAction']);
        $this->resetPage();
    }

    /**
     * 📁 BULK MOVE TO FOLDER
     */
    protected function bulkMoveToFolder($images): void
    {
        foreach ($images as $image) {
            $image->moveToFolder($this->bulkAction['folder']);
        }
    }

    /**
     * 🏷️ BULK ADD TAGS
     */
    protected function bulkAddTags($images): void
    {
        $tagsToAdd = array_filter(
            array_map('trim', explode(',', $this->bulkAction['tags_to_add'] ?? ''))
        );

        foreach ($images as $image) {
            foreach ($tagsToAdd as $tag) {
                $image->addTag($tag);
            }
        }
    }

    /**
     * 🏷️ BULK REMOVE TAGS
     */
    protected function bulkRemoveTags($images): void
    {
        $tagsToRemove = array_filter(
            array_map('trim', explode(',', $this->bulkAction['tags_to_remove'] ?? ''))
        );

        foreach ($images as $image) {
            foreach ($tagsToRemove as $tag) {
                $image->removeTag($tag);
            }
        }
    }

    /**
     * 🗑️ BULK DELETE
     */
    protected function bulkDelete($images): void
    {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        $deleteImageAction = app(\App\Actions\Images\DeleteImageAction::class);

        foreach ($images as $image) {
            try {
                $deleteImageAction->execute($image);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = "Failed to delete {$image->display_title}: ".$e->getMessage();
            }
        }

        // Show appropriate toast notification
        if ($errorCount === 0) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Successfully deleted {$successCount} image".($successCount === 1 ? '' : 's').'!',
            ]);
        } elseif ($successCount === 0) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to delete all images. Please try again.',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => "Deleted {$successCount} image".($successCount === 1 ? '' : 's')." but {$errorCount} failed.",
            ]);
        }
    }

    /**
     * 🎯 HANDLE FLOATING ACTION BAR EXECUTION
     */
    public function handleFloatingAction(array $data): void
    {
        $action = $data['action'];
        $items = $data['items'];

        // For delete actions, show confirmation modal first
        if ($action['type'] === 'delete') {
            $this->pendingDeleteAction = [
                'action' => $action,
                'items' => $items,
            ];
            $this->showDeleteConfirmModal = true;

            return;
        }

        // For other actions, execute immediately
        $this->bulkAction = $action;
        $this->selectedImages = $items;
        $this->applyBulkAction();
    }

    /**
     * ❌ CLEAR SELECTION (FROM FLOATING BAR)
     */
    public function clearSelection(): void
    {
        $this->selectedImages = [];
    }

    /**
     * ✅ CONFIRM BULK DELETE
     */
    public function confirmBulkDelete(): void
    {
        if (empty($this->pendingDeleteAction)) {
            return;
        }

        $action = $this->pendingDeleteAction['action'];
        $items = $this->pendingDeleteAction['items'];

        // Set the bulk action data
        $this->bulkAction = $action;
        $this->selectedImages = $items;

        // Execute the delete
        $this->applyBulkAction();

        // Reset delete state
        $this->cancelBulkDelete();
    }

    /**
     * ❌ CANCEL BULK DELETE
     */
    public function cancelBulkDelete(): void
    {
        $this->pendingDeleteAction = [];
        $this->showDeleteConfirmModal = false;
    }

    /**
     * 🔍 UPDATE FILTERS
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedFolder(): void
    {
        $this->resetPage();
    }

    public function updatedFilterBy(): void
    {
        $this->resetPage();
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.dam.image-library');
    }
}
