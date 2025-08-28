<?php

namespace AsteriskPbxManager\Events;

use Illuminate\Broadcasting\Channel;
use PAMI\Message\Event\EventMessage;

/**
 * Event fired when a call is connected.
 */
class CallConnected extends AsteriskEvent
{
    /**
     * The destination channel.
     *
     * @var string|null
     */
    public ?string $destination = null;

    /**
     * The connected line number.
     *
     * @var string|null
     */
    public ?string $connectedLineNum = null;

    /**
     * The connected line name.
     *
     * @var string|null
     */
    public ?string $connectedLineName = null;

    /**
     * Call direction (inbound/outbound).
     *
     * @var string|null
     */
    public ?string $direction = null;

    /**
     * Create a new call connected event instance.
     *
     * @param EventMessage|null $event
     */
    public function __construct(?EventMessage $event = null)
    {
        parent::__construct($event);
        
        if ($event) {
            $this->extractCallData($event);
        }
    }

    /**
     * Extract call-specific data from the AMI event.
     *
     * @param EventMessage $event
     */
    protected function extractCallData(EventMessage $event): void
    {
        // Extract destination information
        $this->destination = $event->getKey('DestChannel') ?? $event->getKey('Destination');
        
        // Extract connected line information
        $this->connectedLineNum = $event->getKey('ConnectedLineNum');
        $this->connectedLineName = $event->getKey('ConnectedLineName');
        
        // Determine call direction based on available data
        $this->direction = $this->determineCallDirection($event);
        
        // Add call-specific data to the data array
        $this->data = array_merge($this->data, [
            'destination' => $this->destination,
            'connected_line_num' => $this->connectedLineNum,
            'connected_line_name' => $this->connectedLineName,
            'direction' => $this->direction,
            'dial_status' => $event->getKey('DialStatus'),
            'sub_event' => $event->getKey('SubEvent'),
        ]);
    }

    /**
     * Determine the call direction based on event data.
     *
     * @param EventMessage $event
     * @return string|null
     */
    protected function determineCallDirection(EventMessage $event): ?string
    {
        $channel = $event->getKey('Channel');
        $callerIdNum = $event->getKey('CallerIDNum');
        
        // Basic heuristic: if channel starts with SIP/ and CallerIDNum is numeric, 
        // it's likely an inbound call
        if ($channel && str_starts_with($channel, 'SIP/')) {
            if ($callerIdNum && is_numeric($callerIdNum) && strlen($callerIdNum) >= 10) {
                return 'inbound';
            }
            return 'outbound';
        }
        
        return null;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel[]
     */
    public function broadcastOn(): array
    {
        $channelPrefix = config('asterisk-pbx-manager.broadcasting.channel_prefix', 'asterisk');
        $isPrivate = config('asterisk-pbx-manager.broadcasting.private_channels', false);

        $channels = [
            "{$channelPrefix}.calls",
            "{$channelPrefix}.events",
        ];

        // Add channel-specific broadcasting if channel is available
        if ($this->getChannel()) {
            $channels[] = "{$channelPrefix}.calls.{$this->getUniqueId()}";
        }

        return $isPrivate 
            ? array_map(fn($ch) => new \Illuminate\Broadcasting\PrivateChannel($ch), $channels)
            : array_map(fn($ch) => new Channel($ch), $channels);
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'call.connected';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'call_connected',
            'timestamp' => $this->timestamp,
            'channel' => $this->getChannel(),
            'unique_id' => $this->getUniqueId(),
            'caller_id_num' => $this->getCallerIdNum(),
            'caller_id_name' => $this->getCallerIdName(),
            'destination' => $this->destination,
            'connected_line_num' => $this->connectedLineNum,
            'connected_line_name' => $this->connectedLineName,
            'direction' => $this->direction,
            'extension' => $this->getExtension(),
            'context' => $this->getContext(),
        ];
    }

    /**
     * Get the destination channel.
     *
     * @return string|null
     */
    public function getDestination(): ?string
    {
        return $this->destination;
    }

    /**
     * Get the connected line number.
     *
     * @return string|null
     */
    public function getConnectedLineNum(): ?string
    {
        return $this->connectedLineNum;
    }

    /**
     * Get the connected line name.
     *
     * @return string|null
     */
    public function getConnectedLineName(): ?string
    {
        return $this->connectedLineName;
    }

    /**
     * Get the call direction.
     *
     * @return string|null
     */
    public function getDirection(): ?string
    {
        return $this->direction;
    }

    /**
     * Check if this is an inbound call.
     *
     * @return bool
     */
    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    /**
     * Check if this is an outbound call.
     *
     * @return bool
     */
    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * Get the dial status from event data.
     *
     * @return string|null
     */
    public function getDialStatus(): ?string
    {
        return $this->getData('dial_status');
    }

    /**
     * Get the sub event from event data.
     *
     * @return string|null
     */
    public function getSubEvent(): ?string
    {
        return $this->getData('sub_event');
    }
}