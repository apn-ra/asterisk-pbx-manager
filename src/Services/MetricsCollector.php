<?php

namespace AsteriskPbxManager\Services;

use AsteriskPbxManager\Models\AsteriskEvent;
use AsteriskPbxManager\Models\CallLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Metrics Collector Service for Asterisk PBX Manager.
 *
 * Centralized metrics collection and reporting system that gathers
 * performance indicators, operational metrics, and system statistics
 * from all components of the Asterisk PBX Manager package.
 *
 * @author Asterisk PBX Manager Team
 */
class MetricsCollector
{
    /**
     * Metric types constants.
     */
    public const METRIC_TYPE_COUNTER = 'counter';
    public const METRIC_TYPE_GAUGE = 'gauge';
    public const METRIC_TYPE_HISTOGRAM = 'histogram';
    public const METRIC_TYPE_SUMMARY = 'summary';

    /**
     * Metrics configuration.
     *
     * @var array
     */
    protected array $config;

    /**
     * In-memory metrics storage.
     *
     * @var array
     */
    protected array $metrics = [
        'counters'   => [],
        'gauges'     => [],
        'histograms' => [],
        'summaries'  => [],
    ];

    /**
     * Metrics metadata.
     *
     * @var array
     */
    protected array $metadata = [];

    /**
     * Connection Pool Manager instance.
     *
     * @var ConnectionPoolManager|null
     */
    protected ?ConnectionPoolManager $poolManager;

    /**
     * Health Check Service instance.
     *
     * @var HealthCheckService|null
     */
    protected ?HealthCheckService $healthService;

    /**
     * Metrics collection start time.
     *
     * @var Carbon
     */
    protected Carbon $startTime;

    /**
     * Create a new Metrics Collector instance.
     *
     * @param ConnectionPoolManager|null $poolManager
     * @param HealthCheckService|null    $healthService
     */
    public function __construct(
        ?ConnectionPoolManager $poolManager = null,
        ?HealthCheckService $healthService = null
    ) {
        $this->poolManager = $poolManager;
        $this->healthService = $healthService;
        $this->config = config('asterisk-pbx-manager.metrics', []);
        $this->startTime = now();

        $this->initializeMetrics();
    }

    /**
     * Initialize default metrics.
     *
     * @return void
     */
    protected function initializeMetrics(): void
    {
        // AMI Operation Counters
        $this->defineCounter('ami_actions_total', 'Total number of AMI actions executed', ['action_type', 'status']);
        $this->defineCounter('ami_connections_total', 'Total number of AMI connections made', ['status']);
        $this->defineCounter('ami_disconnections_total', 'Total number of AMI disconnections', ['reason']);

        // Event Processing Counters
        $this->defineCounter('events_processed_total', 'Total number of events processed', ['event_type']);
        $this->defineCounter('events_broadcast_total', 'Total number of events broadcast', ['channel']);
        $this->defineCounter('events_logged_total', 'Total number of events logged to database');

        // Call Metrics Counters
        $this->defineCounter('calls_originated_total', 'Total number of calls originated');
        $this->defineCounter('calls_answered_total', 'Total number of calls answered');
        $this->defineCounter('calls_hangup_total', 'Total number of calls hung up', ['cause']);

        // Queue Operations Counters
        $this->defineCounter('queue_operations_total', 'Total number of queue operations', ['operation', 'queue']);
        $this->defineCounter('queue_member_operations_total', 'Total queue member operations', ['operation']);

        // System Gauges
        $this->defineGauge('ami_connections_active', 'Number of active AMI connections');
        $this->defineGauge('connection_pool_size', 'Current connection pool size');
        $this->defineGauge('connection_pool_active', 'Active connections in pool');
        $this->defineGauge('connection_pool_idle', 'Idle connections in pool');
        $this->defineGauge('system_memory_usage_mb', 'Current memory usage in MB');
        $this->defineGauge('active_calls_count', 'Number of active calls');
        $this->defineGauge('queue_calls_waiting', 'Number of calls waiting in queues');

        // Performance Histograms
        $this->defineHistogram('ami_action_duration_seconds', 'AMI action execution time', [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]);
        $this->defineHistogram('connection_acquire_duration_seconds', 'Connection acquisition time', [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1]);
        $this->defineHistogram('event_processing_duration_seconds', 'Event processing time', [0.001, 0.005, 0.01, 0.025, 0.05, 0.1]);
        $this->defineHistogram('database_query_duration_seconds', 'Database query execution time', [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5]);

        // Health Check Gauges
        $this->defineGauge('health_check_status', 'Overall health check status (1=healthy, 0=unhealthy)');
        $this->defineGauge('health_checks_execution_time_ms', 'Health checks execution time in milliseconds');
    }

    /**
     * Define a counter metric.
     *
     * @param string $name
     * @param string $description
     * @param array  $labels
     *
     * @return void
     */
    public function defineCounter(string $name, string $description, array $labels = []): void
    {
        $this->metadata[$name] = [
            'type'        => self::METRIC_TYPE_COUNTER,
            'description' => $description,
            'labels'      => $labels,
        ];

        if (!isset($this->metrics['counters'][$name])) {
            $this->metrics['counters'][$name] = 0;
        }
    }

    /**
     * Define a gauge metric.
     *
     * @param string $name
     * @param string $description
     * @param array  $labels
     *
     * @return void
     */
    public function defineGauge(string $name, string $description, array $labels = []): void
    {
        $this->metadata[$name] = [
            'type'        => self::METRIC_TYPE_GAUGE,
            'description' => $description,
            'labels'      => $labels,
        ];

        if (!isset($this->metrics['gauges'][$name])) {
            $this->metrics['gauges'][$name] = 0;
        }
    }

    /**
     * Define a histogram metric.
     *
     * @param string $name
     * @param string $description
     * @param array  $buckets
     *
     * @return void
     */
    public function defineHistogram(string $name, string $description, array $buckets): void
    {
        $this->metadata[$name] = [
            'type'        => self::METRIC_TYPE_HISTOGRAM,
            'description' => $description,
            'buckets'     => $buckets,
        ];

        if (!isset($this->metrics['histograms'][$name])) {
            $this->metrics['histograms'][$name] = [
                'count'   => 0,
                'sum'     => 0,
                'buckets' => array_fill_keys(array_map('strval', $buckets), 0),
            ];
        }
    }

    /**
     * Increment a counter metric.
     *
     * @param string $name
     * @param int    $value
     * @param array  $labels
     *
     * @return void
     */
    public function incrementCounter(string $name, int $value = 1, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);

        if (!isset($this->metrics['counters'][$key])) {
            $this->metrics['counters'][$key] = 0;
        }

        $this->metrics['counters'][$key] += $value;

        if ($this->config['log_metrics'] ?? false) {
            Log::debug("Counter incremented: {$key} += {$value}");
        }
    }

    /**
     * Set a gauge metric value.
     *
     * @param string $name
     * @param float  $value
     * @param array  $labels
     *
     * @return void
     */
    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);
        $this->metrics['gauges'][$key] = $value;

        if ($this->config['log_metrics'] ?? false) {
            Log::debug("Gauge set: {$key} = {$value}");
        }
    }

    /**
     * Observe a value for a histogram metric.
     *
     * @param string $name
     * @param float  $value
     * @param array  $labels
     *
     * @return void
     */
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->buildMetricKey($name, $labels);

        if (!isset($this->metrics['histograms'][$key])) {
            $buckets = $this->metadata[$name]['buckets'] ?? [];
            $this->metrics['histograms'][$key] = [
                'count'   => 0,
                'sum'     => 0,
                'buckets' => array_fill_keys(array_map('strval', $buckets), 0),
            ];
        }

        $this->metrics['histograms'][$key]['count']++;
        $this->metrics['histograms'][$key]['sum'] += $value;

        // Update buckets
        if (isset($this->metadata[$name]['buckets'])) {
            foreach ($this->metadata[$name]['buckets'] as $bucket) {
                if ($value <= $bucket) {
                    $this->metrics['histograms'][$key]['buckets'][(string) $bucket]++;
                }
            }
        }

        if ($this->config['log_metrics'] ?? false) {
            Log::debug("Histogram observed: {$key} = {$value}");
        }
    }

    /**
     * Collect all current metrics from various sources.
     *
     * @return array
     */
    public function collectMetrics(): array
    {
        $collectedMetrics = [
            'timestamp'      => now()->toISOString(),
            'uptime_seconds' => now()->diffInSeconds($this->startTime),
            'counters'       => $this->metrics['counters'],
            'gauges'         => $this->metrics['gauges'],
            'histograms'     => $this->metrics['histograms'],
            'summaries'      => $this->metrics['summaries'],
        ];

        // Collect connection pool metrics
        if ($this->poolManager && $this->poolManager->isEnabled()) {
            $poolStats = $this->poolManager->getStats();
            $this->updatePoolMetrics($poolStats);
        }

        // Collect health check metrics
        if ($this->healthService) {
            try {
                $healthData = $this->healthService->getSimpleHealth();
                $this->updateHealthMetrics($healthData);
            } catch (\Exception $e) {
                Log::warning('Failed to collect health metrics', ['error' => $e->getMessage()]);
            }
        }

        // Collect system metrics
        $this->updateSystemMetrics();

        // Collect database metrics
        $this->updateDatabaseMetrics();

        return $collectedMetrics;
    }

    /**
     * Update connection pool metrics.
     *
     * @param array $poolStats
     *
     * @return void
     */
    protected function updatePoolMetrics(array $poolStats): void
    {
        $this->setGauge('connection_pool_size', $poolStats['pool_size'] ?? 0);
        $this->setGauge('connection_pool_active', $poolStats['in_use_connections'] ?? 0);
        $this->setGauge('connection_pool_idle', $poolStats['available_connections'] ?? 0);

        // Update counters from pool stats
        $this->setGauge('ami_connections_active', $poolStats['current_active'] ?? 0);
    }

    /**
     * Update health check metrics.
     *
     * @param array $healthData
     *
     * @return void
     */
    protected function updateHealthMetrics(array $healthData): void
    {
        $this->setGauge('health_check_status', $healthData['healthy'] ? 1 : 0);

        // If we have execution time from detailed health check
        if (isset($healthData['execution_time'])) {
            $this->setGauge('health_checks_execution_time_ms', $healthData['execution_time']);
        }
    }

    /**
     * Update system metrics.
     *
     * @return void
     */
    protected function updateSystemMetrics(): void
    {
        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $this->setGauge('system_memory_usage_mb', round($memoryUsage / 1024 / 1024, 2));

        // Peak memory usage
        $peakMemory = memory_get_peak_usage(true);
        $this->setGauge('system_peak_memory_usage_mb', round($peakMemory / 1024 / 1024, 2));
    }

    /**
     * Update database metrics.
     *
     * @return void
     */
    protected function updateDatabaseMetrics(): void
    {
        try {
            // Call logs count
            $callLogsCount = CallLog::count();
            $this->setGauge('database_call_logs_total', $callLogsCount);

            // Events count
            $eventsCount = AsteriskEvent::count();
            $this->setGauge('database_events_total', $eventsCount);

            // Recent activity (last hour)
            $recentCalls = CallLog::where('created_at', '>=', now()->subHour())->count();
            $this->setGauge('database_recent_calls_1h', $recentCalls);

            $recentEvents = AsteriskEvent::where('created_at', '>=', now()->subHour())->count();
            $this->setGauge('database_recent_events_1h', $recentEvents);
        } catch (\Exception $e) {
            Log::warning('Failed to collect database metrics', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Export metrics in Prometheus format.
     *
     * @return string
     */
    public function exportPrometheus(): string
    {
        $metrics = $this->collectMetrics();
        $output = [];

        // Add metadata comments
        foreach ($this->metadata as $name => $meta) {
            $output[] = "# HELP {$name} {$meta['description']}";
            $output[] = "# TYPE {$name} {$meta['type']}";
        }

        // Export counters
        foreach ($metrics['counters'] as $name => $value) {
            $output[] = "{$name} {$value}";
        }

        // Export gauges
        foreach ($metrics['gauges'] as $name => $value) {
            $output[] = "{$name} {$value}";
        }

        // Export histograms
        foreach ($metrics['histograms'] as $name => $data) {
            foreach ($data['buckets'] as $bucket => $count) {
                $output[] = "{$name}_bucket{le=\"{$bucket}\"} {$count}";
            }
            $output[] = "{$name}_count {$data['count']}";
            $output[] = "{$name}_sum {$data['sum']}";
        }

        return implode("\n", $output);
    }

    /**
     * Export metrics in JSON format.
     *
     * @return array
     */
    public function exportJson(): array
    {
        return [
            'metadata'         => $this->metadata,
            'metrics'          => $this->collectMetrics(),
            'export_time'      => now()->toISOString(),
            'collector_uptime' => now()->diffInSeconds($this->startTime),
        ];
    }

    /**
     * Get metrics summary report.
     *
     * @return array
     */
    public function getSummaryReport(): array
    {
        $metrics = $this->collectMetrics();

        $summary = [
            'system' => [
                'uptime_seconds'  => $metrics['uptime_seconds'],
                'memory_usage_mb' => $metrics['gauges']['system_memory_usage_mb'] ?? 0,
                'health_status'   => ($metrics['gauges']['health_check_status'] ?? 0) ? 'healthy' : 'unhealthy',
            ],
            'connections' => [
                'pool_size'          => $metrics['gauges']['connection_pool_size'] ?? 0,
                'active_connections' => $metrics['gauges']['connection_pool_active'] ?? 0,
                'idle_connections'   => $metrics['gauges']['connection_pool_idle'] ?? 0,
            ],
            'operations' => [
                'total_ami_actions'      => array_sum($this->getCountersByPrefix('ami_actions_total')),
                'total_events_processed' => array_sum($this->getCountersByPrefix('events_processed_total')),
                'total_calls_originated' => $metrics['counters']['calls_originated_total'] ?? 0,
            ],
            'database' => [
                'total_call_logs'  => $metrics['gauges']['database_call_logs_total'] ?? 0,
                'total_events'     => $metrics['gauges']['database_events_total'] ?? 0,
                'recent_calls_1h'  => $metrics['gauges']['database_recent_calls_1h'] ?? 0,
                'recent_events_1h' => $metrics['gauges']['database_recent_events_1h'] ?? 0,
            ],
        ];

        return $summary;
    }

    /**
     * Record AMI action execution metrics.
     *
     * @param string $action
     * @param bool   $success
     * @param float  $duration
     *
     * @return void
     */
    public function recordAmiAction(string $action, bool $success, float $duration): void
    {
        $status = $success ? 'success' : 'failure';
        $this->incrementCounter('ami_actions_total', 1, ['action_type' => $action, 'status' => $status]);
        $this->observeHistogram('ami_action_duration_seconds', $duration, ['action_type' => $action]);
    }

    /**
     * Record event processing metrics.
     *
     * @param string $eventType
     * @param float  $duration
     *
     * @return void
     */
    public function recordEventProcessing(string $eventType, float $duration): void
    {
        $this->incrementCounter('events_processed_total', 1, ['event_type' => $eventType]);
        $this->observeHistogram('event_processing_duration_seconds', $duration, ['event_type' => $eventType]);
    }

    /**
     * Record connection pool acquisition metrics.
     *
     * @param bool  $fromPool
     * @param float $duration
     *
     * @return void
     */
    public function recordConnectionAcquisition(bool $fromPool, float $duration): void
    {
        $source = $fromPool ? 'pool' : 'new';
        $this->observeHistogram('connection_acquire_duration_seconds', $duration, ['source' => $source]);
    }

    /**
     * Build metric key with labels.
     *
     * @param string $name
     * @param array  $labels
     *
     * @return string
     */
    protected function buildMetricKey(string $name, array $labels): string
    {
        if (empty($labels)) {
            return $name;
        }

        $labelString = implode(',', array_map(
            fn ($k, $v) => "{$k}=\"{$v}\"",
            array_keys($labels),
            array_values($labels)
        ));

        return "{$name}{{$labelString}}";
    }

    /**
     * Get counters by prefix.
     *
     * @param string $prefix
     *
     * @return array
     */
    protected function getCountersByPrefix(string $prefix): array
    {
        return array_filter(
            $this->metrics['counters'],
            fn ($key) => strpos($key, $prefix) === 0,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Reset all metrics.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->metrics = [
            'counters'   => [],
            'gauges'     => [],
            'histograms' => [],
            'summaries'  => [],
        ];

        $this->initializeMetrics();
        $this->startTime = now();
    }

    /**
     * Get metrics configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get all metric metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if metrics collection is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }
}
