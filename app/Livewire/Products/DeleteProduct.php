<?php

namespace App\Livewire\Products;

use App\Models\Product;
use App\Models\DeletedProductVariant;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

#[Layout('components.layouts.app')]
class DeleteProduct extends Component
{
    public Product $product;
    public string $deletionReason = '';
    public string $deletionNotes = '';
    public bool $showConfirmation = false;

    public function mount(Product $product)
    {
        $this->product = $product;
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

    public function deleteProduct()
    {
        $this->validate([
            'deletionReason' => 'required|in:' . implode(',', array_keys(DeletedProductVariant::getAvailableReasons())),
            'deletionNotes' => 'nullable|string|max:1000'
        ]);

        try {
            DB::transaction(function () {
                // Archive all variants first (with same reason/notes)
                foreach ($this->product->variants as $variant) {
                    // Set deletion reason on each variant so model events can access it
                    $variant->deletion_reason = $this->deletionReason;
                    $variant->deletion_notes = $this->deletionNotes;
                    
                    // Delete variant - this triggers archiving and barcode cleanup
                    $variant->delete();
                }
                
                // Now delete the product itself
                $this->product->delete();
            });

            $variantCount = $this->product->variants()->count();
            session()->flash('message', "Product '{$this->product->name}' and {$variantCount} variants have been deleted and archived.");
            
            return redirect()->route('products.index');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete product: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.products.delete-product', [
            'availableReasons' => DeletedProductVariant::getAvailableReasons(),
            'variantCount' => $this->product->variants()->count()
        ]);
    }
}