<?php

namespace App\Livewire\Images;

use App\Actions\Images\ReprocessImageAction;
use App\Actions\Images\UpdateImageAction;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Image;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * 🎨✏️ IMAGE EDIT FORM - CLEAN EDITING COMPONENT
 *
 * Proper dependency injection, form validation, and clean separation of concerns
 * Used within the ImageShow tab system for consistent UX
 */
class ImageEditForm extends Component
{
    public Image $image;

    // Edit form data
    public string $title = '';

    public string $alt_text = '';

    public string $description = '';

    public string $folder = '';

    public string $tagsString = '';

    // UI state
    public bool $isSaving = false;

    /**
     * 🎪 MOUNT - Initialize with image data
     */
    public function mount(Image $image): void
    {
        $this->image = $image;
        $this->loadImageData();
    }

    /**
     * 📥 LOAD IMAGE DATA INTO FORM
     */
    private function loadImageData(): void
    {
        $this->title = $this->image->title ?? '';
        $this->alt_text = $this->image->alt_text ?? '';
        $this->description = $this->image->description ?? '';
        $this->folder = $this->image->folder ?? 'uncategorized';
        $this->tagsString = implode(', ', $this->image->tags ?? []);
    }

    /**
     * 💾 SAVE IMAGE CHANGES - Uses Actions pattern with proper DI
     */
    public function save(UpdateImageAction $updateImageAction)
    {
        $this->isSaving = true;

        try {
            // Validate tags string format
            $this->validate([
                'tagsString' => 'nullable|string|max:500',
            ]);

            // Convert tags string to array
            $tags = array_filter(
                array_map('trim', explode(',', $this->tagsString))
            );

            // Prepare data for the action
            $data = [
                'title' => $this->title,
                'alt_text' => $this->alt_text,
                'description' => $this->description,
                'folder' => $this->folder,
                'tags' => $tags,
            ];

            // Use the action to update the image
            $this->image = $updateImageAction->execute($this->image, $data);
            $this->loadImageData();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image updated successfully!',
            ]);

        } catch (ValidationException $e) {
            // Handle validation errors
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, implode(', ', $messages));
            }

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Please correct the validation errors and try again.',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $e->getMessage(),
            ]);
        } finally {
            $this->isSaving = false;
        }
    }

    /**
     * 🔄 RESET FORM TO ORIGINAL VALUES
     */
    public function resetForm(): void
    {
        $this->loadImageData();
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Form reset to original values',
        ]);
    }

    /**
     * 🔄 REPROCESS IMAGE METADATA & GENERATE VARIANTS
     *
     * Refresh metadata and optionally generate variants
     */
    public function reprocessImage(ReprocessImageAction $reprocessImageAction, bool $generateVariants = true): void
    {
        $this->isSaving = true;

        // Refresh metadata synchronously
        $result = $reprocessImageAction->execute($this->image);
        
        if ($result['success']) {
            $this->image = $result['data']['image'];
            $this->loadImageData();
        }

        // Generate variants in background if requested and image is large enough
        if ($generateVariants && $this->image->isOriginal() && ($this->image->width > 150 || $this->image->height > 150)) {
            GenerateImageVariantsJob::dispatch($this->image);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Metadata refreshed! Generating variants in background... 🎨',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Image metadata refreshed successfully! 🔄',
            ]);
        }

        $this->isSaving = false;
    }

    /**
     * 📁 GET AVAILABLE FOLDERS FOR DROPDOWN
     *
     * @return array<string>
     */
    public function getFoldersProperty(): array
    {
        return Image::query()
            ->select('folder')
            ->distinct()
            ->whereNotNull('folder')
            ->pluck('folder')
            ->toArray();
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.images.image-edit-form');
    }
}
