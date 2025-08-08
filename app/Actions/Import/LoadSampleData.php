<?php

namespace App\Actions\Import;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LoadSampleData
{
    public function execute($filePath, array $selectedWorksheets, int $sampleSize = 5): array
    {
        $allSampleData = [];

        foreach ($selectedWorksheets as $worksheetIndex) {
            try {
                $reader = IOFactory::createReaderForFile($filePath);
                $reader->setReadDataOnly(true);
                $reader->setLoadSheetsOnly([$worksheetIndex]);
                $reader->setReadEmptyCells(false);

                $spreadsheet = $reader->load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();

                $highestRow = min($worksheet->getHighestRow(), $sampleSize + 1);
                $highestColumn = $worksheet->getHighestColumn();
                $columnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                $sampleData = [];
                for ($row = 2; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($col = 1; $col <= $columnIndex; $col++) {
                        $cellValue = $worksheet->getCell([$col, $row])->getCalculatedValue();
                        $rowData[] = $cellValue !== null ? trim($cellValue) : '';
                    }

                    if (array_filter($rowData, fn ($val) => ! empty($val))) {
                        $sampleData[] = $rowData;
                    }

                    if (count($sampleData) >= $sampleSize) {
                        break;
                    }
                }

                $allSampleData[$worksheetIndex] = $sampleData;

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

            } catch (\Exception $e) {
                Log::error("Failed to load sample data for worksheet {$worksheetIndex}", [
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception("Failed to load sample data for worksheet {$worksheetIndex}: ".$e->getMessage());
            }
        }

        return $allSampleData;
    }
}
