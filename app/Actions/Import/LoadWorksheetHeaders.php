<?php

namespace App\Actions\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class LoadWorksheetHeaders
{
    public function execute($filePath, array $selectedWorksheets): array
    {
        $allHeaders = [];
        
        foreach ($selectedWorksheets as $worksheetIndex) {
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$worksheetIndex]);
                $reader->setReadEmptyCells(false);
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                $highestColumn = $worksheet->getHighestColumn();
                $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                $headers = [];
                for ($col = 1; $col <= $columnIndex; $col++) {
                    $cellValue = $worksheet->getCell([$col, 1])->getCalculatedValue();
                    if (!empty($cellValue)) {
                        $headers[] = trim($cellValue);
                    }
                }
                
                $allHeaders[$worksheetIndex] = $headers;
                
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
            } catch (\Exception $e) {
                Log::error("Failed to load headers for worksheet {$worksheetIndex}", [
                    'error' => $e->getMessage()
                ]);
                throw new \Exception("Failed to load headers for worksheet {$worksheetIndex}: " . $e->getMessage());
            }
        }
        
        return $allHeaders;
    }
}