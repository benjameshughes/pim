# ImportData.php Fixes - Laravel Exception-Based Approach

## Critical Issues Found & Solutions

### 1. Transaction Management (Lines 1194-1202)
**Problem**: Single large transaction causes timeouts
**Solution**: Remove transaction wrapper, let Laravel handle individual model transactions
```php
// Remove DB::beginTransaction() and DB::commit()
// Laravel models handle their own transactions
// Use DB::transaction() only for small atomic operations

private function startActualImport()
{
    // Remove the large transaction wrapper
    if ($this->autoGenerateParentMode) {
        $this->runTwoPhaseImport();
    } else {
        $this->runStandardImport();
    }
}

// For individual operations that need atomicity:
private function createVariantWithPricing($variantData, $pricingData)
{
    return DB::transaction(function () use ($variantData, $pricingData) {
        $variant = ProductVariant::create($variantData);
        if ($pricingData) {
            $variant->pricing()->create($pricingData);
        }
        return $variant;
    });
}
```

### 2. Attribute System Safety (Lines 1818+)
**Problem**: Calls to `setVariantAttributeValue()` without validation
**Solution**: Use Laravel's method validation and custom exceptions
```php
private function setVariantAttribute(ProductVariant $variant, string $key, $value, string $type = 'string')
{
    // Use Laravel's method_exists and throw custom exceptions
    if (!method_exists($variant, 'setVariantAttributeValue')) {
        throw new \App\Exceptions\Import\AttributeMethodException(
            "Variant model does not support attribute system"
        );
    }
    
    if (empty($value)) {
        throw new \App\Exceptions\Import\InvalidAttributeException(
            "Cannot set empty value for attribute: {$key}"
        );
    }
    
    // Let the model method handle its own validation and exceptions
    $variant->setVariantAttributeValue($key, $value, $type, 'core');
}
```

### 3. Mapping Data Validation (Lines 1063-1084)
**Problem**: No validation of mapped data structure  
**Solution**: Use Laravel validation and custom exceptions
```php
private function mapRowData($row, $sheetHeaders = null): array
{
    // Use Laravel's validation instead of try-catch
    if (!is_array($row) || empty($row)) {
        throw new \App\Exceptions\Import\InvalidRowDataException(
            'Row data must be a non-empty array'
        );
    }
    
    $headers = $sheetHeaders ?? $this->headers;
    if (empty($headers)) {
        throw new \App\Exceptions\Import\MissingHeadersException(
            'No headers available for row mapping'
        );
    }
    
    // Use Laravel's app() resolution with validation
    $buildMappingAction = app(\App\Actions\Import\BuildMappingIndex::class);
    $mapRowAction = app(\App\Actions\Import\MapRowToFields::class);
    
    $headerToFieldMapping = $buildMappingAction->execute($this->headers, $this->columnMapping);
    
    if (empty($headerToFieldMapping)) {
        throw new \App\Exceptions\Import\MappingFailedException(
            'Failed to build header-to-field mapping'
        );
    }
    
    $mapped = $mapRowAction->execute($row, $headers, $headerToFieldMapping);
    
    // Validate required fields are present
    $this->validateMappedRowData($mapped);
    
    return $mapped;
}

private function validateMappedRowData(array $mapped): void
{
    $requiredFields = ['product_name', 'variant_sku'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (empty($mapped[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new \App\Exceptions\Import\RequiredFieldsException(
            'Missing required fields: ' . implode(', ', $missingFields)
        );
    }
}
```

### 4. Memory Management (Lines 3167+)
**Problem**: Loading entire sheets into memory
**Solution**: Use Laravel's chunk processing and generators
```php
private function processSheetInChunks($worksheetIndex, callable $processor)
{
    $worksheetName = $this->availableWorksheets[$worksheetIndex]['name'];
    
    if (empty($worksheetName)) {
        throw new \App\Exceptions\Import\InvalidWorksheetException(
            "Worksheet at index {$worksheetIndex} not found"
        );
    }
    
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
    $reader->setReadDataOnly(true);
    $reader->setReadEmptyCells(false);
    $reader->setLoadSheetsOnly([$worksheetName]);
    
    $spreadsheet = $reader->load($this->file->getRealPath());
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Process in chunks using generators (Laravel pattern)
    foreach ($this->getRowChunks($worksheet, 100) as $chunk) {
        $processor($chunk);
    }
    
    $spreadsheet->disconnectWorksheets();
}

private function getRowChunks($worksheet, int $chunkSize): \Generator
{
    $maxRow = $worksheet->getHighestRow();
    $headers = $this->getWorksheetHeaders($worksheet);
    
    $chunk = [];
    for ($row = 2; $row <= $maxRow; $row++) {
        $rowData = $this->getRowData($worksheet, $row, $headers);
        
        if (!empty($rowData)) {
            $chunk[] = ['data' => $rowData, 'headers' => $headers];
            
            if (count($chunk) >= $chunkSize) {
                yield $chunk;
                $chunk = [];
            }
        }
    }
    
    if (!empty($chunk)) {
        yield $chunk;
    }
}
```

### 5. Error Handling Enhancement  
**Solution**: Use Laravel exceptions with proper validation
```php
private function handleVariantImport(Product $product, array $data, ?string $extractedColor, ?string $extractedSize): ?ProductVariant
{
    // Use Laravel's validation instead of try-catch
    if (empty($data['variant_sku'])) {
        throw new \App\Exceptions\Import\RequiredFieldException(
            'Variant SKU is required for import'
        );
    }
    
    if (!$product->exists) {
        throw new \App\Exceptions\Import\InvalidProductException(
            "Product does not exist in database: {$product->name}"
        );
    }
    
    // Use Laravel's model validation
    $variantData = $this->buildVariantData($product, $data);
    
    // Let Eloquent handle database constraints and validation
    $variant = $this->createOrUpdateVariant($variantData, $data);
    
    // Set attributes using the safe method
    $this->setVariantAttributes($variant, $extractedColor, $extractedSize);
    
    return $variant;
}

private function buildVariantData(Product $product, array $data): array
{
    $variantData = [
        'product_id' => $product->id,
        'sku' => $data['variant_sku'],
        'stock_level' => $data['stock_level'] ?? 0,
        'package_length' => $data['package_length'],
        'package_width' => $data['package_width'], 
        'package_height' => $data['package_height'],
        'package_weight' => $data['package_weight'],
    ];
    
    // Remove null values (Laravel best practice)
    return array_filter($variantData, fn($value) => $value !== null);
}
```

### 6. Required Model Validation
Add validation for required models:
```php
private function validateRequiredModels()
{
    $errors = [];
    
    // Check if required models exist
    if (!class_exists('App\\Models\\Product')) {
        $errors[] = 'Product model not found';
    }
    
    if (!class_exists('App\\Models\\ProductVariant')) {
        $errors[] = 'ProductVariant model not found';
    }
    
    // Check if database tables exist
    if (!Schema::hasTable('products')) {
        $errors[] = 'Products table not found';
    }
    
    if (!Schema::hasTable('product_variants')) {
        $errors[] = 'Product variants table not found';
    }
    
    if (!empty($errors)) {
        throw new \Exception('System validation failed: ' . implode(', ', $errors));
    }
}
```

## Immediate Actions Needed:

1. **Break up the single transaction** - Most critical fix
2. **Add attribute method validation** - Prevents method not found errors  
3. **Implement proper null checking** - Prevents undefined array key errors
4. **Add fallback mapping logic** - Prevents service injection failures
5. **Implement memory management** - Prevents out of memory errors

## Testing Steps:

1. Test with small file (< 100 rows) first
2. Test with missing columns
3. Test with malformed data
4. Test with large files (> 1000 rows)
5. Monitor memory usage and transaction times
