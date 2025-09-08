<?php

namespace App\Livewire\Images;

use App\Actions\Images\ReprocessImageAction;
use App\Actions\Images\UpdateImageAction;
use App\Jobs\GenerateImageVariantsJob;
use App\Models\Image;
use App\Services\Attributes\Facades\Attributes;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
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
    public array $tagTokens = [];
    public string $tagInput = '';
    public string $tagValidationMessage = '';

    // UI state
    public bool $isSaving = false;
    public bool $creatingNewFolder = false;
    public string $newFolderName = '';

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
        // Prefill from model fields; if blank, fall back to image attributes
        $this->title = $this->image->title ?: (string) (Attributes::for($this->image)->get('title') ?? '');
        $this->alt_text = $this->image->alt_text ?: (string) (Attributes::for($this->image)->get('alt_text') ?? '');
        $this->description = $this->image->description ?: (string) (Attributes::for($this->image)->get('description') ?? '');
        $this->folder = $this->image->folder
            ?: (string) (Attributes::for($this->image)->get('folder') ?? 'uncategorized');

        // Prefer model tags; if none, pull from attributes (array or CSV)
        $modelTags = $this->image->tags ?? [];
        if (!empty($modelTags)) {
            $this->tagTokens = array_values(array_unique($modelTags));
        } else {
            $attrTags = Attributes::for($this->image)->get('tags');
            if (is_array($attrTags)) {
                $this->tagTokens = array_values(array_unique($attrTags));
            } elseif (is_string($attrTags)) {
                $this->tagTokens = array_values(array_unique(array_filter(array_map('trim', explode(',', $attrTags)))));
            } else {
                $this->tagTokens = [];
            }
        }
        // Keep string in sync for any legacy bindings
        $this->tagsString = implode(', ', $this->tagTokens);
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

            // Use tokenized tags
            $tags = array_values(array_unique(array_filter(array_map('trim', $this->tagTokens))));

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

            // Mirror key fields to image attributes (upsert or create)
            $this->image->bulkUpdateAttributes([
                'title' => $this->title,
                'alt_text' => $this->alt_text,
                'description' => $this->description,
                'folder' => $this->folder,
                'tags' => $tags,
            ], [
                'source' => 'ui:image-edit-form',
            ]);

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
        $modelFolders = Image::query()
            ->select('folder')
            ->distinct()
            ->whereNotNull('folder')
            ->pluck('folder');

        $attrFolders = \App\Models\ImageAttribute::query()
            ->whereHas('attributeDefinition', fn ($q) => $q->where('key', 'folder'))
            ->whereNotNull('value')
            ->pluck('value');

        return $modelFolders->merge($attrFolders)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Available tags from both model column and image attributes
     * @return array<int,string>
     */
    public function getAvailableTagsProperty(): array
    {
        $modelTags = \App\Models\Image::query()
            ->select('tags')
            ->whereNotNull('tags')
            ->get()
            ->pluck('tags')
            ->flatten();

        $attrTagsRaw = \App\Models\ImageAttribute::query()
            ->whereHas('attributeDefinition', fn ($q) => $q->where('key', 'tags'))
            ->whereNotNull('value')
            ->pluck('value');

        $attrTags = collect();
        foreach ($attrTagsRaw as $val) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $attrTags = $attrTags->merge($decoded);
            } else {
                $attrTags = $attrTags->merge(array_filter(array_map('trim', explode(',', $val))));
            }
        }

        return $modelTags->merge($attrTags)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    // Tag token actions
    public function addTagToken(?string $tag = null): void
    {
        $value = $tag !== null ? trim($tag) : trim($this->tagInput);
        if ($value === '') {
            return;
        }
        // Inline validation
        $error = $this->validateTag($value);
        if ($error) {
            $this->addError('tagInput', $error);
            return;
        }
        $this->resetErrorBag('tagInput');
        if (!in_array($value, $this->tagTokens)) {
            $this->tagTokens[] = $value;
            $this->tagsString = implode(', ', $this->tagTokens);
        }
        $this->tagInput = '';
    }

    public function removeTagToken(string $tag): void
    {
        $this->tagTokens = array_values(array_filter($this->tagTokens, fn ($t) => $t !== $tag));
        $this->tagsString = implode(', ', $this->tagTokens);
    }

    /**
     * Clean tags: trim, collapse spaces, dedupe, remove empties
     */
    public function cleanTags(): void
    {
        $cleaned = [];
        foreach ($this->tagTokens as $t) {
            $t = trim(preg_replace('/\s+/', ' ', (string) $t));
            if ($t === '') {
                continue;
            }
            $error = $this->validateTag($t);
            if ($error) {
                // Skip invalid tokens, but report first error
                $this->addError('tagInput', $error);
                continue;
            }
            $cleaned[] = $t;
        }
        $this->tagTokens = array_values(array_unique($cleaned));
        $this->tagsString = implode(', ', $this->tagTokens);
        if (!$this->getErrorBag()->has('tagInput')) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Tags cleaned.',
            ]);
        }
    }

    /**
     * Basic tag validation: allow letters, numbers, spaces, hyphens, underscores; max 50 chars
     */
    protected function validateTag(string $tag): ?string
    {
        if (mb_strlen($tag) > 50) {
            return 'Tags must be 50 characters or fewer.';
        }
        if (!preg_match('/^[A-Za-z0-9 _\-]+$/', $tag)) {
            return 'Only letters, numbers, spaces, hyphens, and underscores allowed in tags.';
        }
        return null;
    }

    public function updatedTagInput(): void
    {
        if ($this->tagInput === '') {
            $this->resetErrorBag('tagInput');
            return;
        }
        $error = $this->validateTag($this->tagInput);
        if ($error) {
            $this->addError('tagInput', $error);
        } else {
            $this->resetErrorBag('tagInput');
        }
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render(): View
    {
        return view('livewire.images.image-edit-form');
    }

    /**
     * ▶️ Start new folder entry
     */
    public function startCreateFolder(): void
    {
        $this->creatingNewFolder = true;
        $this->newFolderName = '';
    }

    /**
     * ❌ Cancel new folder entry
     */
    public function cancelCreateFolder(): void
    {
        $this->creatingNewFolder = false;
        $this->newFolderName = '';
    }

    /**
     * ✅ Confirm new folder and set on form
     */
    public function confirmCreateFolder(): void
    {
        $this->validate([
            'newFolderName' => ['required','string','max:100','regex:/^[A-Za-z0-9_-]+$/'],
        ], [
            'newFolderName.regex' => 'Only letters, numbers, hyphens, and underscores allowed.',
        ]);

        $this->folder = $this->newFolderName;
        $this->creatingNewFolder = false;
        $this->newFolderName = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'New folder set. Save to persist.',
        ]);
    }

    /**
     * Handle Reset from header menu
     */
    #[On('reset-image-edit-form')]
    public function handleResetImageEditForm(): void
    {
        $this->resetForm();
    }

    /**
     * Handle Reprocess & Generate Variants from header menu
     */
    #[On('reprocess-image')]
    public function handleReprocessImage(): void
    {
        $action = app(ReprocessImageAction::class);
        $this->reprocessImage($action, true);
    }
}
