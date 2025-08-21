<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeDefinition;
use App\Models\ProductAttribute;
use App\Models\VariantAttribute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * ✅ ATTRIBUTES VALIDATE COMMAND
 * 
 * Validates attribute consistency, inheritance rules, and data integrity
 * across the entire PIM system with detailed reporting and repair options.
 */
class AttributesValidate extends Command
{
    protected $signature = 'attributes:validate 
                          {--product-id=* : Specific product IDs to validate}
                          {--variant-id=* : Specific variant IDs to validate}
                          {--attribute=* : Specific attribute keys to validate}
                          {--fix : Attempt to fix validation errors automatically}
                          {--report=console : Output format (console, json, csv)}
                          {--output-file= : Save report to file}
                          {--check=* : Specific checks to run (inheritance, validation, orphans, duplicates)}
                          {--severity=* : Filter by severity levels (critical, warning, info)}';

    protected $description = 'Validate attribute consistency, inheritance, and data integrity';

    protected array $validationResults = [
        'critical' => [],
        'warning' => [],
        'info' => [],
        'fixed' => [],
        'stats' => [],
    ];

    protected array $availableChecks = [
        'inheritance' => 'Validate inheritance rules and consistency',
        'validation' => 'Check attribute value validation status',
        'orphans' => 'Find orphaned attributes without definitions',
        'duplicates' => 'Detect duplicate attribute assignments',
        'consistency' => 'Check data consistency across models',
        'performance' => 'Identify performance issues',
    ];

    public function handle(): int
    {
        $this->info('✅ Starting Attributes Validation');
        $this->newLine();

        $startTime = microtime(true);
        $shouldFix = $this->option('fix');
        $checks = $this->option('check') ?: array_keys($this->availableChecks);
        
        if ($shouldFix) {
            $this->warn('🔧 FIX MODE - Validation errors will be repaired when possible');
            $this->newLine();
        }

        try {
            // Validate options
            $this->validateOptions();

            // Display validation plan
            $this->displayValidationPlan($checks);

            // Run validation checks
            $this->runValidationChecks($checks, $shouldFix);

            // Generate and display report
            $duration = microtime(true) - $startTime;
            $this->generateReport($duration);

            // Determine exit code based on results
            return $this->getExitCode();

        } catch (\Exception $e) {
            $this->error('❌ Validation failed: ' . $e->getMessage());
            $this->line('Trace: ' . $e->getFile() . ':' . $e->getLine());
            return Command::FAILURE;
        }
    }

    /**
     * ✅ VALIDATE OPTIONS
     */
    protected function validateOptions(): void
    {
        $checks = $this->option('check') ?: [];
        $invalidChecks = array_diff($checks, array_keys($this->availableChecks));
        
        if (!empty($invalidChecks)) {
            throw new \InvalidArgumentException('Invalid checks: ' . implode(', ', $invalidChecks));
        }

        $severities = $this->option('severity') ?: [];
        $validSeverities = ['critical', 'warning', 'info'];
        $invalidSeverities = array_diff($severities, $validSeverities);
        
        if (!empty($invalidSeverities)) {
            throw new \InvalidArgumentException('Invalid severity levels: ' . implode(', ', $invalidSeverities));
        }
    }

    /**
     * 📋 DISPLAY VALIDATION PLAN
     */
    protected function displayValidationPlan(array $checks): void
    {
        $this->info('📋 Validation Plan:');
        
        foreach ($checks as $check) {
            $description = $this->availableChecks[$check] ?? $check;
            $this->line("  • {$check}: {$description}");
        }
        
        $productIds = $this->option('product-id');
        $variantIds = $this->option('variant-id');
        
        if (!empty($productIds)) {
            $this->line('  • Scope: Products (' . implode(', ', $productIds) . ')');
        } elseif (!empty($variantIds)) {
            $this->line('  • Scope: Variants (' . implode(', ', $variantIds) . ')');
        } else {
            $this->line('  • Scope: All products and variants');
        }
        
        $this->newLine();
    }

    /**
     * 🔍 RUN VALIDATION CHECKS
     */
    protected function runValidationChecks(array $checks, bool $shouldFix): void
    {
        foreach ($checks as $check) {
            $this->info("🔍 Running {$check} validation...");
            
            $methodName = 'validate' . ucfirst($check);
            if (method_exists($this, $methodName)) {
                $this->{$methodName}($shouldFix);
            }
            
            $this->newLine();
        }
    }

    /**
     * 🧬 VALIDATE INHERITANCE
     */
    protected function validateInheritance(bool $shouldFix): void
    {
        $this->line('  Checking inheritance consistency...');
        
        // Get all variant attributes marked as inherited
        $inheritedAttributes = VariantAttribute::inherited()
            ->with(['variant.product', 'attributeDefinition', 'inheritedFromProductAttribute'])
            ->get();

        $progressBar = $this->output->createProgressBar($inheritedAttributes->count());
        
        foreach ($inheritedAttributes as $variantAttr) {
            // Check if parent product attribute still exists
            if (!$variantAttr->inheritedFromProductAttribute) {
                $this->addIssue('critical', 'orphaned_inheritance', 
                    "Variant attribute {$variantAttr->id} claims inheritance from missing product attribute",
                    $variantAttr, $shouldFix ? 'remove_orphaned_inheritance' : null
                );
            }
            // Check if values match
            elseif ($variantAttr->value !== $variantAttr->inheritedFromProductAttribute->value) {
                $this->addIssue('warning', 'inheritance_drift',
                    "Inherited value doesn't match parent for variant {$variantAttr->variant->sku}, attribute {$variantAttr->getAttributeKey()}",
                    $variantAttr, $shouldFix ? 'refresh_inheritance' : null
                );
            }
            
            // Check if attribute definition supports inheritance
            if (!$variantAttr->attributeDefinition->supportsInheritance()) {
                $this->addIssue('critical', 'invalid_inheritance',
                    "Attribute {$variantAttr->getAttributeKey()} marked as inherited but definition doesn't support inheritance",
                    $variantAttr, $shouldFix ? 'clear_invalid_inheritance' : null
                );
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        // Check for missing inheritance opportunities
        $this->checkMissingInheritanceOpportunities($shouldFix);
    }

    /**
     * 🔍 CHECK MISSING INHERITANCE OPPORTUNITIES
     */
    protected function checkMissingInheritanceOpportunities(bool $shouldFix): void
    {
        $this->line('  Checking for missing inheritance opportunities...');
        
        $inheritableDefinitions = AttributeDefinition::getInheritableAttributes();
        
        foreach ($inheritableDefinitions as $definition) {
            // Find products with this attribute that have variants without it
            $productsWithAttribute = Product::whereHas('attributes', function ($query) use ($definition) {
                $query->where('attribute_definition_id', $definition->id);
            })->with(['variants', 'attributes' => function ($query) use ($definition) {
                $query->where('attribute_definition_id', $definition->id);
            }])->get();
            
            foreach ($productsWithAttribute as $product) {
                foreach ($product->variants as $variant) {
                    $hasVariantAttribute = $variant->attributes()
                        ->where('attribute_definition_id', $definition->id)
                        ->exists();
                    
                    if (!$hasVariantAttribute) {
                        $this->addIssue('info', 'missing_inheritance',
                            "Variant {$variant->sku} could inherit {$definition->key} from product {$product->id}",
                            $variant, $shouldFix ? 'create_inheritance' : null
                        );
                    }
                }
            }
        }
    }

    /**
     * ✅ VALIDATE VALIDATION STATUS
     */
    protected function validateValidation(bool $shouldFix): void
    {
        $this->line('  Checking attribute validation status...');
        
        // Check invalid product attributes
        $invalidProductAttrs = ProductAttribute::invalid()->with('attributeDefinition', 'product')->get();
        foreach ($invalidProductAttrs as $attr) {
            $this->addIssue('warning', 'invalid_value',
                "Product {$attr->product->id} has invalid {$attr->getAttributeKey()}: " . 
                implode(', ', $attr->validation_errors ?? []),
                $attr, $shouldFix ? 'revalidate_attribute' : null
            );
        }
        
        // Check invalid variant attributes
        $invalidVariantAttrs = VariantAttribute::invalid()->with('attributeDefinition', 'variant')->get();
        foreach ($invalidVariantAttrs as $attr) {
            $this->addIssue('warning', 'invalid_value',
                "Variant {$attr->variant->sku} has invalid {$attr->getAttributeKey()}: " . 
                implode(', ', $attr->validation_errors ?? []),
                $attr, $shouldFix ? 'revalidate_attribute' : null
            );
        }
        
        // Check for attributes never validated
        $neverValidated = collect()
            ->merge(ProductAttribute::whereNull('last_validated_at')->limit(100)->get())
            ->merge(VariantAttribute::whereNull('last_validated_at')->limit(100)->get());
            
        foreach ($neverValidated as $attr) {
            $this->addIssue('info', 'never_validated',
                "Attribute {$attr->getAttributeKey()} has never been validated",
                $attr, $shouldFix ? 'validate_attribute' : null
            );
        }
    }

    /**
     * 🏚️ VALIDATE ORPHANS
     */
    protected function validateOrphans(bool $shouldFix): void
    {
        $this->line('  Checking for orphaned attributes...');
        
        // Find attributes without valid definitions
        $orphanedProductAttrs = ProductAttribute::whereDoesntHave('attributeDefinition')->get();
        foreach ($orphanedProductAttrs as $attr) {
            $this->addIssue('critical', 'orphaned_attribute',
                "Product attribute {$attr->id} references missing attribute definition {$attr->attribute_definition_id}",
                $attr, $shouldFix ? 'remove_orphaned_attribute' : null
            );
        }
        
        $orphanedVariantAttrs = VariantAttribute::whereDoesntHave('attributeDefinition')->get();
        foreach ($orphanedVariantAttrs as $attr) {
            $this->addIssue('critical', 'orphaned_attribute',
                "Variant attribute {$attr->id} references missing attribute definition {$attr->attribute_definition_id}",
                $attr, $shouldFix ? 'remove_orphaned_attribute' : null
            );
        }
        
        // Find attributes for deleted products/variants
        $orphanedByProduct = ProductAttribute::whereDoesntHave('product')->get();
        foreach ($orphanedByProduct as $attr) {
            $this->addIssue('critical', 'orphaned_by_parent',
                "Product attribute {$attr->id} references missing product {$attr->product_id}",
                $attr, $shouldFix ? 'remove_orphaned_attribute' : null
            );
        }
        
        $orphanedByVariant = VariantAttribute::whereDoesntHave('variant')->get();
        foreach ($orphanedByVariant as $attr) {
            $this->addIssue('critical', 'orphaned_by_parent',
                "Variant attribute {$attr->id} references missing variant {$attr->variant_id}",
                $attr, $shouldFix ? 'remove_orphaned_attribute' : null
            );
        }
    }

    /**
     * 🔄 VALIDATE DUPLICATES
     */
    protected function validateDuplicates(bool $shouldFix): void
    {
        $this->line('  Checking for duplicate attributes...');
        
        // Find duplicate product attributes
        $duplicateProductAttrs = DB::table('product_attributes')
            ->select('product_id', 'attribute_definition_id', DB::raw('COUNT(*) as count'))
            ->groupBy('product_id', 'attribute_definition_id')
            ->having('count', '>', 1)
            ->get();
            
        foreach ($duplicateProductAttrs as $duplicate) {
            $attrs = ProductAttribute::where('product_id', $duplicate->product_id)
                ->where('attribute_definition_id', $duplicate->attribute_definition_id)
                ->get();
                
            $this->addIssue('warning', 'duplicate_attribute',
                "Product {$duplicate->product_id} has {$duplicate->count} instances of attribute definition {$duplicate->attribute_definition_id}",
                $attrs, $shouldFix ? 'merge_duplicates' : null
            );
        }
        
        // Find duplicate variant attributes
        $duplicateVariantAttrs = DB::table('variant_attributes')
            ->select('variant_id', 'attribute_definition_id', DB::raw('COUNT(*) as count'))
            ->groupBy('variant_id', 'attribute_definition_id')
            ->having('count', '>', 1)
            ->get();
            
        foreach ($duplicateVariantAttrs as $duplicate) {
            $attrs = VariantAttribute::where('variant_id', $duplicate->variant_id)
                ->where('attribute_definition_id', $duplicate->attribute_definition_id)
                ->get();
                
            $this->addIssue('warning', 'duplicate_attribute',
                "Variant {$duplicate->variant_id} has {$duplicate->count} instances of attribute definition {$duplicate->attribute_definition_id}",
                $attrs, $shouldFix ? 'merge_duplicates' : null
            );
        }
    }

    /**
     * 🔧 VALIDATE CONSISTENCY
     */
    protected function validateConsistency(bool $shouldFix): void
    {
        $this->line('  Checking data consistency...');
        
        // Check for attributes with null values that shouldn't be null
        $nullRequired = ProductAttribute::whereNull('value')
            ->whereHas('attributeDefinition', function ($query) {
                $query->where('required', true);
            })
            ->with('attributeDefinition', 'product')
            ->get();
            
        foreach ($nullRequired as $attr) {
            $this->addIssue('warning', 'null_required',
                "Product {$attr->product->id} has null value for required attribute {$attr->getAttributeKey()}",
                $attr, $shouldFix ? 'set_default_value' : null
            );
        }
        
        // Check variant consistency
        $variantNullRequired = VariantAttribute::whereNull('value')
            ->whereHas('attributeDefinition', function ($query) {
                $query->where('required', true);
            })
            ->with('attributeDefinition', 'variant')
            ->get();
            
        foreach ($variantNullRequired as $attr) {
            $this->addIssue('warning', 'null_required',
                "Variant {$attr->variant->sku} has null value for required attribute {$attr->getAttributeKey()}",
                $attr, $shouldFix ? 'set_default_value' : null
            );
        }
    }

    /**
     * ⚡ VALIDATE PERFORMANCE
     */
    protected function validatePerformance(bool $shouldFix): void
    {
        $this->line('  Checking for performance issues...');
        
        // Check for large JSON values
        $largeJsonAttrs = collect()
            ->merge(ProductAttribute::where('value', 'like', '%{%')->whereRaw('LENGTH(value) > 10000')->get())
            ->merge(VariantAttribute::where('value', 'like', '%{%')->whereRaw('LENGTH(value) > 10000')->get());
            
        foreach ($largeJsonAttrs as $attr) {
            $size = strlen($attr->value);
            $this->addIssue('info', 'large_value',
                "Attribute {$attr->getAttributeKey()} has large value ({$size} bytes)",
                $attr
            );
        }
        
        // Check for models with too many attributes
        $productsWithManyAttrs = Product::withCount('attributes')
            ->having('attributes_count', '>', 50)
            ->get();
            
        foreach ($productsWithManyAttrs as $product) {
            $this->addIssue('info', 'many_attributes',
                "Product {$product->id} has {$product->attributes_count} attributes (consider grouping)",
                $product
            );
        }
    }

    /**
     * 📝 ADD ISSUE
     */
    protected function addIssue(string $severity, string $type, string $message, $subject = null, string $fixAction = null): void
    {
        $issue = [
            'type' => $type,
            'message' => $message,
            'subject' => $subject,
            'fix_action' => $fixAction,
            'timestamp' => now(),
        ];
        
        $this->validationResults[$severity][] = $issue;
        
        // Attempt fix if requested and possible
        if ($fixAction && method_exists($this, $fixAction)) {
            try {
                $this->{$fixAction}($subject);
                $this->validationResults['fixed'][] = $issue;
                $this->line("    ✅ Fixed: {$message}");
            } catch (\Exception $e) {
                $this->line("    ❌ Fix failed: {$e->getMessage()}");
            }
        }
    }

    /**
     * 📊 GENERATE REPORT
     */
    protected function generateReport(float $duration): void
    {
        $this->newLine();
        $this->info('📊 Validation Report:');
        $this->newLine();

        $total = array_sum([
            count($this->validationResults['critical']),
            count($this->validationResults['warning']),
            count($this->validationResults['info']),
        ]);

        $this->table(
            ['Severity', 'Count'],
            [
                ['Critical', count($this->validationResults['critical'])],
                ['Warning', count($this->validationResults['warning'])],
                ['Info', count($this->validationResults['info'])],
                ['Fixed', count($this->validationResults['fixed'])],
                ['Total', $total],
                ['Duration', round($duration, 2) . 's'],
            ]
        );

        // Show detailed issues if requested
        $severityFilter = $this->option('severity') ?: ['critical', 'warning', 'info'];
        
        foreach ($severityFilter as $severity) {
            if (!empty($this->validationResults[$severity])) {
                $this->displayIssues($severity, $this->validationResults[$severity]);
            }
        }

        // Save to file if requested
        $outputFile = $this->option('output-file');
        if ($outputFile) {
            $this->saveReport($outputFile);
        }
    }

    /**
     * 📝 DISPLAY ISSUES
     */
    protected function displayIssues(string $severity, array $issues): void
    {
        $this->newLine();
        $icon = match($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '•'
        };
        
        $this->line("{$icon} " . strtoupper($severity) . ' ISSUES:');
        
        foreach (array_slice($issues, 0, 10) as $issue) { // Show first 10
            $this->line("  • {$issue['message']}");
        }
        
        if (count($issues) > 10) {
            $remaining = count($issues) - 10;
            $this->line("  ... and {$remaining} more");
        }
    }

    /**
     * 💾 SAVE REPORT
     */
    protected function saveReport(string $filename): void
    {
        $format = $this->option('report');
        $data = $this->validationResults;
        
        switch ($format) {
            case 'json':
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
                break;
            case 'csv':
                $this->saveReportAsCsv($filename, $data);
                break;
            default:
                // Save as text
                $content = $this->formatReportAsText($data);
                file_put_contents($filename, $content);
        }
        
        $this->info("Report saved to: {$filename}");
    }

    /**
     * 🔢 GET EXIT CODE
     */
    protected function getExitCode(): int
    {
        if (!empty($this->validationResults['critical'])) {
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }

    /**
     * 🔧 FIX METHODS
     */
    
    protected function removeOrphanedInheritance($attr): void
    {
        $attr->delete();
    }
    
    protected function refreshInheritance($attr): void
    {
        if ($attr instanceof VariantAttribute) {
            $attr->refreshInheritance();
            $attr->save();
        }
    }
    
    protected function clearInvalidInheritance($attr): void
    {
        if ($attr instanceof VariantAttribute) {
            $attr->clearInheritance();
            $attr->save();
        }
    }
    
    protected function revalidateAttribute($attr): void
    {
        $attr->revalidate();
        $attr->save();
    }
    
    protected function removeOrphanedAttribute($attr): void
    {
        $attr->delete();
    }
    
    protected function setDefaultValue($attr): void
    {
        $defaultValue = $attr->attributeDefinition->default_value;
        if ($defaultValue !== null) {
            $attr->setValue($defaultValue);
            $attr->save();
        }
    }
}