<?php

namespace App\Livewire\DAM;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use App\Services\ImageUploadService;

/**
 * 🎨✏️ IMAGE EDIT - DEDICATED FULL-PAGE EDITING
 *
 * Clean, dedicated page for editing image metadata and properties
 * Replaces modal-based editing with better UX and more space
 */
class ImageEdit extends Component
{
    public Image $image;

    // Edit form data
    public string $title = '';

    public string $alt_text = '';

    public string $description = '';

    public string $folder = '';

    /** @var array<string> */
    public array $tags = [];

    // UI state
    public bool $isSaving = false;

    public string $tagsString = ''; // For easier form input

    // Attachment state
    public bool $showAttachSection = false;

    public string $attachmentType = '';

    public int $attachmentId = 0;

    // Listeners
    protected $listeners = [
        'item-selected' => 'handleItemSelected',
        'item-cleared' => 'handleItemCleared',
    ];

    /**
     * 🎪 MOUNT - Initialize with image data
     */
    public function mount(Image $image): void
    {
        $this->image = $image;

        // Load current values
        $this->title = $image->title ?? '';
        $this->alt_text = $image->alt_text ?? '';
        $this->description = $image->description ?? '';
        $this->folder = $image->folder ?? 'uncategorized';
        $this->tags = $image->tags ?? [];

        // Convert tags array to string for easier editing
        $this->tagsString = implode(', ', $this->tags);

        // Check if image is already attached
        $this->loadExistingAttachments();
    }

    /**
     * 💾 SAVE IMAGE CHANGES
     */
    public function save()
    {
        $this->isSaving = true;

        try {
            $this->validate([
                'title' => 'nullable|string|max:255',
                'alt_text' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'folder' => 'required|string|max:255',
                'tagsString' => 'nullable|string|max:500',
            ]);

            // Convert tags string to array
            $tags = array_filter(
                array_map('trim', explode(',', $this->tagsString))
            );

            // Update the image
            $this->image->update([
                'title' => $this->title,
                'alt_text' => $this->alt_text,
                'description' => $this->description,
                'folder' => $this->folder,
                'tags' => $tags,
            ]);

            // Handle attachment if specified
            if ($this->attachmentType && $this->attachmentId) {
                $this->attachToItem();
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image updated successfully!',
            ]);

            // Redirect back to library
            return $this->redirect('/dam', navigate: true);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update image: '.$e->getMessage(),
            ]);
        } finally {
            $this->isSaving = false;
        }
        
        return null;
    }

    /**
     * 🗑️ DELETE IMAGE
     */
    public function deleteImage(\App\Actions\Images\DeleteImageAction $deleteImageAction)
    {
        $imageName = $this->image->display_title;
        
        $deleteImageAction->execute($this->image);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Image '{$imageName}' deleted successfully!",
            'persist' => true
        ]);
        
        $this->redirect(route('dam.index'), navigate: true);
    }

    /**
     * ❌ CANCEL AND GO BACK
     */
    public function cancel(): void
    {
        $this->redirect('/dam', navigate: true);
    }

    /**
     * 📁 GET AVAILABLE FOLDERS FOR DROPDOWN
     *
     * @return array<string>
     */
    public function getFoldersProperty(): array
    {
        // Guard against deleted model - return folders from all images
        return Image::query()
            ->select('folder')
            ->distinct()
            ->whereNotNull('folder')
            ->pluck('folder')
            ->toArray();
    }

    /**
     * 🔗 LOAD EXISTING ATTACHMENTS
     */
    private function loadExistingAttachments(): void
    {
        // Check if attached to any products
        $productAttachment = $this->image->products()->first();
        if ($productAttachment) {
            $this->attachmentType = 'product';
            $this->attachmentId = $productAttachment->id;

            return;
        }

        // Check if attached to any variants
        $variantAttachment = $this->image->variants()->first();
        if ($variantAttachment) {
            $this->attachmentType = 'variant';
            $this->attachmentId = $variantAttachment->id;
        }
    }

    /**
     * 🔗 HANDLE ITEM SELECTED FROM COMBOBOX
     */
    public function handleItemSelected(array $data): void
    {
        $this->attachmentType = $data['type'];
        $this->attachmentId = $data['id'];
    }

    /**
     * ❌ HANDLE ITEM CLEARED FROM COMBOBOX
     */
    public function handleItemCleared(): void
    {
        $this->attachmentType = '';
        $this->attachmentId = 0;
    }

    /**
     * 🔗 ATTACH TO SELECTED PRODUCT/VARIANT
     */
    public function attachToItem(): void
    {
        if (! $this->attachmentType || ! $this->attachmentId) {
            return;
        }

        try {
            // Detach from any existing attachments first
            $this->image->products()->detach();
            $this->image->variants()->detach();

            // Attach to new item
            if ($this->attachmentType === 'product') {
                $this->image->products()->attach($this->attachmentId);
                $product = \App\Models\Product::find($this->attachmentId);
                $message = "Image attached to product: {$product->name}";
            } else {
                $this->image->variants()->attach($this->attachmentId);
                $variant = \App\Models\ProductVariant::with('product')->find($this->attachmentId);
                $message = "Image attached to variant: {$variant->product->name} - {$variant->name}";
            }

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to attach image: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * 🔓 DETACH FROM ALL PRODUCTS/VARIANTS
     */
    public function detachFromAll(): void
    {
        try {
            $this->image->products()->detach();
            $this->image->variants()->detach();

            $this->attachmentType = '';
            $this->attachmentId = 0;

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image detached from all products and variants',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to detach image: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * 📊 GET CURRENT ATTACHMENTS FOR DISPLAY
     *
     * @return array<string, mixed>
     */
    public function getCurrentAttachments(): array
    {
        // Guard against deleted model
        if (!$this->image->exists) {
            return [];
        }
        
        $attachments = [];

        // Get product attachments
        $products = $this->image->products()->get();
        foreach ($products as $product) {
            $attachments[] = [
                'type' => 'product',
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->parent_sku,
            ];
        }

        // Get variant attachments
        $variants = $this->image->variants()->with('product')->get();
        foreach ($variants as $variant) {
            $attachments[] = [
                'type' => 'variant',
                'id' => $variant->id,
                'name' => $variant->product->name.' - '.$variant->name,
                'sku' => $variant->sku,
            ];
        }

        return $attachments;
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.dam.image-edit');
    }
}
