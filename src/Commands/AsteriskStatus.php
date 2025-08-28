<?php

namespace AsteriskPbxManager\Commands;

use Illuminate\Console\Command;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\QueueManagerService;
use AsteriskPbxManager\Services\ChannelManagerService;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Models\AsteriskEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AsteriskStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'asterisk:status 
                           {--detailed : Show detailed system information}
                           {--json : Output in JSON format}
                           {--refresh : Force refresh cached data}
                           {--period=today : Time period for statistics (today, week, month)}';

    /**
     * The console command description.
     */
    protected $description = 'Display Asterisk PBX system status and statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🔍 Checking Asterisk PBX Manager Status...');
            $this->newLine();

            $statusData = $this->gatherStatusData();

            if ($this->option('json')) {
                $this->line(json_encode($statusData, JSON_PRETTY_PRINT));
                return Command::SUCCESS;
            }

            $this->displayStatus($statusData);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to retrieve status: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Gather all status data.
     */
    protected function gatherStatusData(): array
    {
        $cacheKey = 'asterisk_status_' . $this->option('period');
        $refreshCache = $this->option('refresh');

        if ($refreshCache) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 300, function () {
            return [
                'connection_status' => $this->getConnectionStatus(),
                'system_info' => $this->getSystemInfo(),
                'call_statistics' => $this->getCallStatistics(),
                'event_statistics' => $this->getEventStatistics(),
                'queue_status' => $this->getQueueStatus(),
                'channel_status' => $this->getChannelStatus(),
                'health_check' => $this->performHealthCheck(),
                'timestamp' => now()->toISOString()
            ];
        });
    }

    /**
     * Get connection status.
     */
    protected function getConnectionStatus(): array
    {
        try {
            $asteriskManager = app('asterisk-manager');
            $isConnected = $asteriskManager->isConnected();

            return [
                'connected' => $isConnected,
                'host' => config('asterisk-pbx-manager.connection.host'),
                'port' => config('asterisk-pbx-manager.connection.port'),
                'username' => config('asterisk-pbx-manager.connection.username'),
                'last_check' => now()->toISOString(),
                'status' => $isConnected ? 'connected' : 'disconnected'
            ];
        } catch (\Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'status' => 'error'
            ];
        }
    }

    /**
     * Get system information.
     */
    protected function getSystemInfo(): array
    {
        try {
            $asteriskManager = app('asterisk-manager');
            
            if (!$asteriskManager->isConnected()) {
                return ['status' => 'not_connected'];
            }

            $status = $asteriskManager->getStatus();

            return [
                'asterisk_version' => $status['asterisk_version'] ?? 'Unknown',
                'uptime' => $status['system_uptime'] ?? 'Unknown',
                'reload_time' => $status['last_reload'] ?? 'Unknown',
                'channels' => $status['channels'] ?? 0,
                'calls' => $status['calls'] ?? 0,
                'load_average' => $status['load_average'] ?? 'Unknown',
                'status' => 'available'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get call statistics.
     */
    protected function getCallStatistics(): array
    {
        $period = $this->option('period');
        $dateRange = $this->getDateRange($period);

        try {
            $stats = CallLog::getStatistics($dateRange['start'], $dateRange['end']);
            
            // Add additional metrics
            $stats['answer_rate'] = $stats['total_calls'] > 0 
                ? round(($stats['answered_calls'] / $stats['total_calls']) * 100, 2) 
                : 0;
                
            $stats['average_cost_per_call'] = $stats['total_calls'] > 0 
                ? round($stats['total_cost'] / $stats['total_calls'], 2) 
                : 0;

            $stats['period'] = $period;
            $stats['date_range'] = $dateRange;

            return $stats;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'period' => $period
            ];
        }
    }

    /**
     * Get event statistics.
     */
    protected function getEventStatistics(): array
    {
        $period = $this->option('period');
        $dateRange = $this->getDateRange($period);

        try {
            $stats = AsteriskEvent::getStatistics($dateRange['start'], $dateRange['end']);
            $stats['period'] = $period;
            $stats['date_range'] = $dateRange;

            return $stats;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'period' => $period
            ];
        }
    }

    /**
     * Get queue status.
     */
    protected function getQueueStatus(): array
    {
        try {
            $queueManager = app(QueueManagerService::class);
            $queueStatus = $queueManager->getQueueSummary();

            $summary = [
                'total_queues' => count($queueStatus),
                'total_agents' => 0,
                'available_agents' => 0,
                'total_callers' => 0,
                'queues' => []
            ];

            foreach ($queueStatus as $queue) {
                $summary['total_agents'] += $queue['logged_in'] ?? 0;
                $summary['available_agents'] += $queue['available'] ?? 0;
                $summary['total_callers'] += $queue['callers'] ?? 0;
                
                $summary['queues'][$queue['name']] = [
                    'agents' => $queue['logged_in'] ?? 0,
                    'available' => $queue['available'] ?? 0,
                    'callers' => $queue['callers'] ?? 0,
                    'hold_time' => $queue['hold_time'] ?? 0
                ];
            }

            return $summary;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get channel status.
     */
    protected function getChannelStatus(): array
    {
        try {
            $channelManager = app(ChannelManagerService::class);
            $channels = $channelManager->getChannelStatus();

            $summary = [
                'total_channels' => count($channels),
                'active_calls' => 0,
                'technologies' => [],
                'states' => []
            ];

            foreach ($channels as $channel) {
                if (isset($channel['channel_state']) && in_array($channel['channel_state'], [4, 6])) {
                    $summary['active_calls']++;
                }

                // Extract technology (SIP, PJSIP, etc.)
                $technology = explode('/', $channel['channel'])[0] ?? 'Unknown';
                $summary['technologies'][$technology] = ($summary['technologies'][$technology] ?? 0) + 1;

                // Count channel states
                $state = $channel['channel_state_desc'] ?? 'Unknown';
                $summary['states'][$state] = ($summary['states'][$state] ?? 0) + 1;
            }

            return $summary;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform comprehensive health check.
     */
    protected function performHealthCheck(): array
    {
        $checks = [];

        // Connection health
        $connectionStatus = $this->getConnectionStatus();
        $checks['connection'] = [
            'status' => $connectionStatus['connected'] ? 'healthy' : 'unhealthy',
            'message' => $connectionStatus['connected'] 
                ? 'Connected to Asterisk AMI' 
                : 'Cannot connect to Asterisk AMI'
        ];

        // Database health
        try {
            CallLog::count();
            $checks['database'] = [
                'status' => 'healthy',
                'message' => 'Database connection working'
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'message' => 'Database connection failed: ' . $e->getMessage()
            ];
        }

        // Configuration health
        $requiredConfig = ['host', 'port', 'username', 'secret'];
        $configIssues = [];
        foreach ($requiredConfig as $key) {
            if (empty(config("asterisk-pbx-manager.connection.{$key}"))) {
                $configIssues[] = $key;
            }
        }

        $checks['configuration'] = [
            'status' => empty($configIssues) ? 'healthy' : 'unhealthy',
            'message' => empty($configIssues) 
                ? 'All required configuration present' 
                : 'Missing configuration: ' . implode(', ', $configIssues)
        ];

        // Event processing health
        $recentEvents = AsteriskEvent::where('created_at', '>=', now()->subHours(1))->count();
        $checks['event_processing'] = [
            'status' => $recentEvents > 0 ? 'healthy' : 'warning',
            'message' => "Processed {$recentEvents} events in the last hour"
        ];

        return $checks;
    }

    /**
     * Display formatted status information.
     */
    protected function displayStatus(array $statusData): void
    {
        $this->displayConnectionStatus($statusData['connection_status']);
        $this->newLine();

        if ($this->option('detailed')) {
            $this->displaySystemInfo($statusData['system_info']);
            $this->newLine();
        }

        $this->displayCallStatistics($statusData['call_statistics']);
        $this->newLine();

        $this->displayQueueStatus($statusData['queue_status']);
        $this->newLine();

        if ($this->option('detailed')) {
            $this->displayEventStatistics($statusData['event_statistics']);
            $this->newLine();

            $this->displayChannelStatus($statusData['channel_status']);
            $this->newLine();
        }

        $this->displayHealthCheck($statusData['health_check']);
    }

    /**
     * Display connection status.
     */
    protected function displayConnectionStatus(array $connectionStatus): void
    {
        $this->info('📡 Connection Status');
        $this->table(['Property', 'Value'], [
            ['Host', $connectionStatus['host'] ?? 'N/A'],
            ['Port', $connectionStatus['port'] ?? 'N/A'],
            ['Username', $connectionStatus['username'] ?? 'N/A'],
            ['Status', $connectionStatus['connected'] ? '<info>Connected</info>' : '<error>Disconnected</error>'],
            ['Last Check', $connectionStatus['last_check'] ?? 'N/A']
        ]);
    }

    /**
     * Display system information.
     */
    protected function displaySystemInfo(array $systemInfo): void
    {
        $this->info('🖥️  System Information');
        
        if ($systemInfo['status'] === 'error') {
            $this->error('Error: ' . $systemInfo['error']);
            return;
        }

        $this->table(['Property', 'Value'], [
            ['Asterisk Version', $systemInfo['asterisk_version'] ?? 'N/A'],
            ['Uptime', $systemInfo['uptime'] ?? 'N/A'],
            ['Last Reload', $systemInfo['reload_time'] ?? 'N/A'],
            ['Active Channels', $systemInfo['channels'] ?? 0],
            ['Active Calls', $systemInfo['calls'] ?? 0],
            ['Load Average', $systemInfo['load_average'] ?? 'N/A']
        ]);
    }

    /**
     * Display call statistics.
     */
    protected function displayCallStatistics(array $callStats): void
    {
        $period = ucfirst($callStats['period'] ?? 'unknown');
        $this->info("📞 Call Statistics ({$period})");
        
        if (isset($callStats['status']) && $callStats['status'] === 'error') {
            $this->error('Error: ' . $callStats['error']);
            return;
        }

        $this->table(['Metric', 'Value'], [
            ['Total Calls', number_format($callStats['total_calls'] ?? 0)],
            ['Answered Calls', number_format($callStats['answered_calls'] ?? 0)],
            ['Unanswered Calls', number_format($callStats['unanswered_calls'] ?? 0)],
            ['Answer Rate', ($callStats['answer_rate'] ?? 0) . '%'],
            ['Total Talk Time', $this->formatDuration($callStats['total_talk_time'] ?? 0)],
            ['Average Talk Time', $this->formatDuration($callStats['average_talk_time'] ?? 0)],
            ['Total Cost', '$' . number_format($callStats['total_cost'] ?? 0, 2)],
            ['Inbound Calls', number_format($callStats['inbound_calls'] ?? 0)],
            ['Outbound Calls', number_format($callStats['outbound_calls'] ?? 0)],
            ['Queue Calls', number_format($callStats['queue_calls'] ?? 0)]
        ]);
    }

    /**
     * Display queue status.
     */
    protected function displayQueueStatus(array $queueStatus): void
    {
        $this->info('📋 Queue Status');
        
        if (isset($queueStatus['status']) && $queueStatus['status'] === 'error') {
            $this->error('Error: ' . $queueStatus['error']);
            return;
        }

        $this->table(['Metric', 'Value'], [
            ['Total Queues', $queueStatus['total_queues'] ?? 0],
            ['Total Agents', $queueStatus['total_agents'] ?? 0],
            ['Available Agents', $queueStatus['available_agents'] ?? 0],
            ['Total Callers Waiting', $queueStatus['total_callers'] ?? 0]
        ]);

        if (!empty($queueStatus['queues']) && $this->option('detailed')) {
            $this->newLine();
            $this->info('Queue Details:');
            
            $queueData = [];
            foreach ($queueStatus['queues'] as $name => $data) {
                $queueData[] = [
                    $name,
                    $data['agents'] ?? 0,
                    $data['available'] ?? 0,
                    $data['callers'] ?? 0,
                    $this->formatDuration($data['hold_time'] ?? 0)
                ];
            }
            
            $this->table(['Queue', 'Agents', 'Available', 'Callers', 'Avg Hold'], $queueData);
        }
    }

    /**
     * Display event statistics.
     */
    protected function displayEventStatistics(array $eventStats): void
    {
        $period = ucfirst($eventStats['period'] ?? 'unknown');
        $this->info("📊 Event Statistics ({$period})");
        
        if (isset($eventStats['status']) && $eventStats['status'] === 'error') {
            $this->error('Error: ' . $eventStats['error']);
            return;
        }

        $this->table(['Metric', 'Value'], [
            ['Total Events', number_format($eventStats['total_events'] ?? 0)],
            ['Significant Events', number_format($eventStats['significant_events'] ?? 0)],
            ['Processed Events', number_format($eventStats['processed_events'] ?? 0)],
            ['Pending Events', number_format($eventStats['pending_events'] ?? 0)],
            ['Failed Events', number_format($eventStats['failed_events'] ?? 0)],
            ['Call Events', number_format($eventStats['call_events'] ?? 0)],
            ['Queue Events', number_format($eventStats['queue_events'] ?? 0)],
            ['Bridge Events', number_format($eventStats['bridge_events'] ?? 0)]
        ]);
    }

    /**
     * Display channel status.
     */
    protected function displayChannelStatus(array $channelStatus): void
    {
        $this->info('📞 Channel Status');
        
        if (isset($channelStatus['status']) && $channelStatus['status'] === 'error') {
            $this->error('Error: ' . $channelStatus['error']);
            return;
        }

        $this->table(['Metric', 'Value'], [
            ['Total Channels', $channelStatus['total_channels'] ?? 0],
            ['Active Calls', $channelStatus['active_calls'] ?? 0]
        ]);

        if (!empty($channelStatus['technologies'])) {
            $this->newLine();
            $this->info('Channel Technologies:');
            $techData = [];
            foreach ($channelStatus['technologies'] as $tech => $count) {
                $techData[] = [$tech, $count];
            }
            $this->table(['Technology', 'Count'], $techData);
        }
    }

    /**
     * Display health check results.
     */
    protected function displayHealthCheck(array $healthCheck): void
    {
        $this->info('🏥 Health Check');
        
        $healthData = [];
        foreach ($healthCheck as $check => $result) {
            $status = match($result['status']) {
                'healthy' => '<info>Healthy</info>',
                'warning' => '<comment>Warning</comment>',
                'unhealthy' => '<error>Unhealthy</error>',
                default => $result['status']
            };
            
            $healthData[] = [
                ucfirst(str_replace('_', ' ', $check)),
                $status,
                $result['message']
            ];
        }
        
        $this->table(['Check', 'Status', 'Message'], $healthData);
    }

    /**
     * Get date range based on period.
     */
    protected function getDateRange(string $period): array
    {
        $now = Carbon::now();
        
        return match($period) {
            'today' => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ],
            'week' => [
                'start' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek()
            ],
            'month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth()
            ],
            default => [
                'start' => $now->copy()->startOfDay(),
                'end' => $now->copy()->endOfDay()
            ]
        };
    }

    /**
     * Format duration in seconds to human readable format.
     */
    protected function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $secs = $seconds % 60;
            return $hours . 'h ' . $minutes . 'm ' . $secs . 's';
        }
    }
}