<?php

namespace AsteriskPbxManager\Commands;

use Illuminate\Console\Command;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use AsteriskPbxManager\Models\AsteriskEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonitorEvents extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'asterisk:monitor 
                           {--filter= : Filter events by type (call, queue, bridge, etc.)}
                           {--channel= : Monitor events for specific channel}
                           {--queue= : Monitor events for specific queue}
                           {--significant : Show only significant events}
                           {--raw : Show raw event data}
                           {--limit=50 : Maximum number of events to display}
                           {--follow : Follow new events in real-time}
                           {--save : Save events to database}
                           {--export= : Export events to file (json, csv)}';

    /**
     * The console command description.
     */
    protected $description = 'Monitor Asterisk events in real-time';

    /**
     * Event processor instance.
     */
    protected EventProcessor $eventProcessor;

    /**
     * Asterisk manager service instance.
     */
    protected AsteriskManagerService $asteriskManager;

    /**
     * Flag to control monitoring loop.
     */
    protected bool $keepMonitoring = true;

    /**
     * Event counter.
     */
    protected int $eventCount = 0;

    /**
     * Start time for monitoring session.
     */
    protected Carbon $startTime;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->startTime = Carbon::now();
        $this->eventProcessor = app(EventProcessor::class);
        $this->asteriskManager = app('asterisk-manager');

        try {
            $this->info('🔊 Starting Asterisk Event Monitor...');
            $this->displayConfiguration();
            
            if (!$this->asteriskManager->isConnected()) {
                $this->error('❌ Not connected to Asterisk AMI. Please check your configuration.');
                return Command::FAILURE;
            }

            $this->info('✅ Connected to Asterisk AMI');
            $this->newLine();

            if ($this->option('follow')) {
                return $this->followEvents();
            } else {
                return $this->showRecentEvents();
            }

        } catch (\Exception $e) {
            $this->error('❌ Error during event monitoring: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Display monitoring configuration.
     */
    protected function displayConfiguration(): void
    {
        $config = [];
        
        if ($filter = $this->option('filter')) {
            $config[] = ['Filter', $filter];
        }
        
        if ($channel = $this->option('channel')) {
            $config[] = ['Channel', $channel];
        }
        
        if ($queue = $this->option('queue')) {
            $config[] = ['Queue', $queue];
        }
        
        if ($this->option('significant')) {
            $config[] = ['Significant Only', 'Yes'];
        }
        
        if ($this->option('raw')) {
            $config[] = ['Raw Data', 'Yes'];
        }
        
        $config[] = ['Limit', $this->option('limit')];
        $config[] = ['Follow Mode', $this->option('follow') ? 'Yes' : 'No'];
        $config[] = ['Save to DB', $this->option('save') ? 'Yes' : 'No'];
        
        if ($export = $this->option('export')) {
            $config[] = ['Export Format', strtoupper($export)];
        }

        if (!empty($config)) {
            $this->table(['Setting', 'Value'], $config);
            $this->newLine();
        }
    }

    /**
     * Follow events in real-time.
     */
    protected function followEvents(): int
    {
        $this->info('📡 Monitoring events in real-time (Press Ctrl+C to stop)...');
        $this->newLine();

        // Set up signal handling for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        }

        // Register event listener
        $this->asteriskManager->registerEventListener([$this, 'handleEvent']);

        // Keep the command running
        while ($this->keepMonitoring) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            
            usleep(100000); // Sleep for 100ms
        }

        $this->displaySessionSummary();
        
        return Command::SUCCESS;
    }

    /**
     * Show recent events from database.
     */
    protected function showRecentEvents(): int
    {
        $this->info('📊 Retrieving recent events...');
        
        $query = AsteriskEvent::orderBy('event_timestamp', 'desc')
                             ->limit($this->option('limit'));

        // Apply filters
        $query = $this->applyFilters($query);

        $events = $query->get();

        if ($events->isEmpty()) {
            $this->warn('No events found matching the criteria.');
            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} events:");
        $this->newLine();

        if ($export = $this->option('export')) {
            return $this->exportEvents($events, $export);
        }

        $this->displayEvents($events);

        return Command::SUCCESS;
    }

    /**
     * Handle incoming events.
     */
    public function handleEvent($event): void
    {
        try {
            $this->eventCount++;

            // Process the event
            $processedEvent = $this->processEvent($event);
            
            if (!$this->shouldDisplayEvent($processedEvent)) {
                return;
            }

            // Display the event
            $this->displaySingleEvent($processedEvent);

            // Save to database if requested
            if ($this->option('save')) {
                $this->saveEvent($processedEvent);
            }

        } catch (\Exception $e) {
            $this->error("Error processing event: " . $e->getMessage());
        }
    }

    /**
     * Process raw event data.
     */
    protected function processEvent($event): array
    {
        return [
            'event_name' => $event->getName() ?? 'Unknown',
            'timestamp' => Carbon::now(),
            'channel' => $event->getKey('Channel'),
            'unique_id' => $event->getKey('Uniqueid'),
            'caller_id' => $event->getKey('CallerIDNum'),
            'extension' => $event->getKey('Extension'),
            'context' => $event->getKey('Context'),
            'queue' => $event->getKey('Queue'),
            'bridge_id' => $event->getKey('BridgeId'),
            'status' => $event->getKey('Status'),
            'cause' => $event->getKey('Cause'),
            'raw_data' => $this->option('raw') ? $event->getKeys() : null
        ];
    }

    /**
     * Check if event should be displayed based on filters.
     */
    protected function shouldDisplayEvent(array $event): bool
    {
        // Filter by event type
        if ($filter = $this->option('filter')) {
            $eventCategory = $this->getEventCategory($event['event_name']);
            if (strtolower($eventCategory) !== strtolower($filter)) {
                return false;
            }
        }

        // Filter by channel
        if ($channel = $this->option('channel')) {
            if (!$event['channel'] || strpos($event['channel'], $channel) === false) {
                return false;
            }
        }

        // Filter by queue
        if ($queue = $this->option('queue')) {
            if (!$event['queue'] || $event['queue'] !== $queue) {
                return false;
            }
        }

        // Filter significant events only
        if ($this->option('significant')) {
            $significantEvents = [
                'DialBegin', 'DialEnd', 'Hangup', 'Bridge', 'Unbridge',
                'QueueCallerJoin', 'QueueCallerLeave', 'AgentConnect', 'AgentComplete'
            ];
            
            if (!in_array($event['event_name'], $significantEvents)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display a single event.
     */
    protected function displaySingleEvent(array $event): void
    {
        $timestamp = $event['timestamp']->format('H:i:s');
        $eventName = str_pad($event['event_name'], 20);
        $channel = $event['channel'] ? str_pad(substr($event['channel'], 0, 20), 22) : str_pad('N/A', 22);
        
        $line = "<comment>[{$timestamp}]</comment> <info>{$eventName}</info> {$channel}";
        
        if ($event['caller_id']) {
            $line .= " <comment>from:</comment> {$event['caller_id']}";
        }
        
        if ($event['extension']) {
            $line .= " <comment>to:</comment> {$event['extension']}";
        }
        
        if ($event['queue']) {
            $line .= " <comment>queue:</comment> {$event['queue']}";
        }
        
        if ($event['status']) {
            $line .= " <comment>status:</comment> {$event['status']}";
        }

        $this->line($line);

        // Show raw data if requested
        if ($this->option('raw') && $event['raw_data']) {
            $this->line('  <comment>Raw Data:</comment> ' . json_encode($event['raw_data'], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Display multiple events in table format.
     */
    protected function displayEvents($events): void
    {
        $tableData = [];
        
        foreach ($events as $event) {
            $tableData[] = [
                $event->event_timestamp->format('H:i:s'),
                $event->event_name,
                $event->channel ? substr($event->channel, 0, 20) : 'N/A',
                $event->caller_id_num ?? 'N/A',
                $event->extension ?? 'N/A',
                $event->queue ?? 'N/A',
                $this->getEventCategory($event->event_name)
            ];
        }

        $this->table([
            'Time',
            'Event',
            'Channel',
            'Caller ID',
            'Extension',
            'Queue',
            'Category'
        ], $tableData);
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters($query)
    {
        if ($filter = $this->option('filter')) {
            $eventsByCategory = $this->getEventsByCategory($filter);
            if (!empty($eventsByCategory)) {
                $query->whereIn('event_name', $eventsByCategory);
            }
        }

        if ($channel = $this->option('channel')) {
            $query->where(function ($q) use ($channel) {
                $q->where('channel', 'like', "%{$channel}%")
                  ->orWhere('dest_channel', 'like', "%{$channel}%");
            });
        }

        if ($queue = $this->option('queue')) {
            $query->where('queue', $queue);
        }

        if ($this->option('significant')) {
            $query->where('is_significant', true);
        }

        return $query;
    }

    /**
     * Export events to file.
     */
    protected function exportEvents($events, string $format): int
    {
        $filename = 'asterisk_events_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filepath = storage_path('app/' . $filename);

        try {
            switch (strtolower($format)) {
                case 'json':
                    file_put_contents($filepath, $events->toJson(JSON_PRETTY_PRINT));
                    break;

                case 'csv':
                    $handle = fopen($filepath, 'w');
                    
                    // CSV headers
                    fputcsv($handle, [
                        'Timestamp', 'Event Name', 'Channel', 'Caller ID', 
                        'Extension', 'Queue', 'Status', 'Category'
                    ]);
                    
                    // CSV data
                    foreach ($events as $event) {
                        fputcsv($handle, [
                            $event->event_timestamp,
                            $event->event_name,
                            $event->channel,
                            $event->caller_id_num,
                            $event->extension,
                            $event->queue,
                            $event->status,
                            $this->getEventCategory($event->event_name)
                        ]);
                    }
                    
                    fclose($handle);
                    break;

                default:
                    $this->error("Unsupported export format: {$format}");
                    return Command::FAILURE;
            }

            $this->info("✅ Events exported to: {$filepath}");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to export events: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Save event to database.
     */
    protected function saveEvent(array $eventData): void
    {
        try {
            AsteriskEvent::create([
                'event_name' => $eventData['event_name'],
                'event_timestamp' => $eventData['timestamp'],
                'channel' => $eventData['channel'],
                'unique_id' => $eventData['unique_id'],
                'caller_id_num' => $eventData['caller_id'],
                'extension' => $eventData['extension'],
                'context' => $eventData['context'],
                'queue' => $eventData['queue'],
                'bridge_id' => $eventData['bridge_id'],
                'status' => $eventData['status'],
                'cause' => $eventData['cause'],
                'event_data' => $eventData['raw_data'],
                'received_at' => now(),
                'processing_status' => 'pending'
            ]);
        } catch (\Exception $e) {
            $this->error("Failed to save event: " . $e->getMessage());
        }
    }

    /**
     * Get event category based on event name.
     */
    protected function getEventCategory(string $eventName): string
    {
        return match($eventName) {
            'DialBegin', 'DialEnd', 'Hangup', 'NewCallerid', 'NewConnectedLine' => 'call',
            'BridgeCreate', 'BridgeDestroy', 'BridgeEnter', 'BridgeLeave' => 'bridge',
            'QueueMemberAdded', 'QueueMemberRemoved', 'QueueMemberPause', 'QueueCallerJoin', 'QueueCallerLeave' => 'queue',
            'AgentConnect', 'AgentComplete', 'AgentLogin', 'AgentLogoff' => 'agent',
            'VarSet', 'UserEvent' => 'variable',
            'MonitorStart', 'MonitorStop' => 'monitoring',
            'DTMFBegin', 'DTMFEnd' => 'dtmf',
            'NewExten', 'NewState' => 'state',
            default => 'other'
        };
    }

    /**
     * Get events by category.
     */
    protected function getEventsByCategory(string $category): array
    {
        return match(strtolower($category)) {
            'call' => ['DialBegin', 'DialEnd', 'Hangup', 'NewCallerid', 'NewConnectedLine'],
            'bridge' => ['BridgeCreate', 'BridgeDestroy', 'BridgeEnter', 'BridgeLeave'],
            'queue' => ['QueueMemberAdded', 'QueueMemberRemoved', 'QueueMemberPause', 'QueueCallerJoin', 'QueueCallerLeave'],
            'agent' => ['AgentConnect', 'AgentComplete', 'AgentLogin', 'AgentLogoff'],
            'variable' => ['VarSet', 'UserEvent'],
            'monitoring' => ['MonitorStart', 'MonitorStop'],
            'dtmf' => ['DTMFBegin', 'DTMFEnd'],
            'state' => ['NewExten', 'NewState'],
            default => []
        };
    }

    /**
     * Handle shutdown signal.
     */
    public function handleShutdown(): void
    {
        $this->keepMonitoring = false;
        $this->newLine();
        $this->info('🛑 Monitoring stopped by user.');
    }

    /**
     * Display session summary.
     */
    protected function displaySessionSummary(): void
    {
        $duration = $this->startTime->diffForHumans(Carbon::now(), true);
        
        $this->newLine();
        $this->info('📊 Monitoring Session Summary');
        $this->table(['Metric', 'Value'], [
            ['Duration', $duration],
            ['Events Processed', number_format($this->eventCount)],
            ['Start Time', $this->startTime->format('Y-m-d H:i:s')],
            ['End Time', Carbon::now()->format('Y-m-d H:i:s')],
            ['Events per Minute', $this->eventCount > 0 ? number_format($this->eventCount / max(1, $this->startTime->diffInMinutes(Carbon::now())), 1) : '0']
        ]);
    }
}