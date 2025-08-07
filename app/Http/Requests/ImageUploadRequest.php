<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImageUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Add authorization logic as needed
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxSize = (int) $this->input('max_size', 10240); // KB
        $acceptTypes = $this->input('accept_types', ['jpg', 'jpeg', 'png', 'webp']);
        $maxFiles = (int) $this->input('max_files', 10);

        return [
            'files' => [
                'required',
                'array',
                "max:{$maxFiles}"
            ],
            'files.*' => [
                'required',
                File::image()
                    ->max($maxSize)
                    ->types($acceptTypes),
                'dimensions:min_width=300,min_height=300'
            ],
            'image_type' => [
                'required',
                'in:main,detail,swatch,lifestyle,installation'
            ],
            'model_type' => [
                'nullable',
                'in:product,variant'
            ],
            'model_id' => [
                'nullable',
                'integer',
                'min:1'
            ],
            'process_immediately' => [
                'boolean'
            ],
            'alt_text' => [
                'nullable',
                'string',
                'max:255'
            ]
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'files.*.max' => 'Each image must be smaller than :max KB.',
            'files.*.types' => 'Only JPG, JPEG, PNG, and WebP images are allowed.',
            'files.*.dimensions' => 'Images must be at least 300x300 pixels.',
            'files.max' => 'You can upload a maximum of :max images at once.',
            'image_type.in' => 'Invalid image type selected.',
            'model_type.in' => 'Invalid model type specified.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'files.*' => 'image file',
            'image_type' => 'image type',
            'model_type' => 'model type',
            'model_id' => 'model ID',
            'process_immediately' => 'process immediately option',
            'alt_text' => 'alt text',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure arrays are properly formatted
        if ($this->has('accept_types') && is_string($this->accept_types)) {
            $this->merge([
                'accept_types' => explode(',', $this->accept_types)
            ]);
        }

        // Set default values
        $this->mergeIfMissing([
            'process_immediately' => true,
            'max_size' => 10240,
            'max_files' => 10,
            'accept_types' => ['jpg', 'jpeg', 'png', 'webp']
        ]);
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->wantsJson()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422)
            );
        }

        parent::failedValidation($validator);
    }
}