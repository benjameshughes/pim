<?php

namespace App\Livewire\Images;

use App\Models\Image;
use App\Actions\Images\DeleteImageAction;
use App\UI\Components\Tab;
use App\UI\Components\TabSet;
use Livewire\Component;

/**
 * 🎨✨ IMAGE SHOW - MAIN IMAGE DISPLAY COMPONENT
 *
 * Mirrors ProductShow component structure for consistency
 * Handles image display, editing, and management with tabs
 */
class ImageShow extends Component
{
    public Image $image;

    public function mount(Image $image)
    {
        $this->image = $image->load([
            'products', 
            'variants.product',
            'createdBy'
        ]);
    }

    /**
     * 🗑️ DELETE IMAGE - Uses proper dependency injection
     */
    public function deleteImage(DeleteImageAction $deleteImageAction)
    {
        $imageName = $this->image->display_title;
        $deleteImageAction->execute($this->image);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Image '{$imageName}' deleted successfully! 🗑️",
            'persist' => true
        ]);

        return $this->redirect(route('images.index'), navigate: true);
    }

    /**
     * 📋 DUPLICATE IMAGE METADATA
     */
    public function duplicateImage()
    {
        $newImage = $this->image->replicate();
        $newImage->title = ($this->image->title ?? $this->image->display_title) . ' (Copy)';
        $newImage->filename = pathinfo($this->image->filename, PATHINFO_FILENAME) . '-copy.' . pathinfo($this->image->filename, PATHINFO_EXTENSION);
        
        // Note: This would need file duplication logic in ImageUploadService
        // For now, just duplicate metadata
        $newImage->save();

        $this->dispatch('notify', [
            'type' => 'success', 
            'message' => 'Image metadata duplicated successfully! ✨'
        ]);

        return $this->redirect(route('images.show', $newImage), navigate: true);
    }

    /**
     * 📊 GET IMAGE TABS PROPERTY (mirrors ProductShow)
     */
    public function getImageTabsProperty()
    {
        return TabSet::make()
            ->baseRoute('images.show')
            ->defaultRouteParameters(['image' => $this->image->id])
            ->wireNavigate(true)
            ->tabs([
                Tab::make('overview')
                    ->label('Overview')
                    ->icon('photo'),

                Tab::make('edit')
                    ->label('Edit')
                    ->icon('pencil')
                    ->badge($this->hasChanges() ? '!' : null)
                    ->badgeColor('orange'),

                Tab::make('attachments')
                    ->label('Attachments')
                    ->icon('link')
                    ->badge($this->getAttachmentsCount())
                    ->badgeColor($this->getAttachmentsCount() > 0 ? 'blue' : 'gray'),

                Tab::make('history')
                    ->label('History')
                    ->icon('clock')
                    ->badge($this->getRecentActivityCount())
                    ->badgeColor($this->getActivityBadgeColor()),
            ]);
    }

    /**
     * 🔗 GET ATTACHMENTS COUNT
     */
    private function getAttachmentsCount(): int
    {
        return $this->image->products()->count() + $this->image->variants()->count();
    }

    /**
     * 📊 GET RECENT ACTIVITY COUNT (placeholder - you could implement later)
     */
    private function getRecentActivityCount(): int
    {
        // Could implement with activity log later
        return 0;
    }

    /**
     * 🎨 GET ACTIVITY BADGE COLOR
     */
    private function getActivityBadgeColor(): string
    {
        return $this->getRecentActivityCount() > 0 ? 'blue' : 'gray';
    }

    /**
     * 🔄 CHECK FOR PENDING CHANGES (placeholder)
     */
    private function hasChanges(): bool
    {
        // Could implement change detection later
        return false;
    }

    /**
     * 🎨 RENDER COMPONENT
     */
    public function render()
    {
        return view('livewire.images.image-show');
    }
}