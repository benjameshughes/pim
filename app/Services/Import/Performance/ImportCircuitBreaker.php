<?php

namespace App\Services\Import\Performance;

use App\Models\ImportSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 🛡️ Import Circuit Breaker
 * 
 * Prevents cascade failures during import processing by monitoring error rates
 * and automatically stopping processing when failure thresholds are exceeded.
 * 
 * States:
 * - CLOSED: Normal operation, all calls pass through
 * - OPEN: Failures exceeded threshold, calls fail fast
 * - HALF_OPEN: Testing if service has recovered
 * 
 * Usage:
 * $breaker = ImportCircuitBreaker::forSession($session);
 * $result = $breaker->execute(fn() => $this->riskyOperation());
 */
class ImportCircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';
    
    private ImportSession $session;
    private string $cacheKey;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $halfOpenMaxCalls;
    
    public function __construct(
        ImportSession $session, 
        int $failureThreshold = 5,
        int $recoveryTimeout = 30,
        int $halfOpenMaxCalls = 3
    ) {
        $this->session = $session;
        $this->cacheKey = "circuit_breaker:import:{$session->session_id}";
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->halfOpenMaxCalls = $halfOpenMaxCalls;
    }

    /**
     * 🎯 Create circuit breaker for specific import session
     */
    public static function forSession(ImportSession $session, array $config = []): self
    {
        return new self(
            $session,
            $config['failure_threshold'] ?? 5,
            $config['recovery_timeout'] ?? 30,
            $config['half_open_max_calls'] ?? 3
        );
    }

    /**
     * 🔥 Execute operation with circuit breaker protection
     */
    public function execute(callable $operation, callable $fallback = null)
    {
        $state = $this->getState();
        
        switch ($state['status']) {
            case self::STATE_CLOSED:
                return $this->executeInClosedState($operation);
                
            case self::STATE_OPEN:
                if ($this->shouldAttemptReset($state)) {
                    $this->transitionToHalfOpen();
                    return $this->executeInHalfOpenState($operation);
                } else {
                    return $this->handleOpenState($fallback);
                }
                
            case self::STATE_HALF_OPEN:
                return $this->executeInHalfOpenState($operation);
                
            default:
                // Default to closed state if unknown
                $this->reset();
                return $this->executeInClosedState($operation);
        }
    }

    /**
     * 🔒 Execute in closed state (normal operation)
     */
    private function executeInClosedState(callable $operation)
    {
        try {
            $result = $operation();
            $this->onSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onFailure($e);
            throw $e;
        }
    }

    /**
     * 🔓 Execute in half-open state (testing recovery)
     */
    private function executeInHalfOpenState(callable $operation)
    {
        try {
            $result = $operation();
            $this->onHalfOpenSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->onHalfOpenFailure($e);
            throw $e;
        }
    }

    /**
     * ⚡ Handle open state (fail fast)
     */
    private function handleOpenState(callable $fallback = null)
    {
        Log::warning('Import circuit breaker open - failing fast', [
            'session_id' => $this->session->session_id,
            'state' => $this->getState(),
        ]);

        $this->session->addWarning('Import circuit breaker activated - processing paused for recovery');
        
        if ($fallback) {
            return $fallback();
        }
        
        throw new ImportCircuitBreakerException(
            "Import circuit breaker is open for session {$this->session->session_id}. " .
            "Too many failures detected. System will retry automatically."
        );
    }

    /**
     * ✅ Handle successful operation
     */
    private function onSuccess(): void
    {
        $state = $this->getState();
        
        // Reset failure count on success
        $state['failures'] = 0;
        $state['last_success_time'] = now()->timestamp;
        
        $this->setState($state);
        
        Log::debug('Import circuit breaker - success recorded', [
            'session_id' => $this->session->session_id,
            'state' => $state['status'],
            'consecutive_failures' => $state['failures'],
        ]);
    }

    /**
     * ❌ Handle operation failure
     */
    private function onFailure(\Exception $e): void
    {
        $state = $this->getState();
        $state['failures']++;
        $state['last_failure_time'] = now()->timestamp;
        $state['last_failure_message'] = $e->getMessage();
        
        Log::warning('Import circuit breaker - failure recorded', [
            'session_id' => $this->session->session_id,
            'error' => $e->getMessage(),
            'failure_count' => $state['failures'],
            'threshold' => $this->failureThreshold,
        ]);
        
        // Transition to open state if threshold exceeded
        if ($state['failures'] >= $this->failureThreshold) {
            $this->transitionToOpen($state);
        } else {
            $this->setState($state);
        }
    }

    /**
     * ✅ Handle successful operation in half-open state
     */
    private function onHalfOpenSuccess(): void
    {
        $state = $this->getState();
        $state['half_open_successes']++;
        
        // If enough successes in half-open, transition back to closed
        if ($state['half_open_successes'] >= $this->halfOpenMaxCalls) {
            $this->transitionToClosed();
            
            Log::info('Import circuit breaker recovered - transitioning to closed', [
                'session_id' => $this->session->session_id,
                'successful_tests' => $state['half_open_successes'],
            ]);
            
            $this->session->addWarning('Import circuit breaker recovered - normal processing resumed');
        } else {
            $this->setState($state);
        }
    }

    /**
     * ❌ Handle failure in half-open state
     */
    private function onHalfOpenFailure(\Exception $e): void
    {
        Log::warning('Import circuit breaker half-open test failed', [
            'session_id' => $this->session->session_id,
            'error' => $e->getMessage(),
        ]);
        
        // Failure in half-open immediately goes back to open
        $this->transitionToOpen();
    }

    /**
     * 🔄 Transition to open state
     */
    private function transitionToOpen(array $state = null): void
    {
        $state = $state ?: $this->getState();
        
        $state['status'] = self::STATE_OPEN;
        $state['opened_time'] = now()->timestamp;
        
        $this->setState($state);
        
        Log::error('Import circuit breaker opened', [
            'session_id' => $this->session->session_id,
            'failure_count' => $state['failures'],
            'threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
        ]);
        
        $this->session->addError(
            "Import processing paused due to repeated failures. " .
            "System will automatically retry in {$this->recoveryTimeout} seconds."
        );
    }

    /**
     * 🔄 Transition to half-open state
     */
    private function transitionToHalfOpen(): void
    {
        $state = $this->getState();
        
        $state['status'] = self::STATE_HALF_OPEN;
        $state['half_open_time'] = now()->timestamp;
        $state['half_open_successes'] = 0;
        
        $this->setState($state);
        
        Log::info('Import circuit breaker testing recovery', [
            'session_id' => $this->session->session_id,
            'max_test_calls' => $this->halfOpenMaxCalls,
        ]);
        
        $this->session->addWarning('Import processing testing recovery - limited operations allowed');
    }

    /**
     * 🔄 Transition to closed state
     */
    private function transitionToClosed(): void
    {
        $state = [
            'status' => self::STATE_CLOSED,
            'failures' => 0,
            'last_success_time' => now()->timestamp,
            'last_failure_time' => null,
            'last_failure_message' => null,
            'opened_time' => null,
            'half_open_time' => null,
            'half_open_successes' => 0,
        ];
        
        $this->setState($state);
        
        Log::info('Import circuit breaker closed', [
            'session_id' => $this->session->session_id,
        ]);
    }

    /**
     * 🔍 Check if we should attempt to reset from open state
     */
    private function shouldAttemptReset(array $state): bool
    {
        if (!isset($state['opened_time'])) {
            return true;
        }
        
        $timeSinceOpened = now()->timestamp - $state['opened_time'];
        return $timeSinceOpened >= $this->recoveryTimeout;
    }

    /**
     * 📊 Get current circuit breaker state
     */
    private function getState(): array
    {
        return Cache::get($this->cacheKey, [
            'status' => self::STATE_CLOSED,
            'failures' => 0,
            'last_success_time' => null,
            'last_failure_time' => null,
            'last_failure_message' => null,
            'opened_time' => null,
            'half_open_time' => null,
            'half_open_successes' => 0,
        ]);
    }

    /**
     * 💾 Set circuit breaker state
     */
    private function setState(array $state): void
    {
        // Cache for double the recovery timeout to prevent premature expiration
        Cache::put($this->cacheKey, $state, $this->recoveryTimeout * 2);
    }

    /**
     * 🔄 Reset circuit breaker to initial state
     */
    public function reset(): void
    {
        Cache::forget($this->cacheKey);
        
        Log::info('Import circuit breaker manually reset', [
            'session_id' => $this->session->session_id,
        ]);
        
        $this->session->addWarning('Import circuit breaker manually reset');
    }

    /**
     * 📊 Get current status for monitoring
     */
    public function getStatus(): array
    {
        $state = $this->getState();
        
        return [
            'session_id' => $this->session->session_id,
            'status' => $state['status'],
            'failure_count' => $state['failures'],
            'failure_threshold' => $this->failureThreshold,
            'last_failure_time' => $state['last_failure_time'],
            'last_failure_message' => $state['last_failure_message'],
            'last_success_time' => $state['last_success_time'],
            'recovery_timeout' => $this->recoveryTimeout,
            'is_healthy' => $state['status'] === self::STATE_CLOSED,
            'time_until_retry' => $this->getTimeUntilRetry($state),
        ];
    }

    /**
     * ⏰ Get time until next retry attempt
     */
    private function getTimeUntilRetry(array $state): ?int
    {
        if ($state['status'] !== self::STATE_OPEN) {
            return null;
        }
        
        if (!isset($state['opened_time'])) {
            return 0;
        }
        
        $timeSinceOpened = now()->timestamp - $state['opened_time'];
        return max(0, $this->recoveryTimeout - $timeSinceOpened);
    }

    /**
     * 🎯 Force transition to specific state (for testing)
     */
    public function forceState(string $state): void
    {
        if (!in_array($state, [self::STATE_CLOSED, self::STATE_OPEN, self::STATE_HALF_OPEN])) {
            throw new \InvalidArgumentException("Invalid circuit breaker state: {$state}");
        }
        
        switch ($state) {
            case self::STATE_CLOSED:
                $this->transitionToClosed();
                break;
            case self::STATE_OPEN:
                $this->transitionToOpen();
                break;
            case self::STATE_HALF_OPEN:
                $this->transitionToHalfOpen();
                break;
        }
    }
}

/**
 * 🚨 Import Circuit Breaker Exception
 */
class ImportCircuitBreakerException extends \Exception
{
    //
}