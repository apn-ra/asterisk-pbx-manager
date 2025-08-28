<?php

namespace AsteriskPbxManager\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use PAMI\Client\Impl\ClientImpl;

/**
 * Service for handling graceful shutdown of Asterisk connections and cleanup
 */
class ShutdownHandlerService
{
    /**
     * @var array<string, callable>
     */
    private array $shutdownCallbacks = [];

    /**
     * @var array<ClientImpl>
     */
    private array $connections = [];

    /**
     * @var bool
     */
    private bool $shutdownRegistered = false;

    /**
     * @var bool
     */
    private bool $isShuttingDown = false;

    /**
     * @var int
     */
    private int $shutdownTimeout;

    public function __construct()
    {
        $this->shutdownTimeout = config('asterisk-pbx-manager.shutdown.timeout', 30);
        $this->registerShutdownHandler();
        $this->registerSignalHandlers();
    }

    /**
     * Register a connection for cleanup during shutdown
     *
     * @param string $connectionId
     * @param ClientImpl $connection
     * @return void
     */
    public function registerConnection(string $connectionId, ClientImpl $connection): void
    {
        $this->connections[$connectionId] = $connection;
        
        Log::debug('Registered AMI connection for shutdown handling', [
            'connection_id' => $connectionId,
            'total_connections' => count($this->connections)
        ]);
    }

    /**
     * Unregister a connection (when it's closed manually)
     *
     * @param string $connectionId
     * @return void
     */
    public function unregisterConnection(string $connectionId): void
    {
        unset($this->connections[$connectionId]);
        
        Log::debug('Unregistered AMI connection from shutdown handling', [
            'connection_id' => $connectionId,
            'remaining_connections' => count($this->connections)
        ]);
    }

    /**
     * Register a custom shutdown callback
     *
     * @param string $name
     * @param callable $callback
     * @return void
     */
    public function registerCallback(string $name, callable $callback): void
    {
        $this->shutdownCallbacks[$name] = $callback;
        
        Log::debug('Registered shutdown callback', ['callback_name' => $name]);
    }

    /**
     * Unregister a shutdown callback
     *
     * @param string $name
     * @return void
     */
    public function unregisterCallback(string $name): void
    {
        unset($this->shutdownCallbacks[$name]);
        
        Log::debug('Unregistered shutdown callback', ['callback_name' => $name]);
    }

    /**
     * Perform graceful shutdown
     *
     * @param int|null $timeout Override default timeout
     * @return void
     */
    public function shutdown(?int $timeout = null): void
    {
        if ($this->isShuttingDown) {
            return;
        }

        $this->isShuttingDown = true;
        $shutdownTimeout = $timeout ?? $this->shutdownTimeout;
        
        Log::info('Initiating graceful shutdown', [
            'timeout' => $shutdownTimeout,
            'connections_count' => count($this->connections),
            'callbacks_count' => count($this->shutdownCallbacks)
        ]);

        $startTime = time();

        try {
            // Step 1: Execute custom shutdown callbacks
            $this->executeShutdownCallbacks($shutdownTimeout);

            // Step 2: Close AMI connections gracefully
            $remainingTimeout = $shutdownTimeout - (time() - $startTime);
            if ($remainingTimeout > 0) {
                $this->closeConnections($remainingTimeout);
            }

            // Step 3: Final cleanup
            $this->performFinalCleanup();

            Log::info('Graceful shutdown completed successfully', [
                'duration' => time() - $startTime,
                'connections_closed' => count($this->connections),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error during graceful shutdown', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => time() - $startTime,
            ]);

            // Force cleanup on error
            $this->forceCleanup();
        }
    }

    /**
     * Check if the service is currently shutting down
     *
     * @return bool
     */
    public function isShuttingDown(): bool
    {
        return $this->isShuttingDown;
    }

    /**
     * Get current connection count
     *
     * @return int
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Get registered callback names
     *
     * @return array<string>
     */
    public function getRegisteredCallbacks(): array
    {
        return array_keys($this->shutdownCallbacks);
    }

    /**
     * Register PHP shutdown handler
     *
     * @return void
     */
    private function registerShutdownHandler(): void
    {
        if (!$this->shutdownRegistered) {
            register_shutdown_function([$this, 'handlePhpShutdown']);
            $this->shutdownRegistered = true;
            
            Log::debug('PHP shutdown handler registered');
        }
    }

    /**
     * Register signal handlers for graceful shutdown
     *
     * @return void
     */
    private function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            Log::warning('PCNTL extension not available, signal handling disabled');
            return;
        }

        // Handle SIGTERM (termination request)
        pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        
        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, [$this, 'handleSignal']);
        
        // Handle SIGHUP (hang up)
        pcntl_signal(SIGHUP, [$this, 'handleSignal']);

        Log::debug('Signal handlers registered for graceful shutdown');
    }

    /**
     * Handle PHP shutdown
     *
     * @return void
     */
    public function handlePhpShutdown(): void
    {
        if (!$this->isShuttingDown) {
            Log::info('PHP shutdown detected, initiating graceful shutdown');
            $this->shutdown(10); // Shorter timeout for PHP shutdown
        }
    }

    /**
     * Handle system signals
     *
     * @param int $signal
     * @return void
     */
    public function handleSignal(int $signal): void
    {
        $signalName = $this->getSignalName($signal);
        
        Log::info('Received shutdown signal', ['signal' => $signalName, 'signal_code' => $signal]);
        
        $this->shutdown();
        
        // Exit gracefully
        exit(0);
    }

    /**
     * Execute registered shutdown callbacks
     *
     * @param int $timeout
     * @return void
     */
    private function executeShutdownCallbacks(int $timeout): void
    {
        if (empty($this->shutdownCallbacks)) {
            return;
        }

        Log::info('Executing shutdown callbacks', ['count' => count($this->shutdownCallbacks)]);
        
        $startTime = time();
        $timeoutPerCallback = max(1, intval($timeout / count($this->shutdownCallbacks)));

        foreach ($this->shutdownCallbacks as $name => $callback) {
            if ((time() - $startTime) >= $timeout) {
                Log::warning('Shutdown callback timeout reached, skipping remaining callbacks');
                break;
            }

            try {
                Log::debug('Executing shutdown callback', ['callback_name' => $name]);
                
                // Set a timeout for the callback execution
                $callbackStart = time();
                call_user_func($callback);
                
                $duration = time() - $callbackStart;
                Log::debug('Shutdown callback completed', [
                    'callback_name' => $name,
                    'duration' => $duration
                ]);

            } catch (\Throwable $e) {
                Log::error('Error executing shutdown callback', [
                    'callback_name' => $name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Close all registered connections
     *
     * @param int $timeout
     * @return void
     */
    private function closeConnections(int $timeout): void
    {
        if (empty($this->connections)) {
            return;
        }

        Log::info('Closing AMI connections', ['count' => count($this->connections)]);
        
        $startTime = time();
        $timeoutPerConnection = max(1, intval($timeout / count($this->connections)));

        foreach ($this->connections as $connectionId => $connection) {
            if ((time() - $startTime) >= $timeout) {
                Log::warning('Connection closure timeout reached, forcing remaining connections');
                break;
            }

            try {
                Log::debug('Closing AMI connection', ['connection_id' => $connectionId]);
                
                if ($connection && method_exists($connection, 'close')) {
                    $connection->close();
                }
                
                unset($this->connections[$connectionId]);
                
                Log::debug('AMI connection closed successfully', ['connection_id' => $connectionId]);

            } catch (\Throwable $e) {
                Log::error('Error closing AMI connection', [
                    'connection_id' => $connectionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Perform final cleanup operations
     *
     * @return void
     */
    private function performFinalCleanup(): void
    {
        Log::debug('Performing final cleanup operations');

        // Clear any remaining connections
        $this->connections = [];
        
        // Clear callbacks
        $this->shutdownCallbacks = [];
        
        // Flush any pending logs
        if (method_exists(Log::getLogger(), 'close')) {
            Log::getLogger()->close();
        }
        
        // Trigger garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * Force cleanup when graceful shutdown fails
     *
     * @return void
     */
    private function forceCleanup(): void
    {
        Log::warning('Performing forced cleanup due to shutdown errors');

        // Force close all connections
        foreach ($this->connections as $connectionId => $connection) {
            try {
                if ($connection && method_exists($connection, 'close')) {
                    $connection->close();
                }
            } catch (\Throwable $e) {
                // Ignore errors during forced cleanup
            }
        }

        $this->performFinalCleanup();
    }

    /**
     * Get human-readable signal name
     *
     * @param int $signal
     * @return string
     */
    private function getSignalName(int $signal): string
    {
        $signals = [
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT',
            SIGHUP => 'SIGHUP',
        ];

        return $signals[$signal] ?? "UNKNOWN($signal)";
    }
}