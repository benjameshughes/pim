<?php

namespace App\Livewire\Import;

use App\Services\Import\ImportBuilder;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateImport extends Component
{
    use WithFileUploads;

    public $file;
    public $import_mode = 'create_or_update';
    public $extract_attributes = true;
    public $detect_made_to_measure = true;
    public $dimensions_digits_only = true;
    public $group_by_sku = false;
    public $chunk_size = 50;

    public $uploading = false;
    public $uploadProgress = 0;

    protected $rules = [
        'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
        'import_mode' => 'required|in:create_only,update_existing,create_or_update',
        'extract_attributes' => 'boolean',
        'detect_made_to_measure' => 'boolean',
        'dimensions_digits_only' => 'boolean',
        'group_by_sku' => 'boolean',
        'chunk_size' => 'integer|min:10|max:500',
    ];

    public function submit()
    {
        $this->validate();

        try {
            $this->uploading = true;

            $builder = ImportBuilder::create()
                ->fromFile($this->file)
                ->withMode($this->import_mode);

            if ($this->extract_attributes) {
                $builder->extractAttributes();
            }

            if ($this->detect_made_to_measure) {
                $builder->detectMadeToMeasure();
            }

            if ($this->dimensions_digits_only) {
                $builder->dimensionsDigitsOnly();
            }

            if ($this->group_by_sku) {
                $builder->groupBySku();
            }

            $builder->withChunkSize($this->chunk_size);

            $session = $builder->execute();

            Log::info('Import session created via Livewire', [
                'session_id' => $session->session_id,
                'user_id' => Auth::id(),
                'filename' => $session->original_filename,
                'mode' => $this->import_mode,
            ]);

            $this->dispatch('import-created', [
                'session_id' => $session->session_id,
                'redirect_url' => route('import.show', $session->session_id),
            ]);

            $this->dispatch('toast', [
                'type' => 'success',
                'message' => 'Import session created successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Import session creation failed via Livewire', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filename' => $this->file?->getClientOriginalName(),
            ]);

            $this->dispatch('toast', [
                'type' => 'error',
                'message' => 'Failed to create import: ' . $e->getMessage(),
            ]);
        } finally {
            $this->uploading = false;
            $this->uploadProgress = 0;
        }
    }

    public function updatedFile()
    {
        $this->validate(['file' => 'required|file|mimes:csv,xlsx,xls|max:10240']);
    }

    public function render()
    {
        return view('livewire.import.create-import', [
            'supportedFormats' => ['csv', 'xlsx', 'xls'],
            'importModes' => [
                'create_only' => [
                    'name' => 'Create Only',
                    'description' => 'Skip existing records, only create new ones',
                    'icon' => 'plus-circle',
                ],
                'update_existing' => [
                    'name' => 'Update Existing',
                    'description' => 'Only update existing records, skip new ones',
                    'icon' => 'pencil',
                ],
                'create_or_update' => [
                    'name' => 'Create or Update',
                    'description' => 'Create new records and update existing ones',
                    'icon' => 'refresh',
                ],
            ],
        ]);
    }
}