<?php

namespace App\Services\Import\Actions;

use App\Services\Import\Actions\Middleware\ErrorHandlingMiddleware;
use App\Services\Import\Actions\Middleware\LoggingMiddleware;
use App\Services\Import\Actions\Middleware\TimingMiddleware;

class PipelineBuilder
{
    private ActionPipeline $pipeline;

    public function __construct()
    {
        $this->pipeline = ActionPipeline::create();
    }

    public static function create(): self
    {
        return new self();
    }

    /**
     * Add standard import row processing actions
     */
    public function withStandardImportActions(array $config = []): self
    {
        // Add validation action
        if ($config['validate_rows'] ?? true) {
            $validationRules = $config['validation_rules'] ?? [];
            $this->pipeline->add(
                (new ValidateRowAction(['rules' => $validationRules]))
                    ->setOptional($config['validation_optional'] ?? false)
            );
        }

        // Add attribute extraction
        if ($config['extract_attributes'] ?? true) {
            $this->pipeline->add(
                (new ExtractAttributesAction([
                    'extract_mtm' => $config['extract_mtm'] ?? true,
                    'extract_dimensions' => $config['extract_dimensions'] ?? true,
                ]))->setOptional($config['attribute_extraction_optional'] ?? true)
            );
        }

        // Add product resolution
        $this->pipeline->add(
            new ResolveProductAction([
                'import_mode' => $config['import_mode'] ?? 'create_or_update',
                'use_sku_grouping' => $config['use_sku_grouping'] ?? false,
            ])
        );

        // Add conflict handling
        if ($config['handle_conflicts'] ?? true) {
            $this->pipeline->add(
                (new HandleConflictsAction([
                    'max_retries' => $config['conflict_max_retries'] ?? 3,
                    'halt_on_unresolvable' => $config['halt_on_unresolvable_conflicts'] ?? false,
                    'conflict_resolution' => $config['conflict_resolution'] ?? [],
                ]))->setOptional($config['conflict_handling_optional'] ?? false)
            );
        }

        return $this;
    }

    /**
     * Add standard middleware stack for production use
     */
    public function withProductionMiddleware(array $config = []): self
    {
        // Error handling (outermost)
        $this->pipeline->through(ErrorHandlingMiddleware::withRetries(
            $config['max_retries'] ?? 2
        ));

        // Timing middleware
        $this->pipeline->through(TimingMiddleware::create(
            $config['timeout_seconds'] ?? 30.0,
            $config['include_timing'] ?? true
        ));

        // Logging middleware (innermost for detailed action logging)
        $this->pipeline->through(LoggingMiddleware::create([
            'log_successful' => $config['log_successful'] ?? false,
            'log_failed' => $config['log_failed'] ?? true,
            'log_context' => $config['log_context'] ?? false,
        ]));

        return $this;
    }

    /**
     * Add debug middleware for development
     */
    public function withDebugMiddleware(): self
    {
        $this->pipeline->through(TimingMiddleware::create(300.0, true)); // 5 min timeout
        $this->pipeline->through(LoggingMiddleware::create([
            'log_successful' => true,
            'log_failed' => true,
            'log_context' => true,
        ]));

        return $this;
    }

    /**
     * Add minimal middleware for testing
     */
    public function withTestMiddleware(): self
    {
        $this->pipeline->through(ErrorHandlingMiddleware::withGracefulDegradation());
        return $this;
    }

    /**
     * Add custom action
     */
    public function addAction(ImportAction $action): self
    {
        $this->pipeline->add($action);
        return $this;
    }

    /**
     * Add custom middleware
     */
    public function addMiddleware(ImportMiddleware $middleware): self
    {
        $this->pipeline->through($middleware);
        return $this;
    }

    /**
     * Build and return the configured pipeline
     */
    public function build(): ActionPipeline
    {
        return $this->pipeline;
    }

    /**
     * Convenience method to execute immediately with context
     */
    public function execute(ActionContext $context): ActionResult
    {
        return $this->pipeline->execute($context);
    }

    /**
     * Create a complete import processing pipeline
     */
    public static function importPipeline(array $config = []): self
    {
        return static::create()
            ->withStandardImportActions($config)
            ->withProductionMiddleware($config);
    }

    /**
     * Create a simple validation-only pipeline
     */
    public static function validationPipeline(array $validationRules = []): self
    {
        return static::create()
            ->addAction(new ValidateRowAction(['rules' => $validationRules]))
            ->withTestMiddleware();
    }

    /**
     * Create an attribute extraction only pipeline
     */
    public static function attributePipeline(array $config = []): self
    {
        return static::create()
            ->addAction(new ExtractAttributesAction([
                'extract_mtm' => $config['extract_mtm'] ?? true,
                'extract_dimensions' => $config['extract_dimensions'] ?? true,
            ]))
            ->withTestMiddleware();
    }
}