<?php

namespace App\Actions\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class LoadAllDataForImport
{
    public function execute($filePath, array $selectedWorksheets, bool $skipHeaderRow = true): array
    {
        $allData = [];
        
        foreach ($selectedWorksheets as $worksheetIndex) {
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$worksheetIndex]);
                $reader->setReadEmptyCells(false);
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                $headers = [];
                for ($col = 1; $col <= $columnIndex; $col++) {
                    $cellValue = $worksheet->getCell([$col, 1])->getCalculatedValue();
                    $headers[] = $cellValue !== null ? trim($cellValue) : '';
                }
                
                $startRow = $skipHeaderRow ? 2 : 1;
                $worksheetData = [];
                
                for ($row = $startRow; $row <= $highestRow; $row++) {
                    $rowData = [];
                    $hasData = false;
                    
                    for ($col = 1; $col <= $columnIndex; $col++) {
                        $cellValue = $worksheet->getCell([$col, $row])->getCalculatedValue();
                        $value = $cellValue !== null ? trim($cellValue) : '';
                        $rowData[] = $value;
                        
                        if (!empty($value)) {
                            $hasData = true;
                        }
                    }
                    
                    if ($hasData) {
                        $worksheetData[] = [
                            'data' => $rowData,
                            'headers' => $headers,
                            'row_number' => $row
                        ];
                    }
                }
                
                $allData[$worksheetIndex] = $worksheetData;
                
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
                
                Log::info("Loaded {$worksheetIndex} with " . count($worksheetData) . " rows");
                
            } catch (\Exception $e) {
                Log::error("Failed to load data for worksheet {$worksheetIndex}", [
                    'error' => $e->getMessage()
                ]);
                throw new \Exception("Failed to load data for worksheet {$worksheetIndex}: " . $e->getMessage());
            }
        }
        
        return $allData;
    }
}