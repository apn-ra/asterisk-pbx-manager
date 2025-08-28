<?php

namespace AsteriskPbxManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker Service for handling AMI connection reliability.
 *
 * Implements the circuit breaker pattern to prevent cascading failures
 * and provide automatic recovery mechanisms for AMI connections.
 */
class CircuitBreakerService
{
    public const STATE_CLOSED = 'closed';
    public const STATE_OPEN = 'open';
    public const STATE_HALF_OPEN = 'half_open';

    private const CACHE_PREFIX = 'asterisk_circuit_breaker_';

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $circuits = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'failure_threshold' => config('asterisk-pbx-manager.circuit_breaker.failure_threshold', 5),
            'recovery_timeout'  => config('asterisk-pbx-manager.circuit_breaker.recovery_timeout', 60),
            'success_threshold' => config('asterisk-pbx-manager.circuit_breaker.success_threshold', 3),
            'timeout'           => config('asterisk-pbx-manager.circuit_breaker.timeout', 30),
            'monitor_window'    => config('asterisk-pbx-manager.circuit_breaker.monitor_window', 300),
            'enabled'           => config('asterisk-pbx-manager.circuit_breaker.enabled', true),
        ], $config);
    }

    /**
     * Execute a callable with circuit breaker protection.
     *
     * @param string   $circuitName
     * @param callable $callable
     * @param mixed    $fallback
     *
     * @throws \Exception
     *
     * @return mixed
     */
    public function call(string $circuitName, callable $callable, mixed $fallback = null): mixed
    {
        if (!$this->config['enabled']) {
            return $callable();
        }

        $circuit = $this->getCircuit($circuitName);

        // Check if circuit is open
        if ($circuit['state'] === self::STATE_OPEN) {
            if (!$this->shouldAttemptReset($circuit)) {
                Log::warning('Circuit breaker is open, using fallback', [
                    'circuit'      => $circuitName,
                    'failures'     => $circuit['failures'],
                    'last_failure' => $circuit['last_failure_time'],
                ]);

                return $this->handleFallback($circuitName, $fallback);
            }

            // Move to half-open state
            $this->setState($circuitName, self::STATE_HALF_OPEN);
            Log::info('Circuit breaker moved to half-open state', ['circuit' => $circuitName]);
        }

        try {
            $startTime = microtime(true);
            $result = $this->executeWithTimeout($callable, $this->config['timeout']);
            $duration = (microtime(true) - $startTime) * 1000;

            $this->recordSuccess($circuitName, $duration);

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($circuitName, $e);

            // Use fallback if circuit is now open
            if ($this->getCircuitState($circuitName) === self::STATE_OPEN) {
                return $this->handleFallback($circuitName, $fallback);
            }

            throw $e;
        }
    }

    /**
     * Get the current state of a circuit.
     *
     * @param string $circuitName
     *
     * @return string
     */
    public function getCircuitState(string $circuitName): string
    {
        $circuit = $this->getCircuit($circuitName);

        return $circuit['state'];
    }

    /**
     * Get circuit statistics.
     *
     * @param string $circuitName
     *
     * @return array<string, mixed>
     */
    public function getCircuitStats(string $circuitName): array
    {
        $circuit = $this->getCircuit($circuitName);

        return [
            'name'              => $circuitName,
            'state'             => $circuit['state'],
            'failures'          => $circuit['failures'],
            'successes'         => $circuit['successes'],
            'last_failure_time' => $circuit['last_failure_time'],
            'last_success_time' => $circuit['last_success_time'],
            'total_calls'       => $circuit['total_calls'],
            'avg_response_time' => $circuit['avg_response_time'],
            'failure_rate'      => $this->calculateFailureRate($circuit),
            'uptime_percentage' => $this->calculateUptimePercentage($circuit),
        ];
    }

    /**
     * Get all circuit statistics.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllCircuitStats(): array
    {
        $stats = [];

        foreach (array_keys($this->circuits) as $circuitName) {
            $stats[$circuitName] = $this->getCircuitStats($circuitName);
        }

        return $stats;
    }

    /**
     * Manually reset a circuit.
     *
     * @param string $circuitName
     *
     * @return void
     */
    public function reset(string $circuitName): void
    {
        $this->circuits[$circuitName] = $this->createCircuit();
        $this->persistCircuit($circuitName);

        Log::info('Circuit breaker manually reset', ['circuit' => $circuitName]);
    }

    /**
     * Manually open a circuit.
     *
     * @param string $circuitName
     * @param string $reason
     *
     * @return void
     */
    public function open(string $circuitName, string $reason = 'manual'): void
    {
        $this->setState($circuitName, self::STATE_OPEN);

        Log::warning('Circuit breaker manually opened', [
            'circuit' => $circuitName,
            'reason'  => $reason,
        ]);
    }

    /**
     * Check if a circuit is healthy.
     *
     * @param string $circuitName
     *
     * @return bool
     */
    public function isHealthy(string $circuitName): bool
    {
        $circuit = $this->getCircuit($circuitName);

        return $circuit['state'] === self::STATE_CLOSED &&
               $this->calculateFailureRate($circuit) < 50.0;
    }

    /**
     * Get or create a circuit.
     *
     * @param string $circuitName
     *
     * @return array<string, mixed>
     */
    private function getCircuit(string $circuitName): array
    {
        if (!isset($this->circuits[$circuitName])) {
            $this->circuits[$circuitName] = $this->loadCircuit($circuitName);
        }

        return $this->circuits[$circuitName];
    }

    /**
     * Load circuit from cache or create new.
     *
     * @param string $circuitName
     *
     * @return array<string, mixed>
     */
    private function loadCircuit(string $circuitName): array
    {
        $cacheKey = self::CACHE_PREFIX.$circuitName;
        $circuit = Cache::get($cacheKey);

        if ($circuit === null) {
            $circuit = $this->createCircuit();
            $this->persistCircuit($circuitName, $circuit);
        }

        return $circuit;
    }

    /**
     * Create a new circuit.
     *
     * @return array<string, mixed>
     */
    private function createCircuit(): array
    {
        return [
            'state'               => self::STATE_CLOSED,
            'failures'            => 0,
            'successes'           => 0,
            'last_failure_time'   => null,
            'last_success_time'   => null,
            'total_calls'         => 0,
            'total_response_time' => 0,
            'avg_response_time'   => 0,
            'created_at'          => Carbon::now()->toISOString(),
            'updated_at'          => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Persist circuit to cache.
     *
     * @param string                    $circuitName
     * @param array<string, mixed>|null $circuit
     *
     * @return void
     */
    private function persistCircuit(string $circuitName, ?array $circuit = null): void
    {
        $circuit = $circuit ?? $this->circuits[$circuitName];
        $circuit['updated_at'] = Carbon::now()->toISOString();

        $cacheKey = self::CACHE_PREFIX.$circuitName;
        $ttl = $this->config['monitor_window'] * 2; // Keep data longer than monitor window

        Cache::put($cacheKey, $circuit, $ttl);
    }

    /**
     * Record a successful call.
     *
     * @param string $circuitName
     * @param float  $duration
     *
     * @return void
     */
    private function recordSuccess(string $circuitName, float $duration): void
    {
        $circuit = &$this->circuits[$circuitName];

        $circuit['successes']++;
        $circuit['total_calls']++;
        $circuit['total_response_time'] += $duration;
        $circuit['avg_response_time'] = $circuit['total_response_time'] / $circuit['total_calls'];
        $circuit['last_success_time'] = Carbon::now()->toISOString();

        // Handle state transitions for half-open circuit
        if ($circuit['state'] === self::STATE_HALF_OPEN) {
            if ($circuit['successes'] >= $this->config['success_threshold']) {
                $this->setState($circuitName, self::STATE_CLOSED);
                Log::info('Circuit breaker closed after successful recovery', [
                    'circuit'   => $circuitName,
                    'successes' => $circuit['successes'],
                ]);
            }
        }

        $this->persistCircuit($circuitName);
    }

    /**
     * Record a failed call.
     *
     * @param string     $circuitName
     * @param \Throwable $exception
     *
     * @return void
     */
    private function recordFailure(string $circuitName, \Throwable $exception): void
    {
        $circuit = &$this->circuits[$circuitName];

        $circuit['failures']++;
        $circuit['total_calls']++;
        $circuit['last_failure_time'] = Carbon::now()->toISOString();

        Log::warning('Circuit breaker recorded failure', [
            'circuit'           => $circuitName,
            'failures'          => $circuit['failures'],
            'error'             => $exception->getMessage(),
            'failure_threshold' => $this->config['failure_threshold'],
        ]);

        // Check if we should open the circuit
        if ($circuit['failures'] >= $this->config['failure_threshold']) {
            $this->setState($circuitName, self::STATE_OPEN);

            Log::error('Circuit breaker opened due to failures', [
                'circuit'   => $circuitName,
                'failures'  => $circuit['failures'],
                'threshold' => $this->config['failure_threshold'],
            ]);
        }

        $this->persistCircuit($circuitName);
    }

    /**
     * Set circuit state.
     *
     * @param string $circuitName
     * @param string $state
     *
     * @return void
     */
    private function setState(string $circuitName, string $state): void
    {
        $circuit = &$this->circuits[$circuitName];
        $previousState = $circuit['state'];
        $circuit['state'] = $state;

        // Reset counters on state change
        if ($state === self::STATE_CLOSED) {
            $circuit['failures'] = 0;
            $circuit['successes'] = 0;
        } elseif ($state === self::STATE_HALF_OPEN) {
            $circuit['successes'] = 0; // Reset success count for half-open testing
        }

        Log::info('Circuit breaker state changed', [
            'circuit'        => $circuitName,
            'previous_state' => $previousState,
            'new_state'      => $state,
        ]);

        $this->persistCircuit($circuitName);
    }

    /**
     * Check if circuit should attempt reset.
     *
     * @param array<string, mixed> $circuit
     *
     * @return bool
     */
    private function shouldAttemptReset(array $circuit): bool
    {
        if (!$circuit['last_failure_time']) {
            return true;
        }

        $lastFailure = Carbon::parse($circuit['last_failure_time']);
        $recoverAfter = $lastFailure->addSeconds($this->config['recovery_timeout']);

        return Carbon::now()->gte($recoverAfter);
    }

    /**
     * Execute callable with timeout.
     *
     * @param callable $callable
     * @param int      $timeout
     *
     * @throws \Exception
     *
     * @return mixed
     */
    private function executeWithTimeout(callable $callable, int $timeout): mixed
    {
        // Simple timeout implementation - in production you might want more sophisticated timeout handling
        $startTime = time();
        $result = $callable();

        if ((time() - $startTime) > $timeout) {
            throw new \Exception("Operation timed out after {$timeout} seconds");
        }

        return $result;
    }

    /**
     * Handle fallback when circuit is open.
     *
     * @param string $circuitName
     * @param mixed  $fallback
     *
     * @throws \Exception
     *
     * @return mixed
     */
    private function handleFallback(string $circuitName, mixed $fallback): mixed
    {
        if ($fallback === null) {
            throw new \Exception("Circuit breaker is open for '{$circuitName}' and no fallback provided");
        }

        if (is_callable($fallback)) {
            return $fallback();
        }

        return $fallback;
    }

    /**
     * Calculate failure rate percentage.
     *
     * @param array<string, mixed> $circuit
     *
     * @return float
     */
    private function calculateFailureRate(array $circuit): float
    {
        if ($circuit['total_calls'] === 0) {
            return 0.0;
        }

        return ($circuit['failures'] / $circuit['total_calls']) * 100.0;
    }

    /**
     * Calculate uptime percentage.
     *
     * @param array<string, mixed> $circuit
     *
     * @return float
     */
    private function calculateUptimePercentage(array $circuit): float
    {
        if ($circuit['total_calls'] === 0) {
            return 100.0;
        }

        $successfulCalls = $circuit['total_calls'] - $circuit['failures'];

        return ($successfulCalls / $circuit['total_calls']) * 100.0;
    }
}
