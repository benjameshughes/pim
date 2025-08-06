<?php

namespace App\StackedList\Actions\BulkActions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportBulkAction
{
    public function __construct(
        private string $modelClass,
        private array $columns = []
    ) {}

    /**
     * Execute the bulk export action.
     */
    public function execute(array $selectedIds, string $format = 'csv'): StreamedResponse
    {
        $modelName = class_basename($this->modelClass);
        $filename = strtolower($modelName) . '-export-' . date('Y-m-d-H-i-s') . ".{$format}";

        return match ($format) {
            'csv' => $this->exportToCsv($selectedIds, $filename),
            'xlsx' => $this->exportToExcel($selectedIds, $filename),
            default => throw new \InvalidArgumentException("Unsupported export format: {$format}"),
        };
    }

    /**
     * Export to CSV format.
     */
    private function exportToCsv(array $selectedIds, string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ];

        return response()->stream(function () use ($selectedIds) {
            $handle = fopen('php://output', 'w');

            // Write headers
            $columnHeaders = $this->getColumnHeaders();
            fputcsv($handle, $columnHeaders);

            // Write data in chunks to avoid memory issues
            $this->modelClass::whereIn('id', $selectedIds)
                ->with($this->getRelationships())
                ->chunk(config('stacked-list.export.chunk_size', 1000), function ($items) use ($handle) {
                    foreach ($items as $item) {
                        $row = $this->formatItemForExport($item);
                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Get column headers for export.
     */
    private function getColumnHeaders(): array
    {
        return collect($this->columns)
            ->reject(fn($column) => ($column['type'] ?? '') === 'actions')
            ->pluck('label')
            ->values()
            ->toArray();
    }

    /**
     * Format a model item for export.
     */
    private function formatItemForExport(Model $item): array
    {
        return collect($this->columns)
            ->reject(fn($column) => ($column['type'] ?? '') === 'actions')
            ->map(function ($column) use ($item) {
                $value = data_get($item, $column['key']);
                
                // Handle different column types
                return match ($column['type'] ?? 'text') {
                    'badge' => $this->formatBadgeValue($value, $column),
                    'boolean' => $value ? 'Yes' : 'No',
                    'date' => $value instanceof \Carbon\Carbon ? $value->format('Y-m-d H:i:s') : $value,
                    default => (string) $value,
                };
            })
            ->values()
            ->toArray();
    }

    /**
     * Format badge column value for export.
     */
    private function formatBadgeValue($value, array $column): string
    {
        $badges = $column['badges'] ?? [];
        return $badges[$value]['label'] ?? (string) $value;
    }

    /**
     * Get relationships to eager load for export.
     */
    private function getRelationships(): array
    {
        return collect($this->columns)
            ->filter(fn($column) => str_contains($column['key'] ?? '', '.'))
            ->map(fn($column) => explode('.', $column['key'])[0])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Export to Excel format (placeholder - would need a package like Laravel Excel).
     */
    private function exportToExcel(array $selectedIds, string $filename): StreamedResponse
    {
        // This would require maatwebsite/excel package
        throw new \Exception('Excel export requires Laravel Excel package to be installed');
    }
}