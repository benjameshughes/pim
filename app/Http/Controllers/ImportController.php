<?php

namespace App\Http\Controllers;

use App\Models\ImportSession;
use App\Services\Import\ImportBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    /**
     * Display import dashboard
     */
    public function index()
    {
        $recentImports = ImportSession::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $statistics = [
            'total_imports' => ImportSession::where('user_id', Auth::id())->count(),
            'successful_imports' => ImportSession::where('user_id', Auth::id())
                ->where('status', 'completed')
                ->count(),
            'failed_imports' => ImportSession::where('user_id', Auth::id())
                ->where('status', 'failed')
                ->count(),
            'processing_imports' => ImportSession::where('user_id', Auth::id())
                ->whereIn('status', ['processing', 'dry_run', 'analyzing_file'])
                ->count(),
        ];

        return view('import.new.index', compact('recentImports', 'statistics'));
    }

    /**
     * Show create import form
     */
    public function create()
    {
        $supportedFormats = ['csv', 'xlsx', 'xls'];
        $importModes = [
            'create_only' => 'Create Only - Skip existing records',
            'update_existing' => 'Update Existing - Only update existing records',
            'create_or_update' => 'Create or Update - Create new and update existing',
        ];

        return view('import.create', compact('supportedFormats', 'importModes'));
    }

    /**
     * Store new import session
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'import_mode' => 'required|in:create_only,update_existing,create_or_update',
            'extract_attributes' => 'boolean',
            'detect_made_to_measure' => 'boolean',
            'dimensions_digits_only' => 'boolean',
            'group_by_sku' => 'boolean',
            'chunk_size' => 'integer|min:10|max:500',
        ]);

        try {
            $builder = ImportBuilder::create()
                ->fromFile($request->file('file'))
                ->withMode($request->input('import_mode'));

            if ($request->boolean('extract_attributes')) {
                $builder->extractAttributes();
            }

            if ($request->boolean('detect_made_to_measure')) {
                $builder->detectMadeToMeasure();
            }

            if ($request->boolean('dimensions_digits_only')) {
                $builder->dimensionsDigitsOnly();
            }

            if ($request->boolean('group_by_sku')) {
                $builder->groupBySku();
            }

            if ($request->has('chunk_size')) {
                $builder->withChunkSize($request->integer('chunk_size'));
            }

            $session = $builder->execute();

            Log::info('Import session created', [
                'session_id' => $session->session_id,
                'user_id' => Auth::id(),
                'filename' => $session->original_filename,
                'mode' => $request->input('import_mode'),
            ]);

            return response()->json([
                'success' => true,
                'session_id' => $session->session_id,
                'redirect_url' => route('import.show', $session->session_id),
            ]);

        } catch (\Exception $e) {
            Log::error('Import session creation failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filename' => $request->file('file')->getClientOriginalName(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create import session: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show import session details and progress
     */
    public function show(string $sessionId)
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('import.show', compact('session'));
    }

    /**
     * Get import session status (AJAX)
     */
    public function status(string $sessionId): JsonResponse
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'session_id' => $session->session_id,
            'status' => $session->status,
            'progress_percentage' => $session->progress_percentage,
            'current_stage' => $session->current_stage,
            'current_operation' => $session->current_operation,
            'processed_rows' => $session->processed_rows,
            'successful_rows' => $session->successful_rows,
            'failed_rows' => $session->failed_rows,
            'total_rows' => $session->total_rows,
            'errors' => $session->errors,
            'warnings' => $session->warnings,
            'processing_time_seconds' => $session->processing_time_seconds,
        ]);
    }

    /**
     * Show column mapping interface
     */
    public function mapping(string $sessionId)
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$session->file_analysis) {
            return redirect()->route('import.show', $sessionId)
                ->with('error', 'File analysis not completed yet');
        }

        $availableFields = [
            'product_name' => 'Product Name',
            'variant_sku' => 'Variant SKU', 
            'description' => 'Description',
            'variant_color' => 'Color',
            'variant_size' => 'Size',
            'retail_price' => 'Retail Price',
            'cost_price' => 'Cost Price',
            'barcode' => 'Barcode',
            'barcode_type' => 'Barcode Type',
            'stock_level' => 'Stock Level',
            'package_length' => 'Package Length',
            'package_width' => 'Package Width',
            'package_height' => 'Package Height',
            'package_weight' => 'Package Weight',
        ];

        return view('import.mapping', compact('session', 'availableFields'));
    }

    /**
     * Save column mapping
     */
    public function saveMapping(Request $request, string $sessionId): JsonResponse
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $request->validate([
            'column_mapping' => 'required|array',
            'column_mapping.*' => 'nullable|string',
        ]);

        try {
            $session->update([
                'column_mapping' => $request->input('column_mapping'),
                'status' => 'mapped',
            ]);

            Log::info('Column mapping saved', [
                'session_id' => $session->session_id,
                'mapping_count' => count(array_filter($request->input('column_mapping'))),
            ]);

            return response()->json([
                'success' => true,
                'redirect_url' => route('import.show', $sessionId),
            ]);

        } catch (\Exception $e) {
            Log::error('Column mapping save failed', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to save column mapping: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Start processing after mapping confirmation
     */
    public function startProcessing(string $sessionId): JsonResponse
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (!$session->column_mapping) {
            return response()->json([
                'success' => false,
                'error' => 'Column mapping is required before processing',
            ], 422);
        }

        try {
            $session->update(['status' => 'dry_run']);
            
            // Dispatch dry run job
            \App\Jobs\Import\DryRunJob::dispatch($session)
                ->onQueue('imports');

            Log::info('Import processing started', [
                'session_id' => $session->session_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import processing started',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start import processing', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start processing: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel import session
     */
    public function cancel(string $sessionId): JsonResponse
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (in_array($session->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot cancel import in status: ' . $session->status,
            ], 422);
        }

        try {
            $session->update([
                'status' => 'cancelled',
                'completed_at' => now(),
            ]);

            $session->addWarning('Import cancelled by user');

            Log::info('Import session cancelled', [
                'session_id' => $session->session_id,
                'cancelled_at_status' => $session->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel import', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to cancel import: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download import results/report
     */
    public function download(string $sessionId, string $type = 'report')
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ($session->status !== 'completed') {
            return redirect()->route('import.show', $sessionId)
                ->with('error', 'Import must be completed to download results');
        }

        switch ($type) {
            case 'report':
                return $this->downloadReport($session);
                
            case 'errors':
                return $this->downloadErrors($session);
                
            default:
                abort(404, 'Invalid download type');
        }
    }

    private function downloadReport(ImportSession $session)
    {
        $report = $session->final_results['comprehensive_report'] ?? [];
        
        if (empty($report)) {
            return redirect()->route('import.show', $session->session_id)
                ->with('error', 'No report available for download');
        }

        $filename = 'import-report-' . $session->session_id . '.json';
        
        return response()->json($report, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function downloadErrors(ImportSession $session)
    {
        $errors = $session->errors ?? [];
        
        if (empty($errors)) {
            return redirect()->route('import.show', $session->session_id)
                ->with('error', 'No errors to download');
        }

        $csvContent = "Row,Error,Timestamp\n";
        foreach ($errors as $error) {
            $csvContent .= sprintf(
                "%s,%s,%s\n",
                $error['row'] ?? 'Unknown',
                '"' . str_replace('"', '""', $error['message'] ?? 'Unknown error') . '"',
                $error['timestamp'] ?? now()->toISOString()
            );
        }

        $filename = 'import-errors-' . $session->session_id . '.csv';
        
        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    /**
     * Delete import session
     */
    public function destroy(string $sessionId): JsonResponse
    {
        $session = ImportSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        try {
            // Clean up files if they exist
            if ($session->file_path && \Illuminate\Support\Facades\Storage::exists($session->file_path)) {
                \Illuminate\Support\Facades\Storage::delete($session->file_path);
            }

            $session->delete();

            Log::info('Import session deleted', [
                'session_id' => $session->session_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Import session deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete import session', [
                'session_id' => $session->session_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete import session: ' . $e->getMessage(),
            ], 422);
        }
    }
}