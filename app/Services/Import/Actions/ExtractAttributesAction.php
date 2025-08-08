<?php

namespace App\Services\Import\Actions;

use App\Services\Import\Extraction\MadeToMeasureExtractor;
use App\Services\Import\Extraction\SmartDimensionExtractor;
use Illuminate\Support\Facades\Log;

class ExtractAttributesAction extends ImportAction
{
    private bool $extractMtm;
    private bool $extractDimensions;
    private ?MadeToMeasureExtractor $mtmExtractor = null;
    private ?SmartDimensionExtractor $dimensionExtractor = null;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        $this->extractMtm = $config['extract_mtm'] ?? true;
        $this->extractDimensions = $config['extract_dimensions'] ?? true;

        if ($this->extractMtm) {
            $this->mtmExtractor = app(MadeToMeasureExtractor::class);
        }

        if ($this->extractDimensions) {
            $this->dimensionExtractor = app(SmartDimensionExtractor::class);
        }
    }

    public function execute(ActionContext $context): ActionResult
    {
        $data = $context->getData();
        $extractedData = [];
        
        $this->logAction('Starting attribute extraction', [
            'row_number' => $context->getRowNumber(),
            'extract_mtm' => $this->extractMtm,
            'extract_dimensions' => $this->extractDimensions,
        ]);

        // Made-to-Measure detection
        if ($this->extractMtm && $this->mtmExtractor) {
            $mtmResult = $this->extractMtmData($data);
            if (!empty($mtmResult)) {
                $extractedData = array_merge($extractedData, $mtmResult);
                $this->logAction('MTM extraction completed', [
                    'row_number' => $context->getRowNumber(),
                    'confidence' => $mtmResult['mtm_confidence'] ?? 0,
                ]);
            }
        }

        // Smart dimension extraction
        if ($this->extractDimensions && $this->dimensionExtractor) {
            $dimensionResult = $this->extractDimensionData($data);
            if (!empty($dimensionResult)) {
                $extractedData = array_merge($extractedData, $dimensionResult);
                $this->logAction('Dimension extraction completed', [
                    'row_number' => $context->getRowNumber(),
                    'confidence' => $dimensionResult['dimension_confidence'] ?? 0,
                ]);
            }
        }

        return ActionResult::success([
            'attributes_extracted' => count($extractedData),
            'extracted_fields' => array_keys($extractedData),
        ])->withContextUpdates($extractedData);
    }

    private function extractMtmData(array $data): array
    {
        try {
            $mtmResult = $this->mtmExtractor->extractMultipleFields($data);
            
            if (empty($mtmResult['made_to_measure'])) {
                return [];
            }

            $extractedData = [
                'made_to_measure' => $mtmResult['made_to_measure'],
                'mtm_confidence' => $mtmResult['confidence'],
                'mtm_title_suffix' => $mtmResult['title_suffix'],
            ];

            // Enhance product name with MTM indicator if not present
            if (!empty($data['product_name'])) {
                $enhancedTitle = $this->mtmExtractor->suggestTitleEnhancement(
                    $data['product_name'],
                    $mtmResult
                );
                if ($enhancedTitle !== $data['product_name']) {
                    $extractedData['enhanced_product_name'] = $enhancedTitle;
                }
            }

            return $extractedData;

        } catch (\Exception $e) {
            Log::warning('MTM extraction failed', [
                'error' => $e->getMessage(),
                'data_sample' => array_slice($data, 0, 3),
            ]);
            return [];
        }
    }

    private function extractDimensionData(array $data): array
    {
        try {
            $dimensionResult = $this->dimensionExtractor->extractMultipleFields($data);
            
            if (empty($dimensionResult['dimensions'])) {
                return [];
            }

            $extractedData = [
                'dimension_confidence' => $dimensionResult['confidence'],
                'dimension_source' => $dimensionResult['source_field'] ?? 'unknown',
            ];

            if (isset($dimensionResult['width'])) {
                $extractedData['extracted_width'] = $dimensionResult['width'];
            }

            if (isset($dimensionResult['drop'])) {
                $extractedData['extracted_drop'] = $dimensionResult['drop'];
            }

            // Create a size string if we don't have one
            if (empty($data['variant_size'])) {
                $sizeString = $this->dimensionExtractor->suggestSizeString($dimensionResult);
                if ($sizeString) {
                    $extractedData['extracted_size'] = $sizeString;
                }
            }

            return $extractedData;

        } catch (\Exception $e) {
            Log::warning('Dimension extraction failed', [
                'error' => $e->getMessage(),
                'data_sample' => array_slice($data, 0, 3),
            ]);
            return [];
        }
    }
}