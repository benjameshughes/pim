<?php

namespace App\Services\Import\Actions;

use Illuminate\Support\Facades\Validator;

class ValidateRowAction extends ImportAction
{
    private array $rules = [];
    private array $messages = [];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->rules = $config['rules'] ?? $this->getDefaultRules();
        $this->messages = $config['messages'] ?? [];
    }

    public function execute(ActionContext $context): ActionResult
    {
        $data = $context->getData();
        
        $this->logAction('Validating row data', [
            'row_number' => $context->getRowNumber(),
            'data_keys' => array_keys($data),
        ]);

        // Perform validation
        $validator = Validator::make($data, $this->rules, $this->messages);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $this->logAction('Validation failed', [
                'row_number' => $context->getRowNumber(),
                'errors' => $errors,
            ]);

            return ActionResult::failed(
                'Row validation failed: ' . implode('; ', $errors),
                [
                    'validation_errors' => $validator->errors()->toArray(),
                    'failed_fields' => array_keys($validator->errors()->toArray()),
                ]
            );
        }

        $this->logAction('Validation passed', [
            'row_number' => $context->getRowNumber(),
        ]);

        return ActionResult::success([
            'validation_passed' => true,
            'validated_fields' => array_keys($this->rules),
        ]);
    }

    private function getDefaultRules(): array
    {
        return [
            'product_name' => 'required|string|max:255',
            'variant_sku' => 'required|string|max:100',
            'retail_price' => 'nullable|numeric|min:0',
            'stock_level' => 'nullable|integer|min:0',
            'variant_color' => 'nullable|string|max:50',
            'variant_size' => 'nullable|string|max:50',
            'barcode' => 'nullable|string|max:20',
        ];
    }

    public function withRules(array $rules): self
    {
        $this->rules = array_merge($this->rules, $rules);
        return $this;
    }

    public function withMessages(array $messages): self
    {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }
}