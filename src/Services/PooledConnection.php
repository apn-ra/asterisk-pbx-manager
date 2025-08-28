<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Exceptions\ActionExecutionException;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PAMI\Client\Impl\ClientImpl;
use PAMI\Message\Action\ActionMessage;
use PAMI\Message\Response\ResponseMessage;

/**
 * Pooled Connection wrapper for Asterisk AMI connections.
 *
 * Wraps a PAMI ClientImpl with pooling functionality including
 * state management, health monitoring, usage statistics, and
 * automatic recycling capabilities.
 *
 * @author Asterisk PBX Manager Team
 */
class PooledConnection
{
    /**
     * Connection states.
     */
    public const STATE_IDLE = 'idle';
    public const STATE_IN_USE = 'in_use';
    public const STATE_CONNECTING = 'connecting';
    public const STATE_DISCONNECTED = 'disconnected';
    public const STATE_ERROR = 'error';

    /**
     * The underlying PAMI client.
     *
     * @var ClientImpl
     */
    protected ClientImpl $client;

    /**
     * Unique connection identifier.
     *
     * @var string
     */
    protected string $id;

    /**
     * Connection configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * Current connection state.
     *
     * @var string
     */
    protected string $state = self::STATE_DISCONNECTED;

    /**
     * Connection creation timestamp.
     *
     * @var Carbon
     */
    protected Carbon $createdAt;

    /**
     * Last usage timestamp.
     *
     * @var Carbon
     */
    protected Carbon $lastUsed;

    /**
     * Last health check timestamp.
     *
     * @var Carbon|null
     */
    protected ?Carbon $lastHealthCheck = null;

    /**
     * Connection statistics.
     *
     * @var array
     */
    protected array $stats = [
        'requests_handled'      => 0,
        'total_connect_time'    => 0,
        'total_execution_time'  => 0,
        'successful_requests'   => 0,
        'failed_requests'       => 0,
        'health_check_failures' => 0,
        'last_error'            => null,
    ];

    /**
     * Whether the connection is healthy.
     *
     * @var bool
     */
    protected bool $healthy = true;

    /**
     * Connection error information.
     *
     * @var array|null
     */
    protected ?array $lastError = null;

    /**
     * Create a new Pooled Connection instance.
     *
     * @param ClientImpl $client
     * @param string     $id
     * @param array      $config
     */
    public function __construct(ClientImpl $client, string $id, array $config = [])
    {
        $this->client = $client;
        $this->id = $id;
        $this->config = array_merge([
            'max_requests_per_connection' => 1000,
            'max_connection_age'          => 3600, // 1 hour
            'health_check_interval'       => 60, // 1 minute
            'enable_health_monitoring'    => true,
            'enable_connection_recycling' => true,
            'pooled'                      => true,
        ], $config);

        $this->createdAt = now();
        $this->lastUsed = now();
    }

    /**
     * Connect to Asterisk AMI.
     *
     * @throws AsteriskConnectionException
     *
     * @return void
     */
    public function connect(): void
    {
        $startTime = microtime(true);
        $this->state = self::STATE_CONNECTING;

        try {
            $this->client->open();
            $this->state = self::STATE_IDLE;
            $this->healthy = true;
            $this->lastError = null;

            $connectTime = microtime(true) - $startTime;
            $this->stats['total_connect_time'] += $connectTime;

            Log::debug('Pooled connection established', [
                'connection_id' => $this->id,
                'connect_time'  => $connectTime,
            ]);
        } catch (\Exception $e) {
            $this->state = self::STATE_ERROR;
            $this->healthy = false;
            $this->lastError = [
                'message'   => $e->getMessage(),
                'timestamp' => now(),
                'type'      => 'connection_error',
            ];

            Log::error('Failed to establish pooled connection', [
                'connection_id' => $this->id,
                'error'         => $e->getMessage(),
            ]);

            throw AsteriskConnectionException::networkError(
                $this->config['host'] ?? 'localhost',
                $this->config['port'] ?? 5038,
                'Failed to connect pooled connection: '.$e->getMessage()
            );
        }
    }

    /**
     * Close the connection.
     *
     * @return void
     */
    public function close(): void
    {
        try {
            if ($this->state !== self::STATE_DISCONNECTED) {
                $this->client->close();
            }
        } catch (\Exception $e) {
            Log::warning('Error closing pooled connection', [
                'connection_id' => $this->id,
                'error'         => $e->getMessage(),
            ]);
        } finally {
            $this->state = self::STATE_DISCONNECTED;
            $this->healthy = false;
        }
    }

    /**
     * Send an action through the connection.
     *
     * @param ActionMessage $action
     *
     * @throws ActionExecutionException
     *
     * @return ResponseMessage
     */
    public function sendAction(ActionMessage $action): ResponseMessage
    {
        if ($this->state !== self::STATE_IN_USE) {
            throw ActionExecutionException::invalidState(
                $action->getActionID() ?? 'unknown',
                "Connection is not in use (current state: {$this->state})"
            );
        }

        $startTime = microtime(true);

        try {
            $response = $this->client->send($action);

            $executionTime = microtime(true) - $startTime;
            $this->updateStats(true, $executionTime);

            return $response;
        } catch (\Exception $e) {
            $executionTime = microtime(true) - $startTime;
            $this->updateStats(false, $executionTime, $e->getMessage());

            throw ActionExecutionException::executionFailed(
                $action->getActionID() ?? 'unknown',
                'Action execution failed on pooled connection: '.$e->getMessage()
            );
        }
    }

    /**
     * Mark connection as in use.
     *
     * @return void
     */
    public function markAsInUse(): void
    {
        if ($this->state === self::STATE_IDLE) {
            $this->state = self::STATE_IN_USE;
            $this->lastUsed = now();
        }
    }

    /**
     * Mark connection as available (idle).
     *
     * @return void
     */
    public function markAsAvailable(): void
    {
        if ($this->state === self::STATE_IN_USE || $this->state === self::STATE_CONNECTING) {
            $this->state = self::STATE_IDLE;
            $this->lastUsed = now();
        }
    }

    /**
     * Check if the connection is available for use.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->state === self::STATE_IDLE && $this->healthy;
    }

    /**
     * Check if the connection is currently in use.
     *
     * @return bool
     */
    public function isInUse(): bool
    {
        return $this->state === self::STATE_IN_USE;
    }

    /**
     * Check if the connection is healthy.
     *
     * @return bool
     */
    public function isHealthy(): bool
    {
        if (!$this->healthy) {
            return false;
        }

        // Perform periodic health check if enabled
        if ($this->config['enable_health_monitoring'] ?? true) {
            $interval = $this->config['health_check_interval'] ?? 60;

            if ($this->lastHealthCheck === null ||
                now()->diffInSeconds($this->lastHealthCheck) >= $interval) {
                return $this->performHealthCheck();
            }
        }

        return true;
    }

    /**
     * Perform a health check on the connection.
     *
     * @return bool
     */
    public function performHealthCheck(): bool
    {
        $this->lastHealthCheck = now();

        try {
            // Simple ping test - try to send a basic command
            if ($this->state === self::STATE_DISCONNECTED || $this->state === self::STATE_ERROR) {
                $this->healthy = false;

                return false;
            }

            // Check if the underlying client is still connected
            // Note: PAMI doesn't provide a direct way to check this,
            // so we rely on the client's internal state
            $this->healthy = true;

            return true;
        } catch (\Exception $e) {
            $this->healthy = false;
            $this->stats['health_check_failures']++;
            $this->lastError = [
                'message'   => $e->getMessage(),
                'timestamp' => now(),
                'type'      => 'health_check_error',
            ];

            Log::warning('Health check failed for pooled connection', [
                'connection_id' => $this->id,
                'error'         => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if the connection should be recycled.
     *
     * @return bool
     */
    public function shouldRecycle(): bool
    {
        if (!($this->config['enable_connection_recycling'] ?? true)) {
            return false;
        }

        $maxRequests = $this->config['max_requests_per_connection'] ?? 1000;
        $maxAge = $this->config['max_connection_age'] ?? 3600;

        // Check if connection has handled too many requests
        if ($this->stats['requests_handled'] >= $maxRequests) {
            return true;
        }

        // Check if connection is too old
        if ($this->getAge() >= $maxAge) {
            return true;
        }

        // Check if connection has too many health check failures
        if ($this->stats['health_check_failures'] > 3) {
            return true;
        }

        return false;
    }

    /**
     * Get connection age in seconds.
     *
     * @return int
     */
    public function getAge(): int
    {
        return now()->diffInSeconds($this->createdAt);
    }

    /**
     * Get the connection ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get the current connection state.
     *
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get the last used timestamp.
     *
     * @return Carbon
     */
    public function getLastUsed(): Carbon
    {
        return $this->lastUsed;
    }

    /**
     * Set the last used timestamp.
     *
     * @param Carbon $timestamp
     *
     * @return void
     */
    public function setLastUsed(Carbon $timestamp): void
    {
        $this->lastUsed = $timestamp;
    }

    /**
     * Get connection statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'connection_id'     => $this->id,
            'state'             => $this->state,
            'healthy'           => $this->healthy,
            'age_seconds'       => $this->getAge(),
            'created_at'        => $this->createdAt->toISOString(),
            'last_used'         => $this->lastUsed->toISOString(),
            'last_health_check' => $this->lastHealthCheck?->toISOString(),
            'should_recycle'    => $this->shouldRecycle(),
            'last_error'        => $this->lastError,
        ]);
    }

    /**
     * Get the request count.
     *
     * @return int
     */
    public function getRequestCount(): int
    {
        return $this->stats['requests_handled'];
    }

    /**
     * Update connection statistics.
     *
     * @param bool        $success
     * @param float       $executionTime
     * @param string|null $error
     *
     * @return void
     */
    protected function updateStats(bool $success, float $executionTime, ?string $error = null): void
    {
        $this->stats['requests_handled']++;
        $this->stats['total_execution_time'] += $executionTime;

        if ($success) {
            $this->stats['successful_requests']++;
        } else {
            $this->stats['failed_requests']++;
            $this->stats['last_error'] = $error;

            if ($this->stats['failed_requests'] > 5) {
                $this->healthy = false;
            }
        }
    }

    /**
     * Get the underlying PAMI client.
     *
     * This method is provided for compatibility but should be used sparingly
     * as it bypasses the pooling layer.
     *
     * @return ClientImpl
     */
    public function getClient(): ClientImpl
    {
        return $this->client;
    }

    /**
     * Check if this is a pooled connection.
     *
     * @return bool
     */
    public function isPooled(): bool
    {
        return $this->config['pooled'] ?? true;
    }

    /**
     * Get connection information.
     *
     * @return array
     */
    public function getConnectionInfo(): array
    {
        return [
            'id'               => $this->id,
            'state'            => $this->state,
            'healthy'          => $this->healthy,
            'pooled'           => $this->isPooled(),
            'age'              => $this->getAge(),
            'requests_handled' => $this->getRequestCount(),
            'created_at'       => $this->createdAt,
            'last_used'        => $this->lastUsed,
        ];
    }

    /**
     * String representation of the connection.
     *
     * @return string
     */
    public function __toString(): string
    {
        return "PooledConnection[{$this->id}:{$this->state}]";
    }

    /**
     * Destructor - ensure connection is closed.
     */
    public function __destruct()
    {
        if ($this->state !== self::STATE_DISCONNECTED) {
            $this->close();
        }
    }
}
