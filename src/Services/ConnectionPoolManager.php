<?php

namespace AsteriskPbxManager\Services;

use PAMI\Client\Impl\ClientImpl;
use AsteriskPbxManager\Services\AmiInputSanitizer;
use AsteriskPbxManager\Services\AuditLoggingService;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Connection Pool Manager for Asterisk AMI connections.
 * 
 * Manages a pool of AMI connections for high-load scenarios, providing
 * connection reuse, load balancing, health monitoring, and automatic
 * connection recycling for optimal performance.
 * 
 * @package AsteriskPbxManager\Services
 * @author Asterisk PBX Manager Team
 */
class ConnectionPoolManager
{
    /**
     * Pool configuration settings.
     *
     * @var array
     */
    protected array $config;

    /**
     * Active connection pool.
     *
     * @var array<string, PooledConnection>
     */
    protected array $pool = [];

    /**
     * Connection usage statistics.
     *
     * @var array
     */
    protected array $stats = [
        'total_created' => 0,
        'total_destroyed' => 0,
        'current_active' => 0,
        'current_idle' => 0,
        'total_requests' => 0,
        'pool_hits' => 0,
        'pool_misses' => 0,
        'connection_errors' => 0,
        'last_cleanup' => null,
    ];

    /**
     * AMI Input Sanitizer instance.
     *
     * @var AmiInputSanitizer
     */
    protected AmiInputSanitizer $sanitizer;

    /**
     * Audit Logging Service instance.
     *
     * @var AuditLoggingService
     */
    protected AuditLoggingService $auditLogger;

    /**
     * Connection creation configuration.
     *
     * @var array
     */
    protected array $connectionConfig;

    /**
     * Pool lock for thread safety.
     *
     * @var bool
     */
    protected bool $locked = false;

    /**
     * Create a new Connection Pool Manager instance.
     *
     * @param AmiInputSanitizer $sanitizer
     * @param AuditLoggingService $auditLogger
     */
    public function __construct(AmiInputSanitizer $sanitizer, AuditLoggingService $auditLogger)
    {
        $this->sanitizer = $sanitizer;
        $this->auditLogger = $auditLogger;
        $this->config = config('asterisk-pbx-manager.connection_pool', []);
        $this->connectionConfig = config('asterisk-pbx-manager.connection', []);
        
        $this->initializePool();
    }

    /**
     * Initialize the connection pool.
     *
     * @return void
     */
    protected function initializePool(): void
    {
        $this->config = array_merge([
            'enabled' => false,
            'min_connections' => 2,
            'max_connections' => 10,
            'max_idle_time' => 300, // 5 minutes
            'max_connection_age' => 3600, // 1 hour
            'health_check_interval' => 60, // 1 minute
            'connection_timeout' => 10,
            'acquire_timeout' => 5,
            'cleanup_interval' => 300, // 5 minutes
            'enable_connection_recycling' => true,
            'enable_health_monitoring' => true,
            'max_requests_per_connection' => 1000,
            'connection_validation' => true,
        ], $this->config);

        $this->stats['last_cleanup'] = now();

        // Pre-populate pool with minimum connections if enabled
        if ($this->isEnabled() && $this->config['min_connections'] > 0) {
            $this->warmUpPool();
        }
    }

    /**
     * Check if connection pooling is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Acquire a connection from the pool.
     *
     * @param int|null $timeout Timeout in seconds
     * @return PooledConnection
     * @throws AsteriskConnectionException
     */
    public function acquireConnection(?int $timeout = null): PooledConnection
    {
        $timeout = $timeout ?? $this->config['acquire_timeout'];
        $startTime = microtime(true);
        
        $this->stats['total_requests']++;

        if (!$this->isEnabled()) {
            // If pooling is disabled, create a direct connection
            return $this->createDirectConnection();
        }

        try {
            // Attempt to get a connection from the pool
            $connection = $this->getFromPool();
            
            if ($connection !== null) {
                $this->stats['pool_hits']++;
                $this->auditLogger->logAction('connection_pool', 'acquire', true, [
                    'connection_id' => $connection->getId(),
                    'source' => 'pool',
                    'execution_time' => microtime(true) - $startTime,
                ]);
                
                return $connection;
            }

            // Pool miss - create new connection if under limit
            if ($this->canCreateNewConnection()) {
                $connection = $this->createNewConnection();
                $this->stats['pool_misses']++;
                
                $this->auditLogger->logAction('connection_pool', 'acquire', true, [
                    'connection_id' => $connection->getId(),
                    'source' => 'new',
                    'execution_time' => microtime(true) - $startTime,
                ]);
                
                return $connection;
            }

            // Wait for a connection to become available
            $connection = $this->waitForAvailableConnection($timeout);
            
            if ($connection !== null) {
                $this->stats['pool_hits']++;
                return $connection;
            }

            throw AsteriskConnectionException::timeout(
                $this->connectionConfig['host'] ?? 'localhost',
                $this->connectionConfig['port'] ?? 5038,
                "Connection pool timeout after {$timeout} seconds"
            );

        } catch (\Exception $e) {
            $this->stats['connection_errors']++;
            
            $this->auditLogger->logAction('connection_pool', 'acquire', false, [
                'error' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime,
            ]);

            if ($e instanceof AsteriskConnectionException) {
                throw $e;
            }
            
            throw AsteriskConnectionException::networkError(
                $this->connectionConfig['host'] ?? 'localhost',
                $this->connectionConfig['port'] ?? 5038,
                'Failed to acquire connection from pool: ' . $e->getMessage()
            );
        }
    }

    /**
     * Release a connection back to the pool.
     *
     * @param PooledConnection $connection
     * @return void
     */
    public function releaseConnection(PooledConnection $connection): void
    {
        if (!$this->isEnabled()) {
            // If pooling is disabled, close the connection directly
            $connection->close();
            return;
        }

        try {
            // Check if connection is still healthy
            if (!$connection->isHealthy() || $connection->shouldRecycle()) {
                $this->destroyConnection($connection);
                return;
            }

            // Mark as available and return to pool
            $connection->markAsAvailable();
            $connection->setLastUsed(now());

            $this->auditLogger->logAction('connection_pool', 'release', true, [
                'connection_id' => $connection->getId(),
                'requests_handled' => $connection->getRequestCount(),
                'connection_age' => $connection->getAge(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error releasing connection to pool', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage(),
            ]);
            
            // If there's an error releasing, destroy the connection
            $this->destroyConnection($connection);
        }
    }

    /**
     * Get an available connection from the pool.
     *
     * @return PooledConnection|null
     */
    protected function getFromPool(): ?PooledConnection
    {
        foreach ($this->pool as $id => $connection) {
            if ($connection->isAvailable() && $connection->isHealthy()) {
                $connection->markAsInUse();
                return $connection;
            }
        }

        return null;
    }

    /**
     * Check if we can create a new connection.
     *
     * @return bool
     */
    protected function canCreateNewConnection(): bool
    {
        return count($this->pool) < $this->config['max_connections'];
    }

    /**
     * Create a new pooled connection.
     *
     * @return PooledConnection
     * @throws AsteriskConnectionException
     */
    protected function createNewConnection(): PooledConnection
    {
        try {
            $client = new ClientImpl($this->connectionConfig);
            $connection = new PooledConnection(
                $client,
                $this->generateConnectionId(),
                $this->config
            );

            $connection->connect();
            $this->pool[$connection->getId()] = $connection;
            
            $this->stats['total_created']++;
            $this->updateStats();

            Log::info('Created new pooled AMI connection', [
                'connection_id' => $connection->getId(),
                'pool_size' => count($this->pool),
            ]);

            return $connection;

        } catch (\Exception $e) {
            throw AsteriskConnectionException::networkError(
                $this->connectionConfig['host'] ?? 'localhost',
                $this->connectionConfig['port'] ?? 5038,
                'Failed to create new pooled connection: ' . $e->getMessage()
            );
        }
    }

    /**
     * Create a direct connection (when pooling is disabled).
     *
     * @return PooledConnection
     * @throws AsteriskConnectionException
     */
    protected function createDirectConnection(): PooledConnection
    {
        try {
            $client = new ClientImpl($this->connectionConfig);
            $connection = new PooledConnection(
                $client,
                $this->generateConnectionId(),
                array_merge($this->config, ['pooled' => false])
            );

            $connection->connect();
            
            return $connection;

        } catch (\Exception $e) {
            throw AsteriskConnectionException::networkError(
                $this->connectionConfig['host'] ?? 'localhost',
                $this->connectionConfig['port'] ?? 5038,
                'Failed to create direct connection: ' . $e->getMessage()
            );
        }
    }

    /**
     * Wait for an available connection.
     *
     * @param int $timeout
     * @return PooledConnection|null
     */
    protected function waitForAvailableConnection(int $timeout): ?PooledConnection
    {
        $startTime = time();
        
        while ((time() - $startTime) < $timeout) {
            $connection = $this->getFromPool();
            if ($connection !== null) {
                return $connection;
            }
            
            usleep(100000); // Wait 100ms before retrying
        }

        return null;
    }

    /**
     * Destroy a connection and remove it from the pool.
     *
     * @param PooledConnection $connection
     * @return void
     */
    protected function destroyConnection(PooledConnection $connection): void
    {
        try {
            $connection->close();
            unset($this->pool[$connection->getId()]);
            
            $this->stats['total_destroyed']++;
            $this->updateStats();

            Log::debug('Destroyed pooled AMI connection', [
                'connection_id' => $connection->getId(),
                'pool_size' => count($this->pool),
            ]);

        } catch (\Exception $e) {
            Log::error('Error destroying pooled connection', [
                'connection_id' => $connection->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Warm up the pool with minimum connections.
     *
     * @return void
     */
    protected function warmUpPool(): void
    {
        $targetConnections = $this->config['min_connections'];
        
        for ($i = 0; $i < $targetConnections; $i++) {
            try {
                $connection = $this->createNewConnection();
                $connection->markAsAvailable();
            } catch (\Exception $e) {
                Log::error('Failed to warm up connection pool', [
                    'error' => $e->getMessage(),
                    'connection_index' => $i,
                ]);
                break;
            }
        }

        Log::info('Warmed up connection pool', [
            'target_connections' => $targetConnections,
            'created_connections' => count($this->pool),
        ]);
    }

    /**
     * Perform periodic pool cleanup.
     *
     * @return void
     */
    public function cleanup(): void
    {
        if ($this->locked) {
            return;
        }

        $this->locked = true;

        try {
            $now = now();
            $maxIdleTime = $this->config['max_idle_time'];
            $maxAge = $this->config['max_connection_age'];
            $minConnections = $this->config['min_connections'];
            
            $connectionsToDestroy = [];

            foreach ($this->pool as $id => $connection) {
                // Check if connection should be cleaned up
                if ($connection->isAvailable()) {
                    $idleTime = $now->diffInSeconds($connection->getLastUsed());
                    $age = $connection->getAge();
                    
                    // Keep minimum connections unless they're too old
                    if (count($this->pool) <= $minConnections && $age < $maxAge) {
                        continue;
                    }
                    
                    // Remove idle or old connections
                    if ($idleTime > $maxIdleTime || $age > $maxAge || !$connection->isHealthy()) {
                        $connectionsToDestroy[] = $connection;
                    }
                }
            }

            // Destroy marked connections
            foreach ($connectionsToDestroy as $connection) {
                $this->destroyConnection($connection);
            }

            // Ensure minimum connections
            $currentCount = count($this->pool);
            if ($currentCount < $minConnections) {
                $needed = $minConnections - $currentCount;
                for ($i = 0; $i < $needed; $i++) {
                    try {
                        $connection = $this->createNewConnection();
                        $connection->markAsAvailable();
                    } catch (\Exception $e) {
                        Log::error('Failed to create connection during cleanup', [
                            'error' => $e->getMessage(),
                        ]);
                        break;
                    }
                }
            }

            $this->stats['last_cleanup'] = $now;
            $this->updateStats();

        } finally {
            $this->locked = false;
        }
    }

    /**
     * Get pool statistics.
     *
     * @return array
     */
    public function getStats(): array
    {
        $this->updateStats();
        
        return array_merge($this->stats, [
            'pool_size' => count($this->pool),
            'available_connections' => $this->countAvailableConnections(),
            'in_use_connections' => $this->countInUseConnections(),
            'config' => $this->config,
        ]);
    }

    /**
     * Update internal statistics.
     *
     * @return void
     */
    protected function updateStats(): void
    {
        $this->stats['current_active'] = $this->countInUseConnections();
        $this->stats['current_idle'] = $this->countAvailableConnections();
    }

    /**
     * Count available connections in the pool.
     *
     * @return int
     */
    protected function countAvailableConnections(): int
    {
        $count = 0;
        foreach ($this->pool as $connection) {
            if ($connection->isAvailable()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count in-use connections in the pool.
     *
     * @return int
     */
    protected function countInUseConnections(): int
    {
        $count = 0;
        foreach ($this->pool as $connection) {
            if ($connection->isInUse()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Generate a unique connection ID.
     *
     * @return string
     */
    protected function generateConnectionId(): string
    {
        return 'conn_' . uniqid() . '_' . getmypid();
    }

    /**
     * Close all connections in the pool.
     *
     * @return void
     */
    public function closeAll(): void
    {
        foreach ($this->pool as $connection) {
            $this->destroyConnection($connection);
        }
        
        $this->pool = [];
        $this->updateStats();

        Log::info('Closed all pooled connections');
    }

    /**
     * Get the underlying connection pool (for debugging).
     *
     * @return array
     */
    public function getPool(): array
    {
        return $this->pool;
    }

    /**
     * Destructor - cleanup connections on shutdown.
     */
    public function __destruct()
    {
        $this->closeAll();
    }
}