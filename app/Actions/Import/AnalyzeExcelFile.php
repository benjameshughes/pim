<?php

namespace App\Actions\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use Illuminate\Support\Facades\Log;

class AnalyzeExcelFile
{
    public function execute($filePath): array
    {
        try {
            // Get worksheet names using proper PhpSpreadsheet API
            $reader = IOFactory::createReaderForFile($filePath);
            if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'csv') {
                $worksheetNames = ['Sheet1']; // CSV has only one sheet
            } else {
                $worksheetNames = $reader->listWorksheetNames($filePath);
            }
            $worksheets = [];
            
            foreach ($worksheetNames as $index => $name) {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$name]);
                $reader->setReadEmptyCells(false);
                
                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                
                if ($highestRow > 0) {
                    $worksheets[] = [
                        'index' => $index,
                        'name' => $name,
                        'row_count' => $highestRow,
                        'column_count' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn),
                        'has_data' => $highestRow > 1
                    ];
                }
                
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
            
            return $worksheets;
            
        } catch (Exception $e) {
            Log::error('Excel analysis failed', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to analyze Excel file: ' . $e->getMessage());
        }
    }
}