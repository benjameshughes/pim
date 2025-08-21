<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $productId = $this->route('product') ? $this->route('product')->id : null;

        return [
            'name' => 'required|min:3|max:255',
            'parent_sku' => 'required|unique:products,parent_sku,'.$productId,
            'description' => 'nullable|max:1000',
            'status' => 'required|in:active,inactive',
            'image_url' => 'nullable|url',
        ];
    }
}
