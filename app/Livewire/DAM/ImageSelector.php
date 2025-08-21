<?php

namespace App\Livewire\DAM;

use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 🔗✨ IMAGE SELECTOR - LINK DAM IMAGES TO PRODUCTS ✨🔗
 * 
 * Modal component for selecting and linking existing DAM images to products/variants
 * Features: search, filter, multi-select, and instant linking
 */
class ImageSelector extends Component
{
    use WithPagination;

    // Modal state
    public bool $show = false;
    public ?Model $targetModel = null;
    public string $targetType = ''; // 'product' or 'variant'
    public int $targetId = 0;

    // Selection state
    /** @var int[] */
    public array $selectedImageIds = [];
    public int $maxSelection = 10;
    public bool $allowMultiple = true;
    public bool $setPrimaryOnSingle = true;

    // Search and filtering
    public string $search = '';
    public string $selectedFolder = '';
    /** @var string[] */
    public array $selectedTags = [];
    public string $filterBy = 'unattached'; // Focus on unlinked images by default
    public string $sortBy = 'created_at';
    public string $sortDirection = 'desc';

    // Results
    /** @var array<string, string> */
    protected $listeners = [
        'open-image-selector' => 'openSelector',
        'close-image-selector' => 'closeSelector',
    ];

    /**
     * 🎯 OPEN SELECTOR
     */
    /**
     * @param array<string, mixed> $options
     */
    public function openSelector(string $targetType, int $targetId, array $options = []): void
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        
        // Load target model
        $this->targetModel = match($targetType) {
            'product' => Product::findOrFail($targetId),
            'variant' => ProductVariant::findOrFail($targetId),
            default => throw new \InvalidArgumentException("Invalid target type: {$targetType}"),
        };

        // Apply options
        $this->maxSelection = $options['maxSelection'] ?? 10;
        $this->allowMultiple = $options['allowMultiple'] ?? true;
        $this->setPrimaryOnSingle = $options['setPrimaryOnSingle'] ?? true;

        // Reset state
        $this->selectedImageIds = [];
        $this->search = '';
        $this->selectedFolder = '';
        $this->selectedTags = [];
        $this->resetPage();

        $this->show = true;
    }

    /**
     * 🔚 CLOSE SELECTOR
     */
    public function closeSelector(): void
    {
        $this->show = false;
        $this->reset(['targetModel', 'targetType', 'targetId', 'selectedImageIds']);
    }

    /**
     * 📊 GET AVAILABLE IMAGES
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
        if (!empty($this->selectedTags)) {
            $query->withAnyTag($this->selectedTags);
        }

        // Apply attachment filter
        match ($this->filterBy) {
            'attached' => $query->attached(),
            'unattached' => $query->unattached(),
            'mine' => $query->byUser((int) auth()->id()),
            default => null, // Show all
        };

        // Exclude images already attached to this specific model
        if ($this->targetModel) {
            $query->where(function ($q) {
                $q->where('imageable_type', '!=', get_class($this->targetModel))
                  ->orWhere('imageable_id', '!=', $this->targetModel->id)
                  ->orWhereNull('imageable_type')
                  ->orWhereNull('imageable_id');
            });
        }

        // Apply sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->paginate(12);
    }

    /**
     * 📁 GET AVAILABLE FOLDERS
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
     * 🔄 TOGGLE IMAGE SELECTION
     */
    public function toggleImageSelection(int $imageId): void
    {
        if (in_array($imageId, $this->selectedImageIds)) {
            // Remove from selection
            $this->selectedImageIds = array_values(
                array_filter($this->selectedImageIds, fn($id) => $id !== $imageId)
            );
        } else {
            // Add to selection (check limits)
            if (!$this->allowMultiple) {
                $this->selectedImageIds = [$imageId];
            } elseif (count($this->selectedImageIds) < $this->maxSelection) {
                $this->selectedImageIds[] = $imageId;
            }
        }
    }

    /**
     * ✅ CONFIRM SELECTION AND LINK IMAGES
     */
    public function confirmSelection(): void
    {
        if (empty($this->selectedImageIds) || !$this->targetModel) {
            return;
        }

        $images = Image::whereIn('id', $this->selectedImageIds)->get();
        $linkedCount = 0;

        foreach ($images as $index => $image) {
            // Link image to target model
            $image->attachTo($this->targetModel);

            // Set first image as primary if requested and no primary exists
            if ($this->setPrimaryOnSingle && $index === 0 && method_exists($this->targetModel, 'images')) {
                $existingPrimary = $this->targetModel->images()->primary()->first();
                if (!$existingPrimary) {
                    $image->update(['is_primary' => true]);
                }
            }

            $linkedCount++;
        }

        // Dispatch success event
        $this->dispatch('images-linked', [
            'count' => $linkedCount,
            'targetType' => $this->targetType,
            'targetId' => $this->targetId,
        ]);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "{$linkedCount} image(s) linked successfully!",
        ]);

        $this->closeSelector();
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
     * 📊 GET SELECTION INFO
     */
    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function selectionInfo(): array
    {
        return [
            'count' => count($this->selectedImageIds),
            'max' => $this->maxSelection,
            'canSelectMore' => count($this->selectedImageIds) < $this->maxSelection,
            'hasSelection' => !empty($this->selectedImageIds),
        ];
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.d-a-m.image-selector');
    }
}