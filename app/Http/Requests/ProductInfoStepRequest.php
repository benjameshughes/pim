<?php

namespace App\Http\Requests;

use App\Rules\ParentSkuRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductInfoStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->input('product_id'); // For edit mode

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'parent_sku' => ['required', new ParentSkuRule($productId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'in:draft,active,inactive,archived'],
            'image_url' => ['nullable', 'url', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.min' => 'Product name must be at least 3 characters.',
            'parent_sku.required' => 'Parent SKU is required.',
            'status.required' => 'Product status is required.',
            'status.in' => 'Product status must be draft, active, inactive, or archived.',
            'image_url.url' => 'Image URL must be a valid URL.',
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_sku' => 'Parent SKU',
            'image_url' => 'Image URL',
        ];
    }
}
