<?php

namespace App\Actions\StackedList;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportStackedListDataAction
{
    /**
     * Export stacked list data in the specified format.
     */
    public function execute(
        string $format,
        Collection $data,
        array $columns,
        array $metadata = []
    ): StreamedResponse {
        return match (strtolower($format)) {
            'csv' => $this->exportToCsv($data, $columns, $metadata),
            'xlsx' => $this->exportToXlsx($data, $columns, $metadata),
            'json' => $this->exportToJson($data, $columns, $metadata),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}")
        };
    }

    /**
     * Export data to CSV format.
     */
    protected function exportToCsv(Collection $data, array $columns, array $metadata): StreamedResponse
    {
        $filename = $this->generateFilename($metadata['title'] ?? 'export', 'csv');

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($data, $columns) {
            $file = fopen('php://output', 'w');

            // Header row
            fputcsv($file, array_values($columns));

            // Data rows
            foreach ($data as $item) {
                $row = [];
                foreach (array_keys($columns) as $key) {
                    $row[] = $this->formatCellValue(data_get($item, $key));
                }
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data to JSON format.
     */
    protected function exportToJson(Collection $data, array $columns, array $metadata): StreamedResponse
    {
        $filename = $this->generateFilename($metadata['title'] ?? 'export', 'json');

        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $exportData = [
            'metadata' => array_merge($metadata, [
                'exported_at' => now()->toISOString(),
                'total_records' => $data->count(),
                'columns' => $columns,
            ]),
            'data' => $data->map(function ($item) use ($columns) {
                $row = [];
                foreach (array_keys($columns) as $key) {
                    $row[$key] = data_get($item, $key);
                }

                return $row;
            })->toArray(),
        ];

        $callback = function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export data to XLSX format (placeholder - would need a library like PhpSpreadsheet).
     */
    protected function exportToXlsx(Collection $data, array $columns, array $metadata): StreamedResponse
    {
        // This would require PhpSpreadsheet or similar library
        throw new \RuntimeException('XLSX export requires PhpSpreadsheet library. Install with: composer require phpoffice/phpspreadsheet');
    }

    /**
     * Format a cell value for export.
     */
    protected function formatCellValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \Carbon\Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }

    /**
     * Generate filename for export.
     */
    protected function generateFilename(string $base, string $extension): string
    {
        $slug = Str::slug($base);
        $timestamp = now()->format('Y-m-d-H-i-s');

        return "{$slug}-{$timestamp}.{$extension}";
    }
}
