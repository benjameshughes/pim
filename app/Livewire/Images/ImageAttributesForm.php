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

    /** @var array<string,mixed> */
    public array $values = [];

    public array $groups = [];

    public function mount(Image $image): void
    {
        $this->authorize('manage-images');
        $this->image = $image;

        // Preload existing attribute values
        $this->values = Attributes::for($this->image)->all();

        // Load active definitions grouped for display
        $this->groups = AttributeDefinition::getGroupedAttributes()->map(function ($defs) {
            return $defs->map(function ($def) {
                return [
                    'key' => $def->key,
                    'name' => $def->name,
                    'data_type' => $def->data_type,
                    'input_type' => $def->input_type,
                    'group' => $def->group,
                ];
            })->toArray();
        })->toArray();
    }

    public function save(): void
    {
        $this->authorize('manage-images');

        try {
            $result = $this->image->bulkUpdateAttributes($this->values, [
                'source' => 'ui:image-attributes-form',
            ]);

            if (!empty($result['errors'])) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Some attributes failed to save.',
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

    public function render()
    {
        return view('livewire.images.image-attributes-form');
    }
}

