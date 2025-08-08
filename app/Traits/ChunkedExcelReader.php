<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait ChunkedExcelReader
{
    /**
     * Extract headers from the first row of a worksheet
     */
    protected function extractHeaders(Worksheet $worksheet, int $maxColumns = 50): array
    {
        $headers = [];
        $endColumn = chr(65 + min($maxColumns - 1, 25)); // Limit to reasonable column range

        try {
            $headerRow = $worksheet->rangeToArray("A1:{$endColumn}1", null, true, false, false)[0] ?? [];

            foreach ($headerRow as $header) {
                if (! empty($header)) {
                    $headers[] = (string) $header;
                } else {
                    break; // Stop at first empty header
                }
            }

        } catch (\Exception $e) {
            Log::warning('Failed to extract headers', ['error' => $e->getMessage()]);
        }

        return $headers;
    }

    /**
     * Read worksheet data in manageable chunks to avoid memory issues
     */
    protected function readInChunks(Worksheet $worksheet, array $headers, int $startRow, int $maxRows, int $chunkSize = 1000): \Generator
    {
        $endColumn = chr(65 + max(0, count($headers) - 1));
        $currentRow = $startRow;
        $processedRows = 0;

        while ($processedRows < $maxRows && $currentRow <= $worksheet->getHighestRow()) {
            $chunkEndRow = min($currentRow + $chunkSize - 1, $worksheet->getHighestRow(), $startRow + $maxRows - 1);

            Log::debug('Reading chunk', [
                'start_row' => $currentRow,
                'end_row' => $chunkEndRow,
                'end_column' => $endColumn,
                'headers_count' => count($headers),
            ]);

            // Read the chunk
            try {
                $range = "A{$currentRow}:{$endColumn}{$chunkEndRow}";
                $chunkData = $worksheet->rangeToArray($range, null, true, false, false);

                if (empty($chunkData)) {
                    break;
                }

                $formattedChunk = $this->formatChunkData($chunkData, $headers, $currentRow);

                if (! empty($formattedChunk)) {
                    yield $formattedChunk;
                    $processedRows += count($formattedChunk);
                }

            } catch (\Exception $e) {
                Log::warning('Failed to read chunk', [
                    'range' => $range ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
                break;
            }

            $currentRow = $chunkEndRow + 1;

            // Memory management
            if ($processedRows % ($chunkSize * 2) === 0) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Format chunk data into consistent structure
     */
    protected function formatChunkData(array $chunkData, array $headers, int $startRowNumber): array
    {
        $formattedData = [];
        $rowNumber = $startRowNumber;

        foreach ($chunkData as $rowData) {
            // Skip empty rows
            if (empty(array_filter($rowData))) {
                $rowNumber++;

                continue;
            }

            // Pad or trim to match header count
            $rowData = array_pad(array_slice($rowData, 0, count($headers)), count($headers), null);

            $formattedData[] = [
                'data' => $rowData,
                'headers' => $headers,
                'row_number' => $rowNumber,
            ];

            $rowNumber++;
        }

        return $formattedData;
    }

    /**
     * Efficiently count non-empty rows in a worksheet
     */
    protected function countNonEmptyRows(Worksheet $worksheet, array $headers, int $startRow = 2, int $sampleSize = 100): int
    {
        $highestRow = $worksheet->getHighestRow();

        if ($highestRow <= $startRow) {
            return 0;
        }

        $totalPossibleRows = $highestRow - $startRow + 1;

        // For small worksheets, count all rows
        if ($totalPossibleRows <= $sampleSize * 2) {
            return $this->countAllNonEmptyRows($worksheet, $headers, $startRow, $highestRow);
        }

        // For large worksheets, use sampling
        return $this->estimateRowCountBySampling($worksheet, $headers, $startRow, $highestRow, $sampleSize);
    }

    /**
     * Count all non-empty rows (for smaller datasets)
     */
    protected function countAllNonEmptyRows(Worksheet $worksheet, array $headers, int $startRow, int $endRow): int
    {
        $endColumn = chr(65 + max(0, count($headers) - 1));
        $nonEmptyCount = 0;

        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];

                if (! empty(array_filter($rowData))) {
                    $nonEmptyCount++;
                }
            } catch (\Exception $e) {
                Log::warning('Failed to read row for counting', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $nonEmptyCount;
    }

    /**
     * Estimate row count using statistical sampling (for larger datasets)
     */
    protected function estimateRowCountBySampling(Worksheet $worksheet, array $headers, int $startRow, int $endRow, int $sampleSize): int
    {
        $totalRows = $endRow - $startRow + 1;
        $sampleInterval = max(1, intval($totalRows / $sampleSize));
        $endColumn = chr(65 + max(0, count($headers) - 1));

        $sampledRows = 0;
        $nonEmptyRows = 0;

        for ($row = $startRow; $row <= $endRow && $sampledRows < $sampleSize; $row += $sampleInterval) {
            try {
                $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];

                if (! empty(array_filter($rowData))) {
                    $nonEmptyRows++;
                }

                $sampledRows++;

            } catch (\Exception $e) {
                Log::warning('Failed to read sample row', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        // Calculate estimate
        $estimatedCount = $sampledRows > 0 ? intval(($nonEmptyRows / $sampledRows) * $totalRows) : 0;

        Log::info('Row count estimation completed', [
            'total_possible_rows' => $totalRows,
            'sampled_rows' => $sampledRows,
            'non_empty_samples' => $nonEmptyRows,
            'estimated_count' => $estimatedCount,
            'sample_interval' => $sampleInterval,
        ]);

        return $estimatedCount;
    }

    /**
     * Get optimal chunk size based on available memory and data complexity
     */
    protected function getOptimalChunkSize(int $headerCount, int $estimatedRows): int
    {
        // Base chunk size
        $baseChunkSize = 1000;

        // Adjust based on number of columns
        if ($headerCount > 50) {
            $baseChunkSize = 500;
        } elseif ($headerCount > 100) {
            $baseChunkSize = 250;
        }

        // Adjust based on estimated dataset size
        if ($estimatedRows > 100000) {
            $baseChunkSize = min($baseChunkSize, 500);
        } elseif ($estimatedRows > 500000) {
            $baseChunkSize = min($baseChunkSize, 250);
        }

        // Ensure minimum chunk size
        return max(100, $baseChunkSize);
    }

    /**
     * Safely get worksheet dimensions without loading all data
     */
    protected function getWorksheetDimensions(Worksheet $worksheet): array
    {
        try {
            return [
                'highest_row' => $worksheet->getHighestRow(),
                'highest_column' => $worksheet->getHighestColumn(),
                'highest_column_index' => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestColumn()),
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get worksheet dimensions', ['error' => $e->getMessage()]);

            return [
                'highest_row' => 1,
                'highest_column' => 'A',
                'highest_column_index' => 1,
            ];
        }
    }

    /**
     * Memory-efficient method to peek at sample data
     */
    protected function peekSampleData(Worksheet $worksheet, array $headers, int $sampleRows = 5): array
    {
        $sampleData = [];
        $endColumn = chr(65 + max(0, count($headers) - 1));
        $maxRow = min($worksheet->getHighestRow(), $sampleRows + 1);

        for ($row = 2; $row <= $maxRow; $row++) {
            try {
                $rowData = $worksheet->rangeToArray("A{$row}:{$endColumn}{$row}", null, true, false, false)[0] ?? [];

                if (! empty(array_filter($rowData))) {
                    // Pad or trim to match header count
                    $rowData = array_pad(array_slice($rowData, 0, count($headers)), count($headers), null);

                    // Create associative array with headers
                    $rowWithHeaders = [];
                    foreach ($headers as $index => $header) {
                        $rowWithHeaders[$header] = $rowData[$index] ?? '';
                    }

                    $sampleData[] = $rowWithHeaders;

                    if (count($sampleData) >= $sampleRows) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to read sample row', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return $sampleData;
    }
}
