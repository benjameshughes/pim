# Import System Cleanup Plan

## 🆕 NEW IMPORT SYSTEM FILES (Keep These)
**These files belong to the modern Builder Pattern + Actions Pipeline + Background Jobs system we built:**

### Core Architecture
- `app/Models/ImportSession.php` - New model with WebSocket broadcasting  
- `app/Http/Controllers/ImportController.php` - RESTful controller
- `database/migrations/2025_08_08_155005_create_import_sessions_table.php` - New migration
- `database/factories/ImportSessionFactory.php` - Factory

### Builder Pattern Services  
- `app/Services/Import/ImportBuilder.php` - Main Builder class
- `app/Services/Import/ImportConfigurationBuilder.php` - Config builder
- `app/Services/Import/ImportConfiguration.php` - Configuration object
- `app/Services/Import/ImportOrchestrator.php` - Main orchestrator
- `app/Services/Import/ValidationEngine.php` - Validation engine
- `app/Services/Import/ImportProgressBroadcaster.php` - WebSocket broadcaster
- `app/Services/Import/SkuPatternAnalyzer.php` - SKU analyzer

### Actions Pipeline Architecture
- `app/Services/Import/Actions/` (entire directory) - Actions system
- `app/Services/Import/Conflicts/` (entire directory) - Conflict resolvers  
- `app/Services/Import/Extractors/` (entire directory) - Attribute extractors
- `app/Services/Import/Analyzers/` (entire directory) - File analyzers

### Background Jobs
- `app/Jobs/Import/AnalyzeFileJob.php`
- `app/Jobs/Import/DryRunJob.php` 
- `app/Jobs/Import/ProcessImportJob.php`
- `app/Jobs/Import/FinalizeImportJob.php`

### New Views & Components
- `resources/views/import/` (entire directory) - New RESTful views
- `app/Livewire/Import/CreateImport.php` - New upload component
- `app/Livewire/Import/ImportProgress.php` - New progress component
- `app/Livewire/Import/ColumnMapping.php` - New mapping component

### Events & Broadcasting
- `app/Events/ImportSessionCreated.php`
- `app/Events/ImportSessionUpdated.php`

### New Test Suite  
- `tests/Feature/Import/` (entire directory) - 29/30 passing tests
- `tests/Browser/ImportDashboardTest.php` - Dusk tests
- `tests/Browser/ImportShowPageTest.php` - Dusk tests

---

## 🗑️ OLD IMPORT SYSTEM FILES (Delete These)
**These belong to the legacy 2,400+ line monolithic Livewire system:**

### Legacy Livewire Monolith
- `app/Livewire/DataExchange/Import/ImportData.php` - The 2,400+ line monster
- `app/Livewire/DataExchange/Import/ImportDataRefactored.php` - Failed refactor attempt  
- `app/Livewire/Forms/ImportConfigurationForm.php` - Old form
- `app/Livewire/Forms/ImportProgressForm.php` - Old form
- `resources/views/livewire/data-exchange/import/import-data.blade.php` - Old view
- `resources/views/livewire/data-exchange/import/import-data-refactored.blade.php` - Old view

### Legacy Services (Root Services Directory)
- `app/Services/ImportManagerService.php`
- `app/Services/ImportValidationService.php`
- `app/Services/ImportMappingCache.php` 
- `app/Services/ImportPerformanceService.php`
- `app/Services/ImportSecurityService.php`
- `app/Services/EnhancedImportManagerService.php`
- `app/Services/ImportErrorHandlingService.php`
- `app/Services/ProductImportService.php`
- `app/Services/ImportDataCacheService.php`
- `app/Services/ExcelProcessingService.php`
- `app/Services/AsyncExcelProcessingService.php`
- `app/Services/DataTransformationService.php`
- `app/Services/ColumnMappingService.php`
- `app/Services/BarcodePoolOptimizationService.php`

### Legacy Architecture
- `app/DTOs/Import/` (entire directory) - Old DTO structure
- `app/Exceptions/Import/` (entire directory) - Old exceptions  
- `app/Exceptions/ImportException.php` - Root exception
- `app/Casts/Import/` (entire directory) - Old casting system

### Legacy Events & Jobs
- `app/Events/Import/ImportProgressUpdated.php` - Old progress event
- `app/Jobs/ExcelDataStreamingJob.php` - Old Excel job

### Legacy Tests
- `tests/Feature/ImportMappingCacheTest.php`
- `tests/Feature/ImportModesTest.php`
- `tests/Feature/TwoPhaseImportTest.php`
- `tests/Feature/VariantConstraintTest.php`
- `tests/Feature/BarcodeConstraintTest.php`
- `tests/Feature/TemplateStructureTest.php`

---

## ⚠️ INVESTIGATE BEFORE DELETING
**These files need analysis to determine if they're shared with other systems:**

- `app/Events/ProductImported.php` - May be used by other systems
- `app/Events/ProductVariantImported.php` - May be used by other systems  
- `app/Jobs/ProcessProductImages.php` - May be used by image system
- `app/Jobs/ProcessVariantImages.php` - May be used by image system
- `app/Jobs/ProcessVariantImagesWithMediaLibrary.php` - May be used by image system

---

## 🎯 EXECUTION PLAN
1. **First**: Analyze shared files to confirm they're safe to delete
2. **Then**: Delete all legacy files in batches (services, components, views, tests)  
3. **Finally**: Clean up any broken references or imports
4. **Test**: Run new import test suite to ensure nothing broke

This cleanup will remove the massive technical debt of the 2,400+ line monolithic component and leave only the modern, maintainable Builder Pattern system.