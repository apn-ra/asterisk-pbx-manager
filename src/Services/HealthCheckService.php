<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\ChannelManagerService;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use AsteriskPbxManager\Exceptions\AsteriskConnectionException;
use AsteriskPbxManager\Exceptions\ActionExecutionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Health Check Service for Asterisk PBX Manager.
 * 
 * Provides comprehensive health monitoring capabilities for the Asterisk PBX Manager
 * package, including AMI connection status, database connectivity, configuration
 * validation, and system metrics collection.
 * 
 * @package AsteriskPbxManager\Services
 * @author Asterisk PBX Manager Team
 */
class HealthCheckService
{
    /**
     * Asterisk Manager Service instance.
     *
     * @var AsteriskManagerService
     */
    protected AsteriskManagerService $asteriskManager;

    /**
     * Queue Manager Service instance.
     *
     * @var QueueManagerService
     */
    protected QueueManagerService $queueManager;

    /**
     * Channel Manager Service instance.
     *
     * @var ChannelManagerService
     */
    protected ChannelManagerService $channelManager;

    /**
     * Health check cache TTL in seconds.
     *
     * @var int
     */
    protected int $cacheTtl;

    /**
     * Create a new Health Check Service instance.
     *
     * @param AsteriskManagerService $asteriskManager
     * @param QueueManagerService $queueManager
     * @param ChannelManagerService $channelManager
     */
    public function __construct(
        AsteriskManagerService $asteriskManager,
        QueueManagerService $queueManager,
        ChannelManagerService $channelManager
    ) {
        $this->asteriskManager = $asteriskManager;
        $this->queueManager = $queueManager;
        $this->channelManager = $channelManager;
        $this->cacheTtl = config('asterisk-pbx-manager.health_check.cache_ttl', 30);
    }

    /**
     * Perform comprehensive health check.
     * 
     * Returns detailed health status for all system components including
     * AMI connection, database, configuration, and system metrics.
     *
     * @param bool $useCache Whether to use cached results
     * @return array Health check results
     */
    public function performHealthCheck(bool $useCache = true): array
    {
        $cacheKey = 'asterisk_health_check';
        
        if ($useCache && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $startTime = microtime(true);
        $checks = [];

        try {
            // Connection health check
            $checks['connection'] = $this->checkConnection();

            // Database health check
            $checks['database'] = $this->checkDatabase();

            // Configuration health check
            $checks['configuration'] = $this->checkConfiguration();

            // Event processing health check
            $checks['event_processing'] = $this->checkEventProcessing();

            // System metrics check
            $checks['system_metrics'] = $this->checkSystemMetrics();

            // Queue health check
            $checks['queues'] = $this->checkQueues();

            // Overall health status
            $overallHealth = $this->calculateOverallHealth($checks);
            
            $result = [
                'healthy' => $overallHealth,
                'timestamp' => now()->toISOString(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2), // ms
                'checks' => $checks,
                'version' => config('asterisk-pbx-manager.version', '1.0.0')
            ];

            if ($useCache) {
                Cache::put($cacheKey, $result, $this->cacheTtl);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'healthy' => false,
                'timestamp' => now()->toISOString(),
                'execution_time' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => 'Health check system failure',
                'checks' => [],
                'version' => config('asterisk-pbx-manager.version', '1.0.0')
            ];
        }
    }

    /**
     * Check AMI connection health.
     *
     * @return array Connection health status
     */
    protected function checkConnection(): array
    {
        try {
            $isConnected = $this->asteriskManager->isConnected();
            
            if (!$isConnected) {
                // Attempt to connect
                $this->asteriskManager->connect();
                $isConnected = $this->asteriskManager->isConnected();
            }

            $connectionInfo = $this->asteriskManager->getConnectionInfo();

            return [
                'status' => $isConnected ? 'healthy' : 'unhealthy',
                'connected' => $isConnected,
                'host' => $connectionInfo['host'] ?? config('asterisk-pbx-manager.connection.host'),
                'port' => $connectionInfo['port'] ?? config('asterisk-pbx-manager.connection.port'),
                'username' => config('asterisk-pbx-manager.connection.username'),
                'last_check' => now()->toISOString(),
                'message' => $isConnected 
                    ? 'AMI connection is healthy'
                    : 'AMI connection is not available'
            ];

        } catch (AsteriskConnectionException $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'host' => config('asterisk-pbx-manager.connection.host'),
                'port' => config('asterisk-pbx-manager.connection.port'),
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorReference(),
                'message' => 'AMI connection failed: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage(),
                'message' => 'AMI connection check failed'
            ];
        }
    }

    /**
     * Check database connectivity and health.
     *
     * @return array Database health status
     */
    protected function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test basic connectivity
            $callLogCount = CallLog::count();
            $eventCount = AsteriskEvent::count();
            
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'connected' => true,
                'query_time' => $queryTime,
                'records' => [
                    'call_logs' => $callLogCount,
                    'events' => $eventCount
                ],
                'message' => 'Database connection is healthy'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'connected' => false,
                'error' => $e->getMessage(),
                'message' => 'Database connection failed'
            ];
        }
    }

    /**
     * Check configuration health and completeness.
     *
     * @return array Configuration health status
     */
    protected function checkConfiguration(): array
    {
        $requiredConfig = [
            'connection.host',
            'connection.port', 
            'connection.username',
            'connection.secret'
        ];

        $missingConfig = [];
        $configValues = [];

        foreach ($requiredConfig as $key) {
            $value = config("asterisk-pbx-manager.{$key}");
            if (empty($value)) {
                $missingConfig[] = $key;
            } else {
                // Mask sensitive values
                $configValues[$key] = $key === 'connection.secret' ? '[HIDDEN]' : $value;
            }
        }

        // Check optional but recommended configuration
        $optionalConfig = [
            'events.enabled',
            'logging.enabled',
            'reconnection.enabled'
        ];

        $warnings = [];
        foreach ($optionalConfig as $key) {
            if (config("asterisk-pbx-manager.{$key}") === null) {
                $warnings[] = "Optional config '{$key}' not set";
            }
        }

        $isHealthy = empty($missingConfig);

        return [
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'complete' => $isHealthy,
            'missing_config' => $missingConfig,
            'config_values' => $configValues,
            'warnings' => $warnings,
            'message' => $isHealthy 
                ? 'All required configuration is present'
                : 'Missing required configuration: ' . implode(', ', $missingConfig)
        ];
    }

    /**
     * Check event processing health.
     *
     * @return array Event processing health status
     */
    protected function checkEventProcessing(): array
    {
        try {
            $now = now();
            
            // Check recent events (last hour)
            $recentEvents = AsteriskEvent::where('created_at', '>=', $now->subHour())->count();
            
            // Check event processing rate (last 24 hours)
            $dailyEvents = AsteriskEvent::where('created_at', '>=', $now->subDay())->count();
            
            // Check if events are being processed regularly
            $lastEvent = AsteriskEvent::latest()->first();
            $lastEventAge = $lastEvent ? $now->diffInMinutes($lastEvent->created_at) : null;

            $status = 'healthy';
            $message = 'Event processing is healthy';

            if ($recentEvents === 0 && $lastEventAge && $lastEventAge > 60) {
                $status = 'warning';
                $message = 'No events processed in the last hour';
            }

            return [
                'status' => $status,
                'recent_events_1h' => $recentEvents,
                'daily_events_24h' => $dailyEvents,
                'last_event_age_minutes' => $lastEventAge,
                'events_enabled' => config('asterisk-pbx-manager.events.enabled', false),
                'message' => $message
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Event processing check failed'
            ];
        }
    }

    /**
     * Check system metrics and performance.
     *
     * @return array System metrics health status
     */
    protected function checkSystemMetrics(): array
    {
        try {
            $metrics = [];
            
            // Memory usage
            $memoryUsage = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            
            $metrics['memory'] = [
                'current_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_mb' => round($peakMemory / 1024 / 1024, 2),
                'limit_mb' => $this->getMemoryLimit()
            ];

            // Active channels (if connection is available)
            try {
                if ($this->asteriskManager->isConnected()) {
                    $channels = $this->channelManager->getChannelStatus();
                    $metrics['channels'] = [
                        'total' => count($channels),
                        'active_calls' => $this->countActiveCalls($channels)
                    ];
                }
            } catch (\Exception $e) {
                $metrics['channels'] = ['error' => 'Could not retrieve channel information'];
            }

            // Cache status
            $metrics['cache'] = [
                'enabled' => Cache::getDefaultDriver() !== 'array',
                'driver' => Cache::getDefaultDriver()
            ];

            return [
                'status' => 'healthy',
                'metrics' => $metrics,
                'message' => 'System metrics collected successfully'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
                'message' => 'System metrics check encountered issues'
            ];
        }
    }

    /**
     * Check queue system health.
     *
     * @return array Queue health status
     */
    protected function checkQueues(): array
    {
        try {
            if (!$this->asteriskManager->isConnected()) {
                return [
                    'status' => 'warning',
                    'message' => 'Cannot check queues - AMI not connected'
                ];
            }

            $queueSummary = $this->queueManager->getQueueSummary();
            
            return [
                'status' => 'healthy',
                'total_queues' => count($queueSummary),
                'summary' => $queueSummary,
                'message' => 'Queue system is healthy'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
                'message' => 'Queue health check failed'
            ];
        }
    }

    /**
     * Calculate overall health status based on individual checks.
     *
     * @param array $checks Individual health check results
     * @return bool Overall health status
     */
    protected function calculateOverallHealth(array $checks): bool
    {
        $criticalChecks = ['connection', 'database', 'configuration'];
        
        foreach ($criticalChecks as $checkName) {
            if (isset($checks[$checkName]) && $checks[$checkName]['status'] === 'unhealthy') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get simple health status (for lightweight endpoints).
     *
     * @return array Simple health status
     */
    public function getSimpleHealth(): array
    {
        $cacheKey = 'asterisk_simple_health';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $isConnected = $this->asteriskManager->isConnected();
            $canQueryDb = true;
            
            try {
                CallLog::count();
            } catch (\Exception $e) {
                $canQueryDb = false;
            }

            $healthy = $isConnected && $canQueryDb;
            
            $result = [
                'healthy' => $healthy,
                'status' => $healthy ? 'ok' : 'error',
                'timestamp' => now()->toISOString()
            ];

            Cache::put($cacheKey, $result, 10); // 10 second cache for simple checks
            
            return $result;

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'status' => 'error',
                'timestamp' => now()->toISOString(),
                'error' => 'Health check failed'
            ];
        }
    }

    /**
     * Get memory limit in MB.
     *
     * @return float|null Memory limit in MB
     */
    protected function getMemoryLimit(): ?float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return null; // No limit
        }
        
        $limit = strtolower($limit);
        $bytes = (int) $limit;
        
        if (strpos($limit, 'k') !== false) {
            $bytes *= 1024;
        } elseif (strpos($limit, 'm') !== false) {
            $bytes *= 1024 * 1024;
        } elseif (strpos($limit, 'g') !== false) {
            $bytes *= 1024 * 1024 * 1024;
        }
        
        return round($bytes / 1024 / 1024, 2);
    }

    /**
     * Count active calls from channel data.
     *
     * @param array $channels Channel data
     * @return int Number of active calls
     */
    protected function countActiveCalls(array $channels): int
    {
        $activeCalls = 0;
        
        foreach ($channels as $channel) {
            if (isset($channel['channel_state']) && in_array($channel['channel_state'], [4, 6])) {
                $activeCalls++;
            }
        }
        
        return $activeCalls;
    }

    /**
     * Clear health check cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget('asterisk_health_check');
        Cache::forget('asterisk_simple_health');
    }
}