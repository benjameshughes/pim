<?php

namespace App\Livewire\Images;

use App\Models\AttributeDefinition;
use App\Models\Image;
use App\Services\Attributes\Facades\Attributes;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ImageAttributesForm extends Component
{
    public Image $image;

    /** @var array<int,array{key:string,value:mixed}> */
    public array $entries = [];

    public function mount(Image $image): void
    {
        $this->authorize('manage-images');
        $this->image = $image;

        // Preload from existing image_attributes rows
        $this->entries = $this->image->attributes()
            ->with('attributeDefinition')
            ->get()
            ->map(function ($attr) {
                return [
                    'key' => $attr->attributeDefinition?->key ?? '',
                    'value' => $attr->getTypedValue(),
                ];
            })
            ->values()
            ->toArray();

        if (empty($this->entries)) {
            $this->entries[] = ['key' => '', 'value' => null];
        }
    }

    public function save(): void
    {
        $this->authorize('manage-images');

        // Build map of key=>value from entries
        $map = [];
        foreach ($this->entries as $row) {
            $key = trim((string)($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = $row['value'] ?? null;
        }

        if (empty($map)) {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'No attributes to save.',
            ]);
            return;
        }

        try {
            $result = $this->image->bulkUpdateAttributes($map, [
                'source' => 'ui:image-attributes-form',
            ]);

            if (!empty($result['errors'])) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Some attributes failed to save. Ensure keys are defined.',
                ]);
            } else {
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Image attributes saved successfully.',
                ]);
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'form' => $e->getMessage(),
            ]);
        }
    }

    public function addRow(): void
    {
        $this->entries[] = ['key' => '', 'value' => null];
    }

    public function removeRow(int $index): void
    {
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    public function render()
    {
        return view('livewire.images.image-attributes-form');
    }
}
