<?php

namespace App\DTOs\Import;

/**
 * Contains the results of security validation including threats and warnings
 */
class SecurityValidationResult
{
    private array $threats = [];

    private array $warnings = [];

    private bool $isSecure = true;

    private array $statistics = [];

    public function addThreat(string $type, string $message, string $severity = 'medium', ?int $rowNumber = null): void
    {
        $this->threats[] = [
            'type' => $type,
            'message' => $message,
            'severity' => $severity,
            'row_number' => $rowNumber,
            'timestamp' => now(),
        ];

        // Mark as insecure if high or critical severity
        if (in_array($severity, ['high', 'critical'])) {
            $this->isSecure = false;
        }
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = [
            'message' => $message,
            'timestamp' => now(),
        ];
    }

    public function getThreats(): array
    {
        return $this->threats;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function hasThreats(): bool
    {
        return ! empty($this->threats);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function getThreatCount(): int
    {
        return count($this->threats);
    }

    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    public function isSecure(): bool
    {
        return $this->isSecure;
    }

    public function getHighSeverityThreats(): array
    {
        return array_filter($this->threats, function ($threat) {
            return in_array($threat['severity'], ['high', 'critical']);
        });
    }

    public function getThreatsBySeverity(string $severity): array
    {
        return array_filter($this->threats, function ($threat) use ($severity) {
            return $threat['severity'] === $severity;
        });
    }

    public function getThreatsByType(): array
    {
        $threatsByType = [];

        foreach ($this->threats as $threat) {
            $type = $threat['type'];
            if (! isset($threatsByType[$type])) {
                $threatsByType[$type] = [];
            }
            $threatsByType[$type][] = $threat;
        }

        return $threatsByType;
    }

    public function addStatistic(string $key, $value): void
    {
        $this->statistics[$key] = $value;
    }

    public function getStatistics(): array
    {
        return $this->statistics;
    }

    public function toArray(): array
    {
        return [
            'is_secure' => $this->isSecure,
            'threat_count' => $this->getThreatCount(),
            'warning_count' => $this->getWarningCount(),
            'threats' => $this->threats,
            'warnings' => $this->warnings,
            'high_severity_threats' => count($this->getHighSeverityThreats()),
            'statistics' => $this->statistics,
        ];
    }

    public function getSummary(): array
    {
        $severityCounts = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($this->threats as $threat) {
            $severity = $threat['severity'];
            if (isset($severityCounts[$severity])) {
                $severityCounts[$severity]++;
            }
        }

        return [
            'overall_status' => $this->isSecure ? 'secure' : 'threats_detected',
            'total_threats' => $this->getThreatCount(),
            'total_warnings' => $this->getWarningCount(),
            'severity_breakdown' => $severityCounts,
            'recommendation' => $this->getRecommendation(),
        ];
    }

    private function getRecommendation(): string
    {
        if (count($this->getHighSeverityThreats()) > 0) {
            return 'Import should be rejected due to high-severity security threats';
        }

        if ($this->getThreatCount() > 0) {
            return 'Import can proceed with caution after addressing security concerns';
        }

        if ($this->getWarningCount() > 0) {
            return 'Import appears safe but review warnings before proceeding';
        }

        return 'Import appears secure and can proceed safely';
    }
}
