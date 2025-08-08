<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ProductWizardForm extends Form
{
    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('nullable|string|max:255')]
    public $slug = '';

    #[Validate('nullable|string')]
    public $description = '';

    #[Validate('required|in:active,inactive,discontinued')]
    public $status = 'active';

    // Product features (up to 5)
    #[Validate('nullable|string')]
    public $product_features_1 = '';

    #[Validate('nullable|string')]
    public $product_features_2 = '';

    #[Validate('nullable|string')]
    public $product_features_3 = '';

    #[Validate('nullable|string')]
    public $product_features_4 = '';

    #[Validate('nullable|string')]
    public $product_features_5 = '';

    // Product details (up to 5)
    #[Validate('nullable|string')]
    public $product_details_1 = '';

    #[Validate('nullable|string')]
    public $product_details_2 = '';

    #[Validate('nullable|string')]
    public $product_details_3 = '';

    #[Validate('nullable|string')]
    public $product_details_4 = '';

    #[Validate('nullable|string')]
    public $product_details_5 = '';

    public function reset(...$properties)
    {
        $props = empty($properties) ? array_keys(get_object_vars($this)) : $properties;

        foreach ($props as $property) {
            if (property_exists($this, $property)) {
                $this->{$property} = match ($property) {
                    'status' => 'active',
                    default => ''
                };
            }
        }
    }
}
