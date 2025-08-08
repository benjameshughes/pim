<?php

namespace Database\Factories;

use App\Models\ImportSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ImportSessionFactory extends Factory
{
    protected $model = ImportSession::class;

    public function definition(): array
    {
        return [
            'session_id' => Str::random(32),
            'user_id' => User::factory(),
            'original_filename' => $this->faker->randomElement(['data.csv', 'products.xlsx', 'import.xls']),
            'file_path' => 'imports/' . $this->faker->uuid() . '.csv',
            'file_type' => $this->faker->randomElement(['csv', 'xlsx', 'xls']),
            'file_size' => $this->faker->numberBetween(1024, 10 * 1024 * 1024), // 1KB to 10MB
            'file_hash' => $this->faker->sha256(),
            'status' => 'initializing',
            'current_stage' => null,
            'current_operation' => null,
            'progress_percentage' => 0,
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_seconds' => null,
            'rows_per_second' => null,
            'configuration' => [
                'import_mode' => 'create_or_update',
                'chunk_size' => 50,
                'smart_attribute_extraction' => true,
                'detect_made_to_measure' => false,
                'dimensions_digits_only' => false,
                'group_by_sku' => false,
            ],
            'column_mapping' => null,
            'file_analysis' => null,
            'dry_run_results' => null,
            'final_results' => null,
            'errors' => [],
            'warnings' => [],
            'failure_reason' => null,
            'current_job_id' => null,
            'job_chain_status' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'progress_percentage' => 100,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now(),
            'processing_time_seconds' => 600, // 10 minutes
            'total_rows' => 100,
            'processed_rows' => 100,
            'successful_rows' => 95,
            'failed_rows' => 5,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'failure_reason' => 'Import failed due to validation errors',
            'errors' => [
                ['message' => 'Invalid data format', 'timestamp' => now()->toISOString()],
            ],
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'progress_percentage' => 45,
            'current_stage' => 'processing_data',
            'current_operation' => 'Importing products',
            'started_at' => now()->subMinutes(3),
            'total_rows' => 200,
            'processed_rows' => 90,
            'successful_rows' => 85,
            'failed_rows' => 5,
        ]);
    }

    public function withAnalysis(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_analysis' => [
                'headers' => ['Product Name', 'SKU', 'Price', 'Color'],
                'total_rows' => 150,
                'sample_data' => [
                    ['Test Product 1', 'TEST-001', '99.99', 'Red'],
                    ['Test Product 2', 'TEST-002', '149.99', 'Blue'],
                ],
                'suggested_mapping' => [
                    0 => 'product_name',
                    1 => 'variant_sku',
                    2 => 'retail_price',
                    3 => 'variant_color',
                ],
            ],
        ]);
    }

    public function withMapping(): static
    {
        return $this->state(fn (array $attributes) => [
            'column_mapping' => [
                0 => 'product_name',
                1 => 'variant_sku',
                2 => 'retail_price',
                3 => 'variant_color',
            ],
        ]);
    }

    public function withDryRun(): static
    {
        return $this->state(fn (array $attributes) => [
            'dry_run_results' => [
                'predictions' => [
                    'will_create' => 45,
                    'will_update' => 30,
                    'will_skip' => 25,
                ],
                'validation_summary' => [
                    'total_rows' => 100,
                    'valid_rows' => 95,
                    'invalid_rows' => 5,
                ],
                'conflict_analysis' => [
                    'potential_sku_conflicts' => 2,
                    'potential_barcode_conflicts' => 1,
                ],
                'quality_metrics' => [
                    'data_completeness' => 0.92,
                    'attribute_extraction_success' => 0.88,
                ],
            ],
        ]);
    }

    public function withResults(): static
    {
        return $this->state(fn (array $attributes) => [
            'final_results' => [
                'statistics' => [
                    'products_created' => 20,
                    'variants_created' => 85,
                    'products_updated' => 5,
                    'variants_updated' => 10,
                ],
                'performance_metrics' => [
                    'processing_time' => 120,
                    'rows_per_second' => 0.83,
                ],
                'comprehensive_report' => [
                    'import_summary' => ['total' => 100],
                    'processing_results' => ['success_rate' => 95],
                    'data_created' => ['products' => 20, 'variants' => 85],
                    'quality_metrics' => ['completeness' => 0.92],
                    'recommendations' => [
                        ['type' => 'data_quality', 'message' => 'Consider reviewing failed rows'],
                    ],
                ],
            ],
        ]);
    }
}