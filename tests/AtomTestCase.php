<?php

namespace Tests;

use App\Atom\Resources\ResourceManager;
use App\Atom\Core\Navigation\NavigationManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Base Test Case for Atom Framework
 * 
 * Provides common setup, utilities, and assertions for testing
 * the Atom framework components.
 */
abstract class AtomTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear framework caches before each test
        $this->clearAtomCaches();
        
        // Create test models table if needed
        $this->createTestTables();
        
        // Register test resources
        $this->registerTestResources();
    }

    protected function tearDown(): void
    {
        // Clear caches after each test
        $this->clearAtomCaches();
        
        parent::tearDown();
    }

    /**
     * Clear all Atom framework caches.
     */
    protected function clearAtomCaches(): void
    {
        ResourceManager::clearCache();
        NavigationManager::clear();
    }

    /**
     * Create test database tables.
     */
    protected function createTestTables(): void
    {
        if (!Schema::hasTable('test_models')) {
            Schema::create('test_models', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->nullable();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->integer('priority')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('test_categories')) {
            Schema::create('test_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug');
                $table->timestamps();
            });
        }
    }

    /**
     * Register test resources for testing.
     */
    protected function registerTestResources(): void
    {
        // This will be implemented as needed in individual tests
    }

    /**
     * Create a test model instance.
     */
    protected function createTestModel(array $attributes = []): object
    {
        return (object) array_merge([
            'id' => 1,
            'name' => 'Test Model',
            'email' => 'test@example.com',
            'description' => 'Test description',
            'active' => true,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes);
    }

    /**
     * Assert that a resource is properly registered.
     */
    protected function assertResourceRegistered(string $resourceClass): void
    {
        $this->assertTrue(
            ResourceManager::hasResource($resourceClass),
            "Resource {$resourceClass} should be registered"
        );
    }

    /**
     * Assert that a table has specific columns.
     */
    protected function assertTableHasColumns($table, array $expectedColumns): void
    {
        $columns = $table->toArray()['columns'];
        $columnNames = array_map(function ($column) {
            return is_array($column) ? $column['name'] : $column->getName();
        }, $columns);

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains(
                $expectedColumn,
                $columnNames,
                "Table should have column: {$expectedColumn}"
            );
        }
    }

    /**
     * Assert that navigation contains specific items.
     */
    protected function assertNavigationContains(string $label, $navigation = null): void
    {
        if ($navigation === null) {
            $navigation = NavigationManager::getItems();
        }

        $labels = $navigation->pluck('label')->toArray();
        
        $this->assertContains(
            $label,
            $labels,
            "Navigation should contain item: {$label}"
        );
    }

    /**
     * Create a mock Livewire component for table testing.
     */
    protected function createMockLivewireComponent(): object
    {
        return new class {
            public $tableSearch = '';
            public $tableFilters = [];
            public $tableSortColumn = '';
            public $tableSortDirection = 'asc';
            public $tableRecordsPerPage = 10;
            public $selectedTableRecords = [];

            public function getTableSearch(): string
            {
                return $this->tableSearch;
            }

            public function getTableFilters(): array
            {
                return $this->tableFilters;
            }

            public function getTableSortColumn(): string
            {
                return $this->tableSortColumn;
            }

            public function getTableSortDirection(): string
            {
                return $this->tableSortDirection;
            }

            public function getTableRecordsPerPage(): int
            {
                return $this->tableRecordsPerPage;
            }
        };
    }
}