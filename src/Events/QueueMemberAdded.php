<?php

namespace AsteriskPbxManager\Events;

use Illuminate\Broadcasting\Channel;
use PAMI\Message\Event\EventMessage;

/**
 * Event fired when a member is added to a queue.
 */
class QueueMemberAdded extends AsteriskEvent
{
    /**
     * The queue name.
     *
     * @var string|null
     */
    public ?string $queue = null;

    /**
     * The member interface.
     *
     * @var string|null
     */
    public ?string $interface = null;

    /**
     * The member name.
     *
     * @var string|null
     */
    public ?string $memberName = null;

    /**
     * The member status.
     *
     * @var string|null
     */
    public ?string $status = null;

    /**
     * The member penalty.
     *
     * @var int|null
     */
    public ?int $penalty = null;

    /**
     * The number of calls taken by this member.
     *
     * @var int|null
     */
    public ?int $callsTaken = null;

    /**
     * The timestamp of the last call.
     *
     * @var int|null
     */
    public ?int $lastCall = null;

    /**
     * Whether the member is paused.
     *
     * @var bool
     */
    public bool $paused = false;

    /**
     * Create a new queue member added event instance.
     *
     * @param EventMessage|null $event
     */
    public function __construct(?EventMessage $event = null)
    {
        parent::__construct($event);
        
        if ($event) {
            $this->extractQueueData($event);
        }
    }

    /**
     * Extract queue-specific data from the AMI event.
     *
     * @param EventMessage $event
     */
    protected function extractQueueData(EventMessage $event): void
    {
        // Extract queue information
        $this->queue = $event->getKey('Queue');
        $this->interface = $event->getKey('Interface') ?? $event->getKey('Location');
        $this->memberName = $event->getKey('MemberName') ?? $event->getKey('Name');
        $this->status = $event->getKey('Status') ?? $event->getKey('Membership');
        
        // Extract member statistics
        $this->penalty = $this->extractIntValue($event, 'Penalty');
        $this->callsTaken = $this->extractIntValue($event, 'CallsTaken');
        $this->lastCall = $this->extractIntValue($event, 'LastCall');
        
        // Extract pause status
        $pausedValue = $event->getKey('Paused');
        $this->paused = $pausedValue === '1' || strtolower($pausedValue ?? '') === 'yes';
        
        // Add queue-specific data to the data array
        $this->data = array_merge($this->data, [
            'queue' => $this->queue,
            'interface' => $this->interface,
            'member_name' => $this->memberName,
            'status' => $this->status,
            'penalty' => $this->penalty,
            'calls_taken' => $this->callsTaken,
            'last_call' => $this->lastCall,
            'paused' => $this->paused,
            'state_interface' => $event->getKey('StateInterface'),
            'membership' => $event->getKey('Membership'),
        ]);
    }

    /**
     * Extract integer value from event data.
     *
     * @param EventMessage $event
     * @param string $key
     * @return int|null
     */
    protected function extractIntValue(EventMessage $event, string $key): ?int
    {
        $value = $event->getKey($key);
        return $value !== null ? (int) $value : null;
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
            "{$channelPrefix}.queues",
            "{$channelPrefix}.events",
        ];

        // Add queue-specific broadcasting if queue is available
        if ($this->queue) {
            $channels[] = "{$channelPrefix}.queues.{$this->queue}";
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
        return 'queue.member.added';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith(): array
    {
        return [
            'event_type' => 'queue_member_added',
            'timestamp' => $this->timestamp,
            'queue' => $this->queue,
            'interface' => $this->interface,
            'member_name' => $this->memberName,
            'status' => $this->status,
            'penalty' => $this->penalty,
            'calls_taken' => $this->callsTaken,
            'last_call' => $this->lastCall,
            'paused' => $this->paused,
        ];
    }

    /**
     * Get the queue name.
     *
     * @return string|null
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Get the member interface.
     *
     * @return string|null
     */
    public function getInterface(): ?string
    {
        return $this->interface;
    }

    /**
     * Get the member name.
     *
     * @return string|null
     */
    public function getMemberName(): ?string
    {
        return $this->memberName;
    }

    /**
     * Get the member status.
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Get the member penalty.
     *
     * @return int|null
     */
    public function getPenalty(): ?int
    {
        return $this->penalty;
    }

    /**
     * Get the number of calls taken.
     *
     * @return int|null
     */
    public function getCallsTaken(): ?int
    {
        return $this->callsTaken;
    }

    /**
     * Get the timestamp of the last call.
     *
     * @return int|null
     */
    public function getLastCall(): ?int
    {
        return $this->lastCall;
    }

    /**
     * Check if the member is paused.
     *
     * @return bool
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * Check if the member is available.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !$this->paused && in_array($this->status, ['1', 'Available', 'Not in use']);
    }

    /**
     * Check if the member is busy.
     *
     * @return bool
     */
    public function isBusy(): bool
    {
        return in_array($this->status, ['2', 'In use', 'Busy']);
    }

    /**
     * Check if the member is unavailable.
     *
     * @return bool
     */
    public function isUnavailable(): bool
    {
        return in_array($this->status, ['3', '4', '5', 'Unavailable', 'Invalid', 'Unknown']);
    }

    /**
     * Get the member status in human-readable format.
     *
     * @return string
     */
    public function getStatusText(): string
    {
        $statusMap = [
            '0' => 'Unknown',
            '1' => 'Available',
            '2' => 'In Use',
            '3' => 'Unavailable',
            '4' => 'Invalid',
            '5' => 'Ringing',
            '6' => 'Ring+InUse',
            '7' => 'On Hold',
        ];

        if ($this->paused) {
            return 'Paused';
        }

        return $statusMap[$this->status] ?? $this->status ?? 'Unknown';
    }

    /**
     * Get formatted last call time.
     *
     * @return string|null
     */
    public function getFormattedLastCall(): ?string
    {
        if ($this->lastCall === null || $this->lastCall === 0) {
            return 'Never';
        }

        return date('Y-m-d H:i:s', $this->lastCall);
    }

    /**
     * Get time since last call in seconds.
     *
     * @return int|null
     */
    public function getTimeSinceLastCall(): ?int
    {
        if ($this->lastCall === null || $this->lastCall === 0) {
            return null;
        }

        return time() - $this->lastCall;
    }

    /**
     * Get time since last call in human-readable format.
     *
     * @return string
     */
    public function getFormattedTimeSinceLastCall(): string
    {
        $seconds = $this->getTimeSinceLastCall();
        
        if ($seconds === null) {
            return 'Never';
        }

        if ($seconds < 60) {
            return "{$seconds} seconds ago";
        }

        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            return "{$minutes} minutes ago";
        }

        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            return "{$hours} hours ago";
        }

        $days = floor($seconds / 86400);
        return "{$days} days ago";
    }

    /**
     * Get the state interface.
     *
     * @return string|null
     */
    public function getStateInterface(): ?string
    {
        return $this->getData('state_interface');
    }

    /**
     * Get the membership status.
     *
     * @return string|null
     */
    public function getMembership(): ?string
    {
        return $this->getData('membership');
    }
}