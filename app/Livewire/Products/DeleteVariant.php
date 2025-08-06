<?php

namespace App\Livewire\Products;

use App\Models\ProductVariant;
use App\Models\DeletedProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.app')]
class DeleteVariant extends Component
{
    public ProductVariant $variant;
    public string $deletionReason = '';
    public string $deletionNotes = '';
    public bool $showConfirmation = false;

    public function mount(ProductVariant $variant)
    {
        $this->variant = $variant;
    }

    public function showConfirmationModal()
    {
        $this->showConfirmation = true;
        $this->deletionReason = ''; // Reset reason when showing modal
        $this->deletionNotes = '';
    }

    public function cancelDeletion()
    {
        $this->showConfirmation = false;
        $this->deletionReason = '';
        $this->deletionNotes = '';
    }

    public function deleteVariant()
    {
        $this->validate([
            'deletionReason' => 'required|in:' . implode(',', array_keys(DeletedProductVariant::getAvailableReasons())),
            'deletionNotes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::transaction(function () {
                // Set deletion reason on the model so the booted event can access it
                $this->variant->deletion_reason = $this->deletionReason;
                $this->variant->deletion_notes = $this->deletionNotes;
                
                // Delete the variant - this will trigger archiving via model events
                $this->variant->delete();
            });

            session()->flash('message', "Variant {$this->variant->sku} has been deleted and archived.");
            
            // Redirect back to product view or variant index
            if ($this->variant->product->variants()->count() > 0) {
                return redirect()->route('products.view', $this->variant->product);
            } else {
                return redirect()->route('products.index');
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete variant: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.products.delete-variant', [
            'availableReasons' => DeletedProductVariant::getAvailableReasons()
        ]);
    }
}