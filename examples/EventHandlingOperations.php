<?php

/**
 * Event Handling Operations Example.
 *
 * This example demonstrates event handling operations using the Asterisk PBX Manager package.
 * It shows how to listen for events, create custom listeners, and handle real-time Asterisk events.
 */

require_once __DIR__.'/../vendor/autoload.php';

use AsteriskPbxManager\Events\AsteriskEvent;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;
use AsteriskPbxManager\Models\AsteriskEvent as AsteriskEventModel;
use AsteriskPbxManager\Models\CallLog;
use AsteriskPbxManager\Services\AsteriskManagerService;
use AsteriskPbxManager\Services\EventProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class EventHandlingExample
{
    private AsteriskManagerService $asteriskManager;
    private EventProcessor $eventProcessor;
    private array $eventCounts = [];
    private array $callEvents = [];

    public function __construct(
        AsteriskManagerService $asteriskManager,
        EventProcessor $eventProcessor
    ) {
        $this->asteriskManager = $asteriskManager;
        $this->eventProcessor = $eventProcessor;
    }

    /**
     * Example 1: Register basic event listeners.
     */
    public function registerBasicEventListeners(): void
    {
        echo "🎧 Registering basic event listeners\n";

        // Listen for call connected events
        Event::listen(CallConnected::class, function (CallConnected $event) {
            echo "📞 Call Connected Event:\n";
            echo "   Unique ID: {$event->uniqueId}\n";
            echo "   Channel: {$event->channel}\n";
            echo "   Caller ID: {$event->callerId}\n";
            echo "   Destination: {$event->destination}\n";
            echo "   Connected at: {$event->connectedAt}\n\n";

            $this->trackEvent('call_connected');
            $this->logCallEvent($event);
        });

        // Listen for call ended events
        Event::listen(CallEnded::class, function (CallEnded $event) {
            echo "📴 Call Ended Event:\n";
            echo "   Unique ID: {$event->uniqueId}\n";
            echo "   Channel: {$event->channel}\n";
            echo "   Duration: {$event->duration} seconds\n";
            echo "   Cause: {$event->cause}\n";
            echo "   Ended at: {$event->endedAt}\n\n";

            $this->trackEvent('call_ended');
            $this->updateCallLog($event);
        });

        // Listen for queue member events
        Event::listen(QueueMemberAdded::class, function (QueueMemberAdded $event) {
            echo "👥 Queue Member Added Event:\n";
            echo "   Queue: {$event->queue}\n";
            echo "   Member: {$event->member}\n";
            echo "   Member Name: {$event->memberName}\n";
            echo "   Penalty: {$event->penalty}\n";
            echo "   Added at: {$event->addedAt}\n\n";

            $this->trackEvent('queue_member_added');
        });

        echo "✅ Basic event listeners registered\n\n";
    }

    /**
     * Example 2: Register custom event listeners with filtering.
     */
    public function registerFilteredEventListeners(): void
    {
        echo "🔍 Registering filtered event listeners\n";

        // Listen only for events from specific channels
        Event::listen(CallConnected::class, function (CallConnected $event) {
            if (str_starts_with($event->channel, 'SIP/')) {
                echo "📱 SIP Call Connected: {$event->channel} -> {$event->destination}\n";
                $this->trackEvent('sip_call_connected');
            }
        });

        // Listen only for long duration calls
        Event::listen(CallEnded::class, function (CallEnded $event) {
            if ($event->duration > 300) { // 5 minutes
                echo "⏱️ Long Call Ended: {$event->channel} (Duration: {$event->duration}s)\n";
                $this->trackEvent('long_call_ended');
                $this->handleLongCall($event);
            }
        });

        // Listen for specific queue events
        Event::listen(QueueMemberAdded::class, function (QueueMemberAdded $event) {
            if (in_array($event->queue, ['support', 'sales'])) {
                echo "🎯 Priority Queue Member Added: {$event->member} to {$event->queue}\n";
                $this->trackEvent('priority_queue_member_added');
            }
        });

        echo "✅ Filtered event listeners registered\n\n";
    }

    /**
     * Example 3: Register comprehensive Asterisk event listener.
     */
    public function registerComprehensiveEventListener(): void
    {
        echo "🌐 Registering comprehensive Asterisk event listener\n";

        Event::listen(AsteriskEvent::class, function (AsteriskEvent $event) {
            echo "📡 Asterisk Event: {$event->eventName}\n";
            echo "   Timestamp: {$event->timestamp}\n";
            echo "   Server: {$event->server}\n";

            if (!empty($event->data)) {
                echo "   Data:\n";
                foreach ($event->data as $key => $value) {
                    echo "     {$key}: {$value}\n";
                }
            }
            echo "\n";

            $this->trackEvent('asterisk_event_'.strtolower($event->eventName));
            $this->storeEventInDatabase($event);
        });

        echo "✅ Comprehensive event listener registered\n\n";
    }

    /**
     * Example 4: Start real-time event monitoring.
     */
    public function startEventMonitoring(int $duration = 60): void
    {
        echo "👁️ Starting real-time event monitoring for {$duration} seconds\n";
        echo '='.str_repeat('=', 50)."\n";

        $startTime = time();
        $endTime = $startTime + $duration;

        // Register all listeners
        $this->registerBasicEventListeners();
        $this->registerFilteredEventListeners();
        $this->registerComprehensiveEventListener();

        // Start monitoring loop
        echo "🔄 Monitoring events... (Press Ctrl+C to stop early)\n\n";

        while (time() < $endTime) {
            // In a real implementation, events would be processed automatically
            // Here we simulate some events for demonstration
            if (rand(1, 10) === 1) { // 10% chance each second
                $this->simulateRandomEvent();
            }

            sleep(1);

            // Show progress every 10 seconds
            if ((time() - $startTime) % 10 === 0) {
                $elapsed = time() - $startTime;
                $remaining = $endTime - time();
                echo "⏱️ Monitoring progress: {$elapsed}s elapsed, {$remaining}s remaining\n";
                $this->displayEventStatistics();
            }
        }

        echo "\n✅ Event monitoring completed\n";
        $this->displayFinalStatistics();
    }

    /**
     * Example 5: Event-driven call workflow.
     */
    public function demonstrateEventDrivenWorkflow(): void
    {
        echo "\n🔄 Demonstrating event-driven call workflow\n";
        echo '='.str_repeat('=', 50)."\n";

        // Register workflow-specific listeners
        Event::listen(CallConnected::class, [$this, 'handleCallConnectedWorkflow']);
        Event::listen(CallEnded::class, [$this, 'handleCallEndedWorkflow']);

        // Simulate a call workflow
        echo "📞 Simulating call workflow events...\n\n";

        // Simulate call connected
        $callConnectedEvent = new CallConnected(
            'call_'.time().'_001',
            'SIP/1001',
            'John Doe <1001>',
            '2002',
            now(),
            ['context' => 'default', 'priority' => '1']
        );

        Event::dispatch($callConnectedEvent);
        sleep(2);

        // Simulate call ended
        $callEndedEvent = new CallEnded(
            $callConnectedEvent->uniqueId,
            $callConnectedEvent->channel,
            125,
            'Normal Clearing',
            now(),
            ['billsec' => '120', 'disposition' => 'ANSWERED']
        );

        Event::dispatch($callEndedEvent);

        echo "✅ Event-driven workflow demonstration completed\n\n";
    }

    /**
     * Example 6: Custom event processing and analytics.
     */
    public function demonstrateEventAnalytics(): void
    {
        echo "📊 Demonstrating event analytics\n";
        echo '='.str_repeat('=', 50)."\n";

        // Simulate some events for analytics
        $this->simulateAnalyticsData();

        // Generate analytics reports
        $this->generateCallAnalytics();
        $this->generateQueueAnalytics();
        $this->generateSystemAnalytics();
    }

    /**
     * Handle call connected workflow.
     */
    public function handleCallConnectedWorkflow(CallConnected $event): void
    {
        echo "🚀 Starting call workflow for: {$event->uniqueId}\n";
        echo "   Caller: {$event->callerId}\n";
        echo "   Destination: {$event->destination}\n";

        // Store call start information
        $this->callEvents[$event->uniqueId] = [
            'start_time'  => $event->connectedAt,
            'caller_id'   => $event->callerId,
            'destination' => $event->destination,
            'channel'     => $event->channel,
        ];

        // Trigger any workflow actions
        if ($this->isVipCaller($event->callerId)) {
            echo "   ⭐ VIP caller detected - special handling activated\n";
        }

        if ($this->isEmergencyNumber($event->destination)) {
            echo "   🚨 Emergency call detected - priority routing\n";
        }

        echo "\n";
    }

    /**
     * Handle call ended workflow.
     */
    public function handleCallEndedWorkflow(CallEnded $event): void
    {
        echo "🏁 Ending call workflow for: {$event->uniqueId}\n";
        echo "   Duration: {$event->duration} seconds\n";
        echo "   Cause: {$event->cause}\n";

        if (isset($this->callEvents[$event->uniqueId])) {
            $callData = $this->callEvents[$event->uniqueId];

            // Calculate additional metrics
            $efficiency = $this->calculateCallEfficiency($callData, $event);
            echo "   Efficiency Score: {$efficiency}%\n";

            // Trigger post-call actions
            if ($event->duration < 10) {
                echo "   ⚠️ Short call detected - quality check recommended\n";
            }

            if ($event->cause !== 'Normal Clearing') {
                echo "   ❌ Abnormal termination - investigation needed\n";
            }

            unset($this->callEvents[$event->uniqueId]);
        }

        echo "\n";
    }

    /**
     * Track event occurrence.
     */
    private function trackEvent(string $eventType): void
    {
        if (!isset($this->eventCounts[$eventType])) {
            $this->eventCounts[$eventType] = 0;
        }
        $this->eventCounts[$eventType]++;
    }

    /**
     * Log call event to database.
     */
    private function logCallEvent(CallConnected $event): void
    {
        try {
            CallLog::create([
                'unique_id'    => $event->uniqueId,
                'channel'      => $event->channel,
                'caller_id'    => $event->callerId,
                'destination'  => $event->destination,
                'connected_at' => $event->connectedAt,
                'status'       => 'connected',
            ]);
        } catch (\Exception $e) {
            echo "⚠️ Failed to log call event: {$e->getMessage()}\n";
        }
    }

    /**
     * Update call log when call ends.
     */
    private function updateCallLog(CallEnded $event): void
    {
        try {
            CallLog::where('unique_id', $event->uniqueId)
                   ->update([
                       'ended_at' => $event->endedAt,
                       'duration' => $event->duration,
                       'cause'    => $event->cause,
                       'status'   => 'completed',
                   ]);
        } catch (\Exception $e) {
            echo "⚠️ Failed to update call log: {$e->getMessage()}\n";
        }
    }

    /**
     * Store event in database.
     */
    private function storeEventInDatabase(AsteriskEvent $event): void
    {
        try {
            AsteriskEventModel::create([
                'event_name' => $event->eventName,
                'server'     => $event->server,
                'timestamp'  => $event->timestamp,
                'data'       => json_encode($event->data),
            ]);
        } catch (\Exception $e) {
            echo "⚠️ Failed to store event: {$e->getMessage()}\n";
        }
    }

    /**
     * Simulate random event for demonstration.
     */
    private function simulateRandomEvent(): void
    {
        $events = ['Dial', 'Hangup', 'Bridge', 'QueueMemberAdded', 'QueueMemberRemoved'];
        $eventName = $events[array_rand($events)];

        $event = new AsteriskEvent(
            $eventName,
            'localhost',
            now(),
            $this->generateEventData($eventName)
        );

        Event::dispatch($event);
    }

    /**
     * Generate sample event data.
     */
    private function generateEventData(string $eventName): array
    {
        $baseData = [
            'Event'     => $eventName,
            'Privilege' => 'call,all',
            'Channel'   => 'SIP/'.rand(1001, 1010),
            'UniqueID'  => time().'.'.rand(100, 999),
        ];

        switch ($eventName) {
            case 'Dial':
                return array_merge($baseData, [
                    'Destination'  => 'SIP/'.rand(2001, 2010),
                    'CallerIDNum'  => rand(1001, 1010),
                    'CallerIDName' => 'Test User',
                ]);

            case 'Hangup':
                return array_merge($baseData, [
                    'Cause'    => rand(1, 10) === 1 ? 'Busy' : 'Normal Clearing',
                    'Duration' => rand(10, 300),
                ]);

            case 'QueueMemberAdded':
                return array_merge($baseData, [
                    'Queue'      => ['support', 'sales', 'technical'][rand(0, 2)],
                    'Location'   => $baseData['Channel'],
                    'MemberName' => 'Agent '.rand(1, 10),
                ]);

            default:
                return $baseData;
        }
    }

    /**
     * Display current event statistics.
     */
    private function displayEventStatistics(): void
    {
        if (empty($this->eventCounts)) {
            echo "   No events processed yet\n\n";

            return;
        }

        echo "   📊 Event Statistics:\n";
        foreach ($this->eventCounts as $type => $count) {
            echo "     {$type}: {$count}\n";
        }
        echo "\n";
    }

    /**
     * Display final statistics.
     */
    private function displayFinalStatistics(): void
    {
        echo "\n📊 Final Event Statistics:\n";
        echo '='.str_repeat('=', 50)."\n";

        if (empty($this->eventCounts)) {
            echo "No events were processed during monitoring period.\n";

            return;
        }

        $totalEvents = array_sum($this->eventCounts);
        echo "Total Events Processed: {$totalEvents}\n\n";

        echo "Event Breakdown:\n";
        foreach ($this->eventCounts as $type => $count) {
            $percentage = round(($count / $totalEvents) * 100, 2);
            echo "  {$type}: {$count} ({$percentage}%)\n";
        }

        echo "\n";
    }

    /**
     * Simulate analytics data.
     */
    private function simulateAnalyticsData(): void
    {
        echo "🔄 Generating sample analytics data...\n";

        for ($i = 0; $i < 50; $i++) {
            $this->simulateRandomEvent();
            $this->trackEvent('simulated_event');
        }

        echo "✅ Sample data generated\n\n";
    }

    /**
     * Generate call analytics.
     */
    private function generateCallAnalytics(): void
    {
        echo "📞 Call Analytics Report:\n";
        echo '-'.str_repeat('-', 30)."\n";

        $callConnected = $this->eventCounts['call_connected'] ?? 0;
        $callEnded = $this->eventCounts['call_ended'] ?? 0;
        $longCalls = $this->eventCounts['long_call_ended'] ?? 0;

        echo "Total Calls Connected: {$callConnected}\n";
        echo "Total Calls Ended: {$callEnded}\n";
        echo "Long Duration Calls: {$longCalls}\n";

        if ($callEnded > 0) {
            $completionRate = round(($callEnded / max($callConnected, 1)) * 100, 2);
            echo "Call Completion Rate: {$completionRate}%\n";
        }

        echo "\n";
    }

    /**
     * Generate queue analytics.
     */
    private function generateQueueAnalytics(): void
    {
        echo "👥 Queue Analytics Report:\n";
        echo '-'.str_repeat('-', 30)."\n";

        $queueMembers = $this->eventCounts['queue_member_added'] ?? 0;
        $priorityMembers = $this->eventCounts['priority_queue_member_added'] ?? 0;

        echo "Total Queue Members Added: {$queueMembers}\n";
        echo "Priority Queue Members: {$priorityMembers}\n";

        if ($queueMembers > 0) {
            $priorityRate = round(($priorityMembers / $queueMembers) * 100, 2);
            echo "Priority Queue Rate: {$priorityRate}%\n";
        }

        echo "\n";
    }

    /**
     * Generate system analytics.
     */
    private function generateSystemAnalytics(): void
    {
        echo "🖥️ System Analytics Report:\n";
        echo '-'.str_repeat('-', 30)."\n";

        $totalEvents = array_sum($this->eventCounts);
        $eventTypes = count($this->eventCounts);

        echo "Total System Events: {$totalEvents}\n";
        echo "Event Types Processed: {$eventTypes}\n";
        echo 'Average Events per Type: '.round($totalEvents / max($eventTypes, 1), 2)."\n";

        echo "\n";
    }

    /**
     * Helper methods.
     */
    private function isVipCaller(string $callerId): bool
    {
        $vipNumbers = ['1001', '1002', '9999'];

        return in_array(preg_replace('/[^0-9]/', '', $callerId), $vipNumbers);
    }

    private function isEmergencyNumber(string $destination): bool
    {
        $emergencyNumbers = ['911', '999', '112'];

        return in_array($destination, $emergencyNumbers);
    }

    private function calculateCallEfficiency(array $callData, CallEnded $event): float
    {
        // Simple efficiency calculation based on duration and cause
        $baseScore = min($event->duration / 60, 5) * 20; // Up to 100 for 5+ minutes

        if ($event->cause === 'Normal Clearing') {
            $baseScore += 20;
        }

        return min(round($baseScore, 1), 100);
    }

    private function handleLongCall(CallEnded $event): void
    {
        echo "📊 Long call analysis for {$event->uniqueId}:\n";
        echo '   Duration: '.gmdate('H:i:s', $event->duration)."\n";
        echo "   Suggested actions: Review call quality, customer satisfaction survey\n";
    }
}

// Usage example when running as script
if (php_sapi_name() === 'cli') {
    echo "Asterisk PBX Manager - Event Handling Example\n";
    echo '='.str_repeat('=', 50)."\n";

    try {
        // Note: In a real Laravel application, these would be injected
        $asteriskManager = app(AsteriskManagerService::class);
        $eventProcessor = app(EventProcessor::class);

        $example = new EventHandlingExample($asteriskManager, $eventProcessor);

        // Run demonstrations
        $example->demonstrateEventDrivenWorkflow();
        $example->demonstrateEventAnalytics();

        // Start monitoring (uncomment for real-time monitoring)
        // $example->startEventMonitoring(30);
    } catch (\Exception $e) {
        echo '❌ Failed to initialize example: '.$e->getMessage()."\n";
        exit(1);
    }
}
