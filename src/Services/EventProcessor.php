<?php

namespace AsteriskPbxManager\Services;

use PAMI\Message\Event\EventMessage;
use PAMI\Message\Event\DialEvent;
use PAMI\Message\Event\HangupEvent;
use PAMI\Message\Event\BridgeEvent;
use PAMI\Message\Event\QueueMemberAddedEvent;
use Illuminate\Support\Facades\Log;
use AsteriskPbxManager\Events\CallConnected;
use AsteriskPbxManager\Events\CallEnded;
use AsteriskPbxManager\Events\QueueMemberAdded;
use AsteriskPbxManager\Events\AsteriskEvent;

/**
 * Service for processing Asterisk AMI events with advanced routing.
 */
class EventProcessor
{
    /**
     * Event processing statistics.
     *
     * @var array
     */
    protected array $statistics = [
        'total_events' => 0,
        'processed_events' => 0,
        'failed_events' => 0,
        'event_types' => [],
    ];

    /**
     * Event filters for conditional processing.
     *
     * @var array
     */
    protected array $eventFilters = [];

    /**
     * Custom event handlers.
     *
     * @var array
     */
    protected array $customHandlers = [];

    /**
     * Process an incoming AMI event.
     *
     * @param EventMessage $event
     * @return void
     */
    public function processEvent(EventMessage $event): void
    {
        $this->statistics['total_events']++;
        
        $eventName = $event->getEventName();
        
        // Update statistics
        if (!isset($this->statistics['event_types'][$eventName])) {
            $this->statistics['event_types'][$eventName] = 0;
        }
        $this->statistics['event_types'][$eventName]++;

        try {
            $this->logInfo("Processing Asterisk event: {$eventName}", [
                'event' => $eventName,
                'keys' => $event->getKeys(),
            ]);

            // Check event filters
            if (!$this->shouldProcessEvent($event)) {
                $this->logInfo("Event {$eventName} filtered out", ['event' => $eventName]);
                return;
            }

            // Check for custom handlers first
            if ($this->hasCustomHandler($eventName)) {
                $this->processCustomEvent($event);
                $this->statistics['processed_events']++;
                return;
            }

            // Route to appropriate handler
            switch ($eventName) {
                case 'Dial':
                    $this->handleDialEvent($event);
                    break;
                case 'Hangup':
                    $this->handleHangupEvent($event);
                    break;
                case 'Bridge':
                    $this->handleBridgeEvent($event);
                    break;
                case 'QueueMemberAdded':
                    $this->handleQueueMemberAdded($event);
                    break;
                case 'QueueMemberRemoved':
                    $this->handleQueueMemberRemoved($event);
                    break;
                case 'QueueMemberPause':
                    $this->handleQueueMemberPause($event);
                    break;
                case 'NewChannel':
                case 'Newchannel':
                    $this->handleNewChannelEvent($event);
                    break;
                case 'ChannelStateChange':
                case 'Newstate':
                    $this->handleChannelStateChangeEvent($event);
                    break;
                case 'Extension':
                case 'Newexten':
                    $this->handleExtensionEvent($event);
                    break;
                case 'UserEvent':
                    $this->handleUserEvent($event);
                    break;
                default:
                    $this->handleUnknownEvent($event);
                    break;
            }

            $this->statistics['processed_events']++;
        } catch (\Exception $e) {
            $this->statistics['failed_events']++;
            $this->logError("Error processing event {$eventName}", [
                'event' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle dial events (call initiation/connection).
     *
     * @param EventMessage $event
     */
    protected function handleDialEvent(EventMessage $event): void
    {
        $subEvent = $event->getKey('SubEvent');
        $channel = $event->getKey('Channel');
        $destination = $event->getKey('Destination') ?? $event->getKey('DestChannel');

        $this->logInfo('Processing dial event', [
            'sub_event' => $subEvent,
            'channel' => $channel,
            'destination' => $destination,
        ]);

        if ($subEvent === 'Begin') {
            $this->logInfo('Call initiated', [
                'channel' => $channel,
                'destination' => $destination,
            ]);
            
            // Fire generic dial begin event
            event(new AsteriskEvent($event));
        } elseif ($subEvent === 'End') {
            $dialStatus = $event->getKey('DialStatus');
            
            if ($dialStatus === 'ANSWER') {
                $this->logInfo('Call connected', [
                    'channel' => $channel,
                    'destination' => $destination,
                ]);
                
                // Fire call connected event
                event(new CallConnected($event));
            } else {
                $this->logInfo('Call failed to connect', [
                    'channel' => $channel,
                    'destination' => $destination,
                    'status' => $dialStatus,
                ]);
                
                // Fire generic dial event for failed calls
                event(new AsteriskEvent($event));
            }
        }
    }

    /**
     * Handle hangup events (call termination).
     *
     * @param EventMessage $event
     */
    protected function handleHangupEvent(EventMessage $event): void
    {
        $channel = $event->getKey('Channel');
        $cause = $event->getKey('Cause');
        $causeText = $event->getKey('Cause-txt');

        $this->logInfo('Call ended', [
            'channel' => $channel,
            'cause' => $cause,
            'cause_text' => $causeText,
        ]);

        // Fire call ended event
        event(new CallEnded($event));
    }

    /**
     * Handle bridge events (call bridging).
     *
     * @param EventMessage $event
     */
    protected function handleBridgeEvent(EventMessage $event): void
    {
        $bridgeState = $event->getKey('BridgeState') ?? $event->getKey('State');
        $channel1 = $event->getKey('Channel1');
        $channel2 = $event->getKey('Channel2');

        $this->logInfo('Bridge event', [
            'state' => $bridgeState,
            'channel1' => $channel1,
            'channel2' => $channel2,
        ]);

        // Fire generic bridge event
        event(new AsteriskEvent($event));
    }

    /**
     * Handle queue member added events.
     *
     * @param EventMessage $event
     */
    protected function handleQueueMemberAdded(EventMessage $event): void
    {
        $queue = $event->getKey('Queue');
        $interface = $event->getKey('Interface');
        $memberName = $event->getKey('MemberName');

        $this->logInfo('Queue member added', [
            'queue' => $queue,
            'interface' => $interface,
            'member_name' => $memberName,
        ]);

        // Fire queue member added event
        event(new QueueMemberAdded($event));
    }

    /**
     * Handle queue member removed events.
     *
     * @param EventMessage $event
     */
    protected function handleQueueMemberRemoved(EventMessage $event): void
    {
        $queue = $event->getKey('Queue');
        $interface = $event->getKey('Interface');

        $this->logInfo('Queue member removed', [
            'queue' => $queue,
            'interface' => $interface,
        ]);

        // Fire generic event for now
        event(new AsteriskEvent($event));
    }

    /**
     * Handle queue member pause events.
     *
     * @param EventMessage $event
     */
    protected function handleQueueMemberPause(EventMessage $event): void
    {
        $queue = $event->getKey('Queue');
        $interface = $event->getKey('Interface');
        $paused = $event->getKey('Paused');

        $this->logInfo('Queue member pause changed', [
            'queue' => $queue,
            'interface' => $interface,
            'paused' => $paused,
        ]);

        // Fire generic event for now
        event(new AsteriskEvent($event));
    }

    /**
     * Handle new channel events.
     *
     * @param EventMessage $event
     */
    protected function handleNewChannelEvent(EventMessage $event): void
    {
        $channel = $event->getKey('Channel');
        $state = $event->getKey('ChannelState') ?? $event->getKey('State');

        $this->logInfo('New channel created', [
            'channel' => $channel,
            'state' => $state,
        ]);

        // Fire generic event
        event(new AsteriskEvent($event));
    }

    /**
     * Handle channel state change events.
     *
     * @param EventMessage $event
     */
    protected function handleChannelStateChangeEvent(EventMessage $event): void
    {
        $channel = $event->getKey('Channel');
        $state = $event->getKey('ChannelState') ?? $event->getKey('State');

        $this->logInfo('Channel state changed', [
            'channel' => $channel,
            'state' => $state,
        ]);

        // Fire generic event
        event(new AsteriskEvent($event));
    }

    /**
     * Handle extension events.
     *
     * @param EventMessage $event
     */
    protected function handleExtensionEvent(EventMessage $event): void
    {
        $channel = $event->getKey('Channel');
        $extension = $event->getKey('Extension');
        $context = $event->getKey('Context');
        $application = $event->getKey('Application');

        $this->logInfo('Extension event', [
            'channel' => $channel,
            'extension' => $extension,
            'context' => $context,
            'application' => $application,
        ]);

        // Fire generic event
        event(new AsteriskEvent($event));
    }

    /**
     * Handle user-defined events.
     *
     * @param EventMessage $event
     */
    protected function handleUserEvent(EventMessage $event): void
    {
        $userEvent = $event->getKey('UserEvent');
        
        $this->logInfo('User event', [
            'user_event' => $userEvent,
            'data' => $event->getKeys(),
        ]);

        // Fire generic event
        event(new AsteriskEvent($event));
    }

    /**
     * Handle unknown/unimplemented events.
     *
     * @param EventMessage $event
     */
    protected function handleUnknownEvent(EventMessage $event): void
    {
        $eventName = $event->getEventName();
        
        $this->logInfo("Unknown event type: {$eventName}", [
            'event' => $eventName,
            'keys' => $event->getKeys(),
        ]);

        // Fire generic Asterisk event
        event(new AsteriskEvent($event));
    }

    /**
     * Register a custom event handler.
     *
     * @param string $eventName
     * @param callable $handler
     */
    public function registerCustomHandler(string $eventName, callable $handler): void
    {
        $this->customHandlers[$eventName] = $handler;
    }

    /**
     * Check if a custom handler exists for an event.
     *
     * @param string $eventName
     * @return bool
     */
    protected function hasCustomHandler(string $eventName): bool
    {
        return isset($this->customHandlers[$eventName]);
    }

    /**
     * Process event with custom handler.
     *
     * @param EventMessage $event
     */
    protected function processCustomEvent(EventMessage $event): void
    {
        $eventName = $event->getEventName();
        $handler = $this->customHandlers[$eventName];
        
        $this->logInfo("Processing event with custom handler: {$eventName}");
        
        $handler($event);
    }

    /**
     * Add an event filter.
     *
     * @param callable $filter
     */
    public function addEventFilter(callable $filter): void
    {
        $this->eventFilters[] = $filter;
    }

    /**
     * Check if an event should be processed based on filters.
     *
     * @param EventMessage $event
     * @return bool
     */
    protected function shouldProcessEvent(EventMessage $event): bool
    {
        foreach ($this->eventFilters as $filter) {
            if (!$filter($event)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get processing statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * Reset processing statistics.
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'total_events' => 0,
            'processed_events' => 0,
            'failed_events' => 0,
            'event_types' => [],
        ];
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (config('asterisk-pbx-manager.logging.enabled', true)) {
            Log::channel(config('asterisk-pbx-manager.logging.channel', 'default'))
               ->info($message, $context);
        }
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     */
    protected function logError(string $message, array $context = []): void
    {
        if (config('asterisk-pbx-manager.logging.enabled', true)) {
            Log::channel(config('asterisk-pbx-manager.logging.channel', 'default'))
               ->error($message, $context);
        }
    }
}