<?php

namespace App\Livewire\Barcodes;

use App\Models\Barcode;
use App\Models\ProductVariant;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BarcodeForm extends Component
{
    public Barcode $barcode;

    public bool $isEditing = false;

    // ✨ BARCODE FORM FIELDS
    #[Validate('required|exists:product_variants,id')]
    public $product_variant_id = '';

    #[Validate('required|string|max:255|unique:barcodes,barcode')]
    public $barcode_value = '';

    #[Validate('required|in:caecus,system,ean13,upc')]
    public $type = 'caecus';

    #[Validate('required|in:active,inactive')]
    public $status = 'active';

    public function mount(?Barcode $barcode = null)
    {
        if ($barcode && $barcode->exists) {
            $this->barcode = $barcode;
            $this->isEditing = true;
            $this->product_variant_id = $barcode->product_variant_id;
            $this->barcode_value = $barcode->barcode;
            $this->type = $barcode->type;
            $this->status = $barcode->status;
        } else {
            $this->barcode = new Barcode;
        }
    }

    public function rules()
    {
        return [
            'product_variant_id' => 'required|exists:product_variants,id',
            'barcode_value' => $this->isEditing
                ? 'required|string|max:255|unique:barcodes,barcode,'.$this->barcode->id
                : 'required|string|max:255|unique:barcodes,barcode',
            'type' => 'required|in:caecus,system,ean13,upc',
            'status' => 'required|in:active,inactive',
        ];
    }

    public function save()
    {
        $this->validate();

        try {
            $data = [
                'product_variant_id' => $this->product_variant_id,
                'barcode' => $this->barcode_value,
                'type' => $this->type,
                'status' => $this->status,
            ];

            if ($this->isEditing) {
                $this->barcode->update($data);
                $this->dispatch('success', 'Barcode updated successfully! ✨');
            } else {
                Barcode::create($data);
                $this->dispatch('success', 'Barcode created successfully! 🎉');
            }

            // 🧠 SMART TOAST handles persistence automatically!
            return redirect()->route('barcodes.index');
        } catch (\Exception $e) {
            $this->dispatch('error', 'Failed to save barcode: '.$e->getMessage());
        }
    }

    public function generateBarcode()
    {
        // ✨ PHOENIX BARCODE GENERATOR
        $barcode = match ($this->type) {
            'ean13' => $this->generateEAN13(),
            'upc' => $this->generateUPC(),
            'caecus' => $this->generateCaecusCode(),
            default => $this->generateSystemCode()
        };

        $this->barcode_value = $barcode;
        $this->dispatch('success', "Generated {$this->type} barcode! ✨");
    }

    private function generateEAN13(): string
    {
        // Generate 12 random digits, then calculate check digit
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= rand(0, 9);
        }

        // Calculate EAN13 check digit
        $checksum = 0;
        for ($i = 0; $i < 12; $i++) {
            $checksum += (int) $code[$i] * (($i % 2) ? 3 : 1);
        }
        $checkDigit = (10 - ($checksum % 10)) % 10;

        return $code.$checkDigit;
    }

    private function generateUPC(): string
    {
        // Generate 11 random digits, then calculate check digit
        $code = '';
        for ($i = 0; $i < 11; $i++) {
            $code .= rand(0, 9);
        }

        // Calculate UPC check digit
        $checksum = 0;
        for ($i = 0; $i < 11; $i++) {
            $checksum += (int) $code[$i] * (($i % 2) ? 3 : 1);
        }
        $checkDigit = (10 - ($checksum % 10)) % 10;

        return $code.$checkDigit;
    }

    private function generateCaecusCode(): string
    {
        return 'CAE'.str_pad(rand(1000000, 9999999), 7, '0', STR_PAD_LEFT);
    }

    private function generateSystemCode(): string
    {
        return 'SYS'.str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function render()
    {
        // ✨ PHOENIX PROPER COLLECTION - Keep as models, not arrays!
        $variants = ProductVariant::with('product')
            ->orderBy('sku')
            ->get();

        return view('livewire.barcodes.barcode-form', compact('variants'));
    }
}
