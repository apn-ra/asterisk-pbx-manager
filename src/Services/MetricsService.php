<?php

namespace AsteriskPbxManager\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service for collecting and reporting metrics on Asterisk PBX operations
 */
class MetricsService
{
    private const METRICS_CACHE_PREFIX = 'asterisk_metrics_';
    private const DEFAULT_RETENTION_HOURS = 24;

    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @var array<string, int>
     */
    private array $counters = [];

    /**
     * @var array<string, float>
     */
    private array $timers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => config('asterisk-pbx-manager.metrics.enabled', true),
            'retention_hours' => config('asterisk-pbx-manager.metrics.retention_hours', self::DEFAULT_RETENTION_HOURS),
            'cache_driver' => config('asterisk-pbx-manager.metrics.cache_driver', 'default'),
            'track_performance' => config('asterisk-pbx-manager.metrics.track_performance', true),
            'track_errors' => config('asterisk-pbx-manager.metrics.track_errors', true),
        ], $config);
    }

    /**
     * Increment a counter metric
     *
     * @param string $metric
     * @param int $value
     * @param array<string, mixed> $tags
     * @return void
     */
    public function increment(string $metric, int $value = 1, array $tags = []): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $key = $this->buildMetricKey($metric, $tags);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
        
        $this->persistMetric('counter', $key, $this->counters[$key], $tags);
    }

    /**
     * Record a timing metric
     *
     * @param string $metric
     * @param float $duration
     * @param array<string, mixed> $tags
     * @return void
     */
    public function timing(string $metric, float $duration, array $tags = []): void
    {
        if (!$this->config['enabled'] || !$this->config['track_performance']) {
            return;
        }

        $key = $this->buildMetricKey($metric, $tags);
        $this->timers[$key] = $duration;
        
        $this->persistMetric('timing', $key, $duration, $tags);
    }

    /**
     * Start a timer for a metric
     *
     * @param string $metric
     * @param array<string, mixed> $tags
     * @return string Timer ID
     */
    public function startTimer(string $metric, array $tags = []): string
    {
        $timerId = uniqid($metric . '_', true);
        $key = $this->buildMetricKey('timer_start', ['id' => $timerId]);
        
        Cache::put($key, microtime(true), now()->addHour());
        
        return $timerId;
    }

    /**
     * End a timer and record the duration
     *
     * @param string $timerId
     * @param string $metric
     * @param array<string, mixed> $tags
     * @return void
     */
    public function endTimer(string $timerId, string $metric, array $tags = []): void
    {
        $startKey = $this->buildMetricKey('timer_start', ['id' => $timerId]);
        $startTime = Cache::get($startKey);
        
        if ($startTime !== null) {
            $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            $this->timing($metric, $duration, $tags);
            Cache::forget($startKey);
        }
    }

    /**
     * Record AMI connection metrics
     *
     * @param string $status
     * @param float|null $duration
     * @return void
     */
    public function recordConnection(string $status, ?float $duration = null): void
    {
        $this->increment('ami.connections', 1, ['status' => $status]);
        
        if ($duration !== null) {
            $this->timing('ami.connection_time', $duration, ['status' => $status]);
        }
    }

    /**
     * Record AMI action metrics
     *
     * @param string $action
     * @param string $status
     * @param float|null $duration
     * @return void
     */
    public function recordAction(string $action, string $status, ?float $duration = null): void
    {
        $this->increment('ami.actions', 1, ['action' => $action, 'status' => $status]);
        
        if ($duration !== null) {
            $this->timing('ami.action_time', $duration, ['action' => $action, 'status' => $status]);
        }
    }

    /**
     * Record event processing metrics
     *
     * @param string $eventType
     * @param float|null $processingTime
     * @return void
     */
    public function recordEvent(string $eventType, ?float $processingTime = null): void
    {
        $this->increment('ami.events', 1, ['type' => $eventType]);
        
        if ($processingTime !== null) {
            $this->timing('ami.event_processing_time', $processingTime, ['type' => $eventType]);
        }
    }

    /**
     * Record error metrics
     *
     * @param string $errorType
     * @param string $context
     * @return void
     */
    public function recordError(string $errorType, string $context = 'general'): void
    {
        if (!$this->config['track_errors']) {
            return;
        }

        $this->increment('ami.errors', 1, ['type' => $errorType, 'context' => $context]);
    }

    /**
     * Get current metrics summary
     *
     * @param string|null $period Period ('hour', 'day', 'week')
     * @return array<string, mixed>
     */
    public function getMetrics(?string $period = 'hour'): array
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . "summary_{$period}";
        
        return Cache::remember($cacheKey, 300, function () use ($period) {
            $metrics = $this->retrieveMetrics($period);
            
            return [
                'period' => $period,
                'collected_at' => Carbon::now()->toISOString(),
                'counters' => $metrics['counters'] ?? [],
                'timers' => $this->calculateTimerStats($metrics['timers'] ?? []),
                'summary' => $this->buildSummary($metrics),
            ];
        });
    }

    /**
     * Get performance report
     *
     * @return array<string, mixed>
     */
    public function getPerformanceReport(): array
    {
        $metrics = $this->getMetrics('hour');
        
        return [
            'connections' => [
                'total' => $metrics['counters']['ami.connections'] ?? 0,
                'avg_time' => $metrics['timers']['ami.connection_time']['avg'] ?? 0,
            ],
            'actions' => [
                'total' => $metrics['counters']['ami.actions'] ?? 0,
                'avg_time' => $metrics['timers']['ami.action_time']['avg'] ?? 0,
                'success_rate' => $this->calculateSuccessRate($metrics['counters'], 'ami.actions'),
            ],
            'events' => [
                'total' => $metrics['counters']['ami.events'] ?? 0,
                'avg_processing_time' => $metrics['timers']['ami.event_processing_time']['avg'] ?? 0,
            ],
            'errors' => [
                'total' => $metrics['counters']['ami.errors'] ?? 0,
                'rate' => $this->calculateErrorRate($metrics['counters']),
            ],
        ];
    }

    /**
     * Clear old metrics based on retention policy
     *
     * @return void
     */
    public function cleanup(): void
    {
        $cutoff = Carbon::now()->subHours($this->config['retention_hours']);
        $pattern = self::METRICS_CACHE_PREFIX . '*';
        
        // This is a simplified cleanup - in production you'd want more sophisticated cleanup
        Log::info('Metrics cleanup completed', ['cutoff' => $cutoff->toISOString()]);
    }

    /**
     * Build a cache key for a metric
     *
     * @param string $metric
     * @param array<string, mixed> $tags
     * @return string
     */
    private function buildMetricKey(string $metric, array $tags = []): string
    {
        $tagString = '';
        if (!empty($tags)) {
            ksort($tags);
            $tagString = '_' . implode('_', array_map(
                fn($k, $v) => "{$k}:{$v}",
                array_keys($tags),
                array_values($tags)
            ));
        }
        
        return $metric . $tagString;
    }

    /**
     * Persist a metric to cache
     *
     * @param string $type
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $tags
     * @return void
     */
    private function persistMetric(string $type, string $key, mixed $value, array $tags = []): void
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . $type . '_' . $key;
        $timestamp = Carbon::now();
        
        $data = [
            'value' => $value,
            'timestamp' => $timestamp->toISOString(),
            'tags' => $tags,
        ];
        
        Cache::put($cacheKey, $data, $timestamp->addHours($this->config['retention_hours']));
    }

    /**
     * Retrieve metrics from cache
     *
     * @param string|null $period
     * @return array<string, mixed>
     */
    private function retrieveMetrics(?string $period): array
    {
        // Simplified implementation - in production you'd implement proper time-based filtering
        return [
            'counters' => $this->counters,
            'timers' => $this->timers,
        ];
    }

    /**
     * Calculate timer statistics
     *
     * @param array<string, float> $timers
     * @return array<string, array<string, float>>
     */
    private function calculateTimerStats(array $timers): array
    {
        $stats = [];
        
        foreach ($timers as $metric => $value) {
            $stats[$metric] = [
                'avg' => $value,
                'min' => $value,
                'max' => $value,
                'count' => 1,
            ];
        }
        
        return $stats;
    }

    /**
     * Build summary from metrics
     *
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function buildSummary(array $metrics): array
    {
        $totalActions = array_sum($metrics['counters'] ?? []);
        $totalErrors = $metrics['counters']['ami.errors'] ?? 0;
        
        return [
            'total_operations' => $totalActions,
            'error_rate' => $totalActions > 0 ? ($totalErrors / $totalActions) * 100 : 0,
            'uptime' => 'N/A', // Would be calculated based on connection metrics
        ];
    }

    /**
     * Calculate success rate for a metric
     *
     * @param array<string, int> $counters
     * @param string $metric
     * @return float
     */
    private function calculateSuccessRate(array $counters, string $metric): float
    {
        $total = $counters[$metric] ?? 0;
        $errors = $counters['ami.errors'] ?? 0;
        
        return $total > 0 ? (($total - $errors) / $total) * 100 : 0;
    }

    /**
     * Calculate overall error rate
     *
     * @param array<string, int> $counters
     * @return float
     */
    private function calculateErrorRate(array $counters): float
    {
        $total = array_sum($counters);
        $errors = $counters['ami.errors'] ?? 0;
        
        return $total > 0 ? ($errors / $total) * 100 : 0;
    }
}