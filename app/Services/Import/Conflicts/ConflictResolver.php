<?php

namespace App\Services\Import\Conflicts;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class ConflictResolver
{
    private array $resolvers = [];
    private array $statistics = [
        'conflicts_detected' => 0,
        'conflicts_resolved' => 0,
        'conflicts_failed' => 0,
        'resolution_strategies' => [],
    ];

    public function __construct(array $config = [])
    {
        $this->initializeResolvers($config);
    }

    public static function create(array $config = []): self
    {
        return new self($config);
    }

    public function resolve(QueryException $exception, array $context = []): ConflictResolution
    {
        $this->statistics['conflicts_detected']++;

        $conflictType = $this->identifyConflictType($exception);
        $conflictData = $this->extractConflictData($exception, $context);

        Log::info('Conflict detected', [
            'type' => $conflictType,
            'error_code' => $exception->getCode(),
            'constraint' => $conflictData['constraint'] ?? 'unknown',
            'context_keys' => array_keys($context),
        ]);

        // Find appropriate resolver
        $resolver = $this->getResolver($conflictType);
        if (!$resolver) {
            $this->statistics['conflicts_failed']++;
            return ConflictResolution::failed(
                "No resolver available for conflict type: {$conflictType}",
                ['conflict_type' => $conflictType]
            );
        }

        try {
            $resolution = $resolver->resolve($conflictData, $context);
            
            if ($resolution->isResolved()) {
                $this->statistics['conflicts_resolved']++;
                $this->updateStrategyStats($resolution->getStrategy());
            } else {
                $this->statistics['conflicts_failed']++;
            }

            Log::info('Conflict resolution attempted', [
                'type' => $conflictType,
                'strategy' => $resolution->getStrategy(),
                'resolved' => $resolution->isResolved(),
                'action' => $resolution->getAction(),
            ]);

            return $resolution;

        } catch (\Exception $e) {
            $this->statistics['conflicts_failed']++;
            
            Log::error('Conflict resolution failed', [
                'type' => $conflictType,
                'resolver' => get_class($resolver),
                'error' => $e->getMessage(),
            ]);

            return ConflictResolution::failed(
                "Resolver threw exception: " . $e->getMessage(),
                ['resolver_error' => true]
            );
        }
    }

    public function addResolver(string $conflictType, ConflictResolverInterface $resolver): self
    {
        $this->resolvers[$conflictType] = $resolver;
        return $this;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    private function initializeResolvers(array $config): void
    {
        // Add default resolvers
        $this->resolvers['duplicate_sku'] = new DuplicateSkuResolver($config['sku_resolution'] ?? []);
        $this->resolvers['duplicate_barcode'] = new DuplicateBarcodeResolver($config['barcode_resolution'] ?? []);
        $this->resolvers['variant_constraint'] = new VariantConstraintResolver($config['variant_resolution'] ?? []);
        $this->resolvers['unique_constraint'] = new UniqueConstraintResolver($config['unique_resolution'] ?? []);
    }

    private function identifyConflictType(QueryException $exception): string
    {
        $message = strtolower($exception->getMessage());
        $code = $exception->getCode();

        // MySQL/SQLite integrity constraint violations
        if ($code === 23000 || strpos($message, 'integrity constraint violation') !== false) {
            // Check for specific constraint types
            if (strpos($message, 'product_variants_sku_unique') !== false) {
                return 'duplicate_sku';
            }
            if (strpos($message, 'variant_barcodes_barcode_unique') !== false) {
                return 'duplicate_barcode';
            }
            if (strpos($message, 'product_variants_product_id_color_size_unique') !== false) {
                return 'variant_constraint';
            }
            if (strpos($message, 'unique') !== false) {
                return 'unique_constraint';
            }
        }

        // PostgreSQL specific
        if (strpos($message, 'duplicate key value violates unique constraint') !== false) {
            return 'unique_constraint';
        }

        return 'unknown_constraint';
    }

    private function extractConflictData(QueryException $exception, array $context): array
    {
        $message = $exception->getMessage();
        
        return [
            'exception_message' => $message,
            'exception_code' => $exception->getCode(),
            'constraint' => $this->extractConstraintName($message),
            'conflicting_value' => $this->extractConflictingValue($message, $context),
            'table' => $this->extractTableName($message),
            'column' => $this->extractColumnName($message),
        ];
    }

    private function extractConstraintName(string $message): ?string
    {
        // Match various constraint name patterns
        $patterns = [
            '/constraint [`\'"]?([^`\'"]+)[`\'"]?/i',
            '/unique constraint [`\'"]?([^`\'"]+)[`\'"]?/i',
            '/violates unique constraint [`\'"]?([^`\'"]+)[`\'"]?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function extractConflictingValue(string $message, array $context): ?string
    {
        // Try to extract the conflicting value from the error message
        if (preg_match('/\(([^)]+)\)/', $message, $matches)) {
            return $matches[1];
        }

        // Fallback to context data if we can identify the likely field
        $constraint = $this->extractConstraintName($message);
        if ($constraint && strpos($constraint, 'sku') !== false) {
            return $context['variant_sku'] ?? null;
        }
        if ($constraint && strpos($constraint, 'barcode') !== false) {
            return $context['barcode'] ?? null;
        }

        return null;
    }

    private function extractTableName(string $message): ?string
    {
        if (preg_match('/table [`\'"]?([^`\'"]+)[`\'"]?/i', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractColumnName(string $message): ?string
    {
        if (preg_match('/column [`\'"]?([^`\'"]+)[`\'"]?/i', $message, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function getResolver(string $conflictType): ?ConflictResolverInterface
    {
        return $this->resolvers[$conflictType] ?? null;
    }

    private function updateStrategyStats(string $strategy): void
    {
        if (!isset($this->statistics['resolution_strategies'][$strategy])) {
            $this->statistics['resolution_strategies'][$strategy] = 0;
        }
        $this->statistics['resolution_strategies'][$strategy]++;
    }
}