<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 🎯 CHANNEL FIELD MAPPING MODEL
 *
 * Stores user-defined mappings between PIM fields and marketplace fields:
 * - Global mappings: Apply to all products for a sync account
 * - Product-specific: Override global for specific products
 * - Variant-specific: Override for variant attributes (color, size, etc.)
 * - Supports static values, PIM field mapping, and complex expressions
 */
class ChannelFieldMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'sync_account_id',
        'channel_field_code',
        'category',
        'mapping_type',
        'source_field',
        'static_value',
        'mapping_expression',
        'transformation_rules',
        'is_active',
        'priority',
        'notes',
        'mapping_level',
        'product_id',
        'variant_scope',
        'validation_status',
        'last_validated_at',
        'test_results',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'transformation_rules' => 'array',
        'validation_status' => 'array',
        'test_results' => 'array',
        'last_validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 🔗 SYNC ACCOUNT RELATIONSHIP
     */
    public function syncAccount(): BelongsTo
    {
        return $this->belongsTo(SyncAccount::class);
    }

    /**
     * 🔗 PRODUCT RELATIONSHIP
     *
     * For product-specific overrides
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 🔗 FIELD DEFINITION RELATIONSHIP
     *
     * Get the field definition this mapping targets
     */
    public function fieldDefinition(): HasOne
    {
        return $this->hasOne(ChannelFieldDefinition::class, 'field_code', 'channel_field_code')
            ->where('channel_type', function ($query) {
                $query->select('marketplace_type')
                    ->from('sync_accounts')
                    ->where('id', $this->sync_account_id);
            });
    }

    /**
     * 🎯 SCOPE: Active mappings only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 SCOPE: Global mappings
     */
    public function scopeGlobal($query)
    {
        return $query->where('mapping_level', 'global')
            ->whereNull('product_id');
    }

    /**
     * 🎯 SCOPE: Product-specific mappings
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('mapping_level', 'product')
            ->where('product_id', $productId);
    }

    /**
     * 🎯 SCOPE: Variant-specific mappings
     */
    public function scopeForVariant($query, ?string $variantScope = null)
    {
        $query->where('mapping_level', 'variant');

        if ($variantScope) {
            $query->where('variant_scope', $variantScope);
        }

        return $query;
    }

    /**
     * 🎯 SCOPE: For sync account
     */
    public function scopeForSyncAccount($query, int $syncAccountId)
    {
        return $query->where('sync_account_id', $syncAccountId);
    }

    /**
     * 🎯 SCOPE: For field
     */
    public function scopeForField($query, string $fieldCode)
    {
        return $query->where('channel_field_code', $fieldCode);
    }

    /**
     * 🎯 SCOPE: By mapping type
     */
    public function scopeByType($query, string $mappingType)
    {
        return $query->where('mapping_type', $mappingType);
    }

    /**
     * 📋 GET EFFECTIVE MAPPINGS
     *
     * Get the effective mappings for a product/variant considering hierarchy
     */
    public static function getEffectiveMappings(
        int $syncAccountId,
        ?int $productId = null,
        ?string $variantScope = null,
        ?string $category = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = static::active()
            ->forSyncAccount($syncAccountId)
            ->orderBy('priority', 'desc')
            ->orderBy('mapping_level', 'desc'); // variant > product > global

        // Category filter
        if ($category) {
            $query->where(function ($q) use ($category) {
                $q->where('category', $category)
                    ->orWhereNull('category');
            });
        }

        // Include variant-specific if requested
        if ($variantScope) {
            $query->where(function ($q) use ($productId, $variantScope) {
                $q->where('mapping_level', 'global')
                    ->orWhere(function ($subQ) use ($productId) {
                        $subQ->where('mapping_level', 'product')
                            ->where('product_id', $productId);
                    })
                    ->orWhere(function ($subQ) use ($variantScope) {
                        $subQ->where('mapping_level', 'variant')
                            ->where('variant_scope', $variantScope);
                    });
            });
        }
        // Include product-specific if requested
        elseif ($productId) {
            $query->where(function ($q) use ($productId) {
                $q->where('mapping_level', 'global')
                    ->orWhere(function ($subQ) use ($productId) {
                        $subQ->where('mapping_level', 'product')
                            ->where('product_id', $productId);
                    });
            });
        }
        // Global only
        else {
            $query->global();
        }

        return $query->get();
    }

    /**
     * 🎯 RESOLVE VALUE
     *
     * Resolve the actual value for this mapping given context
     */
    public function resolveValue(array $context): mixed
    {
        return match ($this->mapping_type) {
            'static_value' => $this->static_value,
            'pim_field' => $this->resolvePimField($context),
            'expression' => $this->resolveExpression($context),
            'custom' => $this->resolveCustom($context),
            default => null,
        };
    }

    /**
     * 🔧 RESOLVE PIM FIELD
     *
     * Extract value from PIM context (product/variant data)
     */
    protected function resolvePimField(array $context): mixed
    {
        if (! $this->source_field) {
            return null;
        }

        // Support dot notation: product.name, variant.color, etc.
        $parts = explode('.', $this->source_field);
        $value = $context;

        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } elseif (is_object($value) && isset($value->$part)) {
                $value = $value->$part;
            } else {
                return null;
            }
        }

        return $this->applyTransformations($value);
    }

    /**
     * 🔧 RESOLVE EXPRESSION
     *
     * Evaluate complex mapping expressions
     */
    protected function resolveExpression(array $context): mixed
    {
        if (! $this->mapping_expression) {
            return null;
        }

        // Simple template replacement for now
        // TODO: Implement safe expression evaluation
        $expression = $this->mapping_expression;

        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $expression = str_replace("{{{$key}}}", $value, $expression);
            }
        }

        return $this->applyTransformations($expression);
    }

    /**
     * 🔧 RESOLVE CUSTOM
     *
     * Handle custom mapping logic
     */
    protected function resolveCustom(array $context): mixed
    {
        // TODO: Implement plugin/custom mapping system
        return null;
    }

    /**
     * 🔧 APPLY TRANSFORMATIONS
     *
     * Apply transformation rules to resolved value
     */
    protected function applyTransformations(mixed $value): mixed
    {
        if (! $this->transformation_rules || ! is_array($this->transformation_rules)) {
            return $value;
        }

        foreach ($this->transformation_rules as $rule) {
            $value = $this->applyTransformation($value, $rule);
        }

        return $value;
    }

    /**
     * 🔧 APPLY TRANSFORMATION
     *
     * Apply single transformation rule
     */
    protected function applyTransformation(mixed $value, array $rule): mixed
    {
        return match ($rule['type'] ?? '') {
            'uppercase' => strtoupper($value),
            'lowercase' => strtolower($value),
            'trim' => trim($value),
            'prefix' => ($rule['value'] ?? '').$value,
            'suffix' => $value.($rule['value'] ?? ''),
            'replace' => str_replace($rule['search'] ?? '', $rule['replace'] ?? '', $value),
            'format' => sprintf($rule['format'] ?? '%s', $value),
            default => $value,
        };
    }

    /**
     * ✅ VALIDATE MAPPING
     *
     * Validate this mapping against field definition
     */
    public function validateMapping(): array
    {
        $errors = [];
        $warnings = [];

        // Check if target field exists
        $fieldDefinition = $this->fieldDefinition;
        if (! $fieldDefinition) {
            $errors[] = "Target field '{$this->channel_field_code}' not found in field definitions";
        }

        // Check mapping type requirements
        if ($this->mapping_type === 'pim_field' && ! $this->source_field) {
            $errors[] = 'PIM field mapping requires source_field';
        }

        if ($this->mapping_type === 'static_value' && ! $this->static_value) {
            $warnings[] = 'Static value mapping has empty value';
        }

        if ($this->mapping_type === 'expression' && ! $this->mapping_expression) {
            $errors[] = 'Expression mapping requires mapping_expression';
        }

        // Update validation status
        $this->validation_status = [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
            'validated_at' => now()->toISOString(),
        ];

        $this->last_validated_at = now();
        $this->save();

        return $this->validation_status;
    }

    /**
     * 🧪 TEST MAPPING
     *
     * Test this mapping with sample data
     */
    public function testMapping(array $sampleContext): array
    {
        $startTime = microtime(true);

        try {
            $resolvedValue = $this->resolveValue($sampleContext);
            $executionTime = microtime(true) - $startTime;

            $result = [
                'success' => true,
                'resolved_value' => $resolvedValue,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'tested_at' => now()->toISOString(),
                'sample_context' => $sampleContext,
            ];
        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'tested_at' => now()->toISOString(),
                'sample_context' => $sampleContext,
            ];
        }

        // Store test result
        $this->test_results = $result;
        $this->save();

        return $result;
    }

    /**
     * 📊 GET MAPPING STATISTICS
     *
     * Get statistics for mappings
     */
    public static function getStatistics(): array
    {
        $all = static::all();

        return [
            'total_mappings' => $all->count(),
            'active_mappings' => $all->where('is_active', true)->count(),
            'by_type' => $all->groupBy('mapping_type')->map->count(),
            'by_level' => $all->groupBy('mapping_level')->map->count(),
            'by_sync_account' => $all->groupBy('sync_account_id')->map->count(),
            'validated_mappings' => $all->whereNotNull('last_validated_at')->count(),
            'tested_mappings' => $all->whereNotNull('test_results')->count(),
            'needs_validation' => $all->where('last_validated_at', '<', now()->subWeek())->count(),
        ];
    }

    /**
     * 🏷️ GET DISPLAY NAME
     *
     * Human-readable mapping identifier
     */
    public function getDisplayNameAttribute(): string
    {
        $level = match ($this->mapping_level) {
            'global' => 'Global',
            'product' => "Product #{$this->product_id}",
            'variant' => "Variant ({$this->variant_scope})",
            default => ucfirst($this->mapping_level),
        };

        return "{$level}: {$this->channel_field_code} → {$this->getMappingDescription()}";
    }

    /**
     * 📝 GET MAPPING DESCRIPTION
     *
     * Describe what this mapping does
     */
    public function getMappingDescription(): string
    {
        return match ($this->mapping_type) {
            'static_value' => "Static: \"{$this->static_value}\"",
            'pim_field' => "PIM: {$this->source_field}",
            'expression' => "Expression: {$this->mapping_expression}",
            'custom' => 'Custom Logic',
            default => 'Unknown',
        };
    }

    /**
     * 🎨 GET MAPPING TYPE COLOR
     *
     * UI color for mapping type badges
     */
    public function getMappingTypeColorAttribute(): string
    {
        return match ($this->mapping_type) {
            'static_value' => 'blue',
            'pim_field' => 'green',
            'expression' => 'purple',
            'custom' => 'orange',
            default => 'gray',
        };
    }

    /**
     * 🎨 GET MAPPING LEVEL COLOR
     *
     * UI color for mapping level badges
     */
    public function getMappingLevelColorAttribute(): string
    {
        return match ($this->mapping_level) {
            'global' => 'blue',
            'product' => 'green',
            'variant' => 'purple',
            default => 'gray',
        };
    }
}
