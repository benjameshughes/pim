<?php

namespace App\Livewire\Images;

use App\Models\Image;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * 🔗 IMAGE PRODUCT ATTACHMENT - STANDALONE ATTACHMENT MANAGER
 *
 * Handles attaching/detaching images to products and variants
 * Separated from core image editing for cleaner lifecycle management
 */
class ImageProductAttachment extends Component
{
    public Image $image;

    // Attachment state
    public string $attachmentType = '';
    public int $attachmentId = 0;

    // Listeners
    protected $listeners = [
        'item-selected' => 'handleItemSelected',
        'item-cleared' => 'handleItemCleared',
    ];

    /**
     * 🎪 MOUNT - Initialize with image
     */
    public function mount(Image $image): void
    {
        $this->image = $image;
        $this->loadExistingAttachments();
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
        return view('livewire.images.image-product-attachment');
    }
}