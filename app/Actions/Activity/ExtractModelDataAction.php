<?php

namespace App\Actions\Activity;

use App\Actions\Base\BaseAction;

class ExtractModelDataAction extends BaseAction
{
    protected bool $useTransactions = false; // No DB changes, no transactions needed

    protected function performAction(...$params): array
    {
        $model = $params[0] ?? throw new \InvalidArgumentException('Model is required');
        
        $extractorClass = $this->getExtractorClass($model);
        
        $extracted = class_exists($extractorClass) 
            ? $extractorClass::extract($model)
            : $this->defaultExtraction($model);

        return $this->success('Model data extracted successfully', [
            'extracted_data' => $extracted,
        ]);
    }

    // Convenience method that returns the extracted data directly
    public function extractData(object $model): array
    {
        $result = $this->execute($model);
        return $result['data']['extracted_data'];
    }

    protected function getExtractorClass(object $model): string
    {
        return "App\\Extractors\\" . class_basename($model) . "Extractor";
    }

    protected function defaultExtraction(object $model): array
    {
        $data = method_exists($model, 'toArray') ? $model->toArray() : [];
        
        return collect($data)
            ->only(['id', 'name', 'title', 'sku', 'status', 'type'])
            ->filter()
            ->put('type', class_basename($model))
            ->toArray();
    }
}