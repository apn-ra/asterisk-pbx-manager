<?php

namespace AsteriskPbxManager\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use PAMI\Message\Event\EventMessage;

/**
 * Base class for all Asterisk AMI events.
 */
class AsteriskEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The raw AMI event data.
     *
     * @var EventMessage|null
     */
    public ?EventMessage $event = null;

    /**
     * The event name.
     *
     * @var string
     */
    public string $eventName;

    /**
     * Event timestamp.
     *
     * @var int
     */
    public int $timestamp;

    /**
     * Event data extracted from AMI event.
     *
     * @var array
     */
    public array $data = [];

    /**
     * Create a new Asterisk event instance.
     *
     * @param EventMessage|null $event
     */
    public function __construct(?EventMessage $event = null)
    {
        $this->event = $event;
        $this->timestamp = time();
        $this->eventName = $event ? $event->getEventName() : 'Unknown';
        
        if ($event) {
            $this->extractEventData($event);
        }
    }

    /**
     * Extract data from the AMI event.
     *
     * @param EventMessage $event
     */
    protected function extractEventData(EventMessage $event): void
    {
        $this->data = [
            'event' => $event->getEventName(),
            'timestamp' => $this->timestamp,
            'keys' => $event->getKeys(),
        ];

        // Extract common AMI event fields
        $commonFields = [
            'Channel',
            'Uniqueid',
            'CallerIDNum',
            'CallerIDName',
            'ConnectedLineNum',
            'ConnectedLineName',
            'Extension',
            'Context',
            'Priority',
            'Application',
            'AppData',
        ];

        foreach ($commonFields as $field) {
            $value = $event->getKey($field);
            if ($value !== null) {
                $this->data[strtolower($field)] = $value;
            }
        }
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

        $channelName = "{$channelPrefix}.events";

        return $isPrivate 
            ? [new \Illuminate\Broadcasting\PrivateChannel($channelName)]
            : [new Channel($channelName)];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'asterisk.event';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'event_name' => $this->eventName,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
        ];
    }

    /**
     * Determine if this event should be broadcast.
     *
     * @return bool
     */
    public function shouldBroadcast(): bool
    {
        return config('asterisk-pbx-manager.events.broadcast', true) && 
               config('asterisk-pbx-manager.events.enabled', true);
    }

    /**
     * Get event data by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getData(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get the raw AMI event.
     *
     * @return EventMessage|null
     */
    public function getRawEvent(): ?EventMessage
    {
        return $this->event;
    }

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * Get the event timestamp.
     *
     * @return int
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Get all event data.
     *
     * @return array
     */
    public function getAllData(): array
    {
        return $this->data;
    }

    /**
     * Check if event has specific data key.
     *
     * @param string $key
     * @return bool
     */
    public function hasData(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Get the channel from event data.
     *
     * @return string|null
     */
    public function getChannel(): ?string
    {
        return $this->getData('channel');
    }

    /**
     * Get the unique ID from event data.
     *
     * @return string|null
     */
    public function getUniqueId(): ?string
    {
        return $this->getData('uniqueid');
    }

    /**
     * Get the caller ID number from event data.
     *
     * @return string|null
     */
    public function getCallerIdNum(): ?string
    {
        return $this->getData('calleridnum');
    }

    /**
     * Get the caller ID name from event data.
     *
     * @return string|null
     */
    public function getCallerIdName(): ?string
    {
        return $this->getData('calleridname');
    }

    /**
     * Get the extension from event data.
     *
     * @return string|null
     */
    public function getExtension(): ?string
    {
        return $this->getData('extension');
    }

    /**
     * Get the context from event data.
     *
     * @return string|null
     */
    public function getContext(): ?string
    {
        return $this->getData('context');
    }

    /**
     * Convert the event to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'event_name' => $this->eventName,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
        ];
    }

    /**
     * Convert the event to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}