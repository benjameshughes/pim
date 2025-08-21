<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use Livewire\Component;

class BarcodeShow extends Component
{
    public Barcode $barcode;

    public function mount(Barcode $barcode)
    {
        $this->barcode = $barcode->load(['productVariant.product']);
    }

    public function deleteBarcode()
    {
        $barcodeValue = $this->barcode->barcode;
        $this->barcode->delete();

        $this->dispatch('success', "Barcode '{$barcodeValue}' deleted successfully! 🗑️");

        return $this->redirect(route('barcodes.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.barcodes.barcode-show');
    }
}
