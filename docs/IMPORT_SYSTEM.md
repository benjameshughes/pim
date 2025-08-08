# Import System Documentation

## Overview

The new Import System is a complete redesign of the product import functionality, built using modern Laravel patterns including the Actions Pipeline architecture, Conflict Resolution system, and background job processing. It eliminates the timeout issues of the legacy system while providing intelligent data processing and robust error handling.

## Architecture

### Core Components

#### 1. ImportSession Model
Central entity that tracks import progress and configuration:
- **Session Management**: Unique session IDs, user association, file tracking
- **Progress Tracking**: Real-time progress updates with WebSocket broadcasting  
- **Status Management**: Complete state machine (initializing → analyzing → processing → completed)
- **Statistics Storage**: Comprehensive metrics and performance data

#### 2. ImportBuilder (Fluent API)
Provides an elegant interface for configuring imports:

```php
$session = ImportBuilder::create()
    ->fromFile($uploadedFile)
    ->withMode('create_or_update')
    ->extractAttributes()
    ->detectMadeToMeasure()
    ->dimensionsDigitsOnly()
    ->groupBySku()
    ->execute();
```

#### 3. Background Job Pipeline
Eliminates timeout issues with asynchronous processing:
- **AnalyzeFileJob**: File structure analysis and column mapping suggestions
- **DryRunJob**: Validation and conflict prediction before processing
- **ProcessImportJob**: Main data processing with Actions pipeline integration
- **FinalizeImportJob**: Cleanup, reporting, and notifications

### Actions Pipeline Architecture

The Actions Pipeline provides a modular, extensible approach to row processing:

#### Core Actions
- **ValidateRowAction**: Configurable validation with custom rules
- **ExtractAttributesAction**: MTM detection and dimension extraction
- **ResolveProductAction**: Product creation/resolution with SKU grouping
- **HandleConflictsAction**: Automatic conflict resolution with retry logic

#### Middleware Stack
- **TimingMiddleware**: Execution timing and timeout handling
- **LoggingMiddleware**: Comprehensive action logging
- **ErrorHandlingMiddleware**: Retry logic and graceful degradation

#### Pipeline Configuration
```php
$pipeline = PipelineBuilder::importPipeline([
    'import_mode' => 'create_or_update',
    'extract_mtm' => true,
    'extract_dimensions' => true,
    'use_sku_grouping' => true,
    'handle_conflicts' => true,
    'timeout_seconds' => 60.0,
    'max_retries' => 3,
]);
```

### Conflict Resolution System

Handles database constraint violations intelligently:

#### Conflict Types
- **Duplicate SKU**: Generate unique SKUs, update existing, or skip
- **Duplicate Barcode**: Reassign, remove, or skip conflicting barcodes  
- **Variant Constraints**: Merge data or modify attributes to avoid conflicts
- **Generic Unique Constraints**: Field-specific resolution strategies

#### Resolution Strategies
```php
$resolver = ConflictResolver::create([
    'sku_resolution' => [
        'strategy' => 'generate_unique',
        'generate_unique_sku' => true,
    ],
    'barcode_resolution' => [
        'strategy' => 'reassign',
        'allow_reassignment' => true,
    ],
    'variant_resolution' => [
        'strategy' => 'merge_data',
        'allow_merging' => true,
    ],
]);
```

## Features

### Intelligent Data Extraction

#### Made-to-Measure Detection
Automatically detects MTM, bespoke, and custom products:
- **Pattern Matching**: Multiple regex patterns with confidence scoring
- **Context Awareness**: Field-specific confidence boosts
- **Title Enhancement**: Automatically enhances product names with MTM indicators

#### Smart Dimension Extraction  
Extracts dimensions as pure numbers for database storage:
- **Multiple Patterns**: Width x Drop, SKU-embedded dimensions, various formats
- **Units Removal**: Stores 150, 200 instead of "150cm", "200mm"
- **Size String Generation**: Creates size strings when missing

#### SKU-Based Grouping
Groups variants by SKU patterns instead of name matching:
- **Pattern Analysis**: Detects dominant SKU patterns (001-001, 001-002)
- **Parent SKU Extraction**: Automatically extracts parent SKUs from variants
- **Confidence Scoring**: Validates pattern reliability before application

### Import Modes

#### Create Only
- Skips existing records
- Only creates new products/variants
- Ideal for initial data loads

#### Update Existing  
- Only updates existing records
- Skips non-existent items
- Perfect for data refresh scenarios

#### Create or Update
- Creates new records and updates existing
- Most flexible option
- Handles mixed data scenarios

### Advanced Processing Options

#### Chunked Processing
- Configurable chunk sizes (10-500 rows)
- Memory-efficient processing
- Prevents timeout issues

#### Real-Time Progress Tracking
- WebSocket broadcasting
- Detailed progress stages
- Live statistics updates

#### Comprehensive Error Handling
- Detailed error logging per row
- Graceful degradation options
- Retry mechanisms with exponential backoff

## User Interface

### Import Dashboard
- Import statistics and history
- Status monitoring with progress bars
- Quick access to recent imports

### File Upload Interface
- Drag-and-drop file upload
- Real-time validation
- Configuration options with descriptions

### Column Mapping Interface  
- Visual column-to-field mapping
- Auto-mapping suggestions
- Real-time validation feedback
- Sample data preview

### Progress Monitoring
- Live progress updates
- Detailed processing stages
- Error and warning displays
- Downloadable reports

## API Reference

### ImportBuilder Methods

```php
// File configuration
ImportBuilder::create()
    ->fromFile(UploadedFile $file)
    
// Import behavior
    ->withMode(string $mode)              // create_only, update_existing, create_or_update
    ->withChunkSize(int $size)            // 10-500 rows per chunk
    
// Feature toggles
    ->extractAttributes(bool $enabled)    // Smart attribute extraction
    ->detectMadeToMeasure(bool $enabled)  // MTM detection
    ->dimensionsDigitsOnly(bool $enabled) // Digits-only dimensions  
    ->groupBySku(bool $enabled)           // SKU-based grouping
    
// Execution
    ->execute(): ImportSession
```

### ImportSession Methods

```php
// Progress management
$session->updateProgress(string $stage, string $operation, int $percentage)
$session->markAsStarted()
$session->markAsCompleted()
$session->markAsFailed(string $reason)

// Error handling
$session->addError(string $message)
$session->addWarning(string $message)

// Relationships
$session->user()          // Belongs to User
$session->products()      // Has many Products (created during import)
$session->variants()      // Has many ProductVariants (created during import)
```

### Actions Pipeline

```php
// Create custom pipeline
$pipeline = PipelineBuilder::create()
    ->addAction(new ValidateRowAction(['rules' => $rules]))
    ->addAction(new ExtractAttributesAction(['extract_mtm' => true]))
    ->addAction(new ResolveProductAction(['import_mode' => 'create_or_update']))
    ->withProductionMiddleware()
    ->build();

// Execute pipeline
$context = new ActionContext($rowData, $rowNumber, $configuration);
$result = $pipeline->execute($context);
```

### Conflict Resolution

```php
// Handle conflicts manually
$resolver = ConflictResolver::create($config);
$resolution = $resolver->resolve($queryException, $context);

if ($resolution->isResolved()) {
    if ($resolution->shouldRetry()) {
        $modifiedData = $resolution->getModifiedData();
        // Retry with modified data
    } elseif ($resolution->shouldUpdate()) {
        // Update existing record
    }
}
```

## Configuration

### Environment Variables

```env
# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_IMPORTS=imports

# Import Settings  
IMPORT_MAX_FILE_SIZE=10240          # 10MB in KB
IMPORT_DEFAULT_CHUNK_SIZE=50        # Default rows per chunk
IMPORT_MAX_TIMEOUT=1800            # 30 minutes in seconds

# Conflict Resolution
IMPORT_GENERATE_UNIQUE_SKUS=true
IMPORT_ALLOW_BARCODE_REASSIGNMENT=false
IMPORT_DEFAULT_CONFLICT_STRATEGY=skip
```

### Queue Configuration

Ensure the imports queue is running:

```bash
php artisan queue:work --queue=imports
```

### Storage Configuration

Import files are stored in the `imports/` directory:

```php
// config/filesystems.php
'imports' => [
    'driver' => 'local',
    'root' => storage_path('app/imports'),
    'visibility' => 'private',
],
```

## Testing

### Test Coverage

Comprehensive test suite covering:
- **Unit Tests**: Individual component testing
- **Feature Tests**: End-to-end import flows  
- **Integration Tests**: Job pipeline and database interactions
- **Conflict Resolution Tests**: All conflict types and resolution strategies

### Running Tests

```bash
# All import system tests
php artisan test --filter Import

# Specific test suites
php artisan test tests/Feature/Import/ActionsTest.php
php artisan test tests/Feature/Import/ConflictResolutionTest.php
php artisan test tests/Feature/Import/ImportSystemIntegrationTest.php
php artisan test tests/Unit/Import/MiddlewareTest.php
```

### Test Data Factories

```php
// Create test import session
$session = ImportSession::factory()->create([
    'status' => 'processing',
    'configuration' => ['import_mode' => 'create_or_update'],
]);

// Create test data with conflicts
$existingVariant = ProductVariant::factory()->create(['sku' => 'TEST-001']);
// Import will detect conflict and resolve automatically
```

## Performance Optimization

### Memory Management
- Chunked file processing prevents memory exhaustion
- Streaming file reading for large files
- Automatic garbage collection between chunks

### Database Optimization
- Batch inserts/updates where possible
- Optimized queries with proper indexing
- Connection pooling for high-volume imports

### Queue Optimization
- Separate queue for import jobs
- Configurable job timeouts
- Dead job handling and retry logic

## Troubleshooting

### Common Issues

#### Import Timeouts
**Problem**: Legacy system timeout issues  
**Solution**: New system uses background jobs - no timeouts

#### Memory Exhaustion
**Problem**: Large files cause memory issues  
**Solution**: Chunked processing with configurable chunk sizes

#### Constraint Violations
**Problem**: Database conflicts cause import failures  
**Solution**: Automatic conflict resolution with multiple strategies

#### Column Mapping Issues
**Problem**: Incorrect field mapping  
**Solution**: Auto-mapping with manual override capabilities

### Debug Mode

Enable detailed logging:

```php
// config/logging.php
'import' => [
    'driver' => 'single',
    'path' => storage_path('logs/import.log'),
    'level' => 'debug',
],
```

### Monitoring

Monitor import performance:

```php
// Get import statistics
$stats = ImportSession::selectRaw('
    COUNT(*) as total_imports,
    AVG(processing_time_seconds) as avg_processing_time,
    AVG(successful_rows / NULLIF(total_rows, 0) * 100) as avg_success_rate
')->where('status', 'completed')->first();
```

## Migration from Legacy System

### Compatibility

The new system is designed to coexist with the legacy import:
- Routes: `/import` (new) vs `/import-legacy` (old)  
- Database: Separate `import_sessions` table
- Files: Separate storage directories

### Migration Steps

1. **Test New System**: Run parallel imports to validate
2. **User Training**: Familiarize users with new interface
3. **Data Migration**: Migrate column mappings and preferences
4. **Gradual Rollout**: Phase out legacy system over time

### Rollback Plan

If issues arise:
1. Disable new import routes
2. Revert to legacy system temporarily  
3. Investigate and fix issues
4. Re-enable new system

## Best Practices

### File Preparation
- Use consistent column headers
- Include required fields (product_name, variant_sku)
- Validate data before upload
- Remove empty rows and columns

### Column Mapping
- Review auto-mapping suggestions
- Map all required fields
- Test with small dataset first
- Save mapping templates for reuse

### Import Configuration
- Choose appropriate import mode
- Enable relevant processing options
- Set reasonable chunk sizes
- Monitor progress and adjust

### Error Handling
- Review dry run results
- Address validation errors
- Configure conflict resolution
- Monitor error logs

This documentation provides comprehensive guidance for using, configuring, and troubleshooting the new Import System. The modular architecture ensures scalability and maintainability while providing a superior user experience compared to the legacy system.